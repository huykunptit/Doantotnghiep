<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
// Icons removed - using PrimeIcons

definePageMeta({ layout: false })

interface ServiceInfo {
  service: string
  container: string
  label: string
  icon: string
  state: string
  status: string
  running: boolean
}

// ── State ─────────────────────────────────────────────────────────────
const dockerAvailable = ref(true)
const services = ref<ServiceInfo[]>([])
const loading = ref(true)
const lastRefresh = ref<Date | null>(null)
const actioning = ref<Record<string, string>>({})

const search = ref('')
const filterMode = ref<'all' | 'running' | 'stopped'>('all')
const selectedSvc = ref<ServiceInfo | null>(null)
const logContent = ref('')
const logLoading = ref(false)
const logLines = ref(500)

// ── Terminal resize ────────────────────────────────────────────────────
const logPanelHeight = ref(300)
let isDragging = false
let dragStartY = 0
let dragStartHeight = 0

function startDrag(e: MouseEvent) {
  isDragging = true
  dragStartY = e.clientY
  dragStartHeight = logPanelHeight.value
  document.body.style.userSelect = 'none'
  document.body.style.cursor = 'ns-resize'
  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', stopDrag)
}

function onDrag(e: MouseEvent) {
  if (!isDragging) return
  const delta = dragStartY - e.clientY
  const maxH = Math.floor(window.innerHeight * 0.8)
  logPanelHeight.value = Math.max(120, Math.min(maxH, dragStartHeight + delta))
}

function stopDrag() {
  isDragging = false
  document.body.style.userSelect = ''
  document.body.style.cursor = ''
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDrag)
}

// ── Computed ───────────────────────────────────────────────────────────
const runningCount = computed(() => services.value.filter(s => s.running).length)
const stoppedCount = computed(() => services.value.filter(s => !s.running).length)

const filtered = computed(() => {
  let list = services.value
  if (filterMode.value === 'running') list = list.filter(s => s.running)
  if (filterMode.value === 'stopped') list = list.filter(s => !s.running)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(s => s.container.toLowerCase().includes(q) || s.label.toLowerCase().includes(q))
  }
  return list
})

// ── API helpers ────────────────────────────────────────────────────────
async function fetchStatus() {
  loading.value = true
  try {
    const data = await $fetch<{ dockerAvailable: boolean; services: ServiceInfo[] }>('/docker/status')
    dockerAvailable.value = data.dockerAvailable
    services.value = data.services
    lastRefresh.value = new Date()
    if (selectedSvc.value) {
      const updated = data.services.find(s => s.service === selectedSvc.value!.service)
      if (updated) selectedSvc.value = updated
    }
  } catch {
    dockerAvailable.value = false
  } finally {
    loading.value = false
  }
}

async function doAction(svc: ServiceInfo, action: 'start' | 'stop' | 'restart', e?: Event) {
  e?.stopPropagation()
  actioning.value[svc.service] = action
  try {
    await $fetch('/docker/action', {
      method: 'POST',
      body: { service: svc.service, action },
    })
    await fetchStatus()
  } catch {
    // status refresh shows real state
  } finally {
    delete actioning.value[svc.service]
  }
}

async function fetchLogs(svc: ServiceInfo) {
  selectedSvc.value = svc
  logContent.value = ''
  logLoading.value = true
  try {
    const res = await $fetch<{ logs: string }>(`/docker/logs?container=${svc.container}&lines=${logLines.value}`)
    logContent.value = res.logs
  } catch {
    logContent.value = 'Không lấy được log.'
  } finally {
    logLoading.value = false
  }
}

async function selectRow(svc: ServiceInfo) {
  if (selectedSvc.value?.service === svc.service) {
    selectedSvc.value = null
    logContent.value = ''
    return
  }
  await fetchLogs(svc)
}

async function reloadLog() {
  if (!selectedSvc.value) return
  await fetchLogs(selectedSvc.value)
}

