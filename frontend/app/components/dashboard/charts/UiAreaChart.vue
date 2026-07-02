<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Filler
} from 'chart.js'

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Filler
)

const props = withDefaults(
  defineProps<{
    series: { name: string; values: number[]; color?: string }[]
    labels: string[]
    height?: number
    formatY?: (n: number) => string
  }>(),
  {
    height: 240,
    formatY: (n: number) => n.toLocaleString('vi-VN'),
  }
)

const chartData = computed(() => {
  return {
    labels: props.labels,
    datasets: props.series.map((s, idx) => {
      const color = s.color || ['#0F6E8C', '#1D9E75', '#d97706'][idx] || '#0F6E8C'
      return {
        label: s.name,
        data: s.values,
        borderColor: color,
        backgroundColor: (context: any) => {
          const chart = context.chart
          const { ctx, chartArea } = chart
          if (!chartArea) return null
          const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
          gradient.addColorStop(0, color + '38') // 22% opacity hex
          gradient.addColorStop(1, color + '00')
          return gradient
        },
        fill: true,
        tension: 0.35,
        borderWidth: 2,
        pointBackgroundColor: color,
        pointBorderColor: '#ffffff',
        pointBorderWidth: 1.5,
        pointHoverRadius: 6,
        pointRadius: 4,
      }
    })
  }
})

const chartOptions = computed(() => {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: props.series.length > 1,
        position: 'bottom' as const,
        labels: {
          boxWidth: 10,
          boxHeight: 10,
          usePointStyle: true,
          padding: 16,
          font: {
            family: 'Inter, sans-serif',
            size: 11,
            weight: '600'
          }
        }
      },
      tooltip: {
        backgroundColor: 'rgba(17, 17, 17, 0.95)',
        padding: 12,
        titleFont: {
          family: 'Inter, sans-serif',
          size: 11,
          weight: '700'
        },
        bodyFont: {
          family: 'Inter, sans-serif',
          size: 12
        },
        borderRadius: 12,
        boxPadding: 6,
        callbacks: {
          label: (context: any) => {
            const val = context.raw
            return ` ${context.dataset.label}: ${props.formatY(val)}`
          }
        }
      }
    },
    scales: {
      x: {
        grid: {
          display: false
        },
        ticks: {
          font: {
            family: 'Inter, sans-serif',
            size: 10,
            weight: '600'
          },
          color: '#5f675f'
        }
      },
      y: {
        border: {
          dash: [4, 4]
        },
        grid: {
          color: 'rgba(17, 17, 17, 0.05)'
        },
        ticks: {
          font: {
            family: 'Inter, sans-serif',
            size: 10,
            weight: '600'
          },
          color: '#5f675f',
          callback: (value: any) => props.formatY(value)
        }
      }
    }
  }
})
</script>

<template>
  <div class="chart-container" :style="{ height: `${height}px` }">
    <ClientOnly>
      <Line :data="chartData" :options="chartOptions" />
    </ClientOnly>
  </div>
</template>

<style scoped>
.chart-container {
  width: 100%;
  position: relative;
}
</style>
