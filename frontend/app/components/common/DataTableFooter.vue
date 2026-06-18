<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  current: number
  last: number
  total: number
  perPage: number
  perPageOptions?: number[]
}>(), {
  perPageOptions: () => [10, 25, 50, 100]
})

const emit = defineEmits<{
  page: [page: number]
  'update:perPage': [value: number]
}>()

const localPerPage = ref(props.perPage)

watch(() => props.perPage, (v) => { localPerPage.value = v })

function onPerPageChange() {
  emit('update:perPage', localPerPage.value)
  emit('page', 1)
}

const from = computed(() => {
  if (props.total === 0) return 0
  return (props.current - 1) * props.perPage + 1
})

const to = computed(() => {
  return Math.min(props.current * props.perPage, props.total)
})

const visiblePages = computed(() => {
  const range: number[] = []
  const maxVisible = 5
  let start = Math.max(1, props.current - Math.floor(maxVisible / 2))
  let end = Math.min(props.last, start + maxVisible - 1)
  if (end - start + 1 < maxVisible) {
    start = Math.max(1, end - maxVisible + 1)
  }
  for (let i = start; i <= end; i++) {
    if (i >= 1) range.push(i)
  }
  return range
})
</script>

<template>
  <div class="dt-footer">
    <div class="dt-footer-left">
      <label class="dt-per-page">
        Hiển thị
        <select v-model="localPerPage" @change="onPerPageChange">
          <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
        </select>
        bản ghi
      </label>
    </div>

    <div class="dt-footer-right">
      <p class="dt-info">
        <template v-if="total === 0">
          Không có bản ghi nào
        </template>
        <template v-else>
          Hiển thị <strong>{{ from }}</strong> đến <strong>{{ to }}</strong>
          trong tổng số <strong>{{ total }}</strong> bản ghi
        </template>
      </p>

      <div class="dt-pagination">
        <button
          class="dt-page-btn"
          type="button"
          title="Trang đầu"
          :disabled="current <= 1"
          @click="emit('page', 1)"
        >«</button>

        <button
          class="dt-page-btn"
          type="button"
          title="Trang trước"
          :disabled="current <= 1"
          @click="emit('page', current - 1)"
        >‹</button>

        <button
          v-for="p in visiblePages"
          :key="p"
          class="dt-page-btn"
          :class="{ 'is-active': p === current }"
          type="button"
          @click="emit('page', p)"
        >{{ p }}</button>

        <button
          class="dt-page-btn"
          type="button"
          title="Trang sau"
          :disabled="current >= last"
          @click="emit('page', current + 1)"
        >›</button>

        <button
          class="dt-page-btn"
          type="button"
          title="Trang cuối"
          :disabled="current >= last"
          @click="emit('page', last)"
        >»</button>
      </div>
    </div>
  </div>
</template>
