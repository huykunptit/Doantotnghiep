<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
// Icons removed - using PrimeIcons

export interface FieldConfig {
  key: string
  label: string
  x: number   // percentage 0-100 from left
  y: number   // percentage 0-100 from top
  font_size: number
  font_family: string
  color: string
  font_weight: 'normal' | 'bold'
  text_align: 'left' | 'center' | 'right'
  visible: boolean
}

const AVAILABLE_FONTS = [
  { label: 'Arial', value: 'Arial, sans-serif' },
  { label: 'Georgia', value: 'Georgia, serif' },
  { label: 'Times New Roman', value: '"Times New Roman", serif' },
  { label: 'Courier New', value: '"Courier New", monospace' },
  { label: 'Verdana', value: 'Verdana, sans-serif' },
  { label: 'Trebuchet MS', value: '"Trebuchet MS", sans-serif' },
]

const FIELD_SAMPLE: Record<string, string> = {
  student_name: 'Nguyễn Văn A',
  course_title: 'Lập trình Web với Laravel & Vue.js',
  issued_date: '20 tháng 06 năm 2026',
  credential_id: 'SYLVA-2026-000001',
}

const props = defineProps<{
  backgroundUrl: string | null
  modelValue: FieldConfig[]
  saving?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [fields: FieldConfig[]]
  'save': []
}>()

const fields = ref<FieldConfig[]>(props.modelValue.map(f => ({ ...f })))
const selectedKey = ref<string | null>(null)
const canvasEl = ref<HTMLDivElement | null>(null)
const draggingKey = ref<string | null>(null)
const dragStart = ref({ mx: 0, my: 0, fx: 0, fy: 0 })

watch(() => props.modelValue, (v) => {
  fields.value = v.map(f => ({ ...f }))
}, { deep: true })

const selected = computed(() => fields.value.find(f => f.key === selectedKey.value) ?? null)

function onCanvasMouseDown(e: MouseEvent, key: string) {
  e.preventDefault()
  e.stopPropagation()
  selectedKey.value = key
  draggingKey.value = key
  const field = fields.value.find(f => f.key === key)!
  dragStart.value = { mx: e.clientX, my: e.clientY, fx: field.x, fy: field.y }
  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', onMouseUp)
}

function onMouseMove(e: MouseEvent) {
  if (!draggingKey.value || !canvasEl.value) return
  const rect = canvasEl.value.getBoundingClientRect()
  const dx = ((e.clientX - dragStart.value.mx) / rect.width) * 100
  const dy = ((e.clientY - dragStart.value.my) / rect.height) * 100
  const field = fields.value.find(f => f.key === draggingKey.value)!
  field.x = Math.max(0, Math.min(100, dragStart.value.fx + dx))
  field.y = Math.max(0, Math.min(100, dragStart.value.fy + dy))
  emit('update:modelValue', fields.value)
}

function onMouseUp() {
  draggingKey.value = null
  window.removeEventListener('mousemove', onMouseMove)
  window.removeEventListener('mouseup', onMouseUp)
}

function updateField(key: keyof FieldConfig, value: any) {
  const field = fields.value.find(f => f.key === selectedKey.value)
  if (!field) return
  ;(field as any)[key] = value
  emit('update:modelValue', fields.value)
}

function toggleVisible(key: string) {
  const field = fields.value.find(f => f.key === key)
  if (!field) return
  field.visible = !field.visible
  emit('update:modelValue', fields.value)
}

function resetField(key: string) {
  const original = props.modelValue.find(f => f.key === key)
  if (!original) return
  const idx = fields.value.findIndex(f => f.key === key)
  if (idx !== -1) fields.value[idx] = { ...original }
  emit('update:modelValue', fields.value)
}

function sampleText(field: FieldConfig): string {
  return FIELD_SAMPLE[field.key] ?? field.label
}
</script>

