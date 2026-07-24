<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ name: 'UiKpiCards' })

interface KpiItem {
  label: string
  value: string | number
  subText?: string
  icon?: string
  color?: 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'purple'
  trend?: {
    value: string
    isUp: boolean
  }
}

const props = defineProps<{
  items: KpiItem[]
}>()

const gridClass = computed(() => {
  const n = props.items.length
  if (n <= 2) return 'kpi-grid kpi-grid--2'
  if (n === 3) return 'kpi-grid kpi-grid--3'
  return 'kpi-grid kpi-grid--4'
})

function getColorStyles(color?: string) {
  const map: Record<string, { text: string; bg: string; border: string }> = {
    primary: {
      text: 'var(--color-primary, var(--theme-primary, #1d9e75))',
      bg: 'rgba(29, 158, 117, 0.08)',
      border: 'rgba(29, 158, 117, 0.18)',
    },
    success: {
      text: 'var(--color-success, #10b981)',
      bg: 'rgba(16, 185, 129, 0.08)',
      border: 'rgba(16, 185, 129, 0.18)',
    },
    warning: {
      text: 'var(--color-warning, #f59e0b)',
      bg: 'rgba(245, 158, 11, 0.08)',
      border: 'rgba(245, 158, 11, 0.18)',
    },
    danger: {
      text: 'var(--color-danger, #ef4444)',
      bg: 'rgba(239, 68, 68, 0.08)',
      border: 'rgba(239, 68, 68, 0.18)',
    },
    info: {
      text: 'var(--color-info, #0ea5e9)',
      bg: 'rgba(14, 165, 233, 0.08)',
      border: 'rgba(14, 165, 233, 0.18)',
    },
    purple: {
      text: '#8b5cf6',
      bg: 'rgba(139, 92, 246, 0.08)',
      border: 'rgba(139, 92, 246, 0.18)',
    },
  }
  return map[color || 'primary'] || map.primary
}
</script>

<template>
  <div :class="gridClass">
    <div
      v-for="stat in items"
      :key="stat.label"
      class="kpi-card"
      :style="{
        '--kpi-color': getColorStyles(stat.color).text,
        '--kpi-bg': getColorStyles(stat.color).bg,
        '--kpi-border': getColorStyles(stat.color).border,
      }"
    >
      <div class="kpi-top">
        <span class="kpi-label">{{ stat.label }}</span>
        <div v-if="stat.icon" class="kpi-icon" aria-hidden="true">
          <i :class="['pi', stat.icon]" />
        </div>
      </div>

      <div class="kpi-body">
        <strong class="kpi-value">{{ stat.value }}</strong>
        <div v-if="stat.subText || stat.trend" class="kpi-meta">
          <span
            v-if="stat.trend"
            class="kpi-trend"
            :class="stat.trend.isUp ? 'is-up' : 'is-down'"
          >
            <i :class="['pi', stat.trend.isUp ? 'pi-arrow-up-right' : 'pi-arrow-down-left']" />
            {{ stat.trend.value }}
          </span>
          <span v-if="stat.subText" class="kpi-sub">{{ stat.subText }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kpi-grid {
  display: grid;
  gap: 10px;
  width: 100%;
}

.kpi-grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.kpi-grid--3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.kpi-grid--4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }

@media (min-width: 768px) {
  .kpi-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .kpi-grid--4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

.kpi-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-height: 0;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--kpi-border);
  background: var(--kpi-bg);
  overflow: hidden;
  transition: border-color 160ms ease, transform 160ms ease;
}

.kpi-card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: var(--kpi-color);
  opacity: 0.85;
}

.kpi-card:hover {
  border-color: color-mix(in srgb, var(--kpi-color) 55%, transparent);
  transform: translateY(-1px);
}

.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.kpi-label {
  font-size: 0.68rem;
  font-weight: 750;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--kpi-color);
  line-height: 1.2;
}

.kpi-icon {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: var(--surface-strong, var(--color-surface-strong, #fff));
  color: var(--kpi-color);
  font-size: 0.75rem;
  flex-shrink: 0;
}

.kpi-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.kpi-value {
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.15;
  color: var(--text, var(--color-text, #1f2421));
}

.kpi-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.kpi-sub {
  font-size: 0.72rem;
  color: var(--muted, var(--color-text-muted, #6b7c73));
}

.kpi-trend {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
}

.kpi-trend.is-up {
  background: rgba(16, 185, 129, 0.12);
  color: var(--color-success, #10b981);
}

.kpi-trend.is-down {
  background: rgba(239, 68, 68, 0.12);
  color: var(--color-danger, #ef4444);
}

:global(.dark) .kpi-card,
:global([data-theme='dark']) .kpi-card {
  background: color-mix(in srgb, var(--kpi-color) 10%, var(--surface-strong, #0f1f17));
  border-color: color-mix(in srgb, var(--kpi-color) 22%, transparent);
}

:global(.dark) .kpi-icon,
:global([data-theme='dark']) .kpi-icon {
  background: rgba(255, 255, 255, 0.06);
}

:global(.dark) .kpi-value,
:global([data-theme='dark']) .kpi-value {
  color: var(--text, #f1f5f9);
}

:global(.dark) .kpi-sub,
:global([data-theme='dark']) .kpi-sub {
  color: var(--muted, #94a3b8);
}
</style>
