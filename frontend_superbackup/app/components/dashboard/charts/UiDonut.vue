<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Tooltip,
  Legend,
  ArcElement
} from 'chart.js'

ChartJS.register(
  Tooltip,
  Legend,
  ArcElement
)

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
    thickness: 24,
    centerLabel: '',
    centerValue: '',
  }
)

const PALETTE = ['#0F6E8C', '#1D9E75', '#5DCAA5', '#d97706', '#dc2626', '#7c3aed']

const total = computed(() => props.segments.reduce((sum, s) => sum + s.value, 0))
const arcs = computed(() => {
  const t = total.value
  return props.segments.map((seg, i) => {
    const fraction = t > 0 ? seg.value / t : 0
    return {
      label: seg.label,
      value: seg.value,
      color: seg.color || PALETTE[i % PALETTE.length],
      fraction,
    }
  })
})

const chartData = computed(() => {
  return {
    labels: props.segments.map(s => s.label),
    datasets: [{
      data: props.segments.map(s => s.value),
      backgroundColor: props.segments.map((s, i) => s.color || PALETTE[i % PALETTE.length]),
      borderWidth: 2,
      borderColor: '#ffffff',
      hoverOffset: 4,
    }]
  }
})

const chartOptions = computed(() => {
  return {
    responsive: true,
    maintainAspectRatio: false,
    cutout: props.size ? `${((props.size - props.thickness * 2) / props.size) * 100}%` : '75%',
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        backgroundColor: 'rgba(17, 17, 17, 0.95)',
        padding: 10,
        titleFont: {
          family: 'Be Vietnam Pro, sans-serif',
          size: 11,
          weight: '700'
        },
        bodyFont: {
          family: 'Be Vietnam Pro, sans-serif',
          size: 12
        },
        borderRadius: 10,
        callbacks: {
          label: (context: any) => {
            const val = context.raw
            const t = total.value
            const pct = t > 0 ? Math.round((val / t) * 100) : 0
            return ` ${context.label}: ${val.toLocaleString('vi-VN')} (${pct}%)`
          }
        }
      }
    }
  }
})
</script>

<template>
  <div class="donut-wrap">
    <div class="donut-svg-wrap" :style="{ width: `${size}px`, height: `${size}px` }">
      <ClientOnly>
        <Doughnut :data="chartData" :options="chartOptions" />
      </ClientOnly>
      <div class="donut-center">
        <p v-if="centerLabel" class="donut-center-label">{{ centerLabel }}</p>
        <p class="donut-center-value">{{ centerValue || total }}</p>
      </div>
    </div>

    <ul class="donut-legend">
      <li v-for="(arc, i) in arcs" :key="i">
        <span class="donut-dot" :style="{ background: arc.color }" />
        <span class="donut-label">{{ arc.label }}</span>
        <span class="donut-value">{{ arc.value.toLocaleString('vi-VN') }}</span>
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

.donut-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  pointer-events: none;
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
  margin: 2px 0 0;
  font-size: 1.5rem;
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
