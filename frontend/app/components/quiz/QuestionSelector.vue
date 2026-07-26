<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

export interface QsBankOption {
  id: number
  name: string
  questions_count?: number
  course_id?: number | null
  course?: { id: number, title: string } | null
}

export interface QsQuestionItem {
  id: number
  content: string
  type: string
  difficulty?: number | null
  question_group_id?: number | null
}

export interface QsRandomRule {
  _uid: string
  bank_id: number
  bank_name?: string
  group_id?: number | null
  group_name?: string | null
  difficulty?: number | null
  count: number
}

const props = defineProps<{
  banks: QsBankOption[]
  courseId?: number | null
  loadingBanks?: boolean
  questionIds: number[]
  randomRules: QsRandomRule[]
}>()

const emit = defineEmits<{
  'update:questionIds': [number[]]
  'update:randomRules': [QsRandomRule[]]
}>()

const { t } = useI18n()
const toast = useToast()
const { options: difficultyOptions, difficultyLabel } = useQuestionDifficulty()

type Mode = 'fixed' | 'random' | 'percent'
const mode = ref<Mode>('fixed')

const activeBankId = ref<number | null>(null)
const pool = ref<QsQuestionItem[]>([])
const groups = ref<Array<{ id: number, name: string }>>([])
const loadingPool = ref(false)

const filteredBanks = computed(() => {
  if (props.courseId) {
    return props.banks.filter(b => (b.course_id || b.course?.id) === props.courseId)
  }
  return props.banks
})

const activeBank = computed(() => props.banks.find(b => b.id === activeBankId.value) || null)

const groupOptions = computed(() => groups.value.map(g => ({ label: g.name, value: g.id })))

function stripHtml(html: string) {
  return (html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
}

function groupName(groupId?: number | null) {
  if (!groupId) return '—'
  return groups.value.find(g => g.id === groupId)?.name || '—'
}

async function loadPool(bankId: number | null) {
  groups.value = []
  pool.value = []
  if (!bankId) return
  const bank = props.banks.find(b => b.id === bankId)
  const courseId = bank?.course_id || bank?.course?.id
  if (!courseId) return

  loadingPool.value = true
  try {
    const res = await useApi<{
      questions?: QsQuestionItem[]
      groups?: Array<{ id: number, name: string, questions?: QsQuestionItem[] }>
    }>(`/courses/${courseId}/question-banks/${bankId}`)

    const gs = res.groups || []
    groups.value = gs.map(g => ({ id: g.id, name: g.name }))

    const fromBank = res.questions || []
    const fromGroups = gs.flatMap(g => g.questions || [])
    const map = new Map<number, QsQuestionItem>()
    for (const q of [...fromBank, ...fromGroups]) map.set(q.id, q)
    pool.value = [...map.values()]
  }
  catch {
    pool.value = []
    groups.value = []
  }
  finally {
    loadingPool.value = false
  }
}

watch(activeBankId, id => loadPool(id))
watch(() => props.courseId, () => { activeBankId.value = null })

// ── Fixed mode: DataTable selection synced against master questionIds (cross-bank) ──
const selectedRows = computed<QsQuestionItem[]>({
  get: () => pool.value.filter(q => props.questionIds.includes(q.id)),
  set: (rows) => {
    const poolIds = new Set(pool.value.map(q => q.id))
    const keptOutside = props.questionIds.filter(id => !poolIds.has(id))
    emit('update:questionIds', [...keptOutside, ...rows.map(r => r.id)])
  },
})

// ── Random fixed-count mode ──
const ruleForm = reactive({
  group_id: null as number | null,
  difficulty: null as number | null,
  count: null as number | null,
})

const availableForRuleForm = computed(() => pool.value.filter((q) => {
  if (ruleForm.group_id && q.question_group_id !== ruleForm.group_id) return false
  if (ruleForm.difficulty && q.difficulty !== ruleForm.difficulty) return false
  return true
}).length)

function makeUid() {
  return typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `r${Date.now()}${Math.random()}`
}

function addRandomRule() {
  if (!activeBank.value || !ruleForm.count || ruleForm.count < 1) return
  const rule: QsRandomRule = {
    _uid: makeUid(),
    bank_id: activeBank.value.id,
    bank_name: activeBank.value.name,
    group_id: ruleForm.group_id || null,
    group_name: ruleForm.group_id ? groupName(ruleForm.group_id) : null,
    difficulty: ruleForm.difficulty || null,
    count: ruleForm.count,
  }
  emit('update:randomRules', [...props.randomRules, rule])
  ruleForm.group_id = null
  ruleForm.difficulty = null
  ruleForm.count = null
  toast.add({ severity: 'success', summary: t('quizBuilder.ruleAdded'), life: 2000 })
}

function removeRandomRule(uid: string) {
  emit('update:randomRules', props.randomRules.filter(r => r._uid !== uid))
}

// ── Percent-by-difficulty mode ──
const percentForm = reactive({
  group_id: null as number | null,
  total: null as number | null,
  percents: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 } as Record<number, number>,
})

