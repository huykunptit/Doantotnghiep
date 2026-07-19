<script setup lang="ts">
import { reactive, ref } from 'vue'

definePageMeta({ layout: false })

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'student'
})

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

async function handleRegister() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  if (form.password !== form.password_confirmation) {
    errorMessage.value = 'Mật khẩu xác nhận không khớp.'
    loading.value = false
    return
  }

  try {
    await useApi('/auth/register', {
      method: 'POST',
      body: form
    })

    successMessage.value = 'Đăng ký thành công. Vui lòng đăng nhập để tiếp tục.'
    setTimeout(() => navigateTo('/login'), 900)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể đăng ký tài khoản. Vui lòng thử lại.'
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
          <h2 class="text-xl font-bold text-[#1e293b]">Tạo tài khoản QES LMS</h2>
          <p class="text-xs text-[#64748b] mt-1">Đăng ký tài khoản học tập hoặc giảng dạy trên hệ thống.</p>
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

      <form class="flex flex-col gap-4" @submit.prevent="handleRegister">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[#1e293b]">Họ và tên</span>
          <input v-model="form.name" type="text" required placeholder="Nguyễn Văn A" class="h-10 px-3.5 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-sm focus:outline-none focus:border-[#1d9e75] transition-all" />
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[#1e293b]">Email</span>
          <input v-model="form.email" type="email" required placeholder="you@example.com" class="h-10 px-3.5 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-sm focus:outline-none focus:border-[#1d9e75] transition-all" />
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[#1e293b]">Vai trò</span>
          <select v-model="form.role" class="h-10 px-3.5 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-sm focus:outline-none focus:border-[#1d9e75] transition-all">
            <option value="student">Sinh viên</option>
            <option value="instructor">Giảng viên</option>
          </select>
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[#1e293b]">Mật khẩu</span>
          <input v-model="form.password" type="password" required placeholder="••••••••" class="h-10 px-3.5 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-sm focus:outline-none focus:border-[#1d9e75] transition-all" />
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[#1e293b]">Xác nhận mật khẩu</span>
          <input v-model="form.password_confirmation" type="password" required placeholder="••••••••" class="h-10 px-3.5 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-sm focus:outline-none focus:border-[#1d9e75] transition-all" />
        </label>

        <button type="submit" :disabled="loading" class="h-11 mt-2 rounded-xl bg-[#1d9e75] hover:bg-[#158260] text-white text-sm font-bold shadow-lg shadow-[rgba(29,158,117,0.15)] transition-all cursor-pointer flex items-center justify-center gap-2 disabled:opacity-75">
          <i v-if="loading" class="pi pi-spin pi-spinner text-xs" />
          <span>{{ loading ? 'Đang tạo tài khoản...' : 'Đăng ký' }}</span>
        </button>

        <p class="text-center text-xs text-[#64748b]">
          Đã có tài khoản?
          <NuxtLink to="/login" class="font-bold text-[#1d9e75] hover:underline">Đăng nhập</NuxtLink>
        </p>
      </form>
    </div>
  </div>
</template>