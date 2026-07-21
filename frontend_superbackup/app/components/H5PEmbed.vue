<template>
  <div class="h5p-embed">
    <iframe
      v-if="src"
      ref="iframeRef"
      :src="src"
      class="h5p-embed-frame"
      frameborder="0"
      allowfullscreen="allowfullscreen"
      allow="geolocation *; microphone *; camera *; midi *; encrypted-media *; fullscreen *"
      title="H5P interactive content"
    />
    <div v-else class="h5p-embed-empty">
      <span class="material-symbols-outlined">extension</span>
      <p>Chưa có link nhúng H5P cho bài học này.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps<{
  src?: string | null
}>()

const iframeRef = ref<HTMLIFrameElement | null>(null)
const RESIZER_SRC = 'https://h5p.org/sites/all/modules/h5p/library/js/h5p-resizer.js'
let injectedScript: HTMLScriptElement | null = null

// h5p-resizer listens for postMessage from H5P iframes and resizes them to
// match content height. It's idempotent: loading once globally is enough.
function ensureResizer() {
  if (!import.meta.client) return
  const existing = document.querySelector(`script[data-h5p-resizer="1"]`) as HTMLScriptElement | null
  if (existing) return
  const script = document.createElement('script')
  script.src = RESIZER_SRC
  script.async = true
  script.charset = 'UTF-8'
  script.dataset.h5pResizer = '1'
  document.head.appendChild(script)
  injectedScript = script
}

onMounted(() => {
  if (props.src) ensureResizer()
})

onBeforeUnmount(() => {
  // Leave the resizer in the page — it's harmless and useful for any other
  // H5P iframe that mounts later.
})
</script>

<style scoped>
.h5p-embed {
  width: 100%;
  height: 100%;
  min-height: 400px;
  background: #fff;
  position: relative;
  overflow: hidden;
}

.h5p-embed-frame {
  width: 100%;
  height: 100%;
  min-height: 400px;
  display: block;
  border: 0;
  background: #fff;
}

.h5p-embed-empty {
  width: 100%;
  height: 100%;
  min-height: 400px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #6b7280;
  background: #f9fafb;
  text-align: center;
  padding: 32px;
}

.h5p-embed-empty .material-symbols-outlined {
  font-size: 48px;
  color: #94a3b8;
}

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .h5p-embed, [data-theme="dark"] .h5p-embed-frame { background: var(--surface); }
[data-theme="dark"] .h5p-embed-empty { background: rgba(255, 255, 255, 0.02); color: var(--muted); }
</style>