const percentSum = computed(() => Object.values(percentForm.percents).reduce((s, v) => s + (Number(v) || 0), 0))

function distributeByPercent(total: number, percents: Record<number, number>) {
  const entries = Object.entries(percents).map(([level, pct]) => {
    const exact = total * (Number(pct) || 0) / 100
    return { level: Number(level), exact, floor: Math.floor(exact) }
  })
  const allocated = entries.reduce((s, e) => s + e.floor, 0)
  let remainder = total - allocated
  const byRemainder = [...entries].sort((a, b) => (b.exact - b.floor) - (a.exact - a.floor))
  const result: Record<number, number> = {}
  entries.forEach(e => (result[e.level] = e.floor))
  let i = 0
  while (remainder > 0 && byRemainder.length) {
    const e = byRemainder[i % byRemainder.length]
    result[e.level] += 1
    remainder -= 1
    i += 1
  }
  return result
}

const computedPercentCounts = computed(() => {
  if (!percentForm.total || percentSum.value !== 100) return {} as Record<number, number>
  return distributeByPercent(percentForm.total, percentForm.percents)
})

function availableForLevel(level: number) {
  return pool.value.filter((q) => {
    if (percentForm.group_id && q.question_group_id !== percentForm.group_id) return false
    return q.difficulty === level
  }).length
}

function applyPercent() {
  if (!activeBank.value || !percentForm.total || percentSum.value !== 100) return
  const counts = computedPercentCounts.value
  const groupLabel = percentForm.group_id ? groupName(percentForm.group_id) : null
  const added: QsRandomRule[] = []
  for (const level of difficultyOptions.value) {
    const c = counts[level.value] || 0
    if (c > 0) {
      added.push({
        _uid: makeUid(),
        bank_id: activeBank.value.id,
        bank_name: activeBank.value.name,
        group_id: percentForm.group_id || null,
        group_name: groupLabel,
        difficulty: level.value,
        count: c,
      })
    }
  }
  if (!added.length) return
  emit('update:randomRules', [...props.randomRules, ...added])
  percentForm.total = null
  percentForm.percents = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 }
  toast.add({ severity: 'success', summary: t('quizBuilder.percentApplied', { n: added.reduce((s, r) => s + r.count, 0) }), life: 2500 })
}

const totalRandomCount = computed(() => props.randomRules.reduce((s, r) => s + (r.count || 0), 0))
</script>

