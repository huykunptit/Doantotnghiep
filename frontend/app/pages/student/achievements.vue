<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const certificates = ref<any[]>([])
const transcript = ref<any>(null)
const enrollments = ref<any[]>([])

onMounted(async () => {
  const h = { Authorization: `Bearer ${auth.token}` }
  const [r0, r1, r2] = await Promise.allSettled([
    useApi<any[]>('/user/my-certificates', { headers: h }),
    useApi<any>('/me/transcript', { headers: h }),
    useApi<any[]>('/user/enrollments', { headers: h }),
  ])
  if (r0.status === 'fulfilled') { const d = r0.value; certificates.value = Array.isArray(d) ? d : (d?.data || []) }
  if (r1.status === 'fulfilled') transcript.value = r1.value
  if (r2.status === 'fulfilled') enrollments.value = r2.value || []
  loading.value = false
})

const gpa = computed(() => {
  const raw = transcript.value?.gpa ?? transcript.value?.cumulative_gpa
  return raw ? Number(raw).toFixed(2) : null
})

const gpaLabel = computed(() => {
  const v = parseFloat(gpa.value || '0')
  if (v >= 3.6) return 'Xuất sắc'
  if (v >= 3.2) return 'Giỏi'
  if (v >= 2.5) return 'Khá'
  if (v >= 2.0) return 'Trung bình'
  return '—'
})

const gpaColor = computed(() => {
  const v = parseFloat(gpa.value || '0')
  if (v >= 3.6) return 'green'
  if (v >= 3.2) return 'blue'
  if (v >= 2.5) return 'amber'
  return 'red'
})

const totalCredits = computed(() => transcript.value?.total_credits ?? transcript.value?.credits ?? 0)
const completedCourses = computed(() => enrollments.value.filter(e => (e.progress ?? 0) >= 100).length)
const totalExams = computed(() => transcript.value?.total_exams ?? 0)

