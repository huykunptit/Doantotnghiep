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

