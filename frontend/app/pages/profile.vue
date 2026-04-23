<template>
  <section class="max-w-7xl mx-auto px-4 md:px-8 py-10 md:py-16 min-h-[80vh]">
    <!-- Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-end mb-12 gap-6">
      <div class="max-w-2xl">
        <h2 class="text-4xl md:text-[3.5rem] font-bold font-headline tracking-tight text-on-surface">Cài đặt Tài khoản</h2>
        <p class="text-on-surface-variant text-lg mt-4 font-body leading-relaxed">
          Quản lý thông tin cá nhân, cập nhật bảo mật và tuỳ chỉnh tài khoản của bạn.
        </p>
      </div>
    </header>

    <div class="grid grid-cols-12 gap-8">
      
      <!-- Side NavBar Area -->
      <aside class="col-span-12 lg:col-span-3 space-y-6">
        <!-- User Brief Card -->
        <div class="bg-surface-lowest p-8 rounded-2xl shadow-sm border border-surface-dim text-center">
            <div class="mb-5 flex h-24 w-24 mx-auto items-center justify-center overflow-hidden rounded-full bg-primary/10 text-3xl font-bold text-primary shadow-inner">
              <img v-if="auth.user?.avatar" :src="auth.user.avatar" :alt="auth.user?.name" class="h-full w-full object-cover">
              <span v-else>{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
            </div>
            <h2 class="font-headline text-xl font-bold text-on-surface">{{ auth.user?.name }}</h2>
            <p class="mt-1 text-sm text-on-surface-variant mb-5">{{ auth.user?.email }}</p>
            <StatusBadge :value="roleLabel" />
        </div>

        <!-- Menu Navigation -->
        <div class="bg-surface-lowest p-3 rounded-2xl shadow-sm border border-surface-dim flex flex-col gap-1">
          <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest px-4 py-2">Quản lý Tài khoản</p>
          <button
            v-for="tab in tabs"
            :key="tab.id"
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm transition-all duration-300',
              activeTab === tab.id ? 'bg-primary/5 text-primary shadow-sm translate-x-1' : 'text-on-surface-variant hover:bg-surface-low hover:text-on-surface hover:translate-x-1'
            ]"
            @click="activeTab = tab.id"
          >
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">{{ tab.iconStr }}</span>
            <span>{{ tab.label }}</span>
          </button>
        </div>

        <!-- Quick Links -->
        <div class="bg-surface-lowest p-3 rounded-2xl shadow-sm border border-surface-dim flex flex-col gap-1">
            <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest px-4 py-2">Truy cập nhanh</p>
            <NuxtLink to="/my-courses" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-on-surface-variant rounded-xl hover:bg-surface-low hover:translate-x-1 transition-all">
              <span class="material-symbols-outlined text-[18px]">school</span>
              Khóa học của tôi
            </NuxtLink>
            <NuxtLink to="/orders" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-on-surface-variant rounded-xl hover:bg-surface-low hover:translate-x-1 transition-all">
              <span class="material-symbols-outlined text-[18px]">receipt_long</span>
              Lịch sử đơn hàng
            </NuxtLink>
        </div>
      </aside>

      <!-- Main Form Area -->
      <div class="col-span-12 lg:col-span-9 space-y-8">
        <div class="bg-surface-lowest p-8 md:p-12 rounded-2xl shadow-ambient border border-surface-dim min-h-[600px]">
          <div class="flex items-center justify-between mb-8 border-b border-surface-dim/30 pb-6">
            <div>
              <h3 class="text-3xl font-bold font-headline text-on-surface">{{ activeTab === 'info' ? 'Chỉnh sửa Hồ sơ' : 'Bảo mật & Mật khẩu' }}</h3>
              <p class="text-sm text-on-surface-variant mt-2 max-w-xl">
                {{ activeTab === 'info' ? 'Cập nhật lại thông tin cá nhân và hình đại diện của bạn.' : 'Bảo vệ tài khoản bằng việc thay đổi mật khẩu định kỳ, giúp bảo vệ tài sản học thuật của bạn.' }}
              </p>
            </div>
            <div class="hidden sm:block">
               <span class="material-symbols-outlined text-5xl text-surface-dim/50">{{ activeTab === 'info' ? 'badge' : 'shield_person' }}</span>
            </div>
          </div>
          
          <div class="max-w-3xl fade-in-up">
            <AuthProfileForm v-if="activeTab === 'info'" />
            <AuthChangePasswordForm v-else />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ middleware: 'auth' })

const auth = useAuthStore()
const activeTab = ref<'info' | 'password'>('info')

const tabs = [
  { id: 'info', label: 'Thông tin cá nhân', iconStr: 'person' },
  { id: 'password', label: 'Bảo mật', iconStr: 'lock' },
] as const

const roleLabel = computed(() => {
  const role = auth.user?.role ?? auth.user?.roles?.[0] ?? 'student'
  return {
    admin: 'Quản trị viên',
    instructor: 'Giảng viên',
    student: 'Học viên',
  }[role] ?? 'Học viên'
})
</script>

<style scoped>
.fade-in-up {
  opacity: 0;
  animation: fadeInUp 0.4s ease-out forwards;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
