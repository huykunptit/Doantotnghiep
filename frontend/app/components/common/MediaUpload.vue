<script setup lang="ts">
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
  uploaded: [payload: { url: string, path: string }]
  error: [message: string]
}>()

const { uploadImage } = useAdminUpload()
const { t } = useI18n()

const inputEl = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const isUploading = ref(false)
const errorMessage = ref('')
const lastPath = ref<string | null>(null)

const currentUrl = computed(() => props.modelValue || null)
const hasImage = computed(() => Boolean(currentUrl.value))
const variantClass = computed(() => `preview--${props.variant}`)

watch(() => props.modelValue, (val) => {
  if (!val) lastPath.value = null
})

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
  target.value = ''
}

async function handleFile(file: File) {
  errorMessage.value = ''
  if (props.accept.startsWith('image/') && !file.type.startsWith('image/')) {
    fail(t('upload.imageOnly'))
    return
  }
  if (file.size > props.maxSizeMb * 1024 * 1024) {
    fail(t('upload.tooLarge', { n: props.maxSizeMb }))
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
  }
  catch (err: any) {
    fail(err?.data?.message || err?.message || t('upload.failed'))
  }
  finally {
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
  <div class="media-upload" :class="{ disabled, dragging: isDragging }">
    <div class="shell">
      <div
        class="preview"
        :class="variantClass"
        role="button"
        tabindex="0"
        @click="openPicker"
        @keydown.enter.prevent="openPicker"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
      >
        <img v-if="hasImage" :src="currentUrl!" :alt="label" class="image">
        <span v-else class="initial">{{ placeholderInitial }}</span>
        <div v-if="isUploading" class="overlay">
          <span class="spinner" />
          <span>{{ t('upload.uploading') }}</span>
        </div>
      </div>

      <div class="body">
        <p class="label">{{ label }}</p>
        <p class="hint">{{ hint }}</p>
        <div class="actions">
          <button type="button" class="btn primary" :disabled="disabled || isUploading" @click="openPicker">
            <i :class="hasImage ? 'pi pi-refresh' : 'pi pi-cloud-upload'" />
            <span>{{ hasImage ? t('upload.replace') : t('upload.choose') }}</span>
          </button>
          <button
            v-if="hasImage"
            type="button"
            class="btn ghost"
            :disabled="disabled || isUploading"
            @click="clear"
          >
            <i class="pi pi-trash" />
            <span>{{ t('upload.remove') }}</span>
          </button>
        </div>
        <p v-if="errorMessage" class="error">
          <i class="pi pi-exclamation-circle" />
          {{ errorMessage }}
        </p>
      </div>
    </div>

    <input
      ref="inputEl"
      type="file"
      :accept="accept"
      class="hidden"
      :disabled="disabled || isUploading"
      @change="onChange"
    >
  </div>
</template>

<style scoped>
.media-upload { width: 100%; }
.shell {
  display: flex; align-items: stretch; gap: 16px; padding: 14px;
  border: 1.5px dashed color-mix(in srgb, var(--border) 90%, var(--brand));
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.dragging .shell {
  border-color: var(--brand);
  background: var(--brand-soft);
}
.disabled { opacity: .55; pointer-events: none; }
.preview {
  position: relative; flex-shrink: 0; display: grid; place-items: center;
  overflow: hidden; cursor: pointer; background: var(--brand-soft); color: var(--brand);
}
.preview--avatar { width: 96px; height: 96px; border-radius: 50%; }
.preview--thumbnail { width: 144px; height: 96px; border-radius: 12px; }
.preview--banner { width: min(240px, 42vw); height: 96px; border-radius: 12px; }
.preview--square { width: 96px; height: 96px; border-radius: 12px; }
.image { width: 100%; height: 100%; object-fit: cover; display: block; }
.initial { font-weight: 800; font-size: 1.35rem; }
.overlay {
  position: absolute; inset: 0; display: grid; place-items: center; gap: 6px;
  background: rgba(15, 23, 42, .55); color: #fff; font-size: .78rem; font-weight: 650;
}
.spinner {
  width: 22px; height: 22px; border-radius: 50%;
  border: 2.5px solid rgba(255,255,255,.3); border-top-color: #fff;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.body { display: flex; flex-direction: column; gap: 6px; min-width: 0; flex: 1; }
.label { margin: 0; font-weight: 750; color: var(--text); }
.hint { margin: 0; color: var(--text-muted); font-size: .85rem; font-weight: 500; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.btn {
  display: inline-flex; align-items: center; gap: 8px; border-radius: 999px;
  padding: 8px 12px; font: inherit; font-size: .85rem; font-weight: 700; cursor: pointer;
}
.btn.primary { border: 0; background: var(--brand); color: #fff; }
.btn.ghost {
  border: 1px solid var(--border); background: transparent; color: var(--text-muted);
}
.btn:disabled { opacity: .5; cursor: not-allowed; }
.error {
  margin: 4px 0 0; display: flex; gap: 6px; align-items: center;
  color: var(--p-red-500, #c0392b); font-size: .82rem; font-weight: 600;
}
.hidden { display: none; }
@media (max-width: 560px) {
  .shell { flex-direction: column; align-items: flex-start; }
}
</style>
