<template>
  <div class="min-h-screen flex bg-surface text-on-surface">
    <!-- Desktop Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-surface-lowest border-r border-surface-dim px-4 py-4 lg:flex transition-all duration-300"
    >
      <!-- Logo -->
      <div class="mb-6 px-2 py-2 flex items-center justify-between">
        <div>
          <h1 class="text-lg font-bold font-headline text-on-surface">EduPress</h1>
          <p class="text-xs font-medium text-on-surface-variant">Global Admin Console</p>
        </div>
        <span class="rounded-lg bg-primary/10 px-2 py-1 text-[10px] font-bold text-primary tracking-widest">PRO</span>
      </div>

      <!-- Nav -->
      <nav class="flex-1 space-y-1 overflow-y-auto px-2 pb-5 scrollbar-thin">
        <template v-for="group in navGroups" :key="group.label">
          <p class="px-3 pb-1.5 pt-5 text-[10px] font-bold uppercase tracking-wider text-outline first:pt-0">
            {{ group.label }}
          </p>
          <NuxtLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-body text-sm font-medium transition-all duration-300 relative group/item overflow-hidden"
            :class="isActive(item.to) ? 'bg-surface-low text-primary shadow-sm border border-surface-dim' : 'text-on-surface-variant hover:bg-surface-low hover:text-on-surface'"
          >
            <!-- Active indicator vertical line -->
            <div v-if="isActive(item.to)" class="absolute left-0 top-0 bottom-0 w-1 bg-primary"></div>
            
            <span class="material-symbols-outlined text-[20px] shrink-0 transition-transform group-hover/item:scale-110" :style="isActive(item.to) ? 'font-variation-settings: \'FILL\' 1;' : ''" :data-icon="item.icon">{{ item.icon }}</span>
            <span class="flex-1 truncate">{{ item.label }}</span>
            <span
              v-if="item.badge !== undefined && item.badge > 0"
              class="rounded-full px-2 py-0.5 text-[10px] font-bold shadow-inner"
              :class="isActive(item.to) ? 'bg-primary text-white' : 'bg-primary/10 text-primary'"
            >{{ item.badge > 99 ? '99+' : item.badge }}</span>
          </NuxtLink>
        </template>
      </nav>

      <!-- System Health Quick Block at Bottom -->
      <div class="mt-auto pt-6 space-y-1">
        <div class="p-4 mb-4 cta-gradient rounded-xl shadow-sm relative overflow-hidden">
          <div class="absolute -right-4 -top-4 w-16 h-16 bg-white/20 blur-xl rounded-full"></div>
          <p class="text-[10px] font-bold text-white mb-1.5 uppercase tracking-wider">Hệ thống & Tải trọng</p>
          <div class="h-1.5 w-full bg-white/30 rounded-full overflow-hidden mb-2">
            <div class="h-full bg-white/80 transition-all w-[35%] rounded-full"></div>
          </div>
          <p class="text-[10px] text-white/80 font-medium">Trạng thái: Ổn định</p>
        </div>
        
        <NuxtLink to="/" class="flex items-center gap-3 px-3 py-2 text-on-surface-variant text-sm font-medium hover:text-primary transition-colors rounded-lg">
          <span class="material-symbols-outlined text-[18px]">public</span>
          <span>Về trang chủ</span>
        </NuxtLink>
        <button @click="logout" class="flex w-full items-center gap-3 px-3 py-2 text-error text-sm font-medium hover:bg-error-container/50 transition-colors rounded-lg">
          <span class="material-symbols-outlined text-[18px]">logout</span>
          <span>Đăng xuất</span>
        </button>
      </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="flex min-w-0 flex-1 flex-col lg:ml-64 relative bg-surface">
      <!-- Top glass header -->
      <header class="sticky top-0 z-20 flex h-[72px] items-center justify-between px-6 lg:px-10 bg-surface-lowest/80 backdrop-blur-xl border-b border-surface-dim">
        <div class="flex items-center gap-4">
          <!-- Mobile Toggle -->
          <button @click="showMobileSidebar = true" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-low transition-colors lg:hidden">
            <span class="material-symbols-outlined text-2xl">menu</span>
          </button>
          
          <div class="space-y-0.5 hidden sm:block">
            <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Administrative Control</p>
            <h2 class="text-lg font-bold font-headline text-on-surface truncate">{{ pageTitle }}</h2>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <button class="relative w-10 h-10 rounded-full bg-surface-lowest border border-surface-dim hover:shadow-ambient flex items-center justify-center transition-all group">
            <span class="material-symbols-outlined text-outline group-hover:text-primary transition-colors text-[20px]">notifications</span>
            <span class="absolute right-2.5 top-2.5 flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-secondary opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-secondary"></span>
            </span>
          </button>

          <div class="h-8 w-[1px] bg-surface-dim hidden sm:block"></div>
          
          <div class="flex items-center gap-3">
             <div class="text-right hidden sm:block leading-tight">
                <p class="text-sm font-bold text-on-surface">{{ auth.user?.name || 'Admin root' }}</p>
                <p class="text-[10px] text-outline font-medium">Super Administrator</p>
             </div>
             <div class="w-10 h-10 rounded-full cta-gradient shadow-md flex items-center justify-center border-2 border-white cursor-pointer hover:scale-105 transition-transform">
                <span class="text-white font-bold text-sm">{{ auth.user?.name?.charAt(0)?.toUpperCase() || 'A' }}</span>
             </div>
          </div>
        </div>
      </header>

      <!-- Content Viewport -->
      <main class="container mx-auto flex-1 max-w-[1600px] px-4 pt-8 pb-4 lg:px-8 lg:pt-10 lg:pb-5">
        <div class="min-h-full rounded-[2rem] bg-surface-lowest p-5 shadow-ambient sm:p-6 lg:p-8">
          <slot />
        </div>
      </main>
    </div>

    <!-- Mobile Sidebar (Teleport) -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showMobileSidebar" class="fixed inset-0 z-50 lg:hidden">
          <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showMobileSidebar = false"></div>
          <aside class="absolute bottom-0 left-0 top-0 flex w-72 flex-col overflow-y-auto bg-surface px-4 py-6 shadow-2xl transition-transform animate-slide-in-right">
            <div class="flex items-center justify-between mb-8 px-2">
              <div>
                <h1 class="text-lg font-bold font-headline">EduPress Admin</h1>
              </div>
              <button @click="showMobileSidebar = false" class="p-2 rounded-lg bg-surface-high hover:bg-surface-highest transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
              </button>
            </div>
            
            <nav class="flex-1 space-y-1">
              <template v-for="group in navGroups" :key="group.label">
                <p class="px-3 pb-1.5 pt-5 text-[10px] font-bold uppercase tracking-wider text-outline first:pt-0">{{ group.label }}</p>
                <NuxtLink
                  v-for="item in group.items"
                  :key="item.to"
                  :to="item.to"
                  class="flex items-center gap-3 px-3 py-3 rounded-xl font-medium text-sm transition-all"
                  :class="isActive(item.to) ? 'bg-primary-fixed text-on-primary-fixed' : 'hover:bg-surface-low'"
                  @click="showMobileSidebar = false"
                >
                  <span class="material-symbols-outlined text-[20px]">{{ item.icon }}</span>
                  <span class="flex-1">{{ item.label }}</span>
                  <span v-if="item.badge" class="px-2 py-0.5 rounded bg-primary text-white text-[10px] font-bold">{{ item.badge }}</span>
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
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const showMobileSidebar = ref(false)
const pendingCoursesCount = ref(0)
const totalOrdersCount = ref(0) // Mock just for visual detail

