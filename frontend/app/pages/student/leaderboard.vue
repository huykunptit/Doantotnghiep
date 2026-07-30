<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface RankUser {
  id: number
  name: string
  student_code?: string | null
  avatar?: string | null
  points_balance?: number
  streak_days?: number
}

const toast = useToast()
const auth = useAuthStore()
const { t } = useI18n()
const loading = ref(true)
const top = ref<RankUser[]>([])
const myRank = ref(0)
const myBalance = ref(0)

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ top?: RankUser[], my_rank?: number, my_balance?: number }>('/points/leaderboard')
    top.value = res.top || []
    myRank.value = Number(res.my_rank || 0)
    myBalance.value = Number(res.my_balance || 0)
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.points.boardError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('student.points.boardTitle') }}</h1>
        <p>{{ t('student.points.boardSubtitle') }}</p>
      </div>
    </header>

    <section class="me">
      <div>
        <strong>{{ t('student.points.yourRank') }}</strong>
        <span>#{{ myRank || '—' }} · {{ myBalance }} {{ t('student.points.pts') }}</span>
      </div>
    </section>

    <div v-if="loading" class="empty">…</div>
    <div v-else-if="!top.length" class="empty">{{ t('student.points.boardEmpty') }}</div>
    <ol v-else class="board">
      <li
        v-for="(user, index) in top"
        :key="user.id"
        :class="{ me: user.id === auth.user?.id }"
      >
        <span class="rank">{{ index + 1 }}</span>
        <Avatar v-if="user.avatar" :image="user.avatar" shape="circle" />
        <Avatar v-else :label="(user.name || '?').slice(0, 1).toUpperCase()" shape="circle" />
        <div class="copy">
          <strong>{{ user.name }}</strong>
          <span>{{ user.student_code || t('student.roleLabel') }} · streak {{ user.streak_days || 0 }}</span>
        </div>
        <em>{{ user.points_balance || 0 }}</em>
      </li>
    </ol>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.me {
  padding: 14px 16px; border-radius: 14px; border: 1px solid var(--border);
  background: color-mix(in srgb, var(--brand) 10%, var(--surface));
}
.me strong { display: block; }
.me span { color: var(--text-muted); font-weight: 600; }
.board { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; }
.board li {
  display: grid; grid-template-columns: 36px 40px 1fr auto; gap: 10px; align-items: center;
  padding: 12px 14px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.board li.me { border-color: var(--brand); }
.rank { font-weight: 800; color: var(--brand); text-align: center; }
.copy strong { display: block; }
.copy span { color: var(--text-muted); font-size: .82rem; font-weight: 500; }
.board em { font-style: normal; font-weight: 800; }
.empty { color: var(--text-muted); }
</style>
