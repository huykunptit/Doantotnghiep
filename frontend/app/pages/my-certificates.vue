<script setup lang="ts">
import { ref, onMounted } from 'vue'

definePageMeta({ layout: 'default', middleware: 'auth' })

const certificates = ref<any[]>([])
const loading = ref(true)

async function fetchMyCertificates() {
  loading.value = true
  try {
    certificates.value = await useApi<any[]>('/my-certificates', {
      headers: { Authorization: `Bearer ${useAuthTokenCookie().value}` }
    })
  } catch (e) {
  } finally {
    loading.value = false
  }
}

onMounted(fetchMyCertificates)
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-8 px-4 py-8">
    <AppPageHeader title="Chứng chỉ của tôi" description="Danh sách các chứng chỉ bạn đã đạt được." />

    <div v-if="loading" class="flex justify-center p-8"><div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div></div>
    
    <div v-else-if="certificates.length === 0" class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-500">
      <span class="material-symbols-outlined mb-2 block text-4xl">workspace_premium</span>
      <p>Bạn chưa nhận được chứng chỉ nào. Hoàn thành khoá học để nhận chứng chỉ nhé!</p>
    </div>

    <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <div v-for="cert in certificates" :key="cert.id" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
        <div class="relative aspect-[4/3] bg-slate-100">
          <img v-if="cert.certificate_template?.background_image_url" :src="cert.certificate_template.background_image_url" class="h-full w-full object-cover">
          <div class="absolute inset-0 flex flex-col items-center justify-center bg-black/40 p-4 text-center text-white backdrop-blur-sm">
            <h3 class="font-serif text-xl font-bold">{{ cert.course?.title }}</h3>
            <p class="mt-2 text-sm opacity-90">Cấp ngày: {{ new Date(cert.issued_at).toLocaleDateString('vi-VN') }}</p>
          </div>
        </div>
        <div class="p-5">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Mã chứng nhận</p>
          <div class="mt-1 flex items-center justify-between">
            <code class="rounded bg-slate-100 px-2 py-1 text-sm text-slate-800">{{ cert.credential_id }}</code>
            <NuxtLink :to="`/certificates/verify/${cert.credential_id}`" target="_blank" class="text-sm font-semibold text-primary hover:underline">
              Xem chi tiết
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
