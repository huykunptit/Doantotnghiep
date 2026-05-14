<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'
import AdminFooter from '~/components/dashboard/AdminFooter.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const sidebarOpen = ref(false)
const { siteName, siteLogo } = useSiteSettings()

if (!auth.isReady) auth.initFromStorage()
if (auth.token && !auth.user) await auth.fetchMe()
if (!auth.isLoggedIn || !auth.user) await navigateTo('/login', { replace: true })

const user = computed(() => auth.user)

const searchPlaceholder = computed(() => {
  if (route.path.includes('/curriculum')) return 'Tìm chương, bài học...'
  if (route.path.includes('/question-bank')) return 'Tìm câu hỏi...'
  return 'Tìm khóa học, học viên...'
})

watch(() => route.fullPath, () => { sidebarOpen.value = false })

const navGroups = [
  {
    label: 'Tổng quan',
    items: [{ to: '/instructor', label: 'Bảng điều khiển', icon: 'dashboard' }],
  },
  {
    label: 'Khóa học',
    items: [
      { to: '/instructor/courses', label: 'Khóa học của tôi', icon: 'school' },
      { to: '/courses/create', label: 'Tạo khóa học mới', icon: 'add_circle' },
    ],
  },
  {
    label: 'Khảo thí',
    items: [
      { to: '/instructor/question-bank', label: 'Ngân hàng câu hỏi', icon: 'database' },
      { to: '/instructor/exams', label: 'Đợt thi', icon: 'assignment' },
    ],
  },
  {
    label: 'Học vụ',
    items: [
      { to: '/instructor/sections', label: 'Lớp học phần & điểm', icon: 'grading' },
    ],
  },
  {
    label: 'Kinh doanh',
    items: [
      { to: '/instructor/students', label: 'Học viên', icon: 'group' },
      { to: '/instructor/revenue', label: 'Doanh thu', icon: 'payments' },
    ],
  },
]

function isActive(path: string) {
  return path === '/instructor' ? route.path === '/instructor' : route.path.startsWith(path)
}

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <main class="dashboard-shell">
    <div class="dashboard-frame" :class="{ 'sidebar-open': sidebarOpen }">
      <!-- Sidebar -->
      <aside class="dashboard-sidebar">
        <div class="sidebar-brand">
         <div class="brand-line">
            <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="brand-logo">
            <span v-else class="brand-mark" />
           
          </div>
          <div>
            <p class="sidebar-eyebrow">Giảng viên</p>
            <h1>Instructor</h1>
          </div>
        </div>

        <nav class="sidebar-nav-grouped">
          <section v-for="group in navGroups" :key="group.label" class="sidebar-nav-section">
            <p class="sidebar-label">{{ group.label }}</p>
            <div class="sidebar-nav">
              <NuxtLink
                v-for="item in group.items"
                :key="item.to"
                :to="item.to"
                class="sidebar-link"
                :class="{ 'is-active': isActive(item.to) }"
              >
                <span class="sidebar-icon material-symbols-outlined">{{ item.icon }}</span>
                <span>{{ item.label }}</span>
              </NuxtLink>
            </div>
          </section>
        </nav>

        <div class="sidebar-profile">
          <div class="avatar-chip is-small">{{ user?.name?.slice(0, 2)?.toUpperCase() || 'GV' }}</div>
          <div>
            <strong>{{ user?.name || 'Giảng viên' }}</strong>
            <p>Instructor</p>
          </div>
          <button type="button" class="profile-action button-reset" @click="logout">Thoát</button>
        </div>
      </aside>

      <!-- Mobile overlay -->
      <button
        v-if="sidebarOpen"
        type="button"
        class="dashboard-overlay button-reset"
        aria-label="Đóng sidebar"
        @click="sidebarOpen = false"
      />

      <!-- Main -->
      <section class="dashboard-main">
        <AdminTopbar
          :search-placeholder="searchPlaceholder"
          :user-name="user?.name || 'Giảng viên'"
          :user-avatar="user?.avatar"
          user-role="Instructor"
          dashboard-path="/instructor"
          settings-path="/instructor"
          @toggle-sidebar="sidebarOpen = !sidebarOpen"
        />

        <slot />

        <AdminFooter />
      </section>
    </div>
  </main>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
