<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ inheritAttrs: true })

const props = withDefaults(defineProps<{
  type?: 'button' | 'submit' | 'reset'
  to?: string | Record<string, any> | null
  href?: string | null
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'link'
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
  primary:   'ui-btn--primary',
  secondary: 'ui-btn--secondary',
  ghost:     'ui-btn--ghost',
  danger:    'ui-btn--danger',
  link:      'ui-btn--link',
}

const sizeClasses: Record<string, string> = {
  sm: 'ui-btn--sm',
  md: 'ui-btn--md',
  lg: 'ui-btn--lg',
}

const isButtonDisabled = computed(() => props.disabled || props.loading)

const buttonClass = computed(() => [
  'ui-btn',
  variantClasses[props.variant],
  sizeClasses[props.size],
  props.block && 'ui-btn--block',
  isButtonDisabled.value && 'ui-btn--disabled',
])
</script>

<template>
  <NuxtLink
    v-if="props.to"
    :to="props.to"
    :class="buttonClass"
    :aria-disabled="isButtonDisabled ? 'true' : undefined"
  >
    <i v-if="props.loading" class="pi pi-spin pi-spinner ui-btn-spinner" style="font-size:0.9375rem" />
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
    <i v-if="props.loading" class="pi pi-spin pi-spinner ui-btn-spinner" style="font-size:0.9375rem" />
    <slot />
  </a>

  <button
    v-else
    :type="props.type"
    :disabled="isButtonDisabled"
    :class="buttonClass"
  >
    <i v-if="props.loading" class="pi pi-spin pi-spinner ui-btn-spinner" style="font-size:0.9375rem" />
    <slot />
  </button>
</template>

<style scoped>
/* ── Base ── */
.ui-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border-radius: 8px;
  font-family: inherit;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  text-decoration: none;
  cursor: pointer;
  transition:
    background 150ms ease,
    border-color 150ms ease,
    color 150ms ease,
    transform 150ms ease,
    box-shadow 150ms ease;
  outline: none;
  border: 1px solid transparent;
  user-select: none;
}

.ui-btn:focus-visible {
  outline: 2px solid var(--green);
  outline-offset: 2px;
}

/* ── Sizes ── */
.ui-btn--sm {
  height: 32px;
  padding: 0 14px;
  font-size: 0.8125rem;
}

.ui-btn--md {
  height: 40px;
  padding: 0 18px;
  font-size: 0.875rem;
}

.ui-btn--lg {
  height: 48px;
  padding: 0 24px;
  font-size: 0.9375rem;
}

/* ── Variants ── */
.ui-btn--primary {
  background: var(--green);
  color: #fff;
  box-shadow: 0 1px 3px rgba(var(--primary-rgb), 0.2);
}

.ui-btn--primary:hover {
  background: var(--green-deep);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
}

.ui-btn--primary:active {
  transform: scale(0.97);
  box-shadow: none;
}

.ui-btn--secondary {
  background: var(--green-soft);
  color: var(--green-deep);
  border-color: rgba(var(--primary-rgb), 0.2);
}

.ui-btn--secondary:hover {
  background: rgba(var(--primary-rgb), 0.12);
  border-color: rgba(var(--primary-rgb), 0.3);
  transform: translateY(-1px);
}

.ui-btn--ghost {
  background: transparent;
  color: var(--text);
  border-color: var(--line);
}

.ui-btn--ghost:hover {
  background: rgba(var(--primary-rgb), 0.04);
  border-color: rgba(var(--primary-rgb), 0.2);
  transform: translateY(-1px);
}

.ui-btn--danger {
  background: var(--danger);
  color: #fff;
}

.ui-btn--danger:hover {
  background: #c73b39;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(226, 75, 74, 0.25);
}

.ui-btn--link {
  background: transparent;
  color: var(--green);
  border-color: transparent;
  padding-left: 4px;
  padding-right: 4px;
}

.ui-btn--link:hover {
  color: var(--green-deep);
  text-decoration: underline;
}

/* ── States ── */
.ui-btn--block { width: 100%; }

.ui-btn--disabled {
  pointer-events: none;
  opacity: 0.55;
}

/* ── Spinner ── */
.ui-btn-spinner {
  animation: spin 0.8s linear infinite;
  flex-shrink: 0;
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>
