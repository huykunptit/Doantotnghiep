<script setup lang="ts">
import { onMounted, ref } from 'vue'
// Icons removed - using PrimeIcons
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const certs = ref<any[]>([])

onMounted(async () => {
  try {
    const data = await useApi<any>('/user/my-certificates', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    certs.value = Array.isArray(data) ? data : (data?.data || [])
  } finally {
    loading.value = false
  }
})

function formatDate(d: string) {
  return d ? new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'
}
</script>

<template>
  <div class="ce-page">
    <div class="ce-header">
      <div>
        <p class="ce-kicker">Học vụ</p>
        <h1 class="ce-title">Chứng chỉ của tôi</h1>
      </div>
      <span class="ce-count" v-if="!loading">{{ certs.length }} chứng chỉ</span>
    </div>

    <div v-if="loading" class="ce-grid">
      <div v-for="i in 6" :key="i" class="ce-skeleton" />
    </div>
    <div v-else-if="certs.length === 0" class="ce-empty">
      <i class="pi pi-verified" style="font-size:2.75rem" />
      <h3>Chưa có chứng chỉ nào</h3>
      <p>Hoàn thành các khóa học để nhận chứng chỉ.</p>
      <NuxtLink to="/student/courses" class="ce-empty-link">Xem khóa học của tôi</NuxtLink>
    </div>
    <div v-else class="ce-grid">
      <div v-for="cert in certs" :key="cert.id" class="ce-card">
        <!-- Certificate visual -->
        <div class="ce-visual">
          <div class="ce-visual-inner">
            <div class="ce-visual-icon">🎓</div>
            <div class="ce-visual-text">
              <span class="ce-visual-label">CHỨNG CHỈ</span>
              <h3 class="ce-visual-name">{{ cert.course?.title || cert.certificate?.name || 'Chứng chỉ' }}</h3>
            </div>
          </div>
          <div class="ce-visual-ribbon" />
        </div>

        <!-- Info -->
        <div class="ce-info">
          <p class="ce-course-name">{{ cert.course?.title || cert.certificate?.name || 'Chứng chỉ' }}</p>
          <div class="ce-meta-row">
            <span class="ce-meta-item">
              <span class="ce-meta-lbl">Cấp ngày:</span>
              {{ formatDate(cert.issued_at) }}
            </span>
            <span v-if="cert.expires_at" class="ce-meta-item">
              <span class="ce-meta-lbl">Hết hạn:</span>
              {{ formatDate(cert.expires_at) }}
            </span>
          </div>
          <div v-if="cert.serial_number || cert.credential_id" class="ce-serial">
            ID: {{ cert.serial_number || cert.credential_id }}
          </div>
        </div>

        <!-- Actions -->
        <div class="ce-actions">
          <a v-if="cert.pdf_url || cert.download_url" :href="cert.pdf_url || cert.download_url" target="_blank" rel="noopener" class="ce-btn ce-btn--primary">
            <i class="pi pi-download" style="font-size:0.875rem" /> Tải xuống PDF
          </a>
          <NuxtLink v-if="cert.course?.id" :to="`/learn/${cert.course.id}`" class="ce-btn ce-btn--outline">
            Xem khóa học
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ce-page { max-width: 1100px; margin: 0 auto; }
.ce-header {
  display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px;
}
.ce-kicker { margin: 0 0 4px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); }
.ce-title { margin: 0; font-size: 1.7rem; font-weight: 800; color: var(--text); letter-spacing: -0.02em; }
.ce-count { font-size: 0.84rem; font-weight: 600; color: var(--muted); }

/* Grid */
.ce-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 18px;
}
.ce-skeleton {
  height: 280px; border-radius: 18px;
  background: linear-gradient(90deg, var(--line) 25%, rgba(221,229,225,0.5) 50%, var(--line) 75%);
  background-size: 200% 100%; animation: shimmer 1.4s ease-in-out infinite;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* Card */
.ce-card {
  background: var(--surface-strong); border: 1px solid var(--line);
  border-radius: 18px; overflow: hidden;
  display: flex; flex-direction: column;
  transition: transform 200ms, box-shadow 200ms;
}
.ce-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 30px -12px rgba(8,80,65,0.18);
}

/* Visual header */
.ce-visual {
  position: relative;
  background: linear-gradient(135deg, #063d31 0%, #085041 50%, #0d7a60 100%);
  padding: 28px 24px 22px;
  overflow: hidden;
}
.ce-visual-inner { position: relative; z-index: 1; display: flex; gap: 16px; align-items: center; }
.ce-visual-icon { font-size: 2.5rem; flex-shrink: 0; }
.ce-visual-label { font-size: 0.58rem; font-weight: 800; letter-spacing: 0.18em; text-transform: uppercase; color: rgba(255,255,255,0.55); display: block; margin-bottom: 4px; }
.ce-visual-name { margin: 0; font-size: 0.88rem; font-weight: 800; color: #fff; line-height: 1.4;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ce-visual-ribbon {
  position: absolute; top: 0; right: -20px; bottom: 0; width: 100px;
  background: rgba(255,255,255,0.04);
  transform: skewX(-12deg);
}

/* Info */
.ce-info { padding: 16px 18px 12px; flex: 1; }
.ce-course-name {
  margin: 0 0 10px; font-size: 0.875rem; font-weight: 700; color: var(--text); line-height: 1.4;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.ce-meta-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 8px; }
.ce-meta-item { font-size: 0.75rem; color: var(--muted); }
.ce-meta-lbl { font-weight: 700; color: var(--text); }
.ce-serial { font-size: 0.68rem; color: var(--muted); font-family: monospace; letter-spacing: 0.04em; }

/* Actions */
.ce-actions { display: flex; gap: 8px; padding: 0 18px 18px; }
.ce-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 14px; border-radius: 8px;
  font-size: 0.78rem; font-weight: 700; text-decoration: none;
  transition: background 150ms, color 150ms, border-color 150ms;
}
.ce-btn--primary { background: var(--green-soft); color: var(--green-deep); flex: 1; justify-content: center; }
.ce-btn--primary:hover { background: var(--green); color: #fff; }
.ce-btn--outline { border: 1px solid var(--line); color: var(--muted); background: transparent; }
.ce-btn--outline:hover { background: var(--bg); color: var(--text); }

/* Empty */
.ce-empty {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  padding: 80px 20px; text-align: center; color: var(--muted);
}
.ce-empty h3 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text); }
.ce-empty p { margin: 0; font-size: 0.875rem; }
.ce-empty-link { font-size: 0.875rem; font-weight: 700; color: var(--green); text-decoration: underline; }

[data-theme="dark"] .ce-card { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.07); }

@media (max-width: 640px) { .ce-grid { grid-template-columns: 1fr; } }
</style>
