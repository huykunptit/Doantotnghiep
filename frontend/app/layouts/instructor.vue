<template>
  <div class="min-h-screen bg-surface text-on-surface antialiased">
    <!-- Desktop Sidebar -->
    <aside class="fixed inset-y-0 left-0 hidden w-64 flex-col bg-surface-lowest border-r border-surface-dim px-4 py-6 lg:flex z-40">
      <div class="mb-6 px-4">
        <h1 class="text-lg font-bold text-on-surface font-headline tracking-tight">EduPress</h1>
        <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-widest mt-1">Instructor EduPress</p>
      </div>

      <nav class="flex-1 flex flex-col gap-1 overflow-y-auto no-scrollbar pb-6">
        <div v-for="group in navGroups" :key="group.label" class="mb-4">
          <p class="px-4 pb-2 text-[10px] font-bold uppercase tracking-widest text-outline">{{ group.label }}</p>
          <div class="space-y-1">
            <NuxtLink
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300"
              :class="isActive(item.to) ? 'bg-surface-high text-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-low hover:text-on-surface hover:translate-x-1'"
            >
              <span class="material-symbols-outlined text-[20px] shrink-0" :style="isActive(item.to) ? 'font-variation-settings: \'FILL\' 1;' : ''">{{ item.icon }}</span>
              <span>{{ item.label }}</span>
            </NuxtLink>
          </div>
        </div>
      </nav>

      <div class="mt-auto pt-6 border-t border-surface-dim/30 flex flex-col gap-1">
        <NuxtLink to="/" class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:bg-surface-low hover:translate-x-1 text-sm font-medium transition-all rounded-xl">
          <span class="material-symbols-outlined text-[20px]">public</span>
          <span>Về trang chủ</span>
        </NuxtLink>
        <button @click="logout" class="flex items-center gap-3 px-4 py-3 text-error hover:bg-error-container/20 hover:translate-x-1 text-sm font-medium transition-all rounded-xl text-left w-full">
          <span class="material-symbols-outlined text-[20px]">logout</span>
          <span>Đăng xuất</span>
        </button>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="lg:ml-64 flex flex-col min-h-screen">
      <!-- Top Navigation Anchor (Desktop Integration) -->
      <header class="sticky top-0 z-20 bg-surface/80 shadow-sm backdrop-blur-xl border-b border-surface-dim">
        <div class="flex h-20 items-center justify-between px-6 lg:px-10">
          <div class="flex items-center gap-4">
            <button class="rounded-xl p-2 bg-surface-lowest border border-surface-dim lg:hidden" @click="showMobile = true">
              <span class="material-symbols-outlined">menu</span>
            </button>
            <div>
              <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Quản trị viên / Instructor</p>
              <p class="text-xl font-bold font-headline text-on-surface tracking-tight">{{ pageTitle }}</p>
            </div>
          </div>
          
          <div class="flex items-center gap-6">
            <NuxtLink v-if="isAdmin" to="/admin" class="hidden sm:block text-sm font-bold text-primary hover:text-primary/80 transition-colors uppercase tracking-widest">
              Admin Panel
            </NuxtLink>
            <div class="hidden sm:flex relative group cursor-pointer items-center justify-center">
              <span class="material-symbols-outlined text-outline hover:text-primary transition-colors p-2 bg-surface-lowest border border-surface-dim rounded-full">notifications</span>
              <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-error rounded-full border-2 border-surface"></span>
            </div>
            <div class="flex items-center gap-3 bg-surface-lowest border border-surface-dim pl-2 pr-4 py-1.5 rounded-full shadow-sm cursor-pointer hover:shadow-md transition-all">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 font-bold text-primary text-sm shadow-inner">{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</div>
              <span class="text-sm font-bold text-on-surface">{{ auth.user?.name }}</span>
            </div>
          </div>
        </div>
      </header>

      <main class="flex-1 p-4 lg:p-8">
        <div class="min-h-full rounded-[2rem] bg-surface-lowest p-5 shadow-ambient sm:p-6 lg:p-8">
          <slot />
        </div>
      </main>
    </div>

    <!-- Mobile Sidebar Teleport -->
    <Teleport to="body">
      <div v-if="showMobile" class="fixed inset-0 z-50 lg:hidden">
        <div class="absolute inset-0 bg-on-surface/40 backdrop-blur-sm" @click="showMobile = false"></div>
        <aside class="absolute inset-y-0 left-0 w-72 bg-surface-lowest p-6 shadow-ambient flex flex-col">
          <div class="mb-8 flex items-center justify-between">
            <div>
              <p class="font-headline font-bold text-lg text-on-surface">Instructor Menu</p>
            </div>
            <button class="text-outline p-2 bg-surface-low rounded-full flex items-center justify-center" @click="showMobile = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          
          <div class="flex-1 overflow-y-auto no-scrollbar space-y-6">
            <div v-for="group in navGroups" :key="group.label">
              <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-widest text-outline">{{ group.label }}</p>
              <div class="space-y-1">
                <NuxtLink
                  v-for="item in group.items"
                  :key="item.to"
                  :to="item.to"
                  class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-colors"
                  :class="isActive(item.to) ? 'bg-surface-high text-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-low'"
                  @click="showMobile = false"
                >
                  <span class="material-symbols-outlined text-[20px] shrink-0" :style="isActive(item.to) ? 'font-variation-settings: \'FILL\' 1;' : ''">{{ item.icon }}</span>
                  <span>{{ item.label }}</span>
                </NuxtLink>
              </div>
            </div>
          </div>
          
          <div class="mt-auto pt-6 border-t border-surface-dim/30">
            <button class="w-full flex items-center gap-3 px-4 py-3 text-error hover:bg-error-container/20 rounded-xl font-medium transition-all" @click="logout">
              <span class="material-symbols-outlined">logout</span>
              Đăng xuất
            </button>
          </div>
        </aside>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const showMobile = ref(false)

