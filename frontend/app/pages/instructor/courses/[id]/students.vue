<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <NuxtLink to="/instructor/courses" class="text-sm text-gray-500">← Quay lại</NuxtLink>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">Học viên khóa học</h1>
      </div>
    </div>

    <div class="card p-4 mb-4">
      <input v-model="search" class="input" placeholder="Tìm theo tên hoặc email..." @keyup.enter="loadData" />
      <div class="mt-3">
        <button class="btn-primary text-sm" @click="loadData">Tìm kiếm</button>
      </div>
    </div>

    <div v-if="loading" class="card p-6 text-sm text-gray-500">Đang tải...</div>
    <div v-else-if="students.length === 0" class="card p-6 text-sm text-gray-500">Chưa có học viên.</div>
    <div v-else class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-3">Học viên</th>
            <th class="text-left p-3">Tiến độ</th>
            <th class="text-left p-3">Đã hoàn thành</th>
            <th class="text-left p-3">Đăng ký</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in students" :key="item.id" class="border-t border-gray-100">
            <td class="p-3">
              <p class="font-medium text-gray-900">{{ item.user?.name }}</p>
              <p class="text-xs text-gray-500">{{ item.user?.email }}</p>
            </td>
            <td class="p-3">{{ item.progress_percent }}%</td>
            <td class="p-3">{{ item.completed_lessons }}/{{ item.total_lessons }}</td>
            <td class="p-3">{{ formatDate(item.enrolled_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)

const loading = ref(true)
const search = ref('')
const students = ref<any[]>([])

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('vi-VN')
}

async function loadData() {
  loading.value = true
  try {
    const query = new URLSearchParams()
    if (search.value.trim()) query.set('search', search.value.trim())
    const res = await useApi<any>(`/instructor/courses/${courseId}/students?${query.toString()}`, {
      token: auth.token,
    })
    students.value = res.data || []
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>
