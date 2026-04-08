<template>
  <div class="min-h-[60vh] flex items-center justify-center px-4">
    <div class="card max-w-md w-full p-8 text-center space-y-4">
      <div v-if="isSuccess" class="w-20 h-20 mx-auto bg-primary-light rounded-full flex items-center justify-center">
        <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
      </div>
      <div v-else class="w-20 h-20 mx-auto bg-red-50 rounded-full flex items-center justify-center">
        <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
      </div>

      <h1 class="text-2xl font-bold text-gray-900">
        {{ isSuccess ? 'Thanh toán thành công!' : 'Thanh toán thất bại' }}
      </h1>
      <p class="text-gray-500">
        {{ message }}
      </p>

      <div class="flex gap-3 justify-center pt-2">
        <NuxtLink v-if="isSuccess" to="/my-courses" class="btn-primary">Khóa học của tôi</NuxtLink>
        <NuxtLink to="/courses" class="btn-secondary">Quay lại danh sách khóa học</NuxtLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()

const status = computed(() => String(route.query.status || '').toLowerCase())
const isSuccess = computed(() => ['success', 'paid', 'ok', '00'].includes(status.value))
const message = computed(() => {
  const raw = String(route.query.message || '').trim()
  if (raw) return raw
  return isSuccess.value
    ? 'Giao dịch hoàn tất. Bạn có thể bắt đầu học ngay.'
    : 'Giao dịch không thành công. Vui lòng thử lại.'
})
</script>

