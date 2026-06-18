<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Award, Link2, Check, Download } from 'lucide-vue-next'

definePageMeta({ layout: 'default', middleware: 'auth' })

const certificates = ref<any[]>([])
const loading = ref(true)
const copiedId = ref<string | null>(null)

async function fetchMyCertificates() {
  loading.value = true
  try {
    const res = await useApi<any>('/my-certificates', {
      headers: { Authorization: `Bearer ${useAuthTokenCookie().value}` },
    })
    certificates.value = Array.isArray(res) ? res : res.data || []
  }
  catch {}
  finally { loading.value = false }
}

function copyLink(credentialId: string) {
  const url = `${window.location.origin}/certificates/verify/${credentialId}`
  navigator.clipboard.writeText(url).then(() => {
    copiedId.value = credentialId
    setTimeout(() => { copiedId.value = null }, 2000)
  })
}

function downloadCert(cert: any) {
  const verifyUrl = `${window.location.origin}/certificates/verify/${cert.credential_id}`
  const printWindow = window.open('', '_blank')
  if (!printWindow) return
  const imgHtml = cert.certificate_template?.background_image_url
    ? `<img src="${cert.certificate_template.background_image_url}" style="width:100%;max-width:900px;display:block;margin:0 auto;">`
    : '<div style="width:900px;height:636px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:24px;color:#16a34a;font-family:Georgia,serif;">Chứng chỉ Sylva LMS</div>'

  printWindow.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Chứng chỉ - ${cert.course?.title || ''}</title>
  <style>
    body { margin: 0; font-family: Georgia, serif; background: #fff; }
    .cert-wrap { max-width: 900px; margin: 0 auto; padding: 40px 20px; text-align: center; }
    .cert-title { font-size: 28px; font-weight: bold; margin: 24px 0 8px; color: #14532d; }
    .cert-sub { font-size: 16px; color: #555; margin-bottom: 20px; }
    .cert-meta { font-size: 13px; color: #888; margin-top: 16px; }
    .cred-box { display: inline-block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 20px; margin: 16px auto; font-family: monospace; font-size: 16px; font-weight: bold; color: #374151; }
    .verify-link { font-size: 11px; color: #aaa; word-break: break-all; margin-top: 12px; }
    @media print { body { -webkit-print-color-adjust: exact; } }
  </style></head><body>
  <div class="cert-wrap">
    ${imgHtml}
    <div class="cert-title">${cert.course?.title || 'Chứng chỉ Hoàn thành'}</div>
    <div class="cert-sub">Cấp cho: <strong>${cert.user?.name || 'Học viên'}</strong></div>
    <div class="cert-meta">Ngày cấp: ${new Date(cert.issued_at).toLocaleDateString('vi-VN')}</div>
    <div class="cred-box">${cert.credential_id}</div>
    <div class="verify-link">Xác minh tại: ${verifyUrl}</div>
  </div>
  <script>window.onload=function(){window.print();window.close();}<\/script>
  </body></html>`)
  printWindow.document.close()
}

onMounted(fetchMyCertificates)
</script>

<template>
  <div class="certs-page">
    <!-- Header -->
    <div class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Hồ sơ học tập</p>
        <h1>Chứng chỉ của tôi</h1>
        <p style="margin: 4px 0 0; color: var(--muted); font-size: 0.9rem;">
          Danh sách chứng chỉ bạn đã đạt được. Chia sẻ hoặc tải về để xác nhận kỹ năng.
        </p>
      </div>
      <div class="header-count" v-if="!loading">
        <Award :size="18" :stroke-width="1.75" />
        {{ certificates.length }} chứng chỉ
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="dashboard-card crud-empty">
      <span class="material-symbols-outlined" style="font-size: 36px; opacity: 0.3;">hourglass_empty</span>
      <p>Đang tải chứng chỉ...</p>
    </div>

    <!-- Empty -->
    <div v-else-if="certificates.length === 0" class="dashboard-card crud-empty">
      <Award :size="40" :stroke-width="1.5" style="opacity: 0.35;" />
      <p>Bạn chưa nhận được chứng chỉ nào.</p>
      <NuxtLink to="/courses" class="crud-primary-btn" style="margin-top: 8px;">Khám phá khoá học</NuxtLink>
    </div>

    <!-- Grid -->
    <div v-else class="certs-grid">
      <div v-for="cert in certificates" :key="cert.id" class="cert-card dashboard-card">
        <!-- Thumbnail -->
        <div class="cert-thumb">
          <img
            v-if="cert.certificate_template?.background_image_url"
            :src="cert.certificate_template.background_image_url"
            :alt="cert.course?.title"
            class="cert-thumb-img"
          >
          <div v-else class="cert-thumb-placeholder">
            <Award :size="40" :stroke-width="1.5" />
          </div>
          <!-- Overlay -->
          <div class="cert-overlay">
            <span class="cert-overlay-course">{{ cert.course?.title }}</span>
            <span class="cert-overlay-date">Cấp ngày {{ new Date(cert.issued_at).toLocaleDateString('vi-VN') }}</span>
          </div>
        </div>

        <!-- Body -->
        <div class="cert-body">
          <h3 class="cert-course-name">{{ cert.course?.title || 'Khoá học' }}</h3>

          <div class="cert-cred-row">
            <span class="cert-cred-label">Mã chứng nhận</span>
            <code class="cert-cred-code">{{ cert.credential_id }}</code>
          </div>

          <!-- Actions -->
          <div class="cert-actions">
            <NuxtLink
              :to="`/certificates/verify/${cert.credential_id}`"
              target="_blank"
              class="crud-secondary-btn cert-action-btn"
            >
              Xem chi tiết
            </NuxtLink>
            <button
              type="button"
              class="cert-copy-btn"
              :class="{ 'is-copied': copiedId === cert.credential_id }"
              @click="copyLink(cert.credential_id)"
            >
              <Check v-if="copiedId === cert.credential_id" :size="15" :stroke-width="2.5" />
              <Link2 v-else :size="15" :stroke-width="2" />
              {{ copiedId === cert.credential_id ? 'Đã sao chép' : 'Copy link' }}
            </button>
          </div>

          <button
            type="button"
            class="crud-primary-btn cert-download-btn"
            @click="downloadCert(cert)"
          >
            <Download :size="15" :stroke-width="2" />
            In / Tải về PDF
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.certs-page {
  max-width: 1100px;
  margin: 32px auto;
  padding: 0 24px 60px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Header */
.crud-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.crud-page-header h1 { margin: 4px 0 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.03em; }

.header-count {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 10px;
  background: var(--green-soft);
  color: var(--green-deep);
  font-size: 0.875rem;
  font-weight: 700;
  flex-shrink: 0;
}

/* Grid */
.certs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

/* Card */
.cert-card {
  padding: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 200ms cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 200ms;
}
.cert-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 32px rgba(31,49,43,0.1);
}

/* Thumb */
.cert-thumb {
  position: relative;
  aspect-ratio: 16 / 10;
  background: var(--green-soft);
  overflow: hidden;
  flex-shrink: 0;
}

.cert-thumb-img {
  width: 100%; height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 350ms ease;
}
.cert-card:hover .cert-thumb-img { transform: scale(1.04); }

.cert-thumb-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  color: var(--green);
  opacity: 0.5;
}

.cert-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(10,30,20,0.75) 0%, rgba(0,0,0,0) 50%);
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: flex-end;
  padding: 16px;
  gap: 4px;
}

.cert-overlay-course {
  font-size: 0.9rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cert-overlay-date {
  font-size: 0.72rem;
  color: rgba(255,255,255,0.7);
}

/* Body */
.cert-body {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  flex: 1;
}

.cert-course-name {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--text);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cert-cred-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.cert-cred-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); }
.cert-cred-code {
  display: block;
  background: rgba(var(--green-rgb), 0.06);
  border: 1px solid rgba(var(--green-rgb), 0.15);
  border-radius: 8px;
  padding: 7px 12px;
  font-family: 'Courier New', monospace;
  font-size: 0.8rem;
  color: var(--green-deep);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cert-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.cert-action-btn {
  text-align: center;
  justify-content: center;
  font-size: 0.8rem;
  padding: 8px 12px;
  height: auto;
}

.cert-copy-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 600;
  border: 1px solid var(--line);
  background: transparent;
  color: var(--text);
  cursor: pointer;
  transition: all 150ms;
}
.cert-copy-btn:hover { border-color: var(--green); color: var(--green); }
.cert-copy-btn.is-copied { border-color: var(--green); background: var(--green-soft); color: var(--green-deep); }

.cert-download-btn {
  width: 100%;
  justify-content: center;
  font-size: 0.875rem;
}

@media (max-width: 600px) {
  .certs-page { padding: 0 16px 40px; margin-top: 20px; }
  .certs-grid { grid-template-columns: 1fr; }
}
</style>
