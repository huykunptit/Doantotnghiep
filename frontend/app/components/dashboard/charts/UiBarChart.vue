<script setup lang="ts">
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale
} from 'chart.js'

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale
)

const props = withDefaults(
  defineProps<{
    values: number[]
    labels: string[]
    color?: string
    height?: number
    formatValue?: (n: number) => string
  }>(),
  {
    color: '#1D9E75', // Secondary color mid
    height: 200,
    formatValue: (n: number) => n.toLocaleString('vi-VN'),
  }
)

const chartData = computed(() => {
  return {
    labels: props.labels,
    datasets: [{
      data: props.values,
      backgroundColor: props.color,
      borderRadius: 6,
      borderSkipped: false,
      maxBarThickness: 36,
    }]
  }
})

const chartOptions = computed(() => {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        backgroundColor: 'rgba(17, 17, 17, 0.95)',
        padding: 10,
        titleFont: {
          family: 'Inter, sans-serif',
          size: 11,
          weight: '700'
        },
        bodyFont: {
          family: 'Inter, sans-serif',
          size: 12
        },
        borderRadius: 10,
        callbacks: {
          label: (context: any) => {
            const val = context.raw
            return ` ${props.formatValue(val)}`
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
          callback: (value: any) => props.formatValue(value)
        }
      }
    }
  }
})
</script>

<template>
  <div class="chart-container" :style="{ height: `${height}px` }">
    <ClientOnly>
      <Bar :data="chartData" :options="chartOptions" />
    </ClientOnly>
  </div>
</template>

<style scoped>
.chart-container {
  width: 100%;
  position: relative;
}
</style>
