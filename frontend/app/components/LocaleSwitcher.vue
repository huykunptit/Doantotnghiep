<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

const { locale, locales, setLocale, t } = useI18n()
const toast = useToast()
const open = ref(false)
const root = ref<HTMLElement | null>(null)

const flagSrc: Record<string, string> = {
  vi: '/flags/vi.png',
  en: '/flags/en.png',
}

const options = computed(() =>
  (locales.value as Array<{ code: string, name?: string }>).map(item => ({
    value: item.code,
    name: item.name || item.code,
    flag: flagSrc[item.code] || '',
    short: item.code.toUpperCase(),
  })),
)

const current = computed(() => options.value.find(item => item.value === locale.value) || options.value[0])

async function choose(code: string) {
  open.value = false
  if (!code || code === locale.value) return
  await setLocale(code)
  toast.add({
    severity: 'success',
    summary: t('common.languageChanged'),
    detail: t('common.languageChangedDetail'),
    life: 2500,
  })
}

function onDocClick(event: MouseEvent) {
  if (!root.value?.contains(event.target as Node)) open.value = false
}

onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div ref="root" class="locale-dd" :class="{ open }">
    <button
      type="button"
      class="locale-trigger"
      :aria-expanded="open"
      aria-haspopup="listbox"
      :aria-label="current?.name || 'Language'"
      @click.stop="open = !open"
    >
      <img v-if="current?.flag" :src="current.flag" :alt="current.name" class="flag" width="22" height="16">
      <span class="code">{{ current?.short }}</span>
      <i class="pi pi-chevron-down chevron" />
    </button>

    <ul v-show="open" class="locale-menu" role="listbox">
      <li
        v-for="opt in options"
        :key="opt.value"
        role="option"
        :aria-selected="opt.value === locale"
        :class="{ active: opt.value === locale }"
        @click.stop="choose(opt.value)"
      >
        <img :src="opt.flag" :alt="opt.name" class="flag" width="22" height="16">
        <span class="name">{{ opt.name }}</span>
        <i v-if="opt.value === locale" class="pi pi-check" />
      </li>
    </ul>
  </div>
</template>

<style scoped>
.locale-dd {
  position: relative;
}

.locale-trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 36px;
  padding: 0 12px 0 10px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  color: var(--text);
  font: inherit;
  cursor: pointer;
  transition: border-color .15s ease, box-shadow .15s ease;
}

.locale-trigger:hover,
.locale-dd.open .locale-trigger {
  border-color: color-mix(in srgb, var(--brand) 35%, var(--border));
  box-shadow: var(--shadow-sm);
}

.flag {
  width: 22px;
  height: 16px;
  border-radius: 4px;
  object-fit: cover;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .08);
}

.code {
  font-size: .8rem;
  font-weight: 700;
  letter-spacing: .04em;
}

.chevron {
  font-size: .65rem;
  color: var(--text-muted);
  transition: transform .18s ease;
}

.locale-dd.open .chevron {
  transform: rotate(180deg);
}

.locale-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 40;
  min-width: 188px;
  margin: 0;
  padding: 6px;
  list-style: none;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 96%, transparent);
  box-shadow: var(--shadow-md);
  backdrop-filter: blur(12px);
}

.locale-menu li {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  padding: 0 10px;
  border-radius: 10px;
  color: var(--text);
  cursor: pointer;
  transition: background .12s ease;
}

.locale-menu li:hover {
  background: var(--surface-hover);
}

.locale-menu li.active {
  background: var(--brand-soft);
  color: var(--brand);
}

.name {
  flex: 1;
  font-size: .88rem;
  font-weight: 600;
}

.locale-menu .pi-check {
  font-size: .75rem;
}
</style>
