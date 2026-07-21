<script setup lang="ts">
import { reactive, ref } from 'vue'

definePageMeta({
  layout: false
})

const form = reactive({
  email: '',
  password: ''
})

const loading = ref(false)
const errorMessage = ref('')

async function handleLogin() {
  loading.value = true
  errorMessage.value = ''
  try {
    const tokenCookie = useAuthTokenCookie()
    const userCookie = useAuthUserCookie()
    
    const data = await useApi<any, typeof form>('/auth/login', {
      method: 'POST',
      body: form
    })
    
    // Save to cookies
    tokenCookie.value = data.access_token
    userCookie.value = data.user
    
    await navigateTo('/admin', { replace: true })
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Đăng nhập không thành công. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4 font-['Be_Vietnam_Pro']">
    <div class="w-full max-w-md bg-white border border-[#e2e8f0] rounded-2xl p-8 shadow-md flex flex-col gap-6">
      
      <!-- Logo & Header -->
      <div class="flex flex-col items-center gap-3 text-center">
        <div class="w-12 h-12 rounded-2xl bg-[#1d9e75] flex items-center justify-center text-white text-2xl font-bold shadow-lg shadow-[rgba(29,158,117,0.2)]">
          Q
        </div>
        <div class="flex flex-col">
          <h2 class="text-xl font-bold text-[#1e293b]">Chào mừng quay trở lại</h2>
          <p class="text-xs text-[#64748b] mt-1">Đăng nhập để truy cập hệ thống quản trị QES LMS.</p>
        </div>
      </div>

      <!-- Alert Message -->
      <div v-if="errorMessage" class="p-3 bg-red-50 border border-red-200 text-red-600 rounded-xl text-xs font-medium flex items-center gap-2">
        <i class="pi pi-exclamation-circle text-sm shrink-0" />
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Form -->
      <form class="flex flex-col gap-4" @submit.prevent="handleLogin">
        <!-- Email -->
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[#1e293b]">Email</span>
          <input
            v-model="form.email"
            type="email"
            placeholder="admin@qes.vn"
            required
            class="h-10 px-3.5 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-sm focus:outline-none focus:border-[#1d9e75] transition-all"
          />
        </label>

        <!-- Password -->
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[#1e293b]">Mật khẩu</span>
          <input
            v-model="form.password"
            type="password"
            placeholder="••••••••"
            required
            class="h-10 px-3.5 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-sm focus:outline-none focus:border-[#1d9e75] transition-all"
          />
        </label>

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="loading"
          class="h-11 mt-2 rounded-xl bg-[#1d9e75] hover:bg-[#158260] text-white text-sm font-bold shadow-lg shadow-[rgba(29,158,117,0.15)] transition-all cursor-pointer flex items-center justify-center gap-2 disabled:opacity-75"
        >
          <i v-if="loading" class="pi pi-spin pi-spinner text-xs" />
          <span>{{ loading ? 'Đang xác thực...' : 'Đăng nhập' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>