function formatDate(d?: string) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <div class="ach-page">
    <div>
      <p class="section-kicker">Học vụ</p>
      <h1 class="ach-title">Thành tích của tôi</h1>
    </div>

    <!-- Hero GPA card -->
    <div class="ach-hero" :class="`ach-hero-${gpaColor}`">
      <div class="ach-hero-inner">
        <div class="ach-gpa-wrap">
          <div v-if="loading" class="sd-shimmer" style="width:90px;height:80px;border-radius:12px;display:block"></div>
          <template v-else>
            <span class="ach-gpa-val">{{ gpa || '—' }}</span>
            <span class="ach-gpa-lbl">GPA tích lũy</span>
          </template>
        </div>
        <div class="ach-hero-sep"></div>
        <div class="ach-hero-stats">
          <div class="ach-hero-stat">
            <span class="ach-hs-val">{{ loading ? '—' : gpaLabel }}</span>
            <span class="ach-hs-lbl">Xếp loại</span>
          </div>
          <div class="ach-hero-stat">
            <span class="ach-hs-val">{{ loading ? '—' : totalCredits }}</span>
            <span class="ach-hs-lbl">Tín chỉ tích lũy</span>
          </div>
          <div class="ach-hero-stat">
            <span class="ach-hs-val">{{ loading ? '—' : completedCourses }}</span>
            <span class="ach-hs-lbl">Khóa hoàn thành</span>
          </div>
          <div class="ach-hero-stat">
            <span class="ach-hs-val">{{ loading ? '—' : certificates.length }}</span>
            <span class="ach-hs-lbl">Chứng chỉ</span>
          </div>
        </div>
      </div>
      <div class="ach-hero-deco" aria-hidden="true">
        <div class="ach-deco-1"></div>
        <div class="ach-deco-2"></div>
      </div>
    </div>

    <!-- Certificates -->
    <div class="ach-section">
      <div class="card-head">
        <div>
          <p class="section-kicker">Chứng chỉ</p>
          <h2 class="ach-section-title">Chứng chỉ đã đạt được</h2>
        </div>
        <NuxtLink to="/student/certificates" class="ach-link-more">Xem tất cả</NuxtLink>
      </div>

      <div v-if="loading" class="ach-cert-grid">
        <div v-for="i in 4" :key="i" class="ach-cert-skeleton">
          <span class="sd-shimmer" style="height:100px;display:block;border-radius:0"></span>
          <div style="padding:12px;display:flex;flex-direction:column;gap:6px">
            <span class="sd-shimmer" style="height:12px;width:80%;display:block"></span>
            <span class="sd-shimmer" style="height:10px;width:55%;display:block"></span>
          </div>
        </div>
      </div>

      <div v-else-if="certificates.length" class="ach-cert-grid">
        <div v-for="cert in certificates" :key="cert.id" class="ach-cert-card">
          <div class="ach-cert-top">
            <SylvaIcon name="award" :size="28" class="ach-cert-icon" />
            <div class="ach-cert-badge">Đã cấp</div>
          </div>
          <div class="ach-cert-body">
            <p class="ach-cert-course">{{ cert.course?.title || cert.course_title || 'Khóa học' }}</p>
            <p class="ach-cert-name">{{ cert.title || cert.name || 'Chứng chỉ hoàn thành' }}</p>
            <p class="ach-cert-date">Cấp ngày {{ formatDate(cert.issued_at || cert.created_at) }}</p>
          </div>
          <div class="ach-cert-footer">
            <a v-if="cert.pdf_url || cert.certificate_url" :href="cert.pdf_url || cert.certificate_url" target="_blank" class="ach-btn-dl">
              <SylvaIcon name="download" :size="13" /> Tải PDF
            </a>
            <span v-else class="ach-cert-code">#{{ cert.certificate_number || cert.id }}</span>
          </div>
        </div>
      </div>

      <div v-else class="sd-empty">
        <SylvaIcon name="award" :size="40" />
        <p>Chưa có chứng chỉ nào.</p>
        <NuxtLink to="/student/courses" class="ach-btn-cta">Bắt đầu học ngay</NuxtLink>
      </div>
    </div>

    <!-- Stats strip -->
    <div class="ach-stats-strip">
      <div class="dashboard-card ach-stat-card">
        <div class="ach-sc-icon tone-green"><SylvaIcon name="book-open" :size="20" /></div>
        <div class="ach-sc-info">
          <span class="ach-sc-val">{{ loading ? '…' : enrollments.length }}</span>
          <span class="ach-sc-lbl">Khóa đã đăng ký</span>
        </div>
      </div>
      <div class="dashboard-card ach-stat-card">
        <div class="ach-sc-icon tone-blue"><SylvaIcon name="check-circle" :size="20" /></div>
        <div class="ach-sc-info">
          <span class="ach-sc-val">{{ loading ? '…' : completedCourses }}</span>
          <span class="ach-sc-lbl">Khóa hoàn thành</span>
        </div>
      </div>
      <div class="dashboard-card ach-stat-card">
        <div class="ach-sc-icon tone-amber"><SylvaIcon name="clipboard-list" :size="20" /></div>
        <div class="ach-sc-info">
          <span class="ach-sc-val">{{ loading ? '…' : totalExams }}</span>
          <span class="ach-sc-lbl">Kỳ thi đã làm</span>
        </div>
      </div>
      <div class="dashboard-card ach-stat-card">
        <div class="ach-sc-icon tone-violet"><SylvaIcon name="graduation-cap" :size="20" /></div>
        <div class="ach-sc-info">
          <span class="ach-sc-val">{{ loading ? '…' : totalCredits }}</span>
          <span class="ach-sc-lbl">Tín chỉ</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ach-page { display: flex; flex-direction: column; gap: 24px; }
.ach-title { font-size: 1.5rem; font-weight: 800; color: var(--text); margin: 4px 0 0; }

