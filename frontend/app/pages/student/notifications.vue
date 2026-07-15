<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const notifications = ref<any[]>([])
const marking = ref(false)

onMounted(load)

async function load() {
  loading.value = true
  try {
    const data = await useApi<any>('/user/notifications?per_page=50', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    notifications.value = data?.data || data || []
  } finally {
    loading.value = false
  }
}

async function markAllRead() {
  marking.value = true
  try {
    await useApi('/user/notifications/read-all', {
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }))
  } finally {
    marking.value = false
  }
}

async function markRead(n: any) {
  if (n.read_at) return
  try {
    await useApi(`/user/notifications/${n.id}/read`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    n.read_at = new Date().toISOString()
  } catch {}
}

const unread = computed(() => notifications.value.filter(n => !n.read_at).length)

function formatDate(d: string) {
  if (!d) return ''
  const dt = new Date(d)
  const now = new Date()
  const diff = Math.floor((now.getTime() - dt.getTime()) / 60000)
  if (diff < 1) return 'Vừa xong'
  if (diff < 60) return `${diff} phút trước`
  if (diff < 1440) return `${Math.floor(diff / 60)} giờ trước`
  return dt.toLocaleDateString('vi-VN')
}

function typeIcon(type: string) {
  if (!type) return '📢'
  if (type.includes('course') || type.includes('Course')) return '📚'
  if (type.includes('exam') || type.includes('Exam')) return '📋'
  if (type.includes('grade') || type.includes('Grade')) return '📊'
  if (type.includes('cert') || type.includes('Cert')) return '🏆'
  if (type.includes('payment') || type.includes('Payment')) return '💳'
  return '🔔'
}
</script>

<template>
  <div class="nn-page">
    <div class="nn-header">
      <div class="nn-header-left">
        <p class="nn-kicker">Kênh hỗ trợ</p>
        <h1 class="nn-title">Thông báo</h1>
      </div>
      <div class="nn-header-right">
        <span v-if="unread > 0" class="nn-unread-badge">{{ unread }} chưa đọc</span>
        <button v-if="unread > 0" class="nn-mark-all" :disabled="marking" @click="markAllRead">
          <i class="pi pi-check" style="font-size:0.875rem" />
          {{ marking ? 'Đang xử lý...' : 'Đánh dấu đã đọc' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="nn-list">
      <div v-for="i in 6" :key="i" class="nn-skeleton" />
    </div>
    <div v-else-if="notifications.length === 0" class="nn-empty">
      <i class="pi pi-bell" style="font-size:2.75rem" />
      <h3>Không có thông báo nào</h3>
      <p>Các thông báo về khóa học, kỳ thi và điểm số sẽ xuất hiện ở đây.</p>
    </div>
    <div v-else class="nn-list">
      <button
        v-for="n in notifications"
        :key="n.id"
        class="nn-item"
        :class="{ 'is-unread': !n.read_at }"
        @click="markRead(n)"
      >
        <div class="nn-icon">{{ typeIcon(n.type || n.data?.type) }}</div>
        <div class="nn-body">
          <p class="nn-msg">{{ n.data?.message || n.data?.title || n.data?.body || 'Thông báo mới' }}</p>
          <p v-if="n.data?.description || n.data?.body" class="nn-desc">
            {{ n.data.description || n.data.body }}
          </p>
          <span class="nn-time">{{ formatDate(n.created_at) }}</span>
        </div>
        <div class="nn-unread-dot" v-if="!n.read_at" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.nn-page { max-width: 760px; margin: 0 auto; }
.nn-header {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin-bottom: 24px; gap: 16px; flex-wrap: wrap;
}
.nn-kicker { margin: 0 0 4px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); }
.nn-title { margin: 0; font-size: 1.7rem; font-weight: 800; color: var(--text); letter-spacing: -0.02em; }
.nn-header-right { display: flex; align-items: center; gap: 10px; }
.nn-unread-badge {
  padding: 4px 12px; border-radius: 99px;
  background: rgba(29,158,117,0.12); color: var(--green-deep);
  font-size: 0.75rem; font-weight: 700;
}
.nn-mark-all {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 14px; border-radius: 8px; border: 1px solid var(--line);
  background: var(--surface-strong); color: var(--muted);
  font-size: 0.78rem; font-weight: 600; cursor: pointer;
  transition: background 150ms, color 150ms;
}
.nn-mark-all:hover:not(:disabled) { background: var(--green-soft); color: var(--green-deep); border-color: rgba(29,158,117,0.3); }
.nn-mark-all:disabled { opacity: 0.5; cursor: not-allowed; }

/* List */
.nn-list { display: flex; flex-direction: column; gap: 2px; }
.nn-skeleton {
  height: 72px; border-radius: 12px;
  background: linear-gradient(90deg, var(--line) 25%, rgba(221,229,225,0.5) 50%, var(--line) 75%);
  background-size: 200% 100%; animation: shimmer 1.4s ease-in-out infinite;
  margin-bottom: 2px;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* Item */
.nn-item {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 14px 16px; border-radius: 12px; width: 100%; text-align: left;
  background: var(--surface-strong); border: 1px solid var(--line);
  cursor: pointer; transition: background 150ms;
}
.nn-item:hover { background: var(--bg); }
.nn-item.is-unread {
  background: rgba(29,158,117,0.04);
  border-color: rgba(29,158,117,0.18);
}
.nn-item.is-unread:hover { background: rgba(29,158,117,0.08); }

.nn-icon { font-size: 1.5rem; flex-shrink: 0; margin-top: 1px; }
.nn-body { flex: 1; min-width: 0; }
.nn-msg { margin: 0 0 4px; font-size: 0.875rem; font-weight: 600; color: var(--text); line-height: 1.4; }
.nn-item.is-unread .nn-msg { font-weight: 700; }
.nn-desc { margin: 0 0 6px; font-size: 0.78rem; color: var(--muted); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.nn-time { font-size: 0.7rem; color: var(--muted); }
.nn-unread-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--green); flex-shrink: 0; margin-top: 6px;
}

/* Empty */
.nn-empty {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  padding: 80px 20px; text-align: center; color: var(--muted);
}
.nn-empty h3 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text); }
.nn-empty p { margin: 0; font-size: 0.875rem; max-width: 320px; }

[data-theme="dark"] .nn-item { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.07); }
[data-theme="dark"] .nn-item:hover { background: rgba(255,255,255,0.06); }
[data-theme="dark"] .nn-item.is-unread { background: rgba(29,158,117,0.07); border-color: rgba(29,158,117,0.25); }
[data-theme="dark"] .nn-mark-all { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); }
</style>