const pageTitleMap: Record<string, string> = {
  '/admin': 'Global Performance (Tổng quan)',
  '/admin/users': 'Quản lý Tài Khoản',
  '/admin/roles': 'Phân quyền hệ thống',
  '/admin/courses': 'Trạm kiểm duyệt Khóa học',
  '/admin/categories': 'Hệ thống Danh mục học thuật',
  '/admin/orders': 'Quản lý Giao dịch & Đơn hàng',
  '/admin/payouts': 'Tạm tính payout giảng viên',
  '/admin/reviews': 'Kiểm soát Đánh giá & Report',
  '/admin/ai': 'AI Command Center',
  '/admin/settings': 'Cấu hình Website & Email',
}

const pageTitle = computed(() => {
  if (route.path.startsWith('/admin/courses/')) return 'Kiểm duyệt Nội dung (Curriculum Review)'
  return pageTitleMap[route.path] || 'Trạm Quản trị Trung tâm'
})

const navGroups = computed(() => [
  {
    label: 'Dashboard',
    items: [
      { to: '/admin', label: 'Dashboard', icon: 'dashboard' },
    ],
  },
  {
    label: 'Trí tuệ nhân tạo (AI)',
    items: [
      { to: '/admin/ai', label: 'Quản trị AI', icon: 'psychology' },
    ]
  },
  {
    label: 'Phân quyền & User',
    items: [
      { to: '/admin/users', label: 'Tất cả người dùng', icon: 'groups' },
      { to: '/admin/roles', label: 'Access Control', icon: 'admin_panel_settings' },
    ]
  },
  {
    label: 'Nền tảng đào tạo',
    items: [
      { to: '/admin/courses', label: 'Duyệt Khóa học', icon: 'inventory_2', badge: pendingCoursesCount.value },
      { to: '/admin/categories', label: 'Chuyên mục', icon: 'category' },
      { to: '/admin/reviews', label: 'Kiểm soát nội dung', icon: 'gavel' },
    ]
  },
  {
    label: 'Kinh doanh & Vận hành',
    items: [
      { to: '/admin/orders', label: 'Đơn hàng & Giao dịch', icon: 'receipt_long' },
      { to: '/admin/payouts', label: 'Payout Giảng viên', icon: 'payments' },
    ]
  },

  {
    label: 'Công cụ hệ thống',
    items: [
      { to: '/admin/settings', label: 'Cấu hình chung', icon: 'build' },
      { to: '/admin/logs', label: 'Activity Logs', icon: 'history' },
    ]
  }
])

function isActive(path: string): boolean {
  if (path === '/admin') return route.path === '/admin' || route.path === '/admin/'
  return route.path.startsWith(path)
}

async function logout() {
  await auth.logout()
  router.push('/login')
}

onMounted(async () => {
  try {
    const stats = await $fetch<any>('/api/admin/stats', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => null)
    if(stats) {
       pendingCoursesCount.value = Number(stats?.courses_by_status?.pending_review || 0)
    }
  } catch {}
})
</script>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 4px;
}

.animate-slide-in-right {
  animation: slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes slideInRight {
  from { transform: translateX(-100%); }
  to { transform: translateX(0); }
}
</style>