/* Hero */
.ach-hero {
  border-radius: 16px; overflow: hidden; position: relative;
  background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%);
}
.ach-hero-green { background: linear-gradient(135deg, #064e3b, #047857); }
.ach-hero-blue { background: linear-gradient(135deg, #1e3a8a, #2563eb); }
.ach-hero-amber { background: linear-gradient(135deg, #92400e, #d97706); }
.ach-hero-red { background: linear-gradient(135deg, #7f1d1d, #dc2626); }

.ach-hero-inner {
  position: relative; z-index: 1;
  display: flex; align-items: center; gap: 32px;
  padding: 36px 40px;
}
.ach-gpa-wrap { display: flex; flex-direction: column; align-items: center; gap: 6px; min-width: 100px; }
.ach-gpa-val { font-size: 3.5rem; font-weight: 900; color: #fff; line-height: 1; }
.ach-gpa-lbl { font-size: 0.76rem; font-weight: 600; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.08em; }
.ach-hero-sep { width: 1px; background: rgba(255,255,255,0.2); align-self: stretch; }
.ach-hero-stats { display: flex; gap: 32px; flex-wrap: wrap; }
.ach-hero-stat { display: flex; flex-direction: column; gap: 4px; }
.ach-hs-val { font-size: 1.6rem; font-weight: 800; color: #fff; }
.ach-hs-lbl { font-size: 0.72rem; color: rgba(255,255,255,0.65); font-weight: 500; }

.ach-hero-deco { position: absolute; inset: 0; pointer-events: none; }
.ach-deco-1 { position: absolute; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,0.05); top: -60px; right: -40px; }
.ach-deco-2 { position: absolute; width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,0.04); bottom: -30px; right: 80px; }

/* Section */
.ach-section { background: var(--surface-strong); border: 1px solid var(--line); border-radius: 14px; padding: 20px; }
.ach-section-title { font-size: 1.05rem; font-weight: 700; color: var(--text); margin: 2px 0 0; }
.ach-link-more { font-size: 0.8rem; font-weight: 600; color: var(--green); text-decoration: none; }
.ach-link-more:hover { text-decoration: underline; }

/* Cert grid */
.ach-cert-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin-top: 16px; }
.ach-cert-card { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: var(--surface-strong); box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.08)); }
.ach-cert-top {
  padding: 20px 16px;
  background: linear-gradient(135deg, #064e3b, #065f46);
  display: flex; align-items: center; justify-content: space-between;
}
.ach-cert-icon { color: rgba(255,255,255,0.7); }
.ach-cert-badge {
  font-size: 0.68rem; font-weight: 700; padding: 2px 8px;
  border-radius: 20px; background: rgba(255,255,255,0.2); color: #fff;
}
.ach-cert-body { padding: 12px 14px 8px; }
.ach-cert-course { font-size: 0.7rem; font-weight: 600; color: var(--muted); margin: 0 0 4px; text-transform: uppercase; letter-spacing: 0.04em; }
.ach-cert-name { font-size: 0.88rem; font-weight: 700; color: var(--text); margin: 0 0 4px; }
.ach-cert-date { font-size: 0.72rem; color: var(--muted); margin: 0; }
.ach-cert-footer { padding: 8px 14px 12px; display: flex; align-items: center; justify-content: space-between; }
.ach-btn-dl {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 12px; border-radius: 7px;
  background: var(--green-soft); color: var(--green-deep);
  font-size: 0.76rem; font-weight: 700; text-decoration: none;
  transition: background 150ms;
}
.ach-btn-dl:hover { background: rgba(16,185,129,0.2); }
.ach-cert-code { font-size: 0.72rem; color: var(--muted); font-family: monospace; }
.ach-cert-skeleton { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
.ach-btn-cta {
  display: inline-flex; align-items: center;
  padding: 7px 16px; border-radius: 8px;
  background: var(--green); color: #fff;
  font-size: 0.82rem; font-weight: 700; text-decoration: none; margin-top: 8px;
}

/* Stats strip */
.ach-stats-strip { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.ach-stat-card { display: flex; align-items: center; gap: 14px; padding: 16px; }
.ach-sc-icon {
  width: 44px; height: 44px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ach-sc-icon.tone-green { background: var(--green-soft); color: var(--green-deep); }
.ach-sc-icon.tone-blue { background: var(--secondary-soft); color: var(--secondary); }
.ach-sc-icon.tone-amber { background: var(--accent-soft); color: #92400e; }
.ach-sc-icon.tone-violet { background: rgba(139,92,246,0.1); color: #7c3aed; }
.ach-sc-info { display: flex; flex-direction: column; gap: 2px; }
.ach-sc-val { font-size: 1.5rem; font-weight: 800; color: var(--text); line-height: 1; }
.ach-sc-lbl { font-size: 0.72rem; color: var(--muted); font-weight: 600; }

.sd-shimmer { background: linear-gradient(90deg, var(--line) 25%, var(--bg) 50%, var(--line) 75%); background-size: 200% 100%; animation: sd-shimmer 1.5s infinite; border-radius: 6px; }
@keyframes sd-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.sd-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 20px; color: var(--muted); gap: 10px; }
.sd-empty p { font-size: 0.9rem; }

[data-theme="dark"] .ach-cert-card { background: var(--surface); }

@media (max-width: 900px) {
  .ach-stats-strip { grid-template-columns: repeat(2, 1fr); }
  .ach-hero-inner { flex-direction: column; gap: 20px; padding: 28px 24px; }
  .ach-hero-sep { display: none; }
}
@media (max-width: 640px) {
  .ach-stats-strip { grid-template-columns: repeat(2, 1fr); }
  .ach-cert-grid { grid-template-columns: 1fr; }
  .ach-hero-stats { gap: 20px; }
}
</style>
