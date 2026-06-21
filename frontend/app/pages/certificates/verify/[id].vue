<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { CircleAlert, CircleCheckBig, Award } from 'lucide-vue-next'
import { useRoute } from 'vue-router'

definePageMeta({ layout: 'default' })

const route = useRoute()
const credentialId = route.params.id as string

const certificate = ref<any>(null)
const loading = ref(true)
const error = ref(false)

async function verifyCertificate() {
  loading.value = true
  try {
    certificate.value = await useApi<any>(`/certificates/verify/${credentialId}`)
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

function resolveFieldValue(key: string): string {
  const c = certificate.value
  switch (key) {
    case 'student_name': return c?.user?.name ?? ''
    case 'course_title': return c?.course?.title ?? ''
    case 'issued_date': return c?.issued_at ? new Date(c.issued_at).toLocaleDateString('vi-VN', { day: 'numeric', month: 'long', year: 'numeric' }) : ''
    case 'credential_id': return c?.credential_id ?? ''
    default: return ''
  }
}

onMounted(verifyCertificate)
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-12">
    <div v-if="loading" class="flex justify-center py-20">
      <div class="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <div v-else-if="error" class="text-center py-20">
      <CircleAlert :size="60" :stroke-width="1.75" class="text-rose-500" />
      <h2 class="mt-4 text-2xl font-bold text-slate-800">Không tìm thấy chứng chỉ</h2>
      <p class="mt-2 text-slate-600">Chứng chỉ với mã {{ credentialId }} không tồn tại hoặc không hợp lệ.</p>
    </div>

    <div v-else-if="certificate" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
      <!-- Header bar -->
      <div class="bg-primary px-8 py-6 text-white flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold">Xác minh chứng chỉ</h1>
          <p class="text-primary-100 mt-1">Chứng nhận tính xác thực của chứng chỉ.</p>
        </div>
        <CircleCheckBig :size="40" :stroke-width="1.75" class="opacity-80" />
      </div>

      <div class="p-8">
        <!-- Certificate canvas -->
        <div class="cert-canvas">
          <!-- Background image -->
          <img
            v-if="certificate.certificate_template?.background_image_url"
            :src="certificate.certificate_template.background_image_url"
            class="cert-bg"
            draggable="false"
          >
          <div v-else class="cert-bg cert-bg-blank">
            <Award :size="72" :stroke-width="1" class="text-primary opacity-20" />
          </div>

          <!-- fields_config overlay -->
          <template v-if="certificate.certificate_template?.fields_config?.length">
            <div
              v-for="field in certificate.certificate_template.fields_config"
              v-show="field.visible"
              :key="field.key"
              class="cert-field"
              :style="{
                left: field.x + '%',
                top: field.y + '%',
                fontSize: field.font_size + 'px',
                fontFamily: field.font_family,
                color: field.color,
                fontWeight: field.font_weight,
                textAlign: field.text_align,
                transform: field.text_align === 'center'
                  ? 'translateX(-50%)'
                  : field.text_align === 'right'
                    ? 'translateX(-100%)'
                    : 'none',
              }"
            >
              {{ resolveFieldValue(field.key) }}
            </div>
          </template>

          <!-- Fallback overlay (no fields_config) -->
          <div v-else class="cert-fallback">
            <div v-if="!certificate.certificate_template?.background_image_url" class="mb-8">
              <Award :size="60" :stroke-width="1.75" class="text-primary" />
            </div>
            <h2 class="font-serif text-3xl font-bold text-primary mb-2">CHỨNG NHẬN HOÀN THÀNH</h2>
            <p class="text-lg opacity-80">Cấp cho học viên</p>
            <p class="text-4xl font-bold mt-2 mb-6" style="font-family:'Great Vibes',cursive;">
              {{ certificate.user?.name }}
            </p>
            <p class="text-lg opacity-80">Đã hoàn thành xuất sắc khoá học</p>
            <h3 class="text-2xl font-bold mt-2 text-slate-800">{{ certificate.course?.title }}</h3>
            <div class="mt-auto w-full flex justify-between items-end border-t border-slate-300 pt-6 px-12">
              <div class="text-left">
                <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">Mã chứng nhận</p>
                <p class="font-mono">{{ certificate.credential_id }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">Ngày cấp</p>
                <p>{{ new Date(certificate.issued_at).toLocaleDateString('vi-VN') }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Info strip -->
        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div class="info-cell">
            <span class="info-label">Học viên</span>
            <strong>{{ certificate.user?.name }}</strong>
          </div>
          <div class="info-cell">
            <span class="info-label">Khoá học</span>
            <strong>{{ certificate.course?.title }}</strong>
          </div>
          <div class="info-cell">
            <span class="info-label">Ngày cấp</span>
            <strong>{{ new Date(certificate.issued_at).toLocaleDateString('vi-VN') }}</strong>
          </div>
          <div class="info-cell">
            <span class="info-label">Mã xác nhận</span>
            <code class="cred-code">{{ certificate.credential_id }}</code>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cert-canvas {
  position: relative;
  aspect-ratio: 1600 / 1131;
  width: 100%;
  max-width: 800px;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 24px rgba(0,0,0,0.10);
  background: #f8fafc;
}

.cert-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.cert-bg-blank {
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f0fdf4, #dcfce7);
}

.cert-field {
  position: absolute;
  white-space: nowrap;
  pointer-events: none;
  line-height: 1.25;
}

.cert-fallback {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
  color: #1e293b;
}

.info-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
}
.info-label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #94a3b8;
}
.info-cell strong {
  font-size: 0.875rem;
  color: #1e293b;
}
.cred-code {
  font-size: 0.72rem;
  font-family: 'Courier New', monospace;
  color: #16a34a;
  background: rgba(22,163,74,0.08);
  padding: 2px 6px;
  border-radius: 4px;
}
</style>
