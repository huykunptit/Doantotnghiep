<script setup lang="ts">
import { reactive, ref } from 'vue'

definePageMeta({ layout: false })

const form = reactive({ email: '' })
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

async function handleForgotPassword() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await useApi('/auth/forgot-password', {
      method: 'POST',
      body: form
    })
    successMessage.value = 'Nếu email tồn tại, hệ thống sẽ gửi liên kết đặt lại mật khẩu.'
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể gửi yêu cầu đặt lại mật khẩu.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] flex items-center justify-center p-4 font-['Be_Vietnam_Pro']">
    <div class="w-full max-w-md bg-white border border-[#e2e8f0] rounded-2xl p-8 shadow-md flex flex-col gap-6">
      <div class="flex flex-col items-center gap-3 text-center">
        <div class="w-12 h-12 rounded-2xl bg-[#1d9e75] flex items-center justify-center text-white text-2xl font-bold shadow-lg shadow-[rgba(29,158,117,0.2)]">
          Q
        </div>
        <div>
          <h2 class="text-xl font-bold text-[#1e293b]">Quên mật khẩu</h2>
          <p class="text-xs text-[#64748b] mt-1">Nhập email để nhận liên kết đặt lại mật khẩu.</p>
        </div>
      </div>

      <div v-if="errorMessage" class="p-3 bg-red-50 border border-red-200 text-red-600 rounded-xl text-xs font-medium flex items-center gap-2">
        <i class="pi pi-exclamation-circle text-sm shrink-0" />
        <span>{{ errorMessage }}</span>
      </div>

      <div v-if="successMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-medium flex items-center gap-2">
        <i class="pi pi-check-circle text-sm shrink-0" />
        <span>{{ successMessage }}</span>
      </div>

      <form class="flex flex-col gap-4" @submit.prevent="handleForgotPassword">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[#1e293b]">Email</span>
          <input v-model="form.email" type="email" required placeholder="you@example.com" class="h-10 px-3.5 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-sm focus:outline-none focus:border-[#1d9e75] transition-all" />
        </label>

        <button type="submit" :disabled="loading" class="h-11 mt-2 rounded-xl bg-[#1d9e75] hover:bg-[#158260] text-white text-sm font-bold shadow-lg shadow-[rgba(29,158,117,0.15)] transition-all cursor-pointer flex items-center justify-center gap-2 disabled:opacity-75">
          <i v-if="loading" class="pi pi-spin pi-spinner text-xs" />
          <span>{{ loading ? 'Đang gửi...' : 'Gửi liên kết đặt lại' }}</span>
        </button>

        <p class="text-center text-xs text-[#64748b]">
          Nhớ mật khẩu?
          <NuxtLink to="/login" class="font-bold text-[#1d9e75] hover:underline">Quay lại đăng nhập</NuxtLink>
        </p>
      </form>
    </div>
  </div>
</template>