<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    values: number[]
    color?: string
    height?: number
    fill?: boolean
  }>(),
  {
    color: 'var(--green)',
    height: 36,
    fill: true,
  },
)

const W = 120
const H = computed(() => props.height)

// Sanitize color into a safe SVG/HTML id fragment.
// Without this, `var(--green)` would produce an id like
// `spark-fill-var(--green)` which breaks the `url(#...)` reference and
// the area falls back to solid black fill.
const gradId = computed(() =>
  `spark-fill-${props.color.replace(/[^a-zA-Z0-9_-]/g, '')}`,
)

const points = computed(() => {
  const v = props.values
  if (!v.length) return ''
  const max = Math.max(...v, 1)
  const min = Math.min(...v, 0)
  const range = max - min || 1
  const stepX = W / Math.max(v.length - 1, 1)
  return v
    .map((y, i) => {
      const px = i * stepX
      const py = H.value - 4 - ((y - min) / range) * (H.value - 8)
      return `${px.toFixed(1)},${py.toFixed(1)}`
    })
    .join(' ')
})

const areaPath = computed(() => {
  if (!points.value) return ''
  return `M0,${H.value} L${points.value.replaceAll(' ', ' L')} L${W},${H.value} Z`
})
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" class="w-full" :style="{ height: `${H}px` }">
    <defs>
      <linearGradient :id="gradId" x1="0" x2="0" y1="0" y2="1">
        <stop offset="0%" :stop-color="color" stop-opacity="0.18" />
        <stop offset="100%" :stop-color="color" stop-opacity="0" />
      </linearGradient>
    </defs>
    <path
      v-if="fill && areaPath"
      :d="areaPath"
      :fill="`url(#${gradId})`"
    />
    <polyline
      :points="points"
      fill="none"
      :stroke="color"
      stroke-width="1.6"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
</template>