async function fetchMoreLogs() {
  logLines.value = Math.min(logLines.value + 500, 2000)
  await reloadLog()
}

// ── Status helpers ─────────────────────────────────────────────────────
function dotClass(s: ServiceInfo) {
  if (s.state === 'running') return 'dot-run'
  if (s.state === 'restarting') return 'dot-restart'
  if (s.state === 'paused') return 'dot-pause'
  return 'dot-stop'
}

function stateLabel(s: ServiceInfo) {
  const m: Record<string, string> = {
    running: 'Running',
    exited: 'Exited',
    paused: 'Paused',
    created: 'Created',
    restarting: 'Restarting',
    'not found': 'Not found',
    unknown: 'Unknown',
  }
  return m[s.state] || s.state
}

// ── Lifecycle ──────────────────────────────────────────────────────────
let timer: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  fetchStatus()
  timer = setInterval(fetchStatus, 15_000)
})
onUnmounted(() => {
  if (timer) clearInterval(timer)
  stopDrag()
})
</script>

<template>
  <div class="dd">
    <!-- ── Sidebar ──────────────────────────────────────────────────── -->
    <aside class="dd-sidebar">
      <!-- Logo -->
      <div class="dd-logo">
        <svg width="28" height="28" viewBox="0 0 56 56" fill="none">
          <rect width="56" height="56" rx="10" fill="#0db7ed" fill-opacity="0.15"/>
          <ellipse cx="28" cy="31" rx="18" ry="11" fill="#0db7ed"/>
          <path d="M38 28 Q46 18 44 24 Q42 22 38 28Z" fill="#0db7ed"/>
          <path d="M20 20 Q22 10 24 14 Q23 12 26 14 Q24 10 28 8 Q30 10 30 14" stroke="#0db7ed" stroke-width="2" fill="none" stroke-linecap="round"/>
          <rect x="18" y="24" width="7" height="6" rx="1" fill="white" fill-opacity="0.9"/>
          <rect x="27" y="24" width="7" height="6" rx="1" fill="white" fill-opacity="0.9"/>
          <circle cx="14" cy="31" r="2.5" fill="white"/>
          <circle cx="14" cy="31" r="1" fill="#1a2030"/>
          <path d="M46 35 Q52 30 50 38 Q48 34 46 35Z" fill="#0db7ed"/>
        </svg>
        <span class="dd-logo-text">Docker</span>
      </div>

      <!-- Nav -->
      <nav class="dd-nav">
        <div class="dd-nav-item active">
          <Box :size="15" :stroke-width="2" />
          <span>Containers</span>
          <span class="dd-nav-badge">{{ services.length }}</span>
        </div>
        <div class="dd-nav-item disabled">
          <i class="pi pi-clone" style="font-size:0.9375rem" />
          <span>Images</span>
        </div>
        <div class="dd-nav-item disabled">
          <HardDrive :size="15" :stroke-width="2" />
          <span>Volumes</span>
        </div>
      </nav>

      <!-- Engine status -->
      <div class="dd-engine">
        <span class="dd-engine-dot" :class="dockerAvailable ? 'dot-run' : 'dot-stop'" />
        <div>
          <div class="dd-engine-label">Docker Engine</div>
          <div class="dd-engine-state">{{ dockerAvailable ? 'Running' : 'Unavailable' }}</div>
        </div>
      </div>
    </aside>

    <!-- ── Main ────────────────────────────────────────────────────── -->
    <div class="dd-main">
      <!-- Toolbar -->
      <div class="dd-toolbar">
        <div class="dd-toolbar-left">
          <h2 class="dd-page-title">Containers</h2>
          <div class="dd-counts">
            <span class="dd-count-chip run">{{ runningCount }} Running</span>
            <span class="dd-count-chip stop">{{ stoppedCount }} Stopped</span>
          </div>
        </div>
        <div class="dd-toolbar-right">
          <div class="dd-search">
            <i class="pi pi-search" style="font-size:0.8125rem" />
            <input v-model="search" placeholder="Search containers..." class="dd-search-input" />
          </div>
          <div class="dd-filter">
            <button class="dd-filter-btn" :class="{ active: filterMode === 'all' }" @click="filterMode = 'all'">All</button>
            <button class="dd-filter-btn" :class="{ active: filterMode === 'running' }" @click="filterMode = 'running'">Running</button>
            <button class="dd-filter-btn" :class="{ active: filterMode === 'stopped' }" @click="filterMode = 'stopped'">Stopped</button>
          </div>
          <button class="dd-icon-btn" :class="{ 'icon-spin': loading }" title="Refresh" @click="fetchStatus">
            <i class="pi pi-refresh" style="font-size:0.875rem" />
          </button>
          <span v-if="lastRefresh" class="dd-last">{{ lastRefresh.toLocaleTimeString('vi-VN') }}</span>
        </div>
      </div>

      <!-- Docker unavailable banner -->
      <div v-if="!dockerAvailable && !loading" class="dd-banner-warn">
        <AlertTriangle :size="16" :stroke-width="2" />
        Docker Engine không phản hồi. Hãy chắc chắn Docker đang chạy trên máy này.
      </div>

      <!-- Container table -->
      <div class="dd-table-wrap">
        <table class="dd-table">
          <thead>
            <tr>
              <th class="th-dot" />
              <th class="th-name">
                Name <i class="pi pi-chevron-down" style="font-size:0.75rem" />
              </th>
              <th class="th-image">Service</th>
              <th class="th-status">Status</th>
              <th class="th-state">State</th>
              <th class="th-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="svc in filtered"
              :key="svc.service"
              class="dd-row"
              :class="{
                'dd-row-selected': selectedSvc?.service === svc.service,
                'dd-row-running': svc.running,
                'dd-row-acting': !!actioning[svc.service],
              }"
              @click="selectRow(svc)"
            >
              <td class="td-dot">
                <span class="status-dot" :class="actioning[svc.service] === 'restart' ? 'dot-restart' : dotClass(svc)" />
              </td>

              <td class="td-name">
                <div class="td-name-inner">
                  <span class="material-symbols-outlined td-icon">{{ svc.icon }}</span>
                  <div>
                    <div class="td-container-name">{{ svc.container }}</div>
                    <div class="td-label">{{ svc.label }}</div>
                  </div>
                </div>
              </td>

              <td class="td-image">
                <code>{{ svc.service }}</code>
              </td>

              <td class="td-status">
                <span v-if="actioning[svc.service]" class="td-acting-text">
                  {{ actioning[svc.service] === 'start' ? 'Building & starting...' : actioning[svc.service] + 'ing...' }}
                </span>
                <span v-else class="td-status-text">{{ svc.status || '—' }}</span>
              </td>

              <td class="td-state">
                <span class="dd-state-chip" :class="dotClass(svc)">{{ stateLabel(svc) }}</span>
              </td>

              <td class="td-actions" @click.stop>
                <div class="row-actions">
                  <!-- Build & Start (when stopped) -->
                  <button
                    v-if="!svc.running"
                    class="ra-btn ra-start"
                    :disabled="!!actioning[svc.service]"
                    title="Build & Start"
                    @click="doAction(svc, 'start')"
                  >
                    <Hammer v-if="actioning[svc.service] === 'start'" :size="13" :stroke-width="2" class="icon-spin" />
                    <Hammer v-else :size="13" :stroke-width="2" />
                  </button>
                  <!-- Stop (when running) -->
                  <button
                    v-else
                    class="ra-btn ra-stop"
                    :disabled="!!actioning[svc.service]"
                    title="Stop"
                    @click="doAction(svc, 'stop')"
                  >
                    <Square v-if="actioning[svc.service] === 'stop'" :size="13" :stroke-width="0" class="icon-pulse" style="fill:currentColor;" />
                    <Square v-else :size="13" :stroke-width="0" style="fill:currentColor;" />
                  </button>
                  <!-- Restart -->
                  <button
                    class="ra-btn ra-restart"
                    :disabled="!!actioning[svc.service]"
                    title="Restart"
                    @click="doAction(svc, 'restart')"
                  >
                    <i class="pi pi-replay" style="font-size:0.8125rem" />
                  </button>
                  <!-- Logs -->
                  <button
                    class="ra-btn ra-log"
                    :class="{ 'ra-log-active': selectedSvc?.service === svc.service }"
                    title="Show logs"
                    @click.stop="selectRow(svc)"
                  >
                    <Terminal :size="13" :stroke-width="2" />
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="filtered.length === 0">
              <td colspan="6" class="td-empty">
                <Box :size="32" :stroke-width="1" style="opacity:.2;margin-bottom:8px;" />
                <div>Không tìm thấy container nào</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Log panel (bottom, resizable) ── -->
      <Transition name="log-panel">
        <div v-if="selectedSvc" class="dd-log-panel" :style="{ height: logPanelHeight + 'px' }">
          <!-- Drag handle -->
          <div class="dd-log-drag" @mousedown.prevent="startDrag">
            <div class="dd-log-drag-bar" />
          </div>

          <!-- Tab bar -->
          <div class="dd-log-tabbar">
            <div class="dd-log-tab">
              <span class="status-dot" :class="dotClass(selectedSvc)" style="width:8px;height:8px;" />
              <span class="dd-log-tab-name">{{ selectedSvc.container }}</span>
              <span class="dd-log-lines-badge">{{ logLines }} lines</span>
            </div>
            <div class="dd-log-tab-actions">
              <button
                class="dd-log-icon-btn"
                :disabled="logLines >= 2000"
                title="Fetch 500 more lines"
                @click="fetchMoreLogs"
              >
                <i class="pi pi-chevron-down" style="font-size:0.8125rem" />
                +500
              </button>
              <button
                class="dd-log-icon-btn"
                :class="{ 'icon-spin': logLoading }"
                title="Reload logs"
                @click="reloadLog"
              >
                <i class="pi pi-refresh" style="font-size:0.8125rem" />
              </button>
              <button
                class="dd-log-icon-btn"
                title="Close"
                @click="selectedSvc = null; logContent = ''; logLines = 500"
              >
                <i class="pi pi-times" style="font-size:0.8125rem" />
              </button>
            </div>
          </div>

          <!-- Terminal body -->
          <div class="dd-log-body" ref="logBodyRef">
            <div v-if="logLoading" class="dd-log-loading">
              <span class="dd-log-cursor">▌</span> Fetching logs...
            </div>
            <pre v-else class="dd-log-pre">{{ logContent || '(no output)' }}</pre>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; }

