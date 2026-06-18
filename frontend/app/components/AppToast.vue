<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { CheckCircle2, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next'
import { useToast, type Toast } from '~/composables/useToast'

const { toasts, remove } = useToast()

// track which toasts are in "leaving" state for animation
const leaving = ref<Set<string>>(new Set())

function dismiss(id: string) {
  leaving.value.add(id)
  setTimeout(() => {
    remove(id)
    leaving.value.delete(id)
  }, 320)
}

const iconMap = {
  success: CheckCircle2,
  error: XCircle,
  warning: AlertTriangle,
  info: Info,
}
</script>

<template>
  <Teleport to="body">
    <div class="toast-stack" aria-live="polite" aria-atomic="false">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="toast-item"
          :class="[`toast-${t.type}`, { 'toast-leaving': leaving.has(t.id) }]"
          role="alert"
        >
          <!-- Left icon -->
          <div class="toast-icon">
            <component :is="iconMap[t.type]" :size="18" :stroke-width="2" />
          </div>

          <!-- Content -->
          <div class="toast-body">
            <p class="toast-title">{{ t.title }}</p>
            <p v-if="t.message" class="toast-msg">{{ t.message }}</p>
          </div>

          <!-- Close -->
          <button class="toast-close" :aria-label="`Đóng thông báo: ${t.title}`" @click="dismiss(t.id)">
            <X :size="14" :stroke-width="2.5" />
          </button>

          <!-- Progress bar -->
          <div class="toast-progress" :style="{ animationDuration: `${t.duration}ms` }" />
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
/* ── Stack ── */
.toast-stack {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
  pointer-events: none;
  width: 360px;
  max-width: calc(100vw - 40px);
}

/* ── Item ── */
.toast-item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 14px 14px 14px;
  border-radius: 14px;
  background: var(--surface-strong, #fff);
  box-shadow:
    0 4px 6px -1px rgba(0,0,0,0.07),
    0 10px 24px -4px rgba(0,0,0,0.12),
    0 0 0 1px rgba(0,0,0,0.05);
  pointer-events: auto;
  overflow: hidden;
  border-left: 3px solid transparent;
}

/* Type-based left border + icon color */
.toast-success { border-left-color: #1D9E75; }
.toast-error   { border-left-color: #E24B4A; }
.toast-warning { border-left-color: #F59E0B; }
.toast-info    { border-left-color: #3B82F6; }

.toast-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px; height: 32px;
  border-radius: 8px;
  flex-shrink: 0;
}

.toast-success .toast-icon { background: rgba(29,158,117,0.1); color: #1D9E75; }
.toast-error   .toast-icon { background: rgba(226,75,74,0.1);   color: #E24B4A; }
.toast-warning .toast-icon { background: rgba(245,158,11,0.1);  color: #F59E0B; }
.toast-info    .toast-icon { background: rgba(59,130,246,0.1);  color: #3B82F6; }

.toast-body {
  flex: 1;
  min-width: 0;
  padding-right: 4px;
}

.toast-title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--text, #1F312B);
  line-height: 1.3;
}

.toast-msg {
  margin: 4px 0 0;
  font-size: 0.78rem;
  color: var(--muted, #4A6059);
  line-height: 1.5;
}

.toast-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px; height: 24px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--muted, #4A6059);
  cursor: pointer;
  flex-shrink: 0;
  opacity: 0.6;
  transition: opacity 150ms, background 150ms;
  margin-top: -2px;
}

.toast-close:hover { opacity: 1; background: rgba(0,0,0,0.06); }

/* ── Progress bar ── */
.toast-progress {
  position: absolute;
  bottom: 0; left: 0;
  height: 2px;
  width: 100%;
  transform-origin: left;
  animation: toast-drain linear forwards;
}

.toast-success .toast-progress { background: #1D9E75; }
.toast-error   .toast-progress { background: #E24B4A; }
.toast-warning .toast-progress { background: #F59E0B; }
.toast-info    .toast-progress { background: #3B82F6; }

@keyframes toast-drain {
  from { transform: scaleX(1); }
  to   { transform: scaleX(0); }
}

/* ── Transitions ── */
.toast-enter-active { animation: toast-in 300ms cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
.toast-leave-active { animation: toast-out 300ms ease forwards; }

@keyframes toast-in {
  from { transform: translateX(calc(100% + 20px)); opacity: 0; }
  to   { transform: translateX(0); opacity: 1; }
}

@keyframes toast-out {
  from { transform: translateX(0); opacity: 1; scale: 1; }
  to   { transform: translateX(calc(100% + 20px)); opacity: 0; scale: 0.92; }
}

/* Dark mode */
[data-theme="dark"] .toast-item {
  background: #142D1F;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3), 0 10px 24px -4px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.06);
}
</style>
