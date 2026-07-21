<script setup lang="ts">
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  RadialLinearScale,
  Filler,
  Tooltip,
  Legend,
} from 'chart.js'
import { Line, Doughnut, PolarArea, Radar, Bar } from 'vue-chartjs'
import type { ChartData, ChartOptions } from 'chart.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  RadialLinearScale,
  Filler,
  Tooltip,
  Legend,
)

const props = withDefaults(defineProps<{
  type: 'line' | 'doughnut' | 'polarArea' | 'radar' | 'bar'
  data: ChartData
  options?: ChartOptions
  height?: string
}>(), {
  height: '240px',
})

const chartMap = {
  line: Line,
  doughnut: Doughnut,
  polarArea: PolarArea,
  radar: Radar,
  bar: Bar,
}

const component = computed(() => chartMap[props.type])
</script>

<template>
  <div class="ui-chart" :style="{ height }">
    <component :is="component" :data="data" :options="options" />
  </div>
</template>

<style scoped>
.ui-chart {
  position: relative;
  width: 100%;
}
</style>
