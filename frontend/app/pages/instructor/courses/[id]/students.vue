<template>
  <NuxtLayout name="instructor">
    <section class="space-y-8">
      <div class="flex flex-col gap-4 lg:gap-5">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary">Instructor</p>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h1 class="font-headline text-3xl font-bold tracking-[-0.02em] text-on-surface sm:text-4xl">Học viên khóa học</h1>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-on-surface-variant">Theo dõi danh sách học viên, tiến độ hoàn thành và thời điểm đăng ký.</p>
          </div>
          <div class="flex items-center gap-3">
            <NuxtLink
              to="/instructor/courses"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-surface-dim/60 bg-surface-lowest px-5 py-2.5 text-sm font-bold text-on-surface shadow-sm hover:bg-surface-low transition-all cursor-pointer"
            >
              Quay lại
            </NuxtLink>
          </div>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-[1fr_auto]">
        <input
          v-model="search"
          type="text"
          placeholder="Tìm theo tên hoặc email..."
          class="h-12 w-full rounded-xl border border-surface-dim/60 bg-surface-lowest px-4 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
          @keyup.enter="loadData"
        >
        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark hover:shadow-lg transition-all cursor-pointer"
          @click="loadData"
        >
          Tìm kiếm
        </button>
      </div>

      <div v-if="loading" class="grid gap-4">
        <div v-for="item in 5" :key="item" class="h-24 rounded-3xl border border-surface-dim bg-surface-lowest animate-pulse" />
      </div>

      <UiEmptyState v-else-if="students.length === 0" title="Chưa có học viên" description="Danh sách học viên sẽ xuất hiện khi có người đăng ký khóa học." />

      <div v-else class="space-y-4">
        <UiCard v-for="item in students" :key="item.id">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <p class="font-semibold text-on-surface">{{ item.user?.name }}</p>
              <p class="text-sm text-on-surface-variant">{{ item.user?.email }}</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-3 lg:w-[420px]">
              <div>
                <p class="text-xs uppercase tracking-wide text-outline">Tiến độ</p>
                <p class="mt-1 font-semibold text-on-surface">{{ item.progress_percent }}%</p>
              </div>
              <div>
                <p class="text-xs uppercase tracking-wide text-outline">Hoàn thành</p>
                <p class="mt-1 font-semibold text-on-surface">{{ item.completed_lessons }}/{{ item.total_lessons }}</p>
              </div>
              <div>
                <p class="text-xs uppercase tracking-wide text-outline">Đăng ký</p>
                <p class="mt-1 font-semibold text-on-surface">{{ formatDate(item.enrolled_at) }}</p>
              </div>
            </div>
          </div>
        </UiCard>
      </div>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ middleware: 'instructor' })
const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)
const loading = ref(true)
const search = ref('')
const students = ref<any[]>([])
const formatDate = (value: string) => new Date(value).toLocaleDateString('vi-VN')
async function loadData() { loading.value = true; try { const query = new URLSearchParams(); if (search.value.trim()) query.set('search', search.value.trim()); const res = await useApi<any>(`/instructor/courses/${courseId}/students?${query.toString()}`, { token: auth.token }); students.value = res.data || [] } finally { loading.value = false } }
onMounted(loadData)
</script>
