<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    values: number[]
    labels: string[]
    color?: string
    height?: number
    formatValue?: (n: number) => string
  }>(),
  {
    color: '#2f7a45',
    height: 200,
    formatValue: (n: number) => n.toLocaleString('vi-VN'),
  },
)

const W = 600
const PAD = { top: 18, right: 12, bottom: 28, left: 40 }

const innerW = computed(() => W - PAD.left - PAD.right)
const innerH = computed(() => props.height - PAD.top - PAD.bottom)
const yMax = computed(() => Math.max(...props.values, 1))

const bars = computed(() => {
  const n = props.values.length
  const slot = innerW.value / Math.max(n, 1)
  const barW = Math.min(slot * 0.55, 44)
  return props.values.map((v, i) => {
    const cx = PAD.left + i * slot + slot / 2
    const h = (v / yMax.value) * innerH.value
    return {
      x: cx - barW / 2,
      y: PAD.top + innerH.value - h,
      w: barW,
      h,
      v,
      label: props.labels[i] ?? '',
    }
  })
})

const yTicks = computed(() => [0, yMax.value * 0.5, yMax.value].map((v) => Math.round(v)))
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${height}`" preserveAspectRatio="none" class="w-full block">
    <!-- Y grid -->
    <g>
      <g v-for="(tick, i) in yTicks" :key="i">
        <line
          :x1="PAD.left"
          :x2="PAD.left + innerW"
          :y1="PAD.top + innerH - (tick / yMax) * innerH"
          :y2="PAD.top + innerH - (tick / yMax) * innerH"
          stroke="rgba(17,17,17,0.06)"
          stroke-dasharray="3 3"
        />
        <text
          :x="PAD.left - 8"
          :y="PAD.top + innerH - (tick / yMax) * innerH + 4"
          text-anchor="end"
          class="axis-label"
        >
          {{ formatValue(tick) }}
        </text>
      </g>
    </g>

    <!-- Bars -->
    <g>
      <g v-for="(bar, i) in bars" :key="i" class="bar-group">
        <rect
          :x="bar.x"
          :y="bar.y"
          :width="bar.w"
          :height="bar.h"
          :fill="color"
          rx="6"
        />
        <text
          :x="bar.x + bar.w / 2"
          :y="bar.y - 6"
          text-anchor="middle"
          class="bar-value"
        >
          {{ bar.v > 0 ? formatValue(bar.v) : '' }}
        </text>
        <text
          :x="bar.x + bar.w / 2"
          :y="height - 8"
          text-anchor="middle"
          class="axis-label"
        >
          {{ bar.label }}
        </text>
      </g>
    </g>
  </svg>
</template>

<style scoped>
.axis-label {
  font-size: 11px;
  fill: var(--on-surface-variant, #5f675f);
  font-family: 'Manrope', sans-serif;
  font-weight: 600;
}
.bar-value {
  font-size: 11px;
  fill: var(--on-surface, #111);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.bar-group rect { transition: opacity 0.2s; }
.bar-group:hover rect { opacity: 0.8; }
</style>
