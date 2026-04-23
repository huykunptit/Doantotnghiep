<script setup lang="ts">

const props = defineProps<{
  value: string
}>()

const variant = computed(() => {
  const normalized = props.value.toLowerCase()
  if (['published', 'paid', 'approved', 'success', 'đã thanh toán', 'hoàn thành'].includes(normalized)) return 'success'
  if (['pending', 'draft', 'chờ thanh toán', 'bản nháp'].includes(normalized)) return 'warning'
  if (['failed', 'cancelled', 'rejected', 'thất bại', 'đã hủy'].includes(normalized)) return 'danger'
  return 'default'
})
</script>

<template>
  <UiBadge :variant="variant">
    {{ value }}
  </UiBadge>
</template>

