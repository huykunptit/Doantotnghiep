<script setup lang="ts">
import { ref, onMounted } from 'vue'
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
  } catch (e) {
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(verifyCertificate)
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-12">
    <div v-if="loading" class="flex justify-center py-20"><div class="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent"></div></div>
    
    <div v-else-if="error" class="text-center py-20">
      <span class="material-symbols-outlined text-6xl text-rose-500">error</span>
      <h2 class="mt-4 text-2xl font-bold text-slate-800">Không tìm thấy chứng chỉ</h2>
      <p class="mt-2 text-slate-600">Chứng chỉ với mã {{ credentialId }} không tồn tại hoặc không hợp lệ.</p>
    </div>

    <div v-else-if="certificate" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
      <div class="bg-primary px-8 py-6 text-white flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold">Xác minh chứng chỉ</h1>
          <p class="text-primary-100 mt-1">Chứng nhận tính xác thực của chứng chỉ.</p>
        </div>
        <span class="material-symbols-outlined text-4xl opacity-80">verified</span>
      </div>

      <div class="p-8">
        <div class="relative aspect-[1.414/1] w-full max-w-2xl mx-auto overflow-hidden rounded-xl border border-slate-200 shadow-sm bg-slate-50">
          <!-- Background -->
          <img v-if="certificate.certificate_template?.background_image_url" :src="certificate.certificate_template.background_image_url" class="absolute inset-0 w-full h-full object-cover">
          
          <!-- Content Overlay (Simple) -->
          <div class="absolute inset-0 flex flex-col items-center justify-center p-12 text-center" style="color: #111;">
            <div v-if="!certificate.certificate_template?.background_image_url" class="mb-8">
              <span class="material-symbols-outlined text-6xl text-primary">workspace_premium</span>
            </div>
            <h2 class="font-serif text-3xl font-bold text-primary mb-2">CHỨNG NHẬN HOÀN THÀNH</h2>
            <p class="text-lg opacity-80">Cấp cho học viên</p>
            <p class="text-4xl font-bold mt-2 mb-6" style="font-family: 'Great Vibes', cursive;">{{ certificate.user?.name }}</p>
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
      </div>
    </div>
  </div>
</template>