.dd {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: #1c2028;
  color: #c8cdd3;
  font-family: 'Be Vietnam Pro', system-ui, -apple-system, sans-serif;
  font-size: 13px;
}

/* ── Sidebar ──────────────────────────────────────────────────── */
.dd-sidebar {
  width: 200px;
  flex-shrink: 0;
  background: #14171c;
  border-right: 1px solid #252930;
  display: flex;
  flex-direction: column;
}

.dd-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 16px 14px;
  border-bottom: 1px solid #252930;
}

.dd-logo-text {
  font-size: 15px;
  font-weight: 700;
  color: #e2e8f0;
  letter-spacing: -0.02em;
}

.dd-nav {
  flex: 1;
  padding: 10px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dd-nav-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 10px;
  border-radius: 8px;
  cursor: pointer;
  color: #6c737a;
  font-size: 12.5px;
  font-weight: 500;
  transition: background 120ms, color 120ms;
  user-select: none;
}

.dd-nav-item:hover:not(.disabled) { background: rgba(255,255,255,0.05); color: #c8cdd3; }
.dd-nav-item.active { background: rgba(13,183,237,0.1); color: #0db7ed; }
.dd-nav-item.disabled { opacity: 0.35; cursor: default; }

.dd-nav-badge {
  margin-left: auto;
  background: rgba(13,183,237,0.15);
  color: #0db7ed;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 999px;
}

.dd-engine {
  padding: 14px 14px 16px;
  border-top: 1px solid #252930;
  display: flex;
  align-items: center;
  gap: 10px;
}

.dd-engine-dot {
  width: 9px; height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dd-engine-label { font-size: 11px; font-weight: 600; color: #c8cdd3; }
.dd-engine-state { font-size: 10px; color: #6c737a; margin-top: 1px; }

/* ── Main ─────────────────────────────────────────────────────── */
.dd-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-width: 0;
}

/* ── Toolbar ──────────────────────────────────────────────────── */
.dd-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px 20px 10px;
  background: #1c2028;
  border-bottom: 1px solid #252930;
  flex-shrink: 0;
}

.dd-toolbar-left { display: flex; align-items: center; gap: 14px; }
.dd-toolbar-right { display: flex; align-items: center; gap: 8px; }

.dd-page-title {
  font-size: 15px;
  font-weight: 700;
  color: #e2e8f0;
  margin: 0;
}

.dd-counts { display: flex; gap: 6px; }

.dd-count-chip {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 9px;
  border-radius: 999px;
}

.dd-count-chip.run { background: rgba(61,197,94,0.12); color: #3dc55e; }
.dd-count-chip.stop { background: rgba(108,115,122,0.15); color: #6c737a; }

.dd-search {
  position: relative;
  display: flex;
  align-items: center;
}

.dd-search-icon {
  position: absolute;
  left: 9px;
  color: #4a5058;
  pointer-events: none;
}

.dd-search-input {
  background: #252930;
  border: 1px solid #333940;
  border-radius: 7px;
  padding: 6px 10px 6px 30px;
  color: #c8cdd3;
  font-size: 12.5px;
  font-family: inherit;
  width: 210px;
  outline: none;
  transition: border-color 150ms;
}

.dd-search-input::placeholder { color: #4a5058; }
.dd-search-input:focus { border-color: #0db7ed; }

.dd-filter {
  display: flex;
  background: #252930;
  border: 1px solid #333940;
  border-radius: 7px;
  overflow: hidden;
}

.dd-filter-btn {
  padding: 5px 11px;
  font-size: 11.5px;
  font-weight: 600;
  color: #6c737a;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background 120ms, color 120ms;
  font-family: inherit;
}

.dd-filter-btn:hover { color: #c8cdd3; }
.dd-filter-btn.active { background: rgba(13,183,237,0.15); color: #0db7ed; }

.dd-icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px; height: 30px;
  border-radius: 7px;
  border: 1px solid #333940;
  background: #252930;
  color: #6c737a;
  cursor: pointer;
  transition: color 150ms, background 150ms;
}
.dd-icon-btn:hover { color: #c8cdd3; background: #2e3440; }
.dd-icon-btn.icon-spin svg { animation: spin 1s linear infinite; }

.dd-last { font-size: 10.5px; color: #4a5058; white-space: nowrap; }

.dd-banner-warn {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 9px 20px;
  background: rgba(224,157,60,0.1);
  border-bottom: 1px solid rgba(224,157,60,0.25);
  color: #e09d3c;
  font-size: 12.5px;
  font-weight: 500;
  flex-shrink: 0;
}

/* ── Table ────────────────────────────────────────────────────── */
.dd-table-wrap {
  flex: 1;
  overflow-y: auto;
  overflow-x: auto;
}

.dd-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 700px;
}

.dd-table thead th {
  padding: 9px 12px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: #4a5058;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  border-bottom: 1px solid #252930;
  background: #191d24;
  position: sticky;
  top: 0;
  z-index: 1;
  white-space: nowrap;
  user-select: none;
}

.th-dot { width: 36px; padding-left: 20px !important; }
.th-name { width: 260px; }
.th-image { width: 100px; }
.th-state { width: 100px; }
.th-actions { width: 140px; text-align: right; padding-right: 20px !important; }

.dd-row {
  cursor: pointer;
  border-bottom: 1px solid #22262e;
  transition: background 80ms;
}

.dd-row:hover { background: rgba(255,255,255,0.03); }

.dd-row-selected {
  background: rgba(13,183,237,0.05) !important;
  box-shadow: inset 2px 0 0 #0db7ed;
}

.dd-row-acting { opacity: 0.8; }

.dd-row td {
  padding: 11px 12px;
  vertical-align: middle;
}

.td-dot { width: 36px; padding-left: 20px !important; }

.status-dot {
  display: inline-block;
  width: 10px; height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.dot-run     { background: #3dc55e; box-shadow: 0 0 0 3px rgba(61,197,94,0.15); }
.dot-stop    { background: #4a5058; }
.dot-restart { background: #e09d3c; animation: pulse 1s ease-in-out infinite; }
.dot-pause   { background: #e09d3c; }

.td-name-inner {
  display: flex;
  align-items: center;
  gap: 10px;
}

.td-icon {
  font-size: 17px;
  color: #4a5058;
  flex-shrink: 0;
}

.dd-row-running .td-icon { color: #0db7ed; }

.td-container-name {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12.5px;
  font-weight: 600;
  color: #e2e8f0;
  white-space: nowrap;
}

.td-label {
  font-size: 11px;
  color: #4a5058;
  margin-top: 1px;
  white-space: nowrap;
}

.td-image code {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11.5px;
  color: #6c737a;
  background: rgba(255,255,255,0.04);
  padding: 2px 7px;
  border-radius: 4px;
}

.td-status-text {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11.5px;
  color: #6c737a;
  white-space: nowrap;
}

.td-acting-text {
  font-size: 11.5px;
  color: #e09d3c;
  animation: pulse 1.2s ease-in-out infinite;
}

.dd-state-chip {
  display: inline-block;
  font-size: 10.5px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.dd-state-chip.dot-run     { background: rgba(61,197,94,0.12); color: #3dc55e; }
.dd-state-chip.dot-stop    { background: rgba(74,80,88,0.2); color: #6c737a; }
.dd-state-chip.dot-restart { background: rgba(224,157,60,0.12); color: #e09d3c; }
.dd-state-chip.dot-pause   { background: rgba(224,157,60,0.12); color: #e09d3c; }

.td-actions {
  text-align: right;
  padding-right: 20px !important;
}

.row-actions {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  opacity: 0;
  transition: opacity 150ms;
}

.dd-row:hover .row-actions,
.dd-row-selected .row-actions { opacity: 1; }

.ra-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px; height: 28px;
  border-radius: 6px;
  border: 1px solid transparent;
  background: transparent;
  color: #6c737a;
  cursor: pointer;
  transition: background 120ms, color 120ms, border-color 120ms;
}

.ra-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.ra-start:not(:disabled):hover  { background: rgba(61,197,94,0.12); border-color: rgba(61,197,94,0.3); color: #3dc55e; }
.ra-stop:not(:disabled):hover   { background: rgba(248,81,73,0.1); border-color: rgba(248,81,73,0.3); color: #f85149; }
.ra-restart:not(:disabled):hover{ background: rgba(224,157,60,0.1); border-color: rgba(224,157,60,0.3); color: #e09d3c; }
.ra-log:not(:disabled):hover, .ra-log.ra-log-active { background: rgba(13,183,237,0.1); border-color: rgba(13,183,237,0.3); color: #0db7ed; }

.td-empty {
  text-align: center;
  padding: 60px 20px;
  color: #4a5058;
  font-size: 13px;
}

/* ── Log panel ────────────────────────────────────────────────── */
.dd-log-panel {
  flex-shrink: 0;
  border-top: 1px solid #252930;
  display: flex;
  flex-direction: column;
  background: #0e1117;
  min-height: 120px;
}

/* Drag handle */
.dd-log-drag {
  flex-shrink: 0;
  height: 6px;
  background: #14171c;
  cursor: ns-resize;
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid #252930;
  transition: background 150ms;
}

.dd-log-drag:hover { background: rgba(13,183,237,0.12); }
.dd-log-drag:hover .dd-log-drag-bar { background: #0db7ed; }

.dd-log-drag-bar {
  width: 36px;
  height: 2px;
  border-radius: 99px;
  background: #333940;
  transition: background 150ms;
}

/* Tab bar */
.dd-log-tabbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  height: 36px;
  background: #14171c;
  border-bottom: 1px solid #252930;
  flex-shrink: 0;
}

.dd-log-tab {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  font-weight: 600;
  color: #c8cdd3;
}

.dd-log-tab-name {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11.5px;
}

.dd-log-lines-badge {
  font-size: 10px;
  color: #4a5058;
  background: rgba(255,255,255,0.04);
  padding: 1px 7px;
  border-radius: 999px;
}

.dd-log-tab-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}

.dd-log-icon-btn {
  display: flex; align-items: center; justify-content: center; gap: 4px;
  padding: 0 8px;
  height: 26px;
  border-radius: 5px;
  border: none;
  background: transparent;
  color: #6c737a;
  font-size: 11px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: background 120ms, color 120ms;
  white-space: nowrap;
}
.dd-log-icon-btn:hover:not(:disabled) { background: rgba(255,255,255,0.06); color: #c8cdd3; }
.dd-log-icon-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.dd-log-icon-btn.icon-spin svg { animation: spin 1s linear infinite; }

/* Terminal body */
.dd-log-body {
  flex: 1;
  overflow-y: auto;
  overflow-x: auto;
  padding: 10px 18px;
}

.dd-log-loading {
  color: #4a5058;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  padding-top: 4px;
}

.dd-log-cursor {
  animation: blink 1s step-end infinite;
  color: #0db7ed;
}

.dd-log-pre {
  margin: 0;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11.5px;
  line-height: 1.75;
  color: #a0a8b0;
  white-space: pre-wrap;
  word-break: break-all;
}

/* ── Transitions ──────────────────────────────────────────────── */
.log-panel-enter-active { transition: height 200ms ease, opacity 200ms ease; }
.log-panel-leave-active { transition: height 180ms ease, opacity 150ms ease; }
.log-panel-enter-from, .log-panel-leave-to { height: 0 !important; opacity: 0; }

/* ── Animations ───────────────────────────────────────────────── */
@keyframes spin  { to { transform: rotate(360deg); } }
@keyframes blink { 0%,100%{ opacity:1; } 50%{ opacity:0; } }
@keyframes pulse { 0%,100%{ opacity:1; } 50%{ opacity:0.45; } }

/* ── Scrollbars ───────────────────────────────────────────────── */
.dd-table-wrap::-webkit-scrollbar,
.dd-log-body::-webkit-scrollbar    { width: 6px; height: 6px; }
.dd-table-wrap::-webkit-scrollbar-track,
.dd-log-body::-webkit-scrollbar-track { background: transparent; }
.dd-table-wrap::-webkit-scrollbar-thumb,
.dd-log-body::-webkit-scrollbar-thumb { background: #333940; border-radius: 99px; }
.dd-table-wrap::-webkit-scrollbar-thumb:hover,
.dd-log-body::-webkit-scrollbar-thumb:hover { background: #4a5058; }

/* ── Responsive ───────────────────────────────────────────────── */
@media (max-width: 768px) {
  .dd-sidebar { width: 56px; }
  .dd-logo-text, .dd-nav-item span, .dd-nav-badge,
  .dd-engine-label, .dd-engine-state { display: none; }
  .dd-logo { padding: 14px; justify-content: center; }
  .dd-nav-item { justify-content: center; padding: 10px; }
  .dd-engine { justify-content: center; }
  .dd-search-input { width: 150px; }
}
</style>