<template>
  <div class="qsel">
    <label class="field full">
      <span>{{ t('quizBuilder.questionBank') }}</span>
      <Select
        v-model="activeBankId"
        :options="filteredBanks"
        option-label="name"
        option-value="id"
        filter
        show-clear
        :loading="loadingBanks"
        :placeholder="t('quizBuilder.selectBank')"
        class="w-full"
      >
        <template #option="{ option }">
          <div class="bank-opt">
            <strong>{{ option.name }}</strong>
            <small>{{ option.course?.title || '' }} · {{ option.questions_count || 0 }}</small>
          </div>
        </template>
      </Select>
    </label>

    <div class="qsel-tabs">
      <button type="button" class="tab" :class="{ on: mode === 'fixed' }" @click="mode = 'fixed'">
        <i class="pi pi-list" /> {{ t('quizBuilder.modeFixed') }}
      </button>
      <button type="button" class="tab" :class="{ on: mode === 'random' }" @click="mode = 'random'">
        <i class="pi pi-sync" /> {{ t('quizBuilder.modeRandom') }}
      </button>
      <button type="button" class="tab" :class="{ on: mode === 'percent' }" @click="mode = 'percent'">
        <i class="pi pi-percentage" /> {{ t('quizBuilder.modePercent') }}
      </button>
    </div>

    <!-- Fixed pick -->
    <div v-if="mode === 'fixed'" class="qsel-panel">
      <DataTable
        v-model:selection="selectedRows"
        :value="pool"
        data-key="id"
        :loading="loadingPool"
        selection-mode="multiple"
        paginator
        :rows="8"
        class="q-table"
      >
        <Column selection-mode="multiple" header-style="width:3rem" />
        <Column :header="t('quizBuilder.question')" style="min-width:240px">
          <template #body="{ data }">{{ stripHtml(data.content) }}</template>
        </Column>
        <Column field="type" :header="t('quizBuilder.qType')" style="min-width:110px" />
        <Column :header="t('quizBuilder.difficulty')" style="min-width:110px">
          <template #body="{ data }">{{ difficultyLabel(data.difficulty) }}</template>
        </Column>
        <Column v-if="groups.length" :header="t('quizBuilder.group')" style="min-width:130px">
          <template #body="{ data }">{{ groupName(data.question_group_id) }}</template>
        </Column>
        <template #empty>
          <div class="empty">{{ activeBankId ? t('common.noData') : t('quizBuilder.selectBankFirst') }}</div>
        </template>
      </DataTable>
      <p class="hint">{{ t('quizBuilder.fixedSelected', { n: questionIds.length }) }}</p>
    </div>

    <!-- Random fixed count -->
    <div v-else-if="mode === 'random'" class="qsel-panel">
      <p class="mode-hint">{{ t('quizBuilder.randomHint') }}</p>
      <div class="rule-form">
        <label class="field">
          <span>{{ t('quizBuilder.group') }}</span>
          <Select v-model="ruleForm.group_id" :options="groupOptions" option-label="label" option-value="value" show-clear :placeholder="t('quizBuilder.wholeBank')" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('quizBuilder.difficulty') }}</span>
          <Select v-model="ruleForm.difficulty" :options="difficultyOptions" option-label="label" option-value="value" show-clear :placeholder="t('quizBuilder.anyDifficulty')" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('quizBuilder.count') }}</span>
          <InputNumber v-model="ruleForm.count" :min="1" class="w-full" input-class="w-full" />
        </label>
        <div class="field rule-form-btn">
          <span>&nbsp;</span>
          <Button :label="t('quizBuilder.addRule')" icon="pi pi-plus" :disabled="!activeBankId || !ruleForm.count" @click="addRandomRule" />
        </div>
      </div>
      <p class="hint">{{ t('quizBuilder.poolAvailable', { n: availableForRuleForm }) }}</p>
    </div>

    <!-- Percent by difficulty -->
    <div v-else class="qsel-panel">
      <p class="mode-hint">{{ t('quizBuilder.percentHint') }}</p>
      <div class="rule-form">
        <label class="field">
          <span>{{ t('quizBuilder.group') }}</span>
          <Select v-model="percentForm.group_id" :options="groupOptions" option-label="label" option-value="value" show-clear :placeholder="t('quizBuilder.wholeBank')" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('quizBuilder.totalCount') }}</span>
          <InputNumber v-model="percentForm.total" :min="1" class="w-full" input-class="w-full" />
        </label>
      </div>

      <div class="percent-grid">
        <div v-for="lvl in difficultyOptions" :key="lvl.value" class="percent-cell">
          <span class="lvl-label">{{ lvl.label }}</span>
          <InputNumber v-model="percentForm.percents[lvl.value]" :min="0" :max="100" suffix="%" input-class="w-full" class="w-full" />
          <small class="muted">
            {{ t('quizBuilder.computedCount', { n: computedPercentCounts[lvl.value] || 0 }) }}
            · {{ t('quizBuilder.poolAvailable', { n: availableForLevel(lvl.value) }) }}
          </small>
        </div>
      </div>

      <div class="percent-sum">
        <span>{{ t('quizBuilder.percentSum') }}:</span>
        <Tag :value="percentSum + '%'" :severity="percentSum === 100 ? 'success' : (percentSum > 100 ? 'danger' : 'warning')" />
        <Button
          :label="t('quizBuilder.applyPercent')"
          icon="pi pi-check"
          size="small"
          :disabled="percentSum !== 100 || !percentForm.total || !activeBankId"
          @click="applyPercent"
        />
      </div>
    </div>

    <!-- Configured random rules (visible whenever random/percent tab is used) -->
    <div v-if="mode !== 'fixed' || randomRules.length" class="rule-list">
      <strong class="rule-list-title">{{ t('quizBuilder.configuredRules') }}</strong>
      <div v-for="rule in randomRules" :key="rule._uid" class="rule-row">
        <span class="rule-bank"><i class="pi pi-database" /> {{ rule.bank_name }}</span>
        <span v-if="rule.group_name" class="pill tone-info">{{ rule.group_name }}</span>
        <span v-if="rule.difficulty" class="pill tone-warn">{{ difficultyLabel(rule.difficulty) }}</span>
        <span class="rule-count">{{ t('quizBuilder.countN', { n: rule.count }) }}</span>
        <Button icon="pi pi-times" text rounded severity="danger" size="small" @click="removeRandomRule(rule._uid)" />
      </div>
      <div v-if="!randomRules.length" class="empty small">{{ t('quizBuilder.noRandomRules') }}</div>
    </div>

    <div class="qsel-summary">
      <i class="pi pi-info-circle" />
      <span>{{ t('quizBuilder.summary', { fixed: questionIds.length, random: totalRandomCount }) }}</span>
    </div>
  </div>
