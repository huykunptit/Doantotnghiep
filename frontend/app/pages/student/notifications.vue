<script setup lang="ts">
definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface NotifItem {
  id: number
  type?: string
  title?: string
  message?: string
  link?: string | null
  read_at?: string | null
  created_at?: string
}

const { t, locale } = useI18n()
const loading = ref(true)
const items = ref<NotifItem[]>([])

const dateLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ data?: NotifItem[] }>('/notifications', { query: { per_page: 50 } })
    items.value = res.data || []
  }
  catch {
    items.value = []
  }
  finally {
    loading.value = false
  }
}

async function markAll() {
  await useApi('/notifications/read-all', { method: 'PUT' })
  await load()
}

async function openItem(item: NotifItem) {
  if (!item.read_at) {
    await useApi(`/notifications/${item.id}/read`, { method: 'PUT' }).catch(() => null)
  }
  if (item.link) await navigateTo(item.link)
  else await load()
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('student.notif.title') }}</h1>
        <p>{{ t('student.notif.subtitle') }}</p>
      </div>
      <Button :label="t('student.notif.readAll')" icon="pi pi-check" severity="secondary" @click="markAll" />
    </header>

    <div v-if="loading" class="empty">…</div>
    <div v-else-if="!items.length" class="empty">{{ t('student.notif.empty') }}</div>
    <div v-else class="list">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        class="card"
        :class="{ unread: !item.read_at }"
        @click="openItem(item)"
      >
        <strong>{{ item.title || t('student.notif.untitled') }}</strong>
        <span>{{ item.message }}</span>
        <small>{{ item.created_at ? new Date(item.created_at).toLocaleString(dateLocale) : '' }}</small>
      </button>
    </div>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.list { display: grid; gap: 8px; }
.card {
  text-align: left; display: grid; gap: 4px; padding: 14px 16px; border-radius: 14px;
  border: 1px solid var(--border); background: color-mix(in srgb, var(--surface) 92%, transparent);
  cursor: pointer; color: inherit;
}
.card.unread { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); }
.card span, .card small { color: var(--text-muted); font-weight: 500; }
.empty { color: var(--text-muted); }
</style>
