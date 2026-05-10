<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    segments: { label: string; value: number; color?: string }[]
    size?: number
    thickness?: number
    centerLabel?: string
    centerValue?: string | number
  }>(),
  {
    size: 180,
    thickness: 28,
    centerLabel: '',
    centerValue: '',
  },
)

const PALETTE = ['var(--green)', 'var(--green)', '#d97706', '#dc2626', 'var(--green)', '#7c3aed']

const total = computed(() => props.segments.reduce((sum, s) => sum + s.value, 0))

const arcs = computed(() => {
  const cx = props.size / 2
  const cy = props.size / 2
  const r = (props.size - props.thickness) / 2
  const t = total.value
  if (t === 0) return []

  let angle = -Math.PI / 2
  return props.segments.map((seg, i) => {
    const fraction = seg.value / t
    const sweep = fraction * Math.PI * 2
    const start = angle
    const end = angle + sweep
    angle = end

    const x1 = cx + r * Math.cos(start)
    const y1 = cy + r * Math.sin(start)
    const x2 = cx + r * Math.cos(end)
    const y2 = cy + r * Math.sin(end)
    const large = sweep > Math.PI ? 1 : 0
    const path = `M${x1},${y1} A${r},${r} 0 ${large} 1 ${x2},${y2}`

    return {
      label: seg.label,
      value: seg.value,
      color: seg.color || PALETTE[i % PALETTE.length],
      fraction,
      path,
    }
  })
})
</script>

<template>
  <div class="donut-wrap">
    <div class="donut-svg-wrap" :style="{ width: `${size}px`, height: `${size}px` }">
      <svg :viewBox="`0 0 ${size} ${size}`">
        <!-- Background ring -->
        <circle
          :cx="size / 2"
          :cy="size / 2"
          :r="(size - thickness) / 2"
          fill="none"
          stroke="rgba(17,17,17,0.06)"
          :stroke-width="thickness"
        />
        <!-- Segments -->
        <path
          v-for="(arc, i) in arcs"
          :key="i"
          :d="arc.path"
          fill="none"
          :stroke="arc.color"
          :stroke-width="thickness"
          stroke-linecap="butt"
        />
      </svg>
      <div class="donut-center">
        <p v-if="centerLabel" class="donut-center-label">{{ centerLabel }}</p>
        <p class="donut-center-value">{{ centerValue || total }}</p>
      </div>
    </div>

    <ul class="donut-legend">
      <li v-for="(arc, i) in arcs" :key="i">
        <span class="donut-dot" :style="{ background: arc.color }" />
        <span class="donut-label">{{ arc.label }}</span>
        <span class="donut-value">{{ arc.value }}</span>
        <span class="donut-pct">{{ Math.round(arc.fraction * 100) }}%</span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.donut-wrap {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 28px;
}
.donut-svg-wrap { position: relative; flex-shrink: 0; }
.donut-svg-wrap svg { display: block; transform: rotate(0deg); }

.donut-center {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  text-align: center;
}
.donut-center-label {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--on-surface-variant, #5f675f);
}
.donut-center-value {
  margin: 4px 0 0;
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--on-surface, #111);
}

.donut-legend {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
  flex: 1;
  min-width: 180px;
}
.donut-legend li {
  display: grid;
  grid-template-columns: 14px 1fr auto auto;
  align-items: center;
  gap: 10px;
  font-size: 0.86rem;
}
.donut-dot { width: 10px; height: 10px; border-radius: 3px; }
.donut-label { color: var(--on-surface, #111); font-weight: 600; }
.donut-value {
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.donut-pct {
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--on-surface-variant, #5f675f);
  background: rgba(17, 17, 17, 0.05);
  padding: 2px 8px;
  border-radius: 999px;
}
</style>