</template>

<style scoped>
.qsel { display: flex; flex-direction: column; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field.full { grid-column: 1 / -1; }
.w-full { width: 100%; }
.bank-opt { display: grid; gap: 2px; }
.bank-opt small { color: var(--text-muted); font-size: .74rem; }

.qsel-tabs { display: flex; flex-wrap: wrap; gap: 6px; }
.tab {
  display: inline-flex; align-items: center; gap: 6px;
  min-height: 34px; padding: 0 14px; border: 1px solid var(--border); border-radius: 999px;
  background: var(--surface-subtle); color: var(--text-muted); font: inherit; font-weight: 650; cursor: pointer;
}
.tab.on { background: var(--brand-soft); border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); color: var(--brand); }

.qsel-panel { display: flex; flex-direction: column; gap: 10px; }
.mode-hint { margin: 0; color: var(--text-muted); font-size: .84rem; }
.hint { margin: 0; color: var(--text-muted); font-size: .84rem; font-weight: 600; }
.empty { padding: 24px; color: var(--text-muted); text-align: center; }
.empty.small { padding: 12px; font-size: .84rem; }

.rule-form { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; align-items: end; }
.rule-form-btn { justify-content: flex-end; }
@media (max-width: 900px) { .rule-form { grid-template-columns: 1fr 1fr; } }

.percent-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
.percent-cell { display: flex; flex-direction: column; gap: 4px; padding: 8px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface-subtle); }
.lvl-label { font-size: .78rem; font-weight: 700; }
.muted { color: var(--text-muted); font-size: .72rem; }
.percent-sum { display: flex; align-items: center; gap: 10px; }

.rule-list { display: flex; flex-direction: column; gap: 6px; padding: 10px; border: 1px dashed var(--border); border-radius: 12px; background: var(--surface-subtle); }
.rule-list-title { font-size: .8rem; }
.rule-row { display: flex; align-items: center; gap: 8px; padding: 6px 4px; border-bottom: 1px dashed var(--border); flex-wrap: wrap; }
.rule-row:last-of-type { border-bottom: none; }
.rule-bank { font-weight: 700; font-size: .86rem; display: flex; align-items: center; gap: 6px; }
.rule-count { margin-left: auto; font-weight: 700; color: var(--brand); font-size: .84rem; }

.pill {
  display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px;
  font-size: .72rem; font-weight: 700;
}
.tone-info { background: #e0f2fe; color: #0369a1; }
.tone-warn { background: #fef9c3; color: #a16207; }

.qsel-summary {
  display: flex; align-items: center; gap: 8px; padding: 10px 12px;
  border-radius: 10px; background: color-mix(in srgb, var(--brand) 8%, var(--surface)); color: var(--text);
  font-size: .86rem; font-weight: 600;
}

.q-table :deep(.p-datatable-tbody td) { vertical-align: top; }
</style>
