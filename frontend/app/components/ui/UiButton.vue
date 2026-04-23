<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ inheritAttrs: true })

const props = withDefaults(defineProps<{
  type?: 'button' | 'submit' | 'reset'
  to?: string | Record<string, any> | null
  href?: string | null
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
  loading?: boolean
  block?: boolean
}>(), {
  type: 'button',
  to: null,
  href: null,
  variant: 'primary',
  size: 'md',
  disabled: false,
  loading: false,
  block: false,
})

const variantClasses: Record<string, string> = {
  primary: 'border border-transparent bg-primary text-white shadow-lg shadow-primary/20 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-xl focus:ring-primary/30',
  secondary: 'border border-surface-dim/60 bg-white text-on-surface shadow-sm hover:-translate-y-0.5 hover:bg-surface-low hover:shadow-md focus:ring-surface-tint/15',
  ghost: 'border border-surface-dim/60 bg-surface-lowest text-on-surface shadow-sm hover:-translate-y-0.5 hover:bg-surface-low hover:shadow-md focus:ring-primary/10',
  danger: 'border border-transparent bg-error text-white shadow-lg shadow-error/20 hover:-translate-y-0.5 hover:brightness-95 hover:shadow-xl focus:ring-error/30',
}

const sizeClasses: Record<string, string> = {
  sm: 'min-h-9 px-3.5 py-2 text-xs',
  md: 'min-h-11 px-5 py-2.5 text-sm',
  lg: 'min-h-12 px-6 py-3 text-sm',
}

const isButtonDisabled = computed(() => props.disabled || props.loading)

const buttonClass = computed(() => [
  'inline-flex items-center justify-center gap-2 rounded-xl font-bold leading-none transition-all duration-200 focus:outline-none focus:ring-4 cursor-pointer select-none whitespace-nowrap no-underline',
  variantClasses[props.variant],
  sizeClasses[props.size],
  props.block && 'w-full',
  isButtonDisabled.value && 'pointer-events-none opacity-60',
])
</script>

<template>
  <NuxtLink
    v-if="props.to"
    :to="props.to"
    :class="buttonClass"
    :aria-disabled="isButtonDisabled ? 'true' : undefined"
  >
    <span v-if="props.loading" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
    <slot />
  </NuxtLink>

  <a
    v-else-if="props.href"
    :href="props.href"
    target="_blank"
    rel="noopener noreferrer"
    :class="buttonClass"
    :aria-disabled="isButtonDisabled ? 'true' : undefined"
  >
    <span v-if="props.loading" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
    <slot />
  </a>

  <button
    v-else
    :type="props.type"
    :disabled="isButtonDisabled"
    :class="buttonClass"
  >
    <span v-if="props.loading" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
    <slot />
  </button>
</template>
