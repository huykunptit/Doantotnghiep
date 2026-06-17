<script setup lang="ts">
import { TrendingUp, TrendingDown, Minus } from 'lucide-vue-next'
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
    iconBg: 'rgba(var(--primary-rgb),0.1)',
    iconColor: 'var(--green)',
    sparkline: () => [],
    sparkColor: 'var(--green)',
    loading: false,
  },
)
</script>

<template>
  <article class="stat-card">
    <div v-if="loading" class="stat-skeleton">
      <div class="skel skel--sm" />
      <div class="skel skel--lg" />
      <div class="skel skel--full" />
    </div>

    <template v-else>
      <header class="stat-head">
        <div>
          <p class="stat-label">{{ label }}</p>
          <p class="stat-value">{{ value }}</p>
        </div>
        <div v-if="icon" class="stat-icon" :style="{ background: iconBg, color: iconColor }">
          <SylvaIcon :name="icon" :size="20" :stroke-width="1.75" />
        </div>
      </header>

      <div v-if="sparkline?.length" class="stat-spark">
        <UiSparkline :values="sparkline" :color="sparkColor" :height="40" />
      </div>

      <footer v-if="delta !== null || deltaLabel" class="stat-foot">
        <span
          v-if="delta !== null"
          class="stat-delta"
          :class="{
            'is-up': delta > 0,
            'is-down': delta < 0,
            'is-flat': delta === 0,
          }"
        >
          <TrendingUp v-if="delta > 0" :size="13" :stroke-width="2" />
          <TrendingDown v-else-if="delta < 0" :size="13" :stroke-width="2" />
          <Minus v-else :size="13" :stroke-width="2" />
          {{ delta > 0 ? '+' : '' }}{{ delta }}%
        </span>
        <span class="stat-delta-label">{{ deltaLabel }}</span>
      </footer>
    </template>
  </article>
</template>

<style scoped>
.stat-card {
  position: relative; display: flex; flex-direction: column; gap: 12px;
  padding: 20px 22px;
  background: var(--surface-strong, #fff); border: 1px solid var(--line);
  border-radius: 12px;
  transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 28px -14px rgba(31, 49, 43, 0.14);
  border-color: rgba(var(--primary-rgb), 0.25);
}

/* ── Skeleton ── */
.stat-skeleton { display: flex; flex-direction: column; gap: 8px; }
.skel {
  border-radius: 6px; background: var(--surface);
  animation: pulse 1.4s ease infinite;
}
.skel--sm { height: 12px; width: 90px; }
.skel--lg { height: 32px; width: 120px; margin-top: 4px; }
.skel--full { height: 40px; width: 100%; margin-top: 4px; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.45; } }

/* ── Head ── */
.stat-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.stat-label {
  margin: 0 0 5px; font-size: 0.72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.12em; color: var(--muted);
}
.stat-value {
  margin: 0; font-size: 1.7rem; font-weight: 800;
  letter-spacing: -0.03em; color: var(--text); font-variant-numeric: tabular-nums;
}
.stat-icon {
  display: flex; align-items: center; justify-content: center;
  width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
}

/* ── Spark ── */
.stat-spark { margin: 0 -4px; }

/* ── Foot ── */
.stat-foot {
  display: flex; align-items: center; gap: 8px;
  font-size: 0.78rem; color: var(--muted); margin-top: 2px;
}
.stat-delta {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 8px; border-radius: 999px;
  font-size: 0.74rem; font-weight: 700;
  background: var(--surface); color: var(--muted);
}
.stat-delta.is-up { background: var(--green-soft); color: var(--green-deep); }
.stat-delta.is-down { background: rgba(220, 38, 38, 0.08); color: #dc2626; }
.stat-delta-label { color: var(--muted); }

[data-theme="dark"] .stat-card { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
</style>
