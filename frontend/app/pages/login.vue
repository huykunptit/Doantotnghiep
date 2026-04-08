<template>
  <NuxtLayout name="auth">
    <div class="space-y-8">
      <!-- Form Header -->
      <div class="space-y-3">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Đăng nhập tài khoản</h1>
        <p class="text-[15px] text-gray-500 font-medium">Chào mừng bạn quay lại! Hãy nhập thông tin để tiếp tục học.</p>
      </div>

      <!-- Form Content -->
      <div class="space-y-6">
        <!-- Google OAuth -->
        <a :href="googleUrl" class="group relative flex items-center justify-center gap-3 w-full px-5 py-3 border-2 border-gray-100 bg-white rounded-xl text-sm font-bold text-gray-700 hover:border-gray-200 hover:bg-gray-50/50 hover:shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200 overflow-hidden">
          <div class="absolute inset-0 w-8 bg-gradient-to-r from-transparent via-gray-100/30 to-transparent -translate-x-full group-hover:animate-[shimmer_1.5s_infinite]"></div>
          <svg class="w-[22px] h-[22px]" viewBox="0 0 24 24">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
          Đăng nhập với Google
        </a>

        <!-- Divider -->
        <div class="relative flex items-center">
          <div class="flex-grow border-t border-gray-200"></div>
          <span class="flex-shrink-0 mx-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Hoặc tiếp tục với email</span>
          <div class="flex-grow border-t border-gray-200"></div>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-5">
          <!-- Error alert -->
          <div v-if="error" class="flex items-center gap-3 p-4 rounded-xl bg-red-50 border border-red-100 text-sm font-medium text-red-800 animate-fade-in">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <p>{{ error }}</p>
          </div>

          <div class="space-y-1.5">
            <label for="email" class="block text-sm font-bold text-gray-700">Email</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
              </div>
              <input 
                id="email" 
                v-model="form.email" 
                type="email" 
                class="block w-full pl-11 pr-4 py-3.5 bg-gray-50/50 border border-gray-200 rounded-xl text-[15px] font-medium text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" 
                placeholder="email@example.com" 
                required 
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <label for="password" class="block text-sm font-bold text-gray-700">Mật khẩu</label>
              <NuxtLink to="/forgot-password" class="text-sm font-bold text-primary hover:text-primary-dark transition-colors">Quên mật khẩu?</NuxtLink>
            </div>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
              </div>
              <input 
                id="password" 
                v-model="form.password" 
                :type="showPassword ? 'text' : 'password'" 
                class="block w-full pl-11 pr-12 py-3.5 bg-gray-50/50 border border-gray-200 rounded-xl text-[15px] font-medium text-gray-900 placeholder-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" 
                placeholder="Nhập mật khẩu" 
                required 
              />
              <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-primary transition-colors">
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
              </button>
            </div>
          </div>

          <button type="submit" :disabled="loading" class="group relative w-full flex justify-center py-3.5 px-4 rounded-xl text-[15px] font-bold text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary overflow-hidden transition-colors disabled:opacity-70 disabled:cursor-not-allowed mt-2">
            <span v-if="loading" class="absolute inset-0 flex items-center justify-center">
              <svg class="animate-spin w-5 h-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </span>
            <span :class="{'opacity-0': loading, 'opacity-100': !loading}" class="transition-opacity flex items-center gap-2">
              Đăng nhập tài khoản
              <svg class="w-5 h-5 group-hover:translate-x-1.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </span>
          </button>
        </form>

        <p class="text-center text-[15px] font-medium text-gray-500 pt-2">
          Chưa có tài khoản? 
          <NuxtLink to="/register" class="font-bold text-primary hover:text-primary-dark underline-offset-4 hover:underline transition-all">Đăng ký ngay</NuxtLink>
        </p>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()
const router = useRouter()
const config = useRuntimeConfig()

const form = reactive({ email: '', password: '' })
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)

const googleUrl = computed(() => `${config.public.apiBase}/auth/google/redirect`)

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(form)
    router.push('/')
  } catch (e: any) {
    error.value = e?.data?.message || 'Đăng nhập thất bại. Email hoặc mật khẩu chưa đúng.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (auth.isLoggedIn) router.replace('/')
})
</script>

<style>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.3s ease-out forwards;
}
@keyframes shimmer {
  100% { transform: translateX(100%); }
}
</style>
