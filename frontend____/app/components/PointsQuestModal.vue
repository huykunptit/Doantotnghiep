<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

const STORAGE_KEY = 'quest_modal_snoozed_until'
const SNOOZE_HOURS = 24

const auth = useAuthStore()
const visible = ref(false)
const loading = ref(false)
const questData = ref<any>(null)

const piIconMap: Record<string, string> = {
  'calendar-check': 'pi-calendar-check',
  'flame': 'pi-bolt',
  'trophy': 'pi-trophy',
  'book-open-check': 'pi-book',
  'graduation-cap': 'pi-graduation-cap',
  'medal': 'pi-shield',
  'shopping-bag': 'pi-shopping-bag',
  'clipboard-list': 'pi-list-check',
  'star': 'pi-star',
}

function getPiIcon(key: string) {
  return piIconMap[key] || 'pi-star'
}

onMounted(async () => {
  const snoozedUntil = localStorage.getItem(STORAGE_KEY)
  if (snoozedUntil && Date.now() < Number(snoozedUntil)) return

  if (!auth.token) return

  loading.value = true
  try {
    questData.value = await useApi<any>('/points/quests', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    visible.value = true
  } catch {}
  finally { loading.value = false }
})

function snooze() {
  const until = Date.now() + SNOOZE_HOURS * 3_600_000
  localStorage.setItem(STORAGE_KEY, String(until))
  visible.value = false
}

function neverShow() {
  localStorage.setItem(STORAGE_KEY, String(Date.now() + 365 * 24 * 3_600_000))
  visible.value = false
}

function close() {
  visible.value = false
}

const categoryLabel: Record<string, string> = {
  daily: 'Hàng ngày',
  milestone: 'Cột mốc',
  learning: 'Học tập',
  engagement: 'Tương tác',
}

const grouped = computed(() => {
  if (!questData.value?.quests) return {}
  const map: Record<string, any[]> = {}
  for (const q of questData.value.quests) {
    if (!map[q.category]) map[q.category] = []
    map[q.category].push(q)
  }
  return map
})
</script>

<template>
  <Teleport to="body">
    <Transition name="quest-fade">
      <div v-if="visible" class="qm-backdrop" @click.self="close">
        <div class="qm-modal" role="dialog" aria-modal="true" aria-label="Nhiệm vụ tích điểm">
          <!-- Header -->
          <div class="qm-head">
            <div class="qm-head-left">
              <div class="qm-coin-icon">
                <i class="pi pi-star-fill" style="font-size: 1.25rem" />
              </div>
              <div>
                <h2 class="qm-title">Nhiệm vụ tích điểm</h2>
                <p class="qm-subtitle">Hoàn thành nhiệm vụ để đổi quà hấp dẫn</p>
              </div>
            </div>
            <button class="qm-close" aria-label="Đóng" @click="close">
              <i class="pi pi-times" style="font-size: 1.125rem" />
            </button>
          </div>

          <!-- Balance strip -->
          <div v-if="questData" class="qm-balance-strip">
            <div class="qm-balance-item">
              <i class="pi pi-star-fill text-amber" style="font-size: 1rem" />
              <span><strong>{{ questData.balance.toLocaleString('vi-VN') }}</strong> điểm hiện có</span>
            </div>
            <div class="qm-balance-item">
              <i class="pi pi-bolt text-orange" style="font-size: 1rem" />
              <span>Chuỗi <strong>{{ questData.streak_days }}</strong> ngày</span>
            </div>
            <div class="qm-balance-item">
              <i class="pi pi-trophy text-gold" style="font-size: 1rem" />
              <span>Đã nhận <strong>{{ questData.total_earned.toLocaleString('vi-VN') }}</strong> điểm</span>
            </div>
          </div>

          <!-- Quest list -->
          <div class="qm-body">
            <div v-for="(quests, cat) in grouped" :key="cat" class="qm-group">
              <p class="qm-group-label">{{ categoryLabel[cat] || cat }}</p>
              <div class="qm-quest-list">
                <div v-for="q in quests" :key="q.key" class="qm-quest-item" :class="{ 'is-done': q.done_today }">
                  <div class="qm-quest-icon" :class="`cat-${q.category}`">
                    <i :class="`pi ${getPiIcon(q.icon)}`" style="font-size: 0.9375rem" />
                  </div>
                  <div class="qm-quest-info">
                    <p class="qm-quest-title">{{ q.title }}</p>
                    <p class="qm-quest-desc">{{ q.description }}</p>
                    <div v-if="q.progress !== undefined" class="qm-progress">
                      <div class="qm-progress-track">
                        <div class="qm-progress-fill" :style="{ width: `${Math.round((q.progress / q.target) * 100)}%` }" />
                      </div>
                      <span>{{ q.progress }}/{{ q.target }}</span>
                    </div>
                  </div>
                  <div class="qm-quest-pts">
                    <span class="qm-pts-badge">+{{ q.points }}</span>
                    <span class="qm-pts-label">điểm</span>
                    <span v-if="q.done_today" class="qm-done-mark">✓</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="qm-footer">
            <div class="qm-footer-left">
              <button class="qm-btn-ghost" @click="snooze">Ẩn 24 giờ</button>
              <button class="qm-btn-ghost qm-muted" @click="neverShow">Không nhắc lại</button>
            </div>
            <NuxtLink to="/student/points" class="qm-btn-primary" @click="close">
              Xem shop đổi quà <i class="pi pi-chevron-right" style="font-size: 0.75rem" />
            </NuxtLink>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.qm-backdrop {
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.qm-modal {
  background: var(--surface-strong, #fff);
  border-radius: 20px;
  width: 100%; max-width: 520px;
  max-height: 90vh;
  display: flex; flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  overflow: hidden;
}

/* Head */
.qm-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 20px 14px;
  border-bottom: 1px solid var(--line, #e5e9e6);
  background: linear-gradient(135deg, rgba(var(--green-rgb,15,110,140),.06) 0%, transparent 100%);
}
.qm-head-left { display: flex; align-items: center; gap: 12px; }
.qm-coin-icon {
  width: 40px; height: 40px; border-radius: 12px;
  background: linear-gradient(135deg, #f59e0b, #fbbf24);
  display: flex; align-items: center; justify-content: center; color: #fff;
  flex-shrink: 0;
}
.qm-title { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text, #1e293b); }
.qm-subtitle { margin: 2px 0 0; font-size: 0.75rem; color: var(--muted, #64748b); }
.qm-close {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; border-radius: 8px;
  border: 1px solid var(--line, #e5e9e6);
  background: transparent; color: var(--muted, #64748b); cursor: pointer;
  transition: all 150ms; flex-shrink: 0;
}
.qm-close:hover { background: #fef2f2; color: #ef4444; border-color: #fecaca; }

/* Balance */
.qm-balance-strip {
  display: flex; gap: 0;
  border-bottom: 1px solid var(--line, #e5e9e6);
}
.qm-balance-item {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
  padding: 10px 8px; font-size: 0.78rem; color: var(--text, #1e293b);
  border-right: 1px solid var(--line, #e5e9e6);
}
.qm-balance-item:last-child { border-right: none; }
.qm-balance-item strong { font-weight: 800; }
.text-amber { color: #f59e0b; }
.text-orange { color: #ea580c; }
.text-gold { color: #d97706; }

/* Body */
.qm-body { flex: 1; overflow-y: auto; padding: 16px 20px; display: flex; flex-direction: column; gap: 16px; }
.qm-group-label {
  font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;
  color: var(--muted, #64748b); margin: 0 0 8px;
}
.qm-quest-list { display: flex; flex-direction: column; gap: 6px; }
.qm-quest-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 12px; border-radius: 12px;
  border: 1px solid var(--line, #e5e9e6);
  background: var(--surface, #f8fafc);
  transition: background 150ms;
}
.qm-quest-item.is-done { background: #f0fdf4; border-color: #bbf7d0; opacity: 0.85; }
.qm-quest-icon {
  width: 32px; height: 32px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.cat-daily { background: #dbeafe; color: #2563eb; }
.cat-milestone { background: #fef3c7; color: #d97706; }
.cat-learning { background: #d1fae5; color: #059669; }
.cat-engagement { background: #ede9fe; color: #7c3aed; }

.qm-quest-info { flex: 1; min-width: 0; }
.qm-quest-title { margin: 0; font-size: 0.82rem; font-weight: 700; color: var(--text, #1e293b); }
.qm-quest-desc { margin: 2px 0 0; font-size: 0.72rem; color: var(--muted, #64748b); line-height: 1.4; }

.qm-progress { display: flex; align-items: center; gap: 6px; margin-top: 6px; }
.qm-progress-track { flex: 1; height: 4px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.qm-progress-fill { height: 100%; background: #f59e0b; border-radius: 4px; transition: width 0.4s; }
.qm-progress span { font-size: 0.68rem; font-weight: 700; color: var(--muted); white-space: nowrap; }

.qm-quest-pts {
  display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0;
}
.qm-pts-badge {
  font-size: 0.8rem; font-weight: 800; color: #f59e0b;
  background: #fffbeb; padding: 2px 8px; border-radius: 999px;
  border: 1px solid #fde68a;
}
.qm-pts-label { font-size: 0.62rem; color: var(--muted); }
.qm-done-mark { font-size: 0.75rem; font-weight: 800; color: #16a34a; }

/* Footer */
.qm-footer {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  padding: 12px 20px 16px;
  border-top: 1px solid var(--line, #e5e9e6);
  flex-wrap: wrap;
}
.qm-footer-left { display: flex; gap: 8px; }
.qm-btn-ghost {
  font-size: 0.78rem; font-weight: 600; padding: 7px 12px;
  border-radius: 8px; border: 1px solid var(--line, #e5e9e6);
  background: transparent; color: var(--muted); cursor: pointer;
  transition: all 150ms;
}
.qm-btn-ghost:hover { background: var(--surface, #f8fafc); color: var(--text); }
.qm-btn-ghost.qm-muted { color: #94a3b8; }
.qm-btn-primary {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 0.82rem; font-weight: 700; padding: 8px 16px;
  border-radius: 10px; background: linear-gradient(135deg, #0F6E8C, #1D9E75);
  color: #fff; text-decoration: none; transition: opacity 150ms;
}
.qm-btn-primary:hover { opacity: 0.9; }

/* Transition */
.quest-fade-enter-active, .quest-fade-leave-active { transition: opacity 250ms ease, transform 250ms ease; }
.quest-fade-enter-from, .quest-fade-leave-to { opacity: 0; transform: scale(0.96) translateY(10px); }
</style>
