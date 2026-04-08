<template>
  <div class="min-h-screen flex bg-gradient-to-br from-slate-50 to-gray-100">
    <!-- Desktop Sidebar -->
    <aside
      class="hidden lg:flex lg:flex-col w-64 bg-gray-900 text-white fixed inset-y-0 left-0 z-30"
    >
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
        <span class="text-[10px] font-semibold bg-primary/20 text-primary-300 px-2 py-0.5 rounded-full flex-shrink-0">ADMIN</span>
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
            <span
              v-if="item.badge !== undefined && item.badge > 0"
              class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
              :class="isActive(item.to) ? 'bg-white/20 text-white' : 'bg-amber-500 text-white'"
            >{{ item.badge }}</span>
          </NuxtLink>
        </template>
      </nav>

      <!-- User info + logout at bottom -->
      <div class="px-3 py-4 border-t border-gray-800 space-y-1 flex-shrink-0">
        <div class="flex items-center gap-3 px-3 py-2 mb-1">
          <div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
            <span class="text-xs font-bold text-white">{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-white truncate">{{ auth.user?.name }}</p>
            <p class="text-[10px] text-gray-500 truncate">{{ auth.user?.email }}</p>
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

    <!-- Main content shifted right on desktop -->
    <div class="flex-1 flex flex-col min-w-0 lg:ml-64">
      <!-- Top bar -->
      <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-white/20 shadow-sm h-14 flex items-center px-4 lg:px-6 gap-3 transition-all duration-300">
        <!-- Mobile sidebar toggle -->
        <button @click="showMobileSidebar = true" class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>

        <!-- Breadcrumb / page title -->
        <div class="flex items-center gap-2 text-sm transition-all duration-300">
          <span class="text-gray-400">Admin</span>
          <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          <span class="font-semibold text-gray-900 drop-shadow-sm">{{ pageTitle }}</span>
        </div>

        <div class="ml-auto flex items-center gap-3">
          <!-- Notification Bell Placeholder -->
          <button class="relative p-2 text-gray-400 hover:text-gray-600 transition-colors rounded-full hover:bg-gray-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
          </button>
          
          <div class="hidden sm:flex items-center gap-2 bg-white/50 backdrop-blur-sm border border-gray-200/60 rounded-xl px-3 py-1.5 shadow-sm">
            <div class="w-6 h-6 rounded-full bg-gradient-to-br from-primary to-emerald-500 flex items-center justify-center flex-shrink-0 shadow-sm">
              <span class="text-[10px] font-bold text-white">{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
            </div>
            <span class="text-sm font-semibold text-gray-700">{{ auth.user?.name }}</span>
          </div>
        </div>
      </header>

      <!-- Content -->
      <main class="flex-1 p-4 lg:p-6 animate-fade-in-up">
        <slot />
      </main>
    </div>

    <!-- Mobile Sidebar overlay -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showMobileSidebar" class="fixed inset-0 z-50 lg:hidden">
          <div class="absolute inset-0 bg-black/60" @click="showMobileSidebar = false"></div>
          <aside class="absolute left-0 top-0 bottom-0 w-72 bg-gray-900 text-white flex flex-col overflow-y-auto">
            <div class="flex items-center justify-between px-5 h-14 border-b border-gray-800 flex-shrink-0">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 bg-primary rounded-md flex items-center justify-center">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                </div>
                <span class="font-bold">EduPress Admin</span>
              </div>
              <button @click="showMobileSidebar = false" class="p-1 rounded-lg hover:bg-gray-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <nav class="flex-1 px-3 py-3 space-y-0.5">
              <template v-for="group in navGroups" :key="group.label">
                <p class="px-3 pt-4 pb-1.5 text-[10px] font-semibold text-gray-500 uppercase tracking-widest first:pt-0">{{ group.label }}</p>
                <NuxtLink
                  v-for="item in group.items"
                  :key="item.to"
                  :to="item.to"
                  class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                  :class="isActive(item.to) ? 'bg-primary text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
                  @click="showMobileSidebar = false"
                >
                  <span v-html="item.icon" class="w-5 h-5 flex-shrink-0"></span>
                  <span class="flex-1">{{ item.label }}</span>
                  <span v-if="item.badge !== undefined && item.badge > 0" class="text-[10px] font-bold bg-amber-500 text-white px-1.5 py-0.5 rounded-full">{{ item.badge }}</span>
                </NuxtLink>
              </template>
            </nav>
            <div class="px-3 py-3 border-t border-gray-800 space-y-1 flex-shrink-0">
              <NuxtLink to="/" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition-colors" @click="showMobileSidebar = false">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Về trang chủ
              </NuxtLink>
              <button @click="logout" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-red-400 hover:text-red-300 hover:bg-gray-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                Đăng xuất
              </button>
            </div>
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

const showMobileSidebar = ref(false)
const pendingCoursesCount = ref(0)

const pageTitleMap: Record<string, string> = {
  '/admin': 'Tổng quan',
  '/admin/users': 'Quản lý người dùng',
  '/admin/courses': 'Quản lý khóa học',
  '/admin/categories': 'Quản lý danh mục',
  '/admin/orders': 'Quản lý đơn hàng',
  '/admin/reviews': 'Quản lý đánh giá',
}

const pageTitle = computed(() => {
  if (route.path.startsWith('/admin/courses/')) return 'Chi tiết khóa học'
  return pageTitleMap[route.path] || 'Quản trị'
})

const ICON = {
  dashboard: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" /></svg>',
  users: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
  courses: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>',
  categories: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>',
  orders: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>',
  reviews: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>',
}

const navGroups = computed(() => [
  {
    label: 'Tổng quan',
    items: [
      { to: '/admin', label: 'Dashboard', icon: ICON.dashboard },
    ],
  },
  {
    label: 'Quản lý người dùng',
    items: [
      { to: '/admin/users', label: 'Người dùng', icon: ICON.users },
    ],
  },
  {
    label: 'Quản lý nội dung',
    items: [
      { to: '/admin/courses', label: 'Khóa học', icon: ICON.courses, badge: pendingCoursesCount.value },
      { to: '/admin/categories', label: 'Danh mục', icon: ICON.categories },
      { to: '/admin/reviews', label: 'Đánh giá', icon: ICON.reviews },
    ],
  },
  {
    label: 'Tài chính',
    items: [
      { to: '/admin/orders', label: 'Đơn hàng', icon: ICON.orders },
    ],
  },
])

function isActive(path: string): boolean {
  if (path === '/admin') return route.path === '/admin'
  return route.path.startsWith(path)
}

async function logout() {
  await auth.logout()
  router.push('/login')
}

onMounted(async () => {
  try {
    const stats = await useApi<any>('/admin/stats', { token: auth.token })
    pendingCoursesCount.value = Number(stats?.courses_by_status?.pending_review || 0)
  } catch {}
})
</script>
