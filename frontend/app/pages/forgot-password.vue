<template>
  <NuxtLayout name="auth">
    <div class="space-y-8">
      <!-- Header -->
      <div class="space-y-3">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Quên mật khẩu?</h1>
        <p class="text-[15px] text-gray-500 font-medium leading-relaxed">
          Đừng lo lắng! Nhập email liên kết với tài khoản của bạn, chúng tôi sẽ gửi hướng dẫn đặt lại mật khẩu.
        </p>
      </div>

      <div class="space-y-6">
        <!-- Success State -->
        <div v-if="sent" class="flex flex-col items-center justify-center py-6 animate-fade-in text-center space-y-4">
          <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76" /></svg>
          </div>
          <div class="space-y-1">
            <h3 class="text-lg font-bold text-gray-900">Email đã được gửi!</h3>
            <p class="text-[15px] text-gray-500 font-medium">Vui lòng kiểm tra hộp thư đến của bạn. Link đặt lại mật khẩu sẽ hết hạn sau thời gian ngắn.</p>
          </div>
        </div>

        <!-- Form -->
        <form v-else @submit.prevent="handleSubmit" class="space-y-5 animate-fade-in">
          <!-- Error alert -->
          <div v-if="error" class="flex items-start gap-3 p-4 rounded-xl bg-red-50 border border-red-100 text-sm font-medium text-red-800">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <p>{{ error }}</p>
          </div>

          <div class="space-y-1.5">
            <label for="email" class="block text-sm font-bold text-gray-700">Email của bạn</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
              </div>
              <input 
                id="email" 
                v-model="email" 
                type="email" 
                class="block w-full pl-11 pr-4 py-3.5 bg-gray-50/50 border border-gray-200 rounded-xl text-[15px] font-medium text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" 
                placeholder="Nhập email..." 
                required 
              />
            </div>
          </div>

          <button type="submit" :disabled="loading" class="group relative w-full flex justify-center py-3.5 px-4 rounded-xl text-[15px] font-bold text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary overflow-hidden transition-colors disabled:opacity-70 disabled:cursor-not-allowed mt-2">
            <span v-if="loading" class="absolute inset-0 flex items-center justify-center">
              <svg class="animate-spin w-5 h-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </span>
            <span :class="{'opacity-0': loading, 'opacity-100': !loading}" class="transition-opacity flex items-center gap-2">
              Gửi yêu cầu
              <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </span>
          </button>
        </form>

        <div class="pt-4 flex justify-center">
          <NuxtLink to="/login" class="flex items-center gap-2 text-[14px] font-bold text-gray-500 hover:text-primary transition-colors group">
            <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Quay lại đăng nhập
          </NuxtLink>
        </div>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const email = ref('')
const loading = ref(false)
const error = ref('')
const sent = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await useApi('/auth/forgot-password', { method: 'POST', body: { email: email.value } })
    sent.value = true
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể gửi email. Vui lòng kiểm tra lại địa chỉ email hoặc thử lại sau.'
  } finally {
    loading.value = false
  }
}
</script>

<style>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.3s ease-out forwards;
}
</style>