<template>
  <div class="cert-editor">
    <!-- Canvas -->
    <div class="editor-canvas-wrap">
      <div
        ref="canvasEl"
        class="editor-canvas"
        :class="{ 'has-bg': !!backgroundUrl }"
        @click.self="selectedKey = null"
      >
        <img v-if="backgroundUrl" :src="backgroundUrl" class="canvas-bg" draggable="false">
        <div v-else class="canvas-placeholder">Chưa có phôi chứng chỉ</div>

        <!-- Draggable field tokens -->
        <div
          v-for="field in fields"
          v-show="field.visible"
          :key="field.key"
          class="field-token"
          :class="{ selected: selectedKey === field.key, dragging: draggingKey === field.key }"
          :style="{
            left: field.x + '%',
            top: field.y + '%',
            fontSize: field.font_size + 'px',
            fontFamily: field.font_family,
            color: field.color,
            fontWeight: field.font_weight,
            textAlign: field.text_align,
            transform: field.text_align === 'center' ? 'translateX(-50%)' : field.text_align === 'right' ? 'translateX(-100%)' : 'none',
          }"
          @mousedown="(e) => onCanvasMouseDown(e, field.key)"
        >
          <GripVertical class="drag-handle" :size="12" />
          {{ sampleText(field) }}
        </div>
      </div>
      <p class="canvas-hint">Kéo thả các trường để đặt vị trí. Click vào trường để tuỳ chỉnh font/cỡ chữ.</p>
    </div>

    <!-- Sidebar: field list + properties -->
    <div class="editor-sidebar">
      <!-- Field list -->
      <div class="sidebar-section">
        <p class="sidebar-title">Các trường dữ liệu</p>
        <div
          v-for="field in fields"
          :key="field.key"
          class="field-item"
          :class="{ active: selectedKey === field.key }"
          @click="selectedKey = field.key"
        >
          <span class="field-item-label">{{ field.label }}</span>
          <div class="field-item-actions">
            <button type="button" class="icon-btn" title="Ẩn/hiện" @click.stop="toggleVisible(field.key)">
              <Eye v-if="field.visible" :size="14" />
              <EyeOff v-else :size="14" style="opacity:.4" />
            </button>
            <button type="button" class="icon-btn" title="Đặt lại" @click.stop="resetField(field.key)">
              <i class="pi pi-replay" style="font-size:0.875rem" />
            </button>
          </div>
        </div>
      </div>

      <!-- Field properties -->
      <div v-if="selected" class="sidebar-section">
        <p class="sidebar-title">Tuỳ chỉnh — {{ selected.label }}</p>

        <label class="prop-row">
          <span>Cỡ chữ (px)</span>
          <input
            type="number" min="6" max="120" step="1"
            :value="selected.font_size"
            @input="updateField('font_size', +($event.target as HTMLInputElement).value)"
          >
        </label>

        <label class="prop-row">
          <span>Font chữ</span>
          <select :value="selected.font_family" @change="updateField('font_family', ($event.target as HTMLSelectElement).value)">
            <option v-for="f in AVAILABLE_FONTS" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
        </label>

        <label class="prop-row">
          <span>Màu chữ</span>
          <input type="color" :value="selected.color" @input="updateField('color', ($event.target as HTMLInputElement).value)">
        </label>

        <label class="prop-row">
          <span>Độ đậm</span>
          <select :value="selected.font_weight" @change="updateField('font_weight', ($event.target as HTMLSelectElement).value)">
            <option value="normal">Normal</option>
            <option value="bold">Bold</option>
          </select>
        </label>

        <label class="prop-row">
          <span>Căn chỉnh</span>
          <select :value="selected.text_align" @change="updateField('text_align', ($event.target as HTMLSelectElement).value)">
            <option value="left">Trái</option>
            <option value="center">Giữa</option>
            <option value="right">Phải</option>
          </select>
        </label>

        <div class="prop-row">
          <span>Vị trí X (%)</span>
          <input
            type="number" min="0" max="100" step="0.1"
            :value="selected.x.toFixed(1)"
            @input="updateField('x', +($event.target as HTMLInputElement).value)"
          >
        </div>

        <div class="prop-row">
          <span>Vị trí Y (%)</span>
          <input
            type="number" min="0" max="100" step="0.1"
            :value="selected.y.toFixed(1)"
            @input="updateField('y', +($event.target as HTMLInputElement).value)"
          >
        </div>
      </div>
      <div v-else class="sidebar-empty">Chọn một trường để tuỳ chỉnh</div>

      <button class="save-btn" :disabled="saving" @click="emit('save')">
        {{ saving ? 'Đang lưu...' : 'Lưu cấu hình' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.cert-editor {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 20px;
  min-height: 480px;
}

.editor-canvas-wrap {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.editor-canvas {
  position: relative;
  aspect-ratio: 1600 / 1131;
  background: #f3f4f6;
  border-radius: 10px;
  overflow: hidden;
  border: 2px dashed rgba(0,0,0,0.12);
  cursor: default;
  user-select: none;
}
.editor-canvas.has-bg { border-style: solid; border-color: rgba(0,0,0,0.08); }

.canvas-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  pointer-events: none;
}

.canvas-placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #aaa;
  font-size: 0.875rem;
}

.canvas-hint {
  font-size: 0.72rem;
  color: var(--muted, #888);
  text-align: center;
  margin: 0;
}

.field-token {
  position: absolute;
  white-space: nowrap;
  cursor: grab;
  padding: 2px 6px;
  border-radius: 4px;
  border: 1.5px solid transparent;
  transition: border-color 0.15s;
  display: flex;
  align-items: center;
  gap: 4px;
  line-height: 1.2;
}
.field-token:hover { border-color: rgba(59,130,246,0.5); background: rgba(59,130,246,0.06); }
.field-token.selected { border-color: #3b82f6; background: rgba(59,130,246,0.08); }
.field-token.dragging { cursor: grabbing; opacity: 0.85; }
.drag-handle { flex-shrink: 0; opacity: 0.4; }

/* sidebar */
.editor-sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sidebar-section {
  border: 1px solid var(--line, #e5e7eb);
  border-radius: 12px;
  padding: 14px;
}

.sidebar-title {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted, #888);
  margin: 0 0 10px;
}

.field-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 7px 10px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.8rem;
  transition: background 0.15s;
}
.field-item:hover { background: rgba(0,0,0,0.04); }
.field-item.active { background: rgba(59,130,246,0.1); color: #2563eb; font-weight: 600; }
.field-item-label { flex: 1; }
.field-item-actions { display: flex; gap: 4px; }

.icon-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 3px;
  border-radius: 4px;
  color: inherit;
  opacity: 0.6;
  transition: opacity 0.15s;
}
.icon-btn:hover { opacity: 1; }

.prop-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 0.78rem;
  color: var(--text, #111);
  margin-bottom: 8px;
}
.prop-row span { flex: 1; color: var(--muted, #666); }
.prop-row input[type="number"],
.prop-row select {
  width: 130px;
  padding: 4px 8px;
  border: 1px solid var(--line, #e5e7eb);
  border-radius: 6px;
  font-size: 0.78rem;
  background: transparent;
  color: var(--text, #111);
}
.prop-row input[type="color"] {
  width: 40px;
  height: 28px;
  padding: 2px;
  border: 1px solid var(--line, #e5e7eb);
  border-radius: 6px;
  cursor: pointer;
  background: transparent;
}

.sidebar-empty {
  text-align: center;
  padding: 24px;
  color: var(--muted, #aaa);
  font-size: 0.8rem;
}

.save-btn {
  width: 100%;
  padding: 10px;
  background: var(--green, #16a34a);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.2s;
  margin-top: auto;
}
.save-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.save-btn:not(:disabled):hover { opacity: 0.9; }

@media (max-width: 900px) {
  .cert-editor {
    grid-template-columns: 1fr;
  }
}
</style>
