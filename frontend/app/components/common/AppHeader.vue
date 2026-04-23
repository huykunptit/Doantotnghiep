<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { publicNavigation } from '~/constants/navigation'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const searchQuery = ref('')
const showMenu = ref(false)
const showMobileMenu = ref(false)
const menuRef = ref<HTMLElement | null>(null)

const isAdmin = computed(() => auth.user?.roles?.includes('admin'))
const isInstructor = computed(() => auth.user?.roles?.includes('instructor') || isAdmin.value)

function handleSearch() {
  if (!searchQuery.value.trim()) return
  router.push({ path: '/courses', query: { search: searchQuery.value.trim() } })
  searchQuery.value = ''
  showMobileMenu.value = false
}

async function handleLogout() {
  showMenu.value = false
  await auth.logout()
  router.push('/')
}

function handleClickOutside(e: Event) {
  if (menuRef.value && !menuRef.value.contains(e.target as Node)) showMenu.value = false
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<template>
  <nav class="fixed top-0 w-full z-50 bg-surface-lowest/95 backdrop-blur-md border-b border-surface-dim shadow-sm transition-all duration-300">
    <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
      <!-- Logo Branding -->
      <NuxtLink to="/" class="flex items-center gap-3 text-on-surface hover:opacity-80 transition-opacity">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl cta-gradient shadow-lg shadow-primary/20 text-white font-bold">E</div>
        <div>
          <span class="block font-headline text-lg font-bold tracking-tight leading-none text-on-surface">EduPress</span>
          <span class="block text-[10px] uppercase tracking-widest text-outline mt-0.5">Digital EduPress</span>
        </div>
      </NuxtLink>

      <!-- Central Nav Links & Search -->
      <div class="hidden items-center gap-8 md:flex">
        <div class="flex gap-6 items-center flex-1 justify-center">
          <NuxtLink 
            v-for="item in publicNavigation" 
            :key="item.to" 
            :to="item.to" 
            class="text-sm font-semibold text-on-surface-variant hover:text-primary transition-colors duration-200 border-b-2 border-transparent hover:border-primary pb-0.5"
            active-class="text-primary border-primary"
          >
            {{ item.label }}
          </NuxtLink>
          <ClientOnly>
            <NuxtLink v-if="auth.isLoggedIn" to="/my-courses" class="text-sm font-semibold text-on-surface-variant hover:text-primary transition-colors duration-200 border-b-2 border-transparent hover:border-primary pb-0.5" active-class="text-primary border-primary">Khóa học của tôi</NuxtLink>
          </ClientOnly>
        </div>
      </div>

      <!-- User Interface & Auth -->
      <div class="flex items-center gap-4">
        <div class="hidden lg:block w-48 relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-outline material-symbols-outlined text-lg">search</span>
          <input 
            v-model="searchQuery" 
            class="w-full bg-surface-low border-none rounded-full py-2 pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary focus:bg-surface-lowest transition-all text-on-surface placeholder:text-outline/70"
            placeholder="Tìm kiếm..." 
            @keyup.enter="handleSearch"
          />
        </div>

        <ClientOnly>
          <template v-if="!auth.isLoggedIn">
            <NuxtLink to="/login" class="px-6 py-2 rounded-lg text-primary hover:text-primary-dark font-bold transition-all text-sm">Đăng nhập</NuxtLink>
            <NuxtLink to="/register" class="hidden sm:block px-6 py-2 rounded-lg cta-gradient text-white shadow-md hover:shadow-lg transition-all text-sm font-bold">Đăng ký</NuxtLink>
          </template>

          <template v-else>
          <NotificationBell />
          <div ref="menuRef" class="relative">
            <button class="flex items-center gap-2 rounded-full bg-surface-low p-1.5 pr-3 transition hover:bg-surface-high" @click="showMenu = !showMenu">
              <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-sm font-bold text-primary">
                <img v-if="auth.user?.avatar" :src="auth.user.avatar" class="h-full w-full object-cover">
                <span v-else>{{ auth.user?.name?.charAt(0) }}</span>
              </div>
              <span class="hidden text-sm font-semibold text-on-surface sm:block">{{ auth.user?.name }}</span>
              <span class="material-symbols-outlined text-sm text-outline">expand_more</span>
            </button>
            
            <!-- Dropdown -->
            <div v-if="showMenu" class="absolute right-0 mt-3 w-64 rounded-2xl bg-surface-lowest p-2 shadow-ambient border border-surface-dim fade-in-up">
              <div class="px-4 py-3 bg-surface-low rounded-xl mb-2">
                <p class="text-sm font-bold text-on-surface">{{ auth.user?.name }}</p>
                <p class="text-xs text-outline">{{ auth.user?.email }}</p>
              </div>
              
              <div class="space-y-1 text-sm font-medium">
                <NuxtLink to="/profile" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-on-surface-variant hover:bg-surface-low hover:text-on-surface" @click="showMenu = false">
                  <span class="material-symbols-outlined text-[20px]">person</span> Hồ sơ
                </NuxtLink>
                <NuxtLink to="/orders" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-on-surface-variant hover:bg-surface-low hover:text-on-surface" @click="showMenu = false">
                  <span class="material-symbols-outlined text-[20px]">receipt_long</span> Lịch sử đơn hàng
                </NuxtLink>
                
                <div v-if="isInstructor || isAdmin" class="my-2 border-t border-surface-dim"></div>
                
                <NuxtLink v-if="isInstructor" to="/instructor" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-primary hover:bg-primary/5" @click="showMenu = false">
                  <span class="material-symbols-outlined text-[20px]">workspace_premium</span> Chuyển sang Giảng dạy
                </NuxtLink>
                <NuxtLink v-if="isAdmin" to="/admin" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-secondary hover:bg-secondary/5" @click="showMenu = false">
                  <span class="material-symbols-outlined text-[20px]">admin_panel_settings</span> Bảng quản trị
                </NuxtLink>
                
                <div class="my-2 border-t border-surface-dim"></div>
                
                <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-error hover:bg-error-container/70 transition-colors" @click="handleLogout">
                  <span class="material-symbols-outlined text-[20px]">logout</span> Đăng xuất
                </button>
              </div>
            </div>
          </div>
          </template>
        </ClientOnly>

        <!-- Mobile Menu Toggle -->
        <button class="flex items-center justify-center rounded-lg p-2 text-outline hover:bg-surface-low md:hidden" @click="showMobileMenu = !showMobileMenu">
          <span class="material-symbols-outlined">{{ showMobileMenu ? 'close' : 'menu' }}</span>
        </button>
      </div>
    </div>

    <!-- Mobile Dropdown Navigation -->
    <div v-if="showMobileMenu" class="bg-surface-lowest shadow-lg border-t border-surface-dim md:hidden fade-in-up absolute w-full left-0 top-16">
      <div class="p-4 space-y-4">
        <div class="relative flex items-center">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-outline material-symbols-outlined text-lg">search</span>
          <input 
            v-model="searchQuery" 
            class="w-full bg-surface-low border-none rounded-xl py-3 pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary focus:bg-surface-lowest transition-all text-on-surface"
            placeholder="Tìm kiếm khóa học..." 
            @keyup.enter="handleSearch"
          />
        </div>
        
        <nav class="space-y-1 font-semibold">
          <NuxtLink 
            v-for="item in publicNavigation" 
            :key="item.to" 
            :to="item.to" 
            class="block rounded-xl px-4 py-3 text-sm text-on-surface-variant hover:bg-surface-low hover:text-primary transition-colors" 
            @click="showMobileMenu = false"
          >
            {{ item.label }}
          </NuxtLink>
          <ClientOnly>
            <NuxtLink v-if="auth.isLoggedIn" to="/my-courses" class="block rounded-xl px-4 py-3 text-sm text-on-surface-variant hover:bg-surface-low hover:text-primary transition-colors" @click="showMobileMenu = false">Khóa học của tôi</NuxtLink>
            <NuxtLink 
              v-if="!auth.isLoggedIn" 
              to="/login" 
              class="block rounded-xl px-4 py-3 text-sm text-primary bg-primary/10 hover:bg-primary/20 transition-colors mt-2" 
              @click="showMobileMenu = false"
            >
              Đăng nhập
            </NuxtLink>
          </ClientOnly>
        </nav>
      </div>
    </div>
  </nav>
</template>
