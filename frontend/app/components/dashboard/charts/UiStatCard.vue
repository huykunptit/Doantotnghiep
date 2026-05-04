<script setup lang="ts">
import UiSparkline from './UiSparkline.vue'

withDefaults(
  defineProps<{
    label: string
    value: string | number
    delta?: number | null
    deltaLabel?: string
    icon?: string
    iconBg?: string
    iconColor?: string
    sparkline?: number[]
    sparkColor?: string
    loading?: boolean
  }>(),
  {
    delta: null,
    deltaLabel: 'so với kỳ trước',
    icon: '',
    iconBg: 'rgba(47,122,69,0.1)',
    iconColor: '#2f7a45',
    sparkline: () => [],
    sparkColor: '#2f7a45',
    loading: false,
  },
)
</script>

<template>
  <article class="stat-card-pro">
    <div v-if="loading" class="stat-card-skeleton">
      <div class="h-3 w-24 rounded bg-surface-high animate-pulse" />
      <div class="h-9 w-32 rounded bg-surface-high animate-pulse mt-3" />
      <div class="h-9 w-full rounded bg-surface-high animate-pulse mt-4" />
    </div>

    <template v-else>
      <header class="stat-card-head">
        <div>
          <p class="stat-card-label">{{ label }}</p>
          <p class="stat-card-value">{{ value }}</p>
        </div>
        <div v-if="icon" class="stat-card-icon" :style="{ background: iconBg, color: iconColor }">
          <span class="material-symbols-outlined">{{ icon }}</span>
        </div>
      </header>

      <div v-if="sparkline?.length" class="stat-card-spark">
        <UiSparkline :values="sparkline" :color="sparkColor" :height="40" />
      </div>

      <footer v-if="delta !== null || deltaLabel" class="stat-card-foot">
        <span
          v-if="delta !== null"
          class="stat-delta"
          :class="{
            'is-up': delta > 0,
            'is-down': delta < 0,
            'is-flat': delta === 0,
          }"
        >
          <span class="material-symbols-outlined">
            {{ delta > 0 ? 'trending_up' : delta < 0 ? 'trending_down' : 'trending_flat' }}
          </span>
          {{ delta > 0 ? '+' : '' }}{{ delta }}%
        </span>
        <span class="stat-delta-label">{{ deltaLabel }}</span>
      </footer>
    </template>
  </article>
</template>

<style scoped>
.stat-card-pro {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 20px 22px;
  background: var(--surface-lowest, #fff);
  border: 1px solid var(--surface-dim, #e5e7eb);
  border-radius: 20px;
  transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
}
.stat-card-pro:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 30px -16px rgba(17, 17, 17, 0.16);
  border-color: rgba(47, 122, 69, 0.3);
}

.stat-card-skeleton { display: flex; flex-direction: column; }

.stat-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.stat-card-label {
  margin: 0 0 6px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--on-surface-variant, #5f675f);
}
.stat-card-value {
  margin: 0;
  font-size: 1.7rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--on-surface, #111);
  font-variant-numeric: tabular-nums;
}
.stat-card-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  flex-shrink: 0;
}
.stat-card-icon .material-symbols-outlined { font-size: 22px; }

.stat-card-spark { margin: 0 -4px; }

.stat-card-foot {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.78rem;
  color: var(--on-surface-variant, #5f675f);
  margin-top: 2px;
}
.stat-delta {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 700;
  background: rgba(17, 17, 17, 0.05);
  color: var(--on-surface-variant);
}
.stat-delta .material-symbols-outlined { font-size: 14px; }
.stat-delta.is-up { background: rgba(22, 163, 74, 0.1); color: #16a34a; }
.stat-delta.is-down { background: rgba(220, 38, 38, 0.1); color: #dc2626; }
.stat-delta-label { color: var(--on-surface-variant); }
</style>
