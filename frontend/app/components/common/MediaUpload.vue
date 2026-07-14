<script setup lang="ts">
import { computed, ref, watch } from 'vue'
// Icons removed - using PrimeIcons
import { useAdminUpload } from '~/composables/useAdminUpload'

type Folder = 'users' | 'settings' | 'courses'
type Variant = 'avatar' | 'thumbnail' | 'banner' | 'square'

const props = withDefaults(
  defineProps<{
    modelValue: string | null | undefined
    folder: Folder
    label?: string
    hint?: string
    accept?: string
    maxSizeMb?: number
    variant?: Variant
    placeholderInitial?: string
    disabled?: boolean
    /** Auto-delete the previous file on the server when uploading a new one. */
    cleanupPrevious?: boolean
  }>(),
  {
    label: 'Tải ảnh lên',
    hint: 'PNG, JPG, WEBP — tối đa 5MB',
    accept: 'image/*',
    maxSizeMb: 5,
    variant: 'thumbnail',
    placeholderInitial: '?',
    disabled: false,
    cleanupPrevious: true,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
  uploaded: [payload: { url: string; path: string }]
  error: [message: string]
}>()

const { uploadImage } = useAdminUpload()

const inputEl = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const isUploading = ref(false)
const errorMessage = ref('')
const lastPath = ref<string | null>(null)

const currentUrl = computed(() => props.modelValue || null)
const hasImage = computed(() => Boolean(currentUrl.value))

const variantClass = computed(() => `media-upload-preview--${props.variant}`)

watch(
  () => props.modelValue,
  (val) => {
    // Reset cached path when caller swaps the value externally.
    if (!val) lastPath.value = null
  },
)

function openPicker() {
  if (props.disabled || isUploading.value) return
  inputEl.value?.click()
}

function onDragOver(event: DragEvent) {
  event.preventDefault()
  if (props.disabled || isUploading.value) return
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

async function onDrop(event: DragEvent) {
  event.preventDefault()
  isDragging.value = false
  if (props.disabled || isUploading.value) return
  const file = event.dataTransfer?.files?.[0]
  if (file) await handleFile(file)
}

async function onChange(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) await handleFile(file)
  // Allow re-selecting the same file later.
  target.value = ''
}

async function handleFile(file: File) {
  errorMessage.value = ''

  if (props.accept.startsWith('image/') && !file.type.startsWith('image/')) {
    fail('Vui lòng chọn tệp hình ảnh.')
    return
  }
  if (file.size > props.maxSizeMb * 1024 * 1024) {
    fail(`Tệp vượt quá ${props.maxSizeMb}MB.`)
    return
  }

  isUploading.value = true
  try {
    const response = await uploadImage(
      file,
      props.folder,
      props.cleanupPrevious ? lastPath.value : null,
    )
    lastPath.value = response.path
    emit('update:modelValue', response.url)
    emit('uploaded', { url: response.url, path: response.path })
  } catch (err: any) {
    fail(err?.data?.message || err?.message || 'Tải lên thất bại.')
  } finally {
    isUploading.value = false
  }
}

function fail(message: string) {
  errorMessage.value = message
  emit('error', message)
}

function clear() {
  if (props.disabled || isUploading.value) return
  lastPath.value = null
  emit('update:modelValue', null)
}
</script>

<template>
  <div class="media-upload" :class="{ 'is-disabled': disabled, 'is-dragging': isDragging }">
    <div class="media-upload-shell">
      <div
        class="media-upload-preview"
        :class="variantClass"
        @click="openPicker"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
      >
        <img v-if="hasImage" :src="currentUrl!" :alt="label" class="media-upload-image">
        <span v-else class="media-upload-initial">{{ placeholderInitial }}</span>

        <div v-if="isUploading" class="media-upload-overlay">
          <span class="media-upload-spinner" />
          <span>Đang tải...</span>
        </div>
      </div>

      <div class="media-upload-body">
        <p class="media-upload-label">{{ label }}</p>
        <p class="media-upload-hint">{{ hint }}</p>

        <div class="media-upload-actions">
          <button
            type="button"
            class="media-upload-btn is-primary"
            :disabled="disabled || isUploading"
            @click="openPicker"
          >
            <RefreshCw v-if="hasImage" :size="18" :stroke-width="1.75" />
            <CloudUpload v-else :size="18" :stroke-width="1.75" />
            <span>{{ hasImage ? 'Thay ảnh' : 'Chọn tệp' }}</span>
          </button>
          <button
            v-if="hasImage"
            type="button"
            class="media-upload-btn is-ghost"
            :disabled="disabled || isUploading"
            @click="clear"
          >
            <i class="pi pi-trash" style="font-size:1.125rem" />
            <span>Xoá</span>
          </button>
        </div>

        <p v-if="errorMessage" class="media-upload-error">
          <i class="pi pi-exclamation-circle" style="font-size:1.0rem" />
          {{ errorMessage }}
        </p>
      </div>
    </div>

    <input
      ref="inputEl"
      type="file"
      :accept="accept"
      class="media-upload-input"
      :disabled="disabled || isUploading"
      @change="onChange"
    >
  </div>
</template>

<style scoped>
.media-upload {
  width: 100%;
}

.media-upload-shell {
  display: flex;
  align-items: stretch;
  gap: 16px;
  padding: 14px;
  border: 1.5px dashed rgba(17, 17, 17, 0.14);
  border-radius: 16px;
  background: rgba(var(--green-rgb), 0.025);
  transition: border-color 160ms ease, background-color 160ms ease;
}

.media-upload.is-dragging .media-upload-shell {
  border-color: rgba(var(--green-rgb), 0.55);
  background: rgba(var(--green-rgb), 0.08);
}

.media-upload.is-disabled {
  opacity: 0.6;
  pointer-events: none;
}

.media-upload-preview {
  position: relative;
  flex-shrink: 0;
  background: rgba(var(--green-rgb), 0.08);
  display: grid;
  place-items: center;
  cursor: pointer;
  overflow: hidden;
  color: var(--green-deep);
  transition: transform 160ms ease, box-shadow 160ms ease;
}
.media-upload-preview:hover {
  transform: translateY(-1px);
}

.media-upload-preview--avatar {
  width: 96px;
  height: 96px;
  border-radius: 50%;
}
.media-upload-preview--thumbnail {
  width: 144px;
  height: 96px;
  border-radius: 12px;
}
.media-upload-preview--banner {
  width: 240px;
  height: 96px;
  border-radius: 12px;
}
.media-upload-preview--square {
  width: 96px;
  height: 96px;
  border-radius: 12px;
}

.media-upload-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.media-upload-initial {
  font-weight: 800;
  font-size: 1.4rem;
  letter-spacing: 0.02em;
}

.media-upload-overlay {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  gap: 6px;
  background: rgba(17, 17, 17, 0.55);
  color: #fff;
  font-size: 0.78rem;
  font-weight: 600;
}

.media-upload-spinner {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 2.5px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  animation: media-spin 0.7s linear infinite;
}
@keyframes media-spin { to { transform: rotate(360deg); } }

.media-upload-body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.media-upload-label {
  margin: 0;
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--text);
}

.media-upload-hint {
  margin: 0;
  font-size: 0.8rem;
  color: var(--muted);
  line-height: 1.45;
}

.media-upload-actions {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}

.media-upload-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 140ms ease, border-color 140ms ease, color 140ms ease, transform 140ms ease;
  white-space: nowrap;
}
.media-upload-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.media-upload-btn.is-primary {
  background: var(--green-deep, #166534);
  color: #fff;
}
.media-upload-btn.is-primary:hover:not(:disabled) {
  background: var(--green, #16a34a);
  transform: translateY(-1px);
}

.media-upload-btn.is-ghost {
  background: transparent;
  color: var(--muted);
  border-color: rgba(17, 17, 17, 0.12);
}
.media-upload-btn.is-ghost:hover:not(:disabled) {
  background: rgba(220, 38, 38, 0.08);
  color: #b91c1c;
  border-color: rgba(220, 38, 38, 0.3);
}

.media-upload-error {
  margin: 6px 0 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: #b91c1c;
}

.media-upload-input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
  overflow: hidden;
}
</style>
