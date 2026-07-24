<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    modelValue: File | null
    label?: string
    hint?: string
    accept?: string
    maxSizeMb?: number
    disabled?: boolean
    uploading?: boolean
    progress?: number
    existingUrl?: string | null
    icon?: string
  }>(),
  {
    label: 'Tải tệp lên',
    hint: 'Kéo thả hoặc chọn tệp',
    accept: '*/*',
    maxSizeMb: 200,
    disabled: false,
    uploading: false,
    progress: 0,
    existingUrl: null,
    icon: 'pi pi-cloud-upload',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: File | null]
  error: [message: string]
}>()

const { t } = useI18n()
const inputEl = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const errorMessage = ref('')

const fileName = computed(() => props.modelValue?.name || '')
const hasFile = computed(() => Boolean(props.modelValue || props.existingUrl))

function openPicker() {
  if (props.disabled || props.uploading) return
  inputEl.value?.click()
}

function onDragOver(e: DragEvent) {
  e.preventDefault()
  if (props.disabled || props.uploading) return
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

async function onDrop(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
  if (props.disabled || props.uploading) return
  const file = e.dataTransfer?.files?.[0]
  if (file) takeFile(file)
}

function onChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) takeFile(file)
  ;(e.target as HTMLInputElement).value = ''
}

function takeFile(file: File) {
  errorMessage.value = ''
  if (file.size > props.maxSizeMb * 1024 * 1024) {
    const msg = t('upload.tooLarge', { n: props.maxSizeMb })
    errorMessage.value = msg
    emit('error', msg)
    return
  }
  emit('update:modelValue', file)
}

function clear() {
  if (props.disabled || props.uploading) return
  emit('update:modelValue', null)
}
</script>

<template>
  <div class="drop" :class="{ dragging: isDragging, disabled, busy: uploading }">
    <div
      class="zone"
      role="button"
      tabindex="0"
      @click="openPicker"
      @keydown.enter.prevent="openPicker"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @drop="onDrop"
    >
      <div class="icon-wrap"><i :class="icon" /></div>
      <div class="copy">
        <strong>{{ label }}</strong>
        <p v-if="fileName">{{ fileName }}</p>
        <p v-else-if="existingUrl && !modelValue" class="muted truncate">{{ existingUrl }}</p>
        <p v-else class="muted">{{ hint }}</p>
      </div>
      <div class="actions" @click.stop>
        <Button
          type="button"
          :label="hasFile ? t('upload.replace') : t('upload.choose')"
          :icon="hasFile ? 'pi pi-refresh' : 'pi pi-folder-open'"
          size="small"
          :disabled="disabled || uploading"
          @click="openPicker"
        />
        <Button
          v-if="modelValue"
          type="button"
          :label="t('upload.remove')"
          icon="pi pi-trash"
          severity="secondary"
          text
          size="small"
          :disabled="disabled || uploading"
          @click="clear"
        />
      </div>
    </div>

    <ProgressBar v-if="uploading || progress > 0" :value="progress" class="bar" />
    <p v-if="errorMessage" class="error">{{ errorMessage }}</p>

    <input
      ref="inputEl"
      type="file"
      class="hidden"
      :accept="accept"
      :disabled="disabled || uploading"
      @change="onChange"
    >
  </div>
</template>

<style scoped>
.drop { width: 100%; }
.zone {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 14px;
  align-items: center;
  padding: 16px;
  border: 1.5px dashed color-mix(in srgb, var(--border) 85%, var(--brand));
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
  cursor: pointer;
}
.dragging .zone {
  border-color: var(--brand);
  background: var(--brand-soft);
}
.disabled .zone, .busy .zone { opacity: .65; cursor: default; }
.icon-wrap {
  width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center;
  background: var(--brand-soft); color: var(--brand); font-size: 1.15rem;
}
.copy { min-width: 0; }
.copy strong { display: block; font-size: .95rem; }
.copy p { margin: 4px 0 0; font-size: .85rem; font-weight: 550; }
.muted { color: var(--text-muted); }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 42vw; }
.actions { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; }
.bar { margin-top: 10px; height: 8px; }
.error { margin: 8px 0 0; color: var(--p-red-500, #c0392b); font-size: .82rem; font-weight: 650; }
.hidden { display: none; }
@media (max-width: 700px) {
  .zone { grid-template-columns: auto 1fr; }
  .actions { grid-column: 1 / -1; }
}
</style>
