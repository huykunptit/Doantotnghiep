<template>
  <div class="min-h-screen flex bg-gray-50">
    <!-- Desktop Sidebar -->
    <aside class="hidden lg:flex lg:flex-col w-60 bg-gray-900 text-white fixed inset-y-0 left-0 z-30">
      <!-- Logo -->
      <div class="flex items-center gap-3 px-5 h-16 border-b border-gray-800 flex-shrink-0">
        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <span class="text-base font-bold">EduPress</span>
        </div>
        <span class="text-[10px] font-semibold bg-blue-500/20 text-blue-300 px-2 py-0.5 rounded-full flex-shrink-0">GV</span>
      </div>

      <!-- Nav -->
      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <template v-for="group in navGroups" :key="group.label">
          <p class="px-3 pt-4 pb-1.5 text-[10px] font-semibold text-gray-500 uppercase tracking-widest first:pt-0">
            {{ group.label }}
          </p>
          <NuxtLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="isActive(item.to) ? 'bg-primary text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
          >
            <span v-html="item.icon" class="w-5 h-5 flex-shrink-0"></span>
            <span class="flex-1">{{ item.label }}</span>
          </NuxtLink>
        </template>
      </nav>

      <!-- Bottom -->
      <div class="px-3 py-4 border-t border-gray-800 space-y-1 flex-shrink-0">
        <div class="flex items-center gap-3 px-3 py-2 mb-1">
          <div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
            <span class="text-xs font-bold text-white">{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-white truncate">{{ auth.user?.name }}</p>
            <p class="text-[10px] text-gray-500 truncate">Giảng viên</p>
          </div>
        </div>
        <NuxtLink to="/" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          Về trang chủ
        </NuxtLink>
        <button @click="logout" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-red-400 hover:text-red-300 hover:bg-gray-800 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
          Đăng xuất
        </button>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0 lg:ml-60">
      <!-- Top bar -->
      <header class="sticky top-0 z-20 bg-white border-b border-gray-200 h-14 flex items-center px-4 lg:px-6 gap-3">
        <button @click="showMobile = true" class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
        <div class="flex items-center gap-2 text-sm">
          <span class="text-gray-400">Giảng viên</span>
          <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          <span class="font-semibold text-gray-900">{{ pageTitle }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
          <NuxtLink v-if="isAdmin" to="/admin" class="text-xs text-primary hover:underline font-medium">Admin Panel</NuxtLink>
          <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-3 py-1.5">
            <div class="w-6 h-6 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
              <span class="text-[10px] font-bold text-white">{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
            </div>
            <span class="text-sm font-medium text-gray-700 hidden sm:block">{{ auth.user?.name }}</span>
          </div>
        </div>
      </header>

      <main class="flex-1 p-4 lg:p-6">
        <slot />
      </main>
    </div>

    <!-- Mobile sidebar -->
    <Teleport to="body">
      <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showMobile" class="fixed inset-0 z-50 lg:hidden">
          <div class="absolute inset-0 bg-black/60" @click="showMobile = false"></div>
          <aside class="absolute left-0 top-0 bottom-0 w-64 bg-gray-900 text-white flex flex-col overflow-y-auto">
            <div class="flex items-center justify-between px-5 h-14 border-b border-gray-800 flex-shrink-0">
              <span class="font-bold">EduPress Giảng viên</span>
              <button @click="showMobile = false" class="p-1 rounded-lg hover:bg-gray-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <nav class="flex-1 px-3 py-3 space-y-0.5">
              <template v-for="group in navGroups" :key="group.label">
                <p class="px-3 pt-4 pb-1.5 text-[10px] font-semibold text-gray-500 uppercase tracking-widest first:pt-0">{{ group.label }}</p>
                <NuxtLink v-for="item in group.items" :key="item.to" :to="item.to"
                  class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                  :class="isActive(item.to) ? 'bg-primary text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
                  @click="showMobile = false">
                  <span v-html="item.icon" class="w-5 h-5 flex-shrink-0"></span>
                  <span>{{ item.label }}</span>
                </NuxtLink>
              </template>
            </nav>
          </aside>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>



<script setup lang="ts">
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const showMobile = ref(false)

const isAdmin = computed(() => auth.user?.roles?.includes('admin'))

const pageTitleMap: Record<string, string> = {
  '/instructor': 'Dashboard',
  '/instructor/courses': 'Khóa học của tôi',
  '/courses/create': 'Tạo khóa học',
}

const pageTitle = computed(() => {
  if (route.path.includes('/curriculum')) return 'Quản lý Curriculum'
  if (route.path.includes('/students')) return 'Danh sách học viên'
  if (route.path.includes('/revenue')) return 'Doanh thu'
  if (route.path.includes('/edit')) return 'Chỉnh sửa khóa học'
  return pageTitleMap[route.path] || 'Giảng viên'
})

const ICON = {
  dashboard: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" /></svg>',
  courses: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>',
  add: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>',
}

const navGroups = [
  {
    label: 'Tổng quan',
    items: [
      { to: '/instructor', label: 'Dashboard', icon: ICON.dashboard },
    ],
  },
  {
    label: 'Khóa học',
    items: [
      { to: '/instructor/courses', label: 'Khóa học của tôi', icon: ICON.courses },
      { to: '/courses/create', label: 'Tạo khóa học mới', icon: ICON.add },
    ],
  },
]

function isActive(path: string): boolean {
  if (path === '/instructor') return route.path === '/instructor'
  return route.path.startsWith(path)
}

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>
