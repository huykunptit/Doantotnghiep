<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(
  defineProps<{
    series: { name: string; values: number[]; color?: string }[]
    labels: string[]
    height?: number
    formatY?: (n: number) => string
    formatTooltip?: (n: number, label: string, seriesName: string) => string
  }>(),
  {
    height: 240,
    formatY: (n: number) => n.toLocaleString('vi-VN'),
    formatTooltip: undefined,
  },
)

const W = 600
const H = computed(() => props.height)
const PAD = { top: 12, right: 12, bottom: 28, left: 48 }

const allValues = computed(() => props.series.flatMap((s) => s.values))
const yMax = computed(() => {
  const m = Math.max(...allValues.value, 1)
  const pow = Math.pow(10, Math.floor(Math.log10(m)))
  return Math.ceil(m / pow) * pow
})
const yTicks = computed(() => {
  const max = yMax.value
  return [0, max * 0.25, max * 0.5, max * 0.75, max].map((v) => Math.round(v))
})

const innerW = computed(() => W - PAD.left - PAD.right)
const innerH = computed(() => H.value - PAD.top - PAD.bottom)

const xStep = computed(() => innerW.value / Math.max(props.labels.length - 1, 1))

const seriesPaths = computed(() => {
  return props.series.map((s, sIdx) => {
    const color = s.color || ['#2f7a45', '#1976d2', '#d97706'][sIdx] || '#2f7a45'
    const pts = s.values.map((v, i) => {
      const x = PAD.left + i * xStep.value
      const y = PAD.top + innerH.value - (v / yMax.value) * innerH.value
      return { x, y, v }
    })
    const linePath = pts
      .map((p, i) => (i === 0 ? `M${p.x},${p.y}` : `L${p.x},${p.y}`))
      .join(' ')
    const areaPath = `${linePath} L${PAD.left + innerW.value},${PAD.top + innerH.value} L${PAD.left},${PAD.top + innerH.value} Z`
    return { name: s.name, color, pts, linePath, areaPath, gradId: `area-fill-${sIdx}-${color.replace('#', '')}` }
  })
})

const hover = ref<{ idx: number; x: number; y: number } | null>(null)

function handleMove(e: MouseEvent) {
  const svg = e.currentTarget as SVGSVGElement
  const rect = svg.getBoundingClientRect()
  const xRel = ((e.clientX - rect.left) / rect.width) * W
  const dataX = xRel - PAD.left
  const idx = Math.round(dataX / xStep.value)
  if (idx < 0 || idx >= props.labels.length) {
    hover.value = null
    return
  }
  hover.value = {
    idx,
    x: PAD.left + idx * xStep.value,
    y: e.clientY - rect.top,
  }
}
function handleLeave() {
  hover.value = null
}
</script>

<template>
  <div class="area-chart-wrap">
    <svg
      :viewBox="`0 0 ${W} ${H}`"
      preserveAspectRatio="none"
      class="area-chart-svg"
      @mousemove="handleMove"
      @mouseleave="handleLeave"
    >
      <defs>
        <linearGradient
          v-for="s in seriesPaths"
          :id="s.gradId"
          :key="s.gradId"
          x1="0"
          x2="0"
          y1="0"
          y2="1"
        >
          <stop offset="0%" :stop-color="s.color" stop-opacity="0.22" />
          <stop offset="100%" :stop-color="s.color" stop-opacity="0" />
        </linearGradient>
      </defs>

      <!-- Y grid + labels -->
      <g class="grid">
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
            {{ formatY(tick) }}
          </text>
        </g>
      </g>

      <!-- X labels -->
      <g class="grid">
        <text
          v-for="(label, i) in labels"
          :key="label + i"
          :x="PAD.left + i * xStep"
          :y="H - 8"
          text-anchor="middle"
          class="axis-label"
        >
          {{ label }}
        </text>
      </g>

      <!-- Series -->
      <g v-for="s in seriesPaths" :key="s.name">
        <path :d="s.areaPath" :fill="`url(#${s.gradId})`" />
        <path :d="s.linePath" fill="none" :stroke="s.color" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
        <circle
          v-for="(p, i) in s.pts"
          :key="i"
          :cx="p.x"
          :cy="p.y"
          :r="hover && hover.idx === i ? 5 : 3"
          :fill="s.color"
          stroke="#fff"
          stroke-width="1.5"
        />
      </g>

      <!-- Hover guide -->
      <line
        v-if="hover"
        :x1="hover.x"
        :x2="hover.x"
        :y1="PAD.top"
        :y2="PAD.top + innerH"
        stroke="rgba(17,17,17,0.18)"
        stroke-dasharray="3 3"
      />
    </svg>

    <!-- Tooltip -->
    <div
      v-if="hover"
      class="chart-tooltip"
      :style="{ left: `${(hover.x / W) * 100}%` }"
    >
      <p class="chart-tooltip-label">{{ labels[hover.idx] }}</p>
      <div v-for="s in seriesPaths" :key="s.name" class="chart-tooltip-row">
        <span class="chart-tooltip-dot" :style="{ background: s.color }" />
        <span class="chart-tooltip-name">{{ s.name }}</span>
        <strong>{{ formatY(s.pts[hover.idx].v) }}</strong>
      </div>
    </div>

    <!-- Legend -->
    <div v-if="series.length > 1" class="chart-legend">
      <span v-for="s in seriesPaths" :key="s.name" class="chart-legend-item">
        <span class="chart-legend-dot" :style="{ background: s.color }" />
        {{ s.name }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.area-chart-wrap { position: relative; width: 100%; }
.area-chart-svg { width: 100%; display: block; }
.axis-label {
  font-size: 11px;
  fill: var(--on-surface-variant, #5f675f);
  font-family: 'Manrope', sans-serif;
  font-weight: 600;
}
.chart-tooltip {
  position: absolute;
  top: 0;
  transform: translateX(-50%);
  background: rgba(17, 17, 17, 0.92);
  color: #fff;
  padding: 10px 12px;
  border-radius: 12px;
  font-size: 0.78rem;
  pointer-events: none;
  white-space: nowrap;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
}
.chart-tooltip-label {
  margin: 0 0 6px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.7;
}
.chart-tooltip-row { display: flex; align-items: center; gap: 8px; padding: 2px 0; }
.chart-tooltip-dot { width: 8px; height: 8px; border-radius: 50%; }
.chart-tooltip-name { opacity: 0.8; margin-right: 6px; }
.chart-tooltip-row strong { font-variant-numeric: tabular-nums; }

.chart-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 8px;
  padding: 0 12px;
  font-size: 0.78rem;
  color: var(--on-surface-variant);
}
.chart-legend-item { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
.chart-legend-dot { width: 10px; height: 10px; border-radius: 3px; }
</style>
