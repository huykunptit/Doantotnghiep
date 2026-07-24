<script setup lang="ts">
import UiSparkline from './UiSparkline.vue'

withDefaults(
  defineProps<{
    label: string
    value: string | number
    delta?: number | null
    deltaLabel?: string
    icon?: string        // PrimeIcons name e.g. 'pi-users'
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
    iconBg: 'var(--color-primary-soft)',
    iconColor: 'var(--color-primary)',
    sparkline: () => [],
    sparkColor: 'var(--color-primary)',
    loading: false,
  },
)
</script>

<template>
  <article class="stat-card">
    <!-- Skeleton -->
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
          <i :class="`pi ${icon}`" style="font-size:1.125rem" />
        </div>
      </header>

      <div v-if="sparkline?.length" class="stat-spark">
        <UiSparkline :values="sparkline" :color="sparkColor" :height="48" />
      </div>

      <footer v-if="delta !== null || deltaLabel" class="stat-foot">
        <span
          v-if="delta !== null"
          class="stat-delta"
          :class="{ 'is-up': delta > 0, 'is-down': delta < 0, 'is-flat': delta === 0 }"
        >
          <i v-if="delta > 0"  class="pi pi-arrow-up"   style="font-size:0.7rem" />
          <i v-else-if="delta < 0" class="pi pi-arrow-down" style="font-size:0.7rem" />
          <i v-else class="pi pi-minus" style="font-size:0.7rem" />
          {{ delta > 0 ? '+' : '' }}{{ delta }}%
        </span>
        <span class="stat-delta-label">{{ deltaLabel }}</span>
      </footer>
    </template>
  </article>
</template>

<style scoped>
.stat-card {
  position: relative; display: flex; flex-direction: column; gap: 8px;
  padding: 16px 18px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px -8px rgba(0,0,0,0.12);
  border-color: rgba(var(--theme-primary-rgb, 14,165,233), 0.3);
}

/* ── Skeleton ── */
.stat-skeleton { display: flex; flex-direction: column; gap: 8px; }
.skel {
  border-radius: 6px; background: #f1f5f9;
  animation: pulse 1.4s ease infinite;
}
.skel--sm   { height: 12px; width: 90px; }
.skel--lg   { height: 32px; width: 120px; margin-top: 4px; }
.skel--full { height: 40px; width: 100%; margin-top: 4px; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

/* ── Head ── */
.stat-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.stat-label {
  margin: 0 0 4px;
  font-size: 0.7rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: #64748b; /* slate-500 */
}
.stat-value {
  margin: 0; font-size: 1.5rem; font-weight: 800;
  letter-spacing: -0.03em; color: #1e293b;
  font-variant-numeric: tabular-nums;
}
.stat-icon {
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
}

/* ── Spark ── */
.stat-spark { margin: 4px -4px 0; }

/* ── Foot ── */
.stat-foot { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; margin-top: 4px; }
.stat-delta {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 8px; border-radius: 999px;
  font-size: 0.72rem; font-weight: 700;
  background: #f1f5f9; color: #64748b;
}
.stat-delta.is-up   { background: #dcfce7; color: #16a34a; }
.stat-delta.is-down { background: #fee2e2; color: #dc2626; }
.stat-delta-label   { color: #94a3b8; font-size: 0.75rem; }

/* ── Dark mode ── */
:global(.dark) .stat-card { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
:global(.dark) .stat-value { color: #f1f5f9; }
:global(.dark) .skel { background: rgba(255,255,255,0.08); }
</style>
