<script setup lang="ts">
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

defineProps<{
  items: KpiItem[]
}>()

function getColorStyles(color?: string) {
  const map: Record<string, { text: string; bg: string; border: string }> = {
    primary: {
      text: 'var(--color-primary, #1d9e75)',
      bg: 'rgba(29, 158, 117, 0.06)',
      border: 'rgba(29, 158, 117, 0.15)'
    },
    success: {
      text: 'var(--color-success, #10b981)',
      bg: 'rgba(16, 185, 129, 0.06)',
      border: 'rgba(16, 185, 129, 0.15)'
    },
    warning: {
      text: 'var(--color-warning, #f59e0b)',
      bg: 'rgba(245, 158, 11, 0.06)',
      border: 'rgba(245, 158, 11, 0.15)'
    },
    danger: {
      text: 'var(--color-danger, #ef4444)',
      bg: 'rgba(239, 68, 68, 0.06)',
      border: 'rgba(239, 68, 68, 0.15)'
    },
    info: {
      text: 'var(--color-info, #0ea5e9)',
      bg: 'rgba(14, 165, 233, 0.06)',
      border: 'rgba(14, 165, 233, 0.15)'
    },
    purple: {
      text: '#8b5cf6',
      bg: 'rgba(139, 92, 246, 0.06)',
      border: 'rgba(139, 92, 246, 0.15)'
    }
  }
  
  return map[color || 'primary'] || map.primary
}
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
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
      <div class="kpi-bg-shape" />
      
      <div class="kpi-header">
        <span class="kpi-label">{{ stat.label }}</span>
        <div v-if="stat.icon" class="kpi-icon-wrap">
          <i :class="['pi', stat.icon]" />
        </div>
      </div>
      
      <div class="kpi-content">
        <strong class="kpi-value">{{ stat.value }}</strong>
        
        <div v-if="stat.subText || stat.trend" class="kpi-meta">
          <span 
            v-if="stat.trend" 
            :class="['kpi-trend', stat.trend.isUp ? 'is-up' : 'is-down']"
          >
            <i :class="['pi', stat.trend.isUp ? 'pi-arrow-up-right' : 'pi-arrow-down-left']" />
            {{ stat.trend.value }}
          </span>
          <span v-if="stat.subText" class="kpi-subtext">{{ stat.subText }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kpi-card {
  background: linear-gradient(135deg, var(--kpi-bg) 0%, rgba(255, 255, 255, 0.4) 100%);
  border: 1px solid var(--kpi-border);
  border-radius: 20px;
  padding: 22px 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 16px;
  min-height: 128px;
  transition: all 300ms cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.015);
}

.kpi-card:hover {
  transform: translateY(-4px);
  border-color: var(--kpi-color);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.05), 0 4px 8px rgba(0, 0, 0, 0.02);
}

.kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: var(--kpi-color);
  opacity: 0.8;
  transition: transform 300ms;
}

.kpi-bg-shape {
  position: absolute;
  bottom: -24px;
  right: -24px;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: var(--kpi-color);
  filter: blur(45px);
  opacity: 0.12;
  pointer-events: none;
  transition: transform 500ms;
}

.kpi-card:hover .kpi-bg-shape {
  transform: scale(1.3);
  opacity: 0.18;
}

.kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
  position: relative;
  z-index: 2;
}

.kpi-label {
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--kpi-color);
}

.kpi-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: var(--color-surface-strong, #fff);
  color: var(--kpi-color);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
  transition: transform 300ms;
}

.kpi-card:hover .kpi-icon-wrap {
  transform: scale(1.1) rotate(5deg);
}

.kpi-content {
  display: flex;
  flex-direction: column;
  gap: 6px;
  position: relative;
  z-index: 2;
}

.kpi-value {
  font-size: 2.1rem;
  font-weight: 900;
  line-height: 1.1;
  letter-spacing: -0.03em;
  color: var(--color-text, #1f2421);
}

.kpi-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.kpi-trend {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
}

.kpi-trend.is-up {
  background: rgba(16, 185, 129, 0.1);
  color: var(--color-success, #10b981);
}

.kpi-trend.is-down {
  background: rgba(239, 68, 68, 0.1);
  color: var(--color-danger, #ef4444);
}

.kpi-subtext {
  font-size: 0.75rem;
  color: var(--color-text-secondary, #4a6059);
}

/* Dark mode overrides */
:global([data-theme="dark"]) .kpi-card {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.01) 0%, rgba(255, 255, 255, 0.03) 100%);
  border-color: rgba(255, 255, 255, 0.05);
  box-shadow: none;
}

:global([data-theme="dark"]) .kpi-card:hover {
  border-color: var(--kpi-color);
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.06) 100%);
}

:global([data-theme="dark"]) .kpi-icon-wrap {
  background: rgba(255, 255, 255, 0.04);
  color: var(--kpi-color);
  box-shadow: none;
}

:global([data-theme="dark"]) .kpi-value {
  color: rgba(255, 255, 255, 0.95);
}

:global([data-theme="dark"]) .kpi-subtext {
  color: rgba(255, 255, 255, 0.55);
}
</style>

