<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  modelValue?: string
  label?: string
  placeholder?: string
  disabled?: boolean
  error?: string
  hint?: string
  size?: 'md' | 'lg'
}>(), {
  modelValue: '',
  label: '',
  placeholder: 'Chọn ngày...',
  disabled: false,
  error: '',
  hint: '',
  size: 'md',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const isOpen = ref(false)
const containerRef = ref<HTMLElement | null>(null)

// Parse modelValue (YYYY-MM-DD) into display value
const parseDate = (val: string) => {
  if (!val) return null
  const parts = val.split('-')
  if (parts.length !== 3) return null
  const year = parseInt(parts[0], 10)
  const month = parseInt(parts[1], 10) - 1
  const day = parseInt(parts[2], 10)
  return new Date(year, month, day)
}

const selectedDate = computed(() => parseDate(props.modelValue))

// For calendar view state
const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())

// Sync calendar view when props.modelValue changes
watch(() => props.modelValue, (newVal) => {
  const parsed = parseDate(newVal)
  if (parsed) {
    currentMonth.value = parsed.getMonth()
    currentYear.value = parsed.getFullYear()
  }
}, { immediate: true })

const monthNames = [
  'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4',
  'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8',
  'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'
]

const weekdays = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7']

// Calendar days generation
const daysInMonth = (month: number, year: number) => new Date(year, month + 1, 0).getDate()
const startDayOfWeek = (month: number, year: number) => new Date(year, month, 1).getDay()

const calendarDays = computed(() => {
  const days = []
  const prevMonth = currentMonth.value === 0 ? 11 : currentMonth.value - 1
  const prevYear = currentMonth.value === 0 ? currentYear.value - 1 : currentYear.value
  const nextMonth = currentMonth.value === 11 ? 0 : currentMonth.value + 1
  const nextYear = currentMonth.value === 11 ? currentYear.value + 1 : currentYear.value

  const totalDays = daysInMonth(currentMonth.value, currentYear.value)
  const prevTotalDays = daysInMonth(prevMonth, prevYear)
  const startDay = startDayOfWeek(currentMonth.value, currentYear.value)

  // Previous month padding days
  for (let i = startDay - 1; i >= 0; i--) {
    days.push({
      day: prevTotalDays - i,
      month: prevMonth,
      year: prevYear,
      isCurrentMonth: false,
    })
  }

  // Current month days
  for (let i = 1; i <= totalDays; i++) {
    days.push({
      day: i,
      month: currentMonth.value,
      year: currentYear.value,
      isCurrentMonth: true,
    })
  }

  // Next month padding days
  const remaining = 42 - days.length
  for (let i = 1; i <= remaining; i++) {
    days.push({
      day: i,
      month: nextMonth,
      year: nextYear,
      isCurrentMonth: false,
    })
  }

  return days
})

const formattedDisplayDate = computed(() => {
  const date = selectedDate.value
  if (!date) return ''
  const dd = String(date.getDate()).padStart(2, '0')
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const yyyy = date.getFullYear()
  return `${dd}/${mm}/${yyyy}`
})

const prevMonthAction = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value -= 1
  } else {
    currentMonth.value -= 1
  }
}

const nextMonthAction = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value += 1
  } else {
    currentMonth.value += 1
  }
}

const selectDay = (cell: { day: number, month: number, year: number }) => {
  const mm = String(cell.month + 1).padStart(2, '0')
  const dd = String(cell.day).padStart(2, '0')
  const dateStr = `${cell.year}-${mm}-${dd}`
  emit('update:modelValue', dateStr)
  isOpen.value = false
}

const clearDate = (e: Event) => {
  e.stopPropagation()
  emit('update:modelValue', '')
}

const toggleOpen = () => {
  if (props.disabled) return
  isOpen.value = !isOpen.value
}

// Click outside handler
const handleClickOutside = (e: MouseEvent) => {
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

const isToday = (day: number, month: number, year: number) => {
  const today = new Date()
  return today.getDate() === day && today.getMonth() === month && today.getFullYear() === year
}

const isSelected = (day: number, month: number, year: number) => {
  const date = selectedDate.value
  if (!date) return false
  return date.getDate() === day && date.getMonth() === month && date.getFullYear() === year
}
</script>

<template>
  <div ref="containerRef" class="ui-datepicker-wrap">
    <span v-if="props.label" class="ui-datepicker-label">{{ props.label }}</span>
    
    <div class="ui-datepicker-input-container">
      <button
        type="button"
        :disabled="props.disabled"
        :class="[
          'ui-datepicker-btn',
          props.size === 'lg' ? 'ui-datepicker-btn--lg' : 'ui-datepicker-btn--md',
          props.error && 'ui-datepicker-btn--error',
          props.disabled && 'ui-datepicker-btn--disabled',
          isOpen && 'ui-datepicker-btn--active'
        ]"
        @click="toggleOpen"
      >
        <i class="pi pi-calendar ui-datepicker-icon-calendar" style="font-size:1rem" />
        <span class="ui-datepicker-value" :class="{ 'ui-datepicker-placeholder': !formattedDisplayDate }">
          {{ formattedDisplayDate || props.placeholder }}
        </span>
        <button
          v-if="formattedDisplayDate && !props.disabled"
          type="button"
          class="ui-datepicker-clear-btn"
          @click="clearDate"
        >
          <i class="pi pi-times" style="font-size:0.875rem" />
        </button>
      </button>

      <!-- Calendar Panel Dropdown -->
      <transition name="fade-slide">
        <div v-if="isOpen" class="ui-datepicker-panel">
          <div class="ui-datepicker-header">
            <button type="button" class="ui-datepicker-nav-btn" @click="prevMonthAction">
              <i class="pi pi-chevron-left" style="font-size:1rem" />
            </button>
            <div class="ui-datepicker-header-label">
              {{ monthNames[currentMonth] }} {{ currentYear }}
            </div>
            <button type="button" class="ui-datepicker-nav-btn" @click="nextMonthAction">
              <i class="pi pi-chevron-right" style="font-size:1rem" />
            </button>
          </div>

          <div class="ui-datepicker-weekdays">
            <span v-for="wd in weekdays" :key="wd" class="ui-datepicker-wd">
              {{ wd }}
            </span>
          </div>

          <div class="ui-datepicker-days-grid">
            <button
              v-for="(cell, idx) in calendarDays"
              :key="idx"
              type="button"
              :class="[
                'ui-datepicker-day-cell',
                !cell.isCurrentMonth && 'ui-datepicker-day-cell--outside',
                isToday(cell.day, cell.month, cell.year) && 'ui-datepicker-day-cell--today',
                isSelected(cell.day, cell.month, cell.year) && 'ui-datepicker-day-cell--selected'
              ]"
              @click="selectDay(cell)"
            >
              {{ cell.day }}
            </button>
          </div>
        </div>
      </transition>
    </div>

    <span v-if="props.error" class="ui-datepicker-error">{{ props.error }}</span>
    <span v-else-if="props.hint" class="ui-datepicker-hint">{{ props.hint }}</span>
  </div>
