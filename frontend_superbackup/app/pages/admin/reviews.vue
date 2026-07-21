<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '~/composables/useToast'

const toast = useToast()

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const reviews = ref<any[]>([])
const allReviews = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const ratingFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const expandedRows = ref({})
const deleteTarget = ref<any>(null)

const formatDate = (date?: string) =>
  !date ? '—' : new Date(date).toLocaleDateString('vi-VN', { year: 'numeric', month: 'short', day: 'numeric' })

const positiveCount = computed(() => allReviews.value.filter(r => r.rating >= 4).length)
const negativeCount = computed(() => allReviews.value.filter(r => r.rating <= 2).length)
const avgRating = computed(() => {
  if (!allReviews.value.length) return 0
  return (allReviews.value.reduce((s, r) => s + (r.rating || 0), 0) / allReviews.value.length).toFixed(1)
})
const positivePercent = computed(() =>
  allReviews.value.length ? Math.round((positiveCount.value / allReviews.value.length) * 100) : 0
)
const negativePercent = computed(() =>
  allReviews.value.length ? Math.round((negativeCount.value / allReviews.value.length) * 100) : 0
)

async function fetchReviews(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const q = new URLSearchParams({ page: String(page), per_page: '12' })
    if (search.value.trim()) q.set('search', search.value.trim())
    if (ratingFilter.value) q.set('rating', ratingFilter.value)
    const data = await useApi<any>(`/admin/reviews?${q}`, { headers: authHeaders() })
    reviews.value = data.data || []
    totalPages.value = data.last_page || 1
    totalItems.value = data.total || 0

    if (allReviews.value.length === 0) {
      const all = await useApi<any>('/admin/reviews?per_page=200', { headers: authHeaders() })
      allReviews.value = all.data || []
    }
  }
  catch { reviews.value = [] }
  finally { loading.value = false }
}

async function removeReview(review: any) {
  try {
    await useApi(`/admin/reviews/${review.id}`, { method: 'DELETE', headers: authHeaders() })
    allReviews.value = allReviews.value.filter(r => r.id !== review.id)
    toast.success('Đã xoá đánh giá', `Đánh giá của ${review.user?.name || 'người dùng'} đã được xoá.`)
    await fetchReviews(currentPage.value)
    deleteTarget.value = null
  }
  catch (e: any) {
    toast.error('Xoá thất bại', e?.data?.message || 'Không thể xoá đánh giá này.')
  }
}

function exportCSV() {
  const rows = reviews.value.map(r => [
    r.id, r.course?.title || '', r.user?.name || '', r.user?.email || '',
    r.rating, `"${(r.comment || '').replace(/"/g, '""')}"`, formatDate(r.created_at),
  ])
  const header = ['ID', 'Khoá học', 'Người đánh giá', 'Email', 'Số sao', 'Nội dung', 'Ngày tạo']
  const csv = [header, ...rows].map(r => r.join(',')).join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = 'reviews_export.csv'; a.click()
  URL.revokeObjectURL(url)
}

const visiblePages = computed(() => {
  const range: number[] = []
  const maxVisible = 5
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
  let end = Math.min(totalPages.value, start + maxVisible - 1)
  if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1)
  for (let i = start; i <= end; i++) { if (i >= 1) range.push(i) }
  return range
})

const ratingTier = (r: number) => r >= 4 ? 'positive' : r <= 2 ? 'negative' : 'neutral'

onMounted(() => fetchReviews(1))
</script>

<template>
  <div class="flex flex-col gap-5">
    <header>
      <p class="text-xs font-semibold uppercase tracking-wider text-surface-500">Khóa học</p>
      <h1 class="mt-1 text-2xl font-bold text-surface-900 dark:text-surface-0">Kiểm duyệt đánh giá</h1>
      <p class="mt-1 text-sm text-surface-500">Theo dõi phản hồi học viên và gỡ đánh giá vi phạm.</p>
    </header>
    <Card>
      <template #content>
        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div class="flex flex-1 flex-col gap-3 sm:flex-row">
            <IconField class="w-full sm:max-w-md"><InputIcon class="pi pi-search" /><InputText v-model="search" class="w-full" placeholder="Tìm nội dung đánh giá..." @keyup.enter="fetchReviews(1)" /></IconField>
            <Select v-model="ratingFilter" :options="[{label:'Tất cả sao',value:''},{label:'5 sao',value:'5'},{label:'4 sao',value:'4'},{label:'3 sao',value:'3'},{label:'2 sao',value:'2'},{label:'1 sao',value:'1'}]" option-label="label" option-value="value" class="w-full sm:w-44" @change="fetchReviews(1)" />
          </div>
          <Button label="Xuất CSV" icon="pi pi-download" severity="secondary" outlined @click="exportCSV" />
        </div>
        <DataTable v-model:expanded-rows="expandedRows" :value="reviews" data-key="id" :loading="loading" striped-rows responsive-layout="scroll">
          <template #empty>Không có đánh giá nào khớp bộ lọc.</template>
          <Column expander style="width: 3rem" />
          <Column header="Học viên"><template #body="{data}"><div><strong>{{ data.user?.name || 'Ẩn danh' }}</strong><div class="text-sm text-surface-500">{{ data.user?.email || '—' }}</div></div></template></Column>
          <Column header="Khóa học"><template #body="{data}">{{ data.course?.title || 'Không rõ khóa học' }}</template></Column>
          <Column header="Đánh giá"><template #body="{data}"><Rating :model-value="data.rating" readonly /><Tag :value="`${data.rating}/5`" :severity="ratingTier(data.rating) === 'positive' ? 'success' : ratingTier(data.rating) === 'negative' ? 'danger' : 'warn'" class="mt-2" /></template></Column>
          <Column header="Ngày tạo"><template #body="{data}">{{ formatDate(data.created_at) }}</template></Column>
          <Column header="Thao tác" style="width:6rem"><template #body="{data}"><Button icon="pi pi-trash" severity="danger" text rounded aria-label="Xóa" @click="deleteTarget = data" /></template></Column>
          <template #expansion="{data}"><div class="p-4"><strong>Nội dung đánh giá</strong><p class="mt-2 whitespace-pre-wrap text-surface-600 dark:text-surface-300">{{ data.comment || 'Không có nhận xét chi tiết.' }}</p></div></template>
        </DataTable>
        <Paginator v-if="totalPages > 1" class="mt-4" :first="(currentPage - 1) * 12" :rows="12" :total-records="totalItems" @page="fetchReviews($event.page + 1)" />
      </template>
    </Card>
    <Dialog :visible="!!deleteTarget" modal header="Xóa đánh giá" class="w-[min(28rem,calc(100vw-2rem))]" @update:visible="!$event && (deleteTarget = null)">
      <p>Xóa đánh giá {{ deleteTarget?.rating }}★ của <strong>{{ deleteTarget?.user?.name || 'người dùng' }}</strong>? Hành động này không thể hoàn tác.</p>
      <template #footer><Button label="Hủy" severity="secondary" text @click="deleteTarget = null" /><Button label="Xóa" severity="danger" icon="pi pi-trash" @click="removeReview(deleteTarget)" /></template>
    </Dialog>
  </div>
</template>