const isAdmin = computed(() => auth.user?.roles?.includes('admin'))

const pageTitleMap: Record<string, string> = {
  '/instructor': 'Tổng quan',
  '/instructor/courses': 'Khóa học của tôi',
  '/courses/create': 'Tạo khóa học mới',
}

const pageTitle = computed(() => {
  if (route.path.includes('/curriculum')) return 'Quản lý bài giảng (Curriculum)'
  if (route.path.includes('/students')) return 'Danh sách học viên'
  if (route.path.includes('/revenue')) return 'Phân tích Doanh thu'
  if (route.path.includes('/question-bank')) return 'Ngân hàng câu hỏi'
  if (route.path.includes('/exams')) return 'Đợt thi & đánh giá'
  if (route.path.includes('/edit')) return 'Chỉnh sửa khóa học'
  return pageTitleMap[route.path] || 'Bảng điều khiển'
})

const navGroups = [
  { 
    label: 'Tổng quan', 
    items: [
      { to: '/instructor', label: 'Bảng điều khiển', icon: 'dashboard' }
    ] 
  },
  { 
    label: 'Khóa học', 
    items: [
      { to: '/instructor/courses', label: 'Khóa học của tôi', icon: 'school' }, 
      { to: '/courses/create', label: 'Tạo mới', icon: 'add_circle' },
      // Có trỏ curriculum chung ở đây cũng được nhưng thường vào từ khóa học cụ thể
    ] 
  },
  {
    label: 'Khảo thí',
    items: [
      { to: '/instructor/question-bank', label: 'Ngân hàng câu hỏi', icon: 'database' },
      { to: '/instructor/exams', label: 'Đợt thi', icon: 'assignment' }
    ]
  },
  {
    label: 'Kinh doanh',
    items: [
      { to: '/instructor/students', label: 'Học viên', icon: 'group' },
      { to: '/instructor/revenue', label: 'Doanh thu', icon: 'payments' }
    ]
  }
]

function isActive(path: string) { return path === '/instructor' ? route.path === '/instructor' : route.path.startsWith(path) }

async function logout() { 
  await auth.logout()
  router.push('/login') 
}
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
