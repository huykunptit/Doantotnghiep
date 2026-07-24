<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import Image from '@tiptap/extension-image'
import { useAdminUpload } from '~/composables/useAdminUpload'

const props = withDefaults(defineProps<{
  modelValue: string
  placeholder?: string
  enableImages?: boolean
  uploadFolder?: 'users' | 'settings' | 'courses'
}>(), {
  placeholder: 'Nhập nội dung...',
  enableImages: false,
  uploadFolder: 'courses',
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const { uploadImage } = useAdminUpload()
const fileInput = ref<HTMLInputElement | null>(null)
const uploadingImage = ref(false)

const editor = useEditor({
  content: props.modelValue || '',
  extensions: [
    StarterKit,
    Underline,
    Link.configure({ openOnClick: false }),
    Image.configure({ inline: false }),
    Placeholder.configure({ placeholder: props.placeholder }),
  ],
  editorProps: { attributes: { class: 'rich-editor__content-inner' } },
  onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
})

watch(() => props.modelValue, (value) => {
  if (!editor.value || value === editor.value.getHTML()) return
  editor.value.commands.setContent(value || '', false)
})

onBeforeUnmount(() => editor.value?.destroy())

function setLink() {
  const current = editor.value?.getAttributes('link').href || ''
  const url = window.prompt('Nhập liên kết', current)
  if (!editor.value || url === null) return
  if (!url) return editor.value.chain().focus().unsetLink().run()
  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

function triggerImagePicker() {
  fileInput.value?.click()
}

async function onImageSelected(event: Event) {
  const file = (event.target as HTMLInputElement)?.files?.[0]
  if (!file || !editor.value) return

  uploadingImage.value = true
  try {
    const uploaded = await uploadImage(file, props.uploadFolder)
    editor.value.chain().focus().setImage({ src: uploaded.url, alt: file.name }).run()
  } finally {
    uploadingImage.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}
</script>

<template>
  <ClientOnly>
    <div class="rich-editor" v-if="editor">
      <div class="rich-editor__toolbar">
        <button type="button" :class="{ active: editor.isActive('bold') }" @click="editor.chain().focus().toggleBold().run()">B</button>
        <button type="button" :class="{ active: editor.isActive('italic') }" @click="editor.chain().focus().toggleItalic().run()">I</button>
        <button type="button" :class="{ active: editor.isActive('underline') }" @click="editor.chain().focus().toggleUnderline().run()">U</button>
        <button type="button" :class="{ active: editor.isActive('strike') }" @click="editor.chain().focus().toggleStrike().run()">S</button>
        <button type="button" @click="setLink">Link</button>
        <button type="button" :class="{ active: editor.isActive('heading', { level: 1 }) }" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">H1</button>
        <button type="button" :class="{ active: editor.isActive('heading', { level: 2 }) }" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
        <button type="button" :class="{ active: editor.isActive('bulletList') }" @click="editor.chain().focus().toggleBulletList().run()">• List</button>
        <button type="button" :class="{ active: editor.isActive('orderedList') }" @click="editor.chain().focus().toggleOrderedList().run()">1. List</button>
        <button type="button" :class="{ active: editor.isActive('blockquote') }" @click="editor.chain().focus().toggleBlockquote().run()">❝</button>
        <button type="button" :class="{ active: editor.isActive('codeBlock') }" @click="editor.chain().focus().toggleCodeBlock().run()">&lt;/&gt;</button>
        <button v-if="enableImages" type="button" :disabled="uploadingImage" @click="triggerImagePicker">{{ uploadingImage ? '...' : 'Image' }}</button>
        <button type="button" @click="editor.chain().focus().undo().run()">↺</button>
        <button type="button" @click="editor.chain().focus().redo().run()">↻</button>
        <input ref="fileInput" class="rich-editor__file-input" type="file" accept="image/*" @change="onImageSelected" />
      </div>
      <EditorContent :editor="editor" class="rich-editor__content" />
    </div>
    <template #fallback><div class="crud-textarea">Đang tải trình soạn thảo...</div></template>
  </ClientOnly>
</template>

<style scoped>
.rich-editor {
  border: 1px solid #dbe6f5;
  border-radius: 12px;
  overflow: hidden;
  background: #fff;
}

.rich-editor__toolbar {
  display: flex;
  gap: 4px;
  padding: 8px;
  background: #f8fbff;
  border-bottom: 1px solid #dbe6f5;
  flex-wrap: wrap;
}

.rich-editor__toolbar button {
  padding: 6px 12px;
  border: 1px solid #dbe6f5;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.85rem;
  transition: 0.15s ease;
}

.rich-editor__toolbar button:hover:not(:disabled) {
  background: #dbeafe;
  border-color: #60a5fa;
}

.rich-editor__toolbar button.active {
  background: var(--green, #1d9e75);
  color: white;
  border-color: var(--green, #1d9e75);
}

.rich-editor__toolbar button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.rich-editor__content {
  min-height: 200px;
  padding: 12px;
}

.rich-editor__content :deep(.rich-editor__content-inner) {
  outline: none;
  word-wrap: break-word;
  min-height: 200px;
  padding: 8px;
  line-height: 1.6;
}

.rich-editor__content :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
  margin: 8px 0;
}

.rich-editor__content :deep(p) {
  margin: 8px 0;
}

.rich-editor__content :deep(h1) {
  font-size: 1.5rem;
  font-weight: 900;
  margin: 12px 0 8px;
}

.rich-editor__content :deep(h2) {
  font-size: 1.25rem;
  font-weight: 800;
  margin: 12px 0 8px;
}

.rich-editor__content :deep(ul),
.rich-editor__content :deep(ol) {
  margin: 8px 0 8px 24px;
}

.rich-editor__content :deep(li) {
  margin: 4px 0;
}

.rich-editor__content :deep(blockquote) {
  border-left: 4px solid var(--green, #1d9e75);
  padding-left: 12px;
  color: #64748b;
  font-style: italic;
  margin: 8px 0;
}

.rich-editor__content :deep(code) {
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.9em;
}

.rich-editor__content :deep(pre) {
  background: #1e293b;
  color: #e2e8f0;
  padding: 12px;
  border-radius: 6px;
  overflow-x: auto;
  margin: 8px 0;
}

.rich-editor__content :deep(a) {
  color: var(--green, #1d9e75);
  text-decoration: underline;
  cursor: pointer;
}

.rich-editor__file-input {
  display: none;
}
</style>