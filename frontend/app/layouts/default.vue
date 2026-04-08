<template>
  <div class="min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="sticky top-0 z-50 bg-white border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Left: Logo + Links -->
          <div class="flex items-center gap-8">
            <NuxtLink to="/" class="flex items-center gap-2">
              <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
              </div>
              <span class="text-xl font-bold text-gray-900">EduPress</span>
            </NuxtLink>

            <div class="hidden md:flex items-center gap-1">
              <NuxtLink to="/courses" class="px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                Khóa học
              </NuxtLink>
              <NuxtLink v-if="auth.isLoggedIn" to="/my-courses" class="px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                Khóa học của tôi
              </NuxtLink>
              <NuxtLink v-if="isInstructor || isAdmin" to="/instructor" class="px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                Giảng viên
              </NuxtLink>
              <NuxtLink v-if="isAdmin" to="/admin" class="px-3 py-2 rounded-lg text-sm font-medium text-primary hover:text-primary-dark hover:bg-primary-light transition-colors">
                Quản trị
              </NuxtLink>
            </div>
          </div>

          <!-- Right: Search + Auth -->
          <div class="flex items-center gap-3">
            <div class="hidden lg:block relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Tìm kiếm khóa học..."
                class="w-64 pl-9 pr-4 py-2 rounded-lg border border-gray-300 text-sm focus:border-primary focus:ring-2 focus:ring-primary-light focus:outline-none transition-colors"
                @keyup.enter="handleSearch"
              />
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>

            <template v-if="!auth.isLoggedIn">
              <NuxtLink to="/login" class="btn-ghost text-sm">Đăng nhập</NuxtLink>
              <NuxtLink to="/register" class="btn-primary text-sm">Đăng ký</NuxtLink>
            </template>

            <div v-else class="relative" ref="menuRef">
              <button @click="showMenu = !showMenu" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                <div class="w-8 h-8 rounded-full bg-primary-light flex items-center justify-center overflow-hidden">
                  <img v-if="auth.user?.avatar" :src="auth.user.avatar" class="w-full h-full object-cover" />
                  <span v-else class="text-sm font-semibold text-primary">{{ auth.user?.name?.charAt(0) }}</span>
                </div>
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <Transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
              >
                <div v-if="showMenu" class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-lg py-1 z-50">
                  <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-900">{{ auth.user?.name }}</p>
                    <p class="text-xs text-gray-500">{{ auth.user?.email }}</p>
                  </div>
                  <NuxtLink to="/profile" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50" @click="showMenu = false">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    Hồ sơ
                  </NuxtLink>
                  <NuxtLink to="/my-courses" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50" @click="showMenu = false">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                    Khóa học của tôi
                  </NuxtLink>
                  <NuxtLink to="/orders" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50" @click="showMenu = false">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Đơn hàng
                  </NuxtLink>
                  <div class="border-t border-gray-100 mt-1 pt-1">
                    <button @click="handleLogout" class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                      Đăng xuất
                    </button>
                  </div>
                </div>
              </Transition>
            </div>

            <button @click="showMobileMenu = !showMobileMenu" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="!showMobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Mobile nav -->
        <div v-if="showMobileMenu" class="md:hidden border-t border-gray-100 py-3 space-y-1">
          <NuxtLink to="/courses" class="block px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50" @click="showMobileMenu = false">Khóa học</NuxtLink>
          <NuxtLink v-if="auth.isLoggedIn" to="/my-courses" class="block px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50" @click="showMobileMenu = false">Khóa học của tôi</NuxtLink>
          <NuxtLink v-if="isInstructor || isAdmin" to="/instructor" class="block px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50" @click="showMobileMenu = false">Giảng viên</NuxtLink>
          <NuxtLink v-if="isAdmin" to="/admin" class="block px-3 py-2 rounded-lg text-sm text-primary hover:bg-primary-light" @click="showMobileMenu = false">Quản trị</NuxtLink>
        </div>
      </div>
    </nav>

    <main class="flex-1">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <div class="md:col-span-2">
            <div class="flex items-center gap-2 mb-4">
              <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
              </div>
              <span class="text-xl font-bold">EduPress</span>
            </div>
            <p class="text-gray-400 text-sm max-w-sm">Nền tảng học tập trực tuyến hàng đầu. Kết nối giảng viên và học viên trên mọi lĩnh vực.</p>
          </div>
          <div>
            <h4 class="text-sm font-semibold mb-4 uppercase tracking-wider text-gray-400">Liên kết</h4>
            <ul class="space-y-2 text-sm">
              <li><NuxtLink to="/courses" class="text-gray-300 hover:text-white transition-colors">Khóa học</NuxtLink></li>
              <li><NuxtLink to="/register" class="text-gray-300 hover:text-white transition-colors">Trở thành giảng viên</NuxtLink></li>
            </ul>
          </div>
          <div>
            <h4 class="text-sm font-semibold mb-4 uppercase tracking-wider text-gray-400">Hỗ trợ</h4>
            <ul class="space-y-2 text-sm">
              <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Liên hệ</a></li>
              <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Điều khoản sử dụng</a></li>
            </ul>
          </div>
        </div>
        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm text-gray-500">
          &copy; {{ new Date().getFullYear() }} EduPress. Đồ án tốt nghiệp PTIT.
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const router = useRouter()

const searchQuery = ref('')
const showMenu = ref(false)
const showMobileMenu = ref(false)
const menuRef = ref<HTMLElement | null>(null)

const isAdmin = computed(() => auth.user?.roles?.includes('admin'))
const isInstructor = computed(() => auth.user?.roles?.includes('instructor') || isAdmin.value)

function handleSearch() {
  if (searchQuery.value.trim()) {
    router.push({ path: '/courses', query: { search: searchQuery.value.trim() } })
    searchQuery.value = ''
  }
}

async function handleLogout() {
  showMenu.value = false
  await auth.logout()
  router.push('/')
}

function handleClickOutside(e: Event) {
  if (menuRef.value && !menuRef.value.contains(e.target as Node)) {
    showMenu.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>
