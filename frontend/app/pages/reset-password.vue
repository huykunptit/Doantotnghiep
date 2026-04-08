<template>
  <NuxtLayout name="auth">
    <div class="space-y-8">
      <!-- Header -->
      <div class="space-y-3">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Tạo mật khẩu mới</h1>
        <p class="text-[15px] text-gray-500 font-medium">Bảo mật tài khoản của bạn bằng một mật khẩu mạnh mới.</p>
      </div>

      <div class="space-y-6">
        <!-- Success State -->
        <div v-if="success" class="flex flex-col items-center justify-center py-6 animate-fade-in text-center space-y-5">
          <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
          <div class="space-y-2">
            <h3 class="text-lg font-bold text-gray-900">Hoàn tất thay đổi!</h3>
            <p class="text-[15px] text-gray-500 font-medium">Tuyệt vời, bạn đã cập nhật mật khẩu thành công.</p>
          </div>
          <NuxtLink to="/login" class="inline-flex justify-center py-3 px-8 rounded-xl text-[15px] font-bold text-white bg-primary hover:bg-primary-dark transition-colors">
            Đăng nhập ngay
          </NuxtLink>
        </div>

        <!-- Form -->
        <form v-else @submit.prevent="handleReset" class="space-y-5 animate-fade-in">
          <!-- Error alert -->
          <div v-if="error" class="flex items-start gap-3 p-4 rounded-xl bg-red-50 border border-red-100 text-sm font-medium text-red-800">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <p>{{ error }}</p>
          </div>

          <div class="space-y-1.5 opacity-60">
            <label for="email" class="block text-sm font-bold text-gray-700">Email</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
              </div>
              <input 
                id="email" 
                v-model="form.email" 
                type="email" 
                disabled
                class="block w-full pl-11 pr-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-[15px] font-medium text-gray-500 cursor-not-allowed" 
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <label for="password" class="block text-sm font-bold text-gray-700">Mật khẩu mới</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
              </div>
              <input 
                id="password" 
                v-model="form.password" 
                :type="showPassword ? 'text' : 'password'" 
                class="block w-full pl-11 pr-12 py-3.5 bg-gray-50/50 border border-gray-200 rounded-xl text-[15px] font-medium text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" 
                placeholder="Tối thiểu 6 ký tự" 
                required 
                minlength="6"
              />
              <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-primary transition-colors">
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
              </button>
            </div>
          </div>

          <div class="space-y-1.5">
            <label for="password_confirmation" class="block text-sm font-bold text-gray-700">Xác nhận mật khẩu</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
              <input 
                id="password_confirmation" 
                v-model="form.password_confirmation" 
                :type="showPassword ? 'text' : 'password'" 
                class="block w-full pl-11 pr-4 py-3.5 bg-gray-50/50 border border-gray-200 rounded-xl text-[15px] font-medium text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" 
                placeholder="Nhập lại mật khẩu" 
                required 
              />
            </div>
          </div>

          <button type="submit" :disabled="loading" class="group relative w-full flex justify-center py-3.5 px-4 rounded-xl text-[15px] font-bold text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary overflow-hidden transition-colors disabled:opacity-70 disabled:cursor-not-allowed mt-4">
            <span v-if="loading" class="absolute inset-0 flex items-center justify-center">
              <svg class="animate-spin w-5 h-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </span>
            <span :class="{'opacity-0': loading, 'opacity-100': !loading}" class="transition-opacity flex items-center gap-2">
              Lưu mật khẩu mới
              <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </span>
          </button>
        </form>

      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const route = useRoute()
const form = reactive({
  email: (route.query.email as string) || '',
  password: '',
  password_confirmation: '',
})
const loading = ref(false)
const error = ref('')
const success = ref(false)
const showPassword = ref(false)

async function handleReset() {
  error.value = ''
  if (form.password !== form.password_confirmation) {
    error.value = 'Mật khẩu xác nhận không khớp.'
    return
  }
  loading.value = true
  try {
    await useApi('/auth/reset-password', {
      method: 'POST',
      body: {
        token: route.query.token,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
      },
    })
    success.value = true
  } catch (e: any) {
    error.value = e?.data?.message || 'Cập nhật thất bại. Xin vui lòng thử lại sau.'
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
