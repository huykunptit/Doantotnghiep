<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  description?: string
}>(), {
  title: 'Đã có lỗi xảy ra',
  description: 'Vui lòng thử lại sau hoặc kiểm tra lại dữ liệu.',
})
</script>

<template>
  <div class="rounded-3xl border border-error/20 bg-error-container p-6 text-error">
    <div class="flex items-start gap-3">
      <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-surface-lowest text-error">!</div>
      <div>
        <h3 class="text-sm font-semibold">{{ title }}</h3>
        <p class="mt-1 text-sm text-error">{{ description }}</p>
        <div v-if="$slots.default" class="mt-3">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

