<script setup lang="ts">
const props = withDefaults(defineProps<{
  viewAllTo?: string
}>(), {
  viewAllTo: '/student/notifications',
})

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
const panel = ref()
const loading = ref(false)
const items = ref<NotifItem[]>([])
const unread = ref(0)

const dateLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function formatTime(value?: string) {
  if (!value) return ''
  return new Date(value).toLocaleString(dateLocale.value, {
    dateStyle: 'short',
    timeStyle: 'short',
  })
}

async function loadUnread() {
  try {
    const res = await useApi<{ count?: number }>('/notifications/unread-count')
    unread.value = Number(res.count || 0)
  }
  catch {
    unread.value = 0
  }
}

async function loadList() {
  loading.value = true
  try {
    const res = await useApi<{ data?: NotifItem[] }>('/notifications', { query: { per_page: 12 } })
    items.value = res.data || []
  }
  catch {
    items.value = []
  }
  finally {
    loading.value = false
  }
}

async function openPanel(event: Event) {
  panel.value?.toggle(event)
  await Promise.all([loadList(), loadUnread()])
}

async function markOne(item: NotifItem) {
  if (!item.read_at) {
    try {
      await useApi(`/notifications/${item.id}/read`, { method: 'PUT' })
      item.read_at = new Date().toISOString()
      unread.value = Math.max(0, unread.value - 1)
    }
    catch { /* ignore */ }
  }
  if (item.link) {
    panel.value?.hide()
    await navigateTo(item.link)
  }
}

async function markAll() {
  try {
    await useApi('/notifications/read-all', { method: 'PUT' })
    items.value = items.value.map(n => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
    unread.value = 0
  }
  catch { /* ignore */ }
}

onMounted(loadUnread)

// Near-realtime poll (no WebSocket/Pusher in stack yet)
let timer: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  timer = setInterval(loadUnread, 15000)
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <div class="bell-wrap">
    <Button
      icon="pi pi-bell"
      severity="secondary"
      text
      rounded
      :aria-label="t('student.notif.title')"
      @click="openPanel"
    />
    <span v-if="unread > 0" class="badge">{{ unread > 99 ? '99+' : unread }}</span>

    <Popover ref="panel" class="notif-pop">
      <div class="panel">
        <header>
          <strong>{{ t('student.notif.title') }}</strong>
          <Button :label="t('student.notif.readAll')" text size="small" @click="markAll" />
        </header>
        <div v-if="loading" class="empty">…</div>
        <div v-else-if="!items.length" class="empty">{{ t('student.notif.empty') }}</div>
        <ul v-else>
          <li
            v-for="item in items"
            :key="item.id"
            :class="{ unread: !item.read_at }"
            @click="markOne(item)"
          >
            <strong>{{ item.title || t('student.notif.untitled') }}</strong>
            <span class="rich" v-html="item.message || ''" />
            <small>{{ formatTime(item.created_at) }}</small>
          </li>
        </ul>
        <footer>
          <Button :label="t('student.notif.viewAll')" text size="small" @click="navigateTo(props.viewAllTo); panel?.hide()" />
        </footer>
      </div>
    </Popover>
  </div>
</template>

<style scoped>
.bell-wrap { position: relative; }
.badge {
  position: absolute; top: 2px; right: 2px; min-width: 16px; height: 16px; padding: 0 4px;
  border-radius: 999px; background: #dc2626; color: #fff; font-size: .65rem; font-weight: 800;
  display: grid; place-items: center; pointer-events: none;
}
.panel { width: min(360px, 86vw); display: grid; gap: 8px; }
.panel header, .panel footer { display: flex; justify-content: space-between; align-items: center; }
.panel ul { list-style: none; margin: 0; padding: 0; max-height: 360px; overflow: auto; display: grid; gap: 6px; }
.panel li {
  display: grid; gap: 2px; padding: 10px; border-radius: 10px; border: 1px solid var(--border);
  cursor: pointer; background: color-mix(in srgb, var(--surface) 94%, transparent);
}
.panel li.unread { border-color: color-mix(in srgb, var(--brand) 45%, var(--border)); background: color-mix(in srgb, var(--brand) 8%, var(--surface)); }
.panel li span, .panel li small { color: var(--text-muted); font-size: .82rem; font-weight: 500; }
.empty { color: var(--text-muted); padding: 8px 0; }
</style>