</template>

<style scoped>
.ui-datepicker-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
  position: relative;
  width: 100%;
}

.ui-datepicker-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text);
}

.ui-datepicker-input-container {
  position: relative;
  width: 100%;
}

.ui-datepicker-btn {
  display: flex;
  align-items: center;
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--surface-strong, #fff);
  color: var(--text);
  font: inherit;
  text-align: left;
  outline: none;
  cursor: pointer;
  position: relative;
  transition: border-color 150ms, box-shadow 150ms, background 150ms;
}

.ui-datepicker-btn--md {
  height: 40px;
  padding: 0 14px;
  font-size: 0.875rem;
}

.ui-datepicker-btn--lg {
  height: 48px;
  padding: 0 16px;
  font-size: 0.9375rem;
}

.ui-datepicker-btn:hover {
  border-color: var(--green);
}

.ui-datepicker-btn--active,
.ui-datepicker-btn:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px var(--green-soft);
}

.ui-datepicker-icon-calendar {
  color: var(--muted);
  margin-right: 10px;
  flex-shrink: 0;
}

.ui-datepicker-value {
  flex: 1;
}

.ui-datepicker-placeholder {
  color: var(--muted);
}

.ui-datepicker-clear-btn {
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  margin-left: 6px;
  transition: background 150ms, color 150ms;
}

.ui-datepicker-clear-btn:hover {
  background: rgba(0, 0, 0, 0.05);
  color: var(--text);
}

/* Dropdown Panel */
.ui-datepicker-panel {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  z-index: 50;
  width: 300px;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 16px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
  padding: 16px;
  user-select: none;
}

.ui-datepicker-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.ui-datepicker-header-label {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text);
}

.ui-datepicker-nav-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: transparent;
  color: var(--text);
  cursor: pointer;
  transition: all 150ms;
}

.ui-datepicker-nav-btn:hover {
  background: rgba(29, 158, 117, 0.08);
  border-color: var(--green);
  color: var(--green);
}

.ui-datepicker-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  text-align: center;
  margin-bottom: 6px;
}

.ui-datepicker-wd {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--muted);
  padding: 4px 0;
}

.ui-datepicker-days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.ui-datepicker-day-cell {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  border-radius: 8px;
  font-size: 0.825rem;
  font-weight: 500;
  color: var(--text);
  cursor: pointer;
  transition: all 150ms;
}

.ui-datepicker-day-cell:hover {
  background: rgba(29, 158, 117, 0.08);
  color: var(--green);
}

.ui-datepicker-day-cell--outside {
  color: var(--muted);
  opacity: 0.4;
}

.ui-datepicker-day-cell--today {
  border: 1px solid var(--green);
  color: var(--green);
  font-weight: 700;
}

.ui-datepicker-day-cell--selected {
  background: var(--green) !important;
  color: #fff !important;
  font-weight: 700;
  box-shadow: 0 4px 10px var(--green-soft);
}

.ui-datepicker-btn--error {
  border-color: var(--danger);
  background: var(--danger-soft);
}

.ui-datepicker-btn--error:focus {
  border-color: var(--danger);
  box-shadow: 0 0 0 3px var(--danger-soft);
}

.ui-datepicker-btn--disabled {
  cursor: not-allowed;
  opacity: 0.6;
  background: var(--surface);
}

.ui-datepicker-error {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--danger);
}

.ui-datepicker-hint {
  font-size: 0.75rem;
  color: var(--muted);
}

/* Animations */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: transform 150ms cubic-bezier(0.16, 1, 0.3, 1), opacity 150ms;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  transform: translateY(8px);
  opacity: 0;
}

/* Dark mode compatibility */
:global([data-theme="dark"]) .ui-datepicker-btn {
  background: var(--surface-strong, rgba(255, 255, 255, 0.03));
}
:global([data-theme="dark"]) .ui-datepicker-panel {
  background: #111a17;
  border-color: rgba(255, 255, 255, 0.08);
}
:global([data-theme="dark"]) .ui-datepicker-nav-btn {
  border-color: rgba(255, 255, 255, 0.1);
}
:global([data-theme="dark"]) .ui-datepicker-clear-btn:hover {
  background: rgba(255, 255, 255, 0.08);
}
</style>
