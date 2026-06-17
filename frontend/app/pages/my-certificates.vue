<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Award } from 'lucide-vue-next'

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
    : '<div style="width:900px;height:636px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:24px;color:#999;">Không có phôi chứng chỉ</div>'

  printWindow.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Chứng chỉ - ${cert.course?.title}</title>
  <style>
    body { margin: 0; font-family: Georgia, serif; background: #fff; }
    .cert-wrap { max-width: 900px; margin: 0 auto; padding: 40px 20px; text-align: center; }
    .cert-title { font-size: 28px; font-weight: bold; margin: 24px 0 8px; }
    .cert-sub { font-size: 16px; color: #555; margin-bottom: 20px; }
    .cert-meta { font-size: 13px; color: #888; margin-top: 20px; }
    .verify-link { font-size: 12px; color: #aaa; word-break: break-all; }
    @media print { body { -webkit-print-color-adjust: exact; } }
  </style></head><body>
  <div class="cert-wrap">
    ${imgHtml}
    <div class="cert-title">${cert.course?.title || 'Chứng chỉ Hoàn thành'}</div>
    <div class="cert-sub">Cấp cho: <strong>${cert.user?.name || 'Học viên'}</strong></div>
    <div class="cert-meta">Ngày cấp: ${new Date(cert.issued_at).toLocaleDateString('vi-VN')}</div>
    <div class="cert-meta">Mã chứng nhận: <strong>${cert.credential_id}</strong></div>
    <div class="verify-link">Xác minh tại: ${verifyUrl}</div>
  </div>
  <script>window.onload=function(){window.print();window.close();}<\/script>
  </body></html>`)
  printWindow.document.close()
}

onMounted(fetchMyCertificates)
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-8 px-4 py-8">
    <AppPageHeader
      title="Chứng chỉ của tôi"
      description="Danh sách các chứng chỉ bạn đã đạt được. Chia sẻ hoặc in để chứng minh kỹ năng."
    />

    <div v-if="loading" class="flex justify-center p-8">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <div
      v-else-if="certificates.length === 0"
      class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-500"
    >
      <Award :size="40" :stroke-width="1.75" class="mb-2 mx-auto" />
      <p>Bạn chưa nhận được chứng chỉ nào. Hoàn thành khoá học để nhận chứng chỉ nhé!</p>
    </div>

    <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="cert in certificates"
        :key="cert.id"
        class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
      >
        <!-- Thumbnail -->
        <div class="relative aspect-[16/11] bg-slate-100">
          <img
            v-if="cert.certificate_template?.background_image_url"
            :src="cert.certificate_template.background_image_url"
            class="h-full w-full object-cover"
          >
          <div
            class="absolute inset-0 flex flex-col items-center justify-center bg-black/40 p-4 text-center text-white backdrop-blur-sm"
          >
            <Award :size="36" :stroke-width="1.75" class="mb-2 opacity-70" />
            <h3 class="text-base font-bold leading-tight">{{ cert.course?.title }}</h3>
            <p class="mt-2 text-xs opacity-80">
              Cấp ngày: {{ new Date(cert.issued_at).toLocaleDateString('vi-VN') }}
            </p>
          </div>
        </div>

        <!-- Info -->
        <div class="p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
            Mã chứng nhận
          </p>
          <code class="mt-1 block rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700">
            {{ cert.credential_id }}
          </code>

          <!-- Actions -->
          <div class="mt-4 flex flex-wrap gap-2">
            <NuxtLink
              :to="`/certificates/verify/${cert.credential_id}`"
              target="_blank"
              class="flex-1 rounded-xl border border-slate-200 py-2 text-center text-sm font-semibold text-on-surface transition hover:border-primary hover:text-primary"
            >
              Xem chi tiết
            </NuxtLink>
            <button
              type="button"
              class="flex-1 rounded-xl border py-2 text-center text-sm font-semibold transition"
              :class="copiedId === cert.credential_id
                ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                : 'border-slate-200 text-on-surface hover:border-primary hover:text-primary'"
              @click="copyLink(cert.credential_id)"
            >
              {{ copiedId === cert.credential_id ? '✓ Đã sao chép' : 'Copy link' }}
            </button>
            <button
              type="button"
              class="w-full rounded-xl bg-primary py-2 text-center text-sm font-semibold text-white transition hover:opacity-90"
              @click="downloadCert(cert)"
            >
              In / Tải về
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
