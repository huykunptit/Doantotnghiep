<script setup lang="ts">
const props = withDefaults(defineProps<{
  content?: string | null
  emptyText?: string
  compact?: boolean
}>(), {
  content: '',
  emptyText: 'Chưa có nội dung.',
  compact: false,
})

const hasContent = computed(() => Boolean(props.content && props.content.trim()))
</script>

<template>
  <div
    class="rich-content"
    :class="{ 'is-compact': compact, 'is-empty': !hasContent }"
    v-html="hasContent ? content : `<p>${emptyText}</p>`"
  />
</template>

