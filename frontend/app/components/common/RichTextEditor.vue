<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import Image from '@tiptap/extension-image'
import TextAlign from '@tiptap/extension-text-align'
import { TextStyle } from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import Highlight from '@tiptap/extension-highlight'

const props = withDefaults(defineProps<{
  modelValue?: string | null
  height?: string
  placeholder?: string
  readonly?: boolean
  /** Inline variant: single-row toolbar, short body — for list items. */
  compact?: boolean
}>(), {
  modelValue: '',
  height: '200px',
  placeholder: '',
  readonly: false,
  compact: false,
})

const emit = defineEmits<{ 'update:modelValue': [string] }>()

const editor = useEditor({
  content: props.modelValue || '',
  editable: !props.readonly,
  immediatelyRender: false,
  extensions: [
    StarterKit.configure({
      heading: props.compact ? false : { levels: [1, 2, 3] },
      codeBlock: props.compact ? false : undefined,
    }),
    Underline,
    Link.configure({ openOnClick: false, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } }),
    Placeholder.configure({ placeholder: props.placeholder || 'Nhập nội dung...' }),
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    TextStyle,
    Color,
    Highlight.configure({ multicolor: true }),
    ...(props.compact ? [] : [Image.configure({ inline: false })]),
  ],
  editorProps: {
    attributes: {
      class: 'rte__body-inner',
    },
  },
  onUpdate: ({ editor: ed }) => {
    const html = ed.getHTML()
    emit('update:modelValue', html === '<p></p>' ? '' : html)
  },
})

watch(() => props.modelValue, (value) => {
  if (!editor.value) return
  const next = value || ''
  if (next === editor.value.getHTML()) return
  if (next === '' && editor.value.getHTML() === '<p></p>') return
  editor.value.commands.setContent(next, { emitUpdate: false })
})

watch(() => props.readonly, (readonly) => {
  editor.value?.setEditable(!readonly)
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})

function setLink() {
  if (!editor.value || props.readonly) return
  const current = editor.value.getAttributes('link').href || ''
  const url = window.prompt('Nhập liên kết', current)
  if (url === null) return
  if (!url) {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }
  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

function addImage() {
  if (!editor.value || props.readonly || props.compact) return
  const url = window.prompt('URL ảnh')
  if (!url) return
  editor.value.chain().focus().setImage({ src: url }).run()
}

const bodyStyle = computed(() => ({
  minHeight: props.compact ? '90px' : props.height,
}))
</script>

<template>
  <ClientOnly>
    <div v-if="editor" class="rte" :class="{ 'rte--compact': compact, 'rte--readonly': readonly }">
      <div v-if="!readonly" class="rte__toolbar" role="toolbar">
        <template v-if="!compact">
          <button type="button" title="Heading 1" :class="{ active: editor.isActive('heading', { level: 1 }) }" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">H1</button>
          <button type="button" title="Heading 2" :class="{ active: editor.isActive('heading', { level: 2 }) }" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
          <button type="button" title="Heading 3" :class="{ active: editor.isActive('heading', { level: 3 }) }" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</button>
          <span class="rte__sep" />
        </template>

        <button type="button" title="Bold" :class="{ active: editor.isActive('bold') }" @click="editor.chain().focus().toggleBold().run()"><b>B</b></button>
        <button type="button" title="Italic" :class="{ active: editor.isActive('italic') }" @click="editor.chain().focus().toggleItalic().run()"><i>I</i></button>
        <button type="button" title="Underline" :class="{ active: editor.isActive('underline') }" @click="editor.chain().focus().toggleUnderline().run()"><u>U</u></button>
        <span class="rte__sep" />

        <button type="button" title="Bullet list" :class="{ active: editor.isActive('bulletList') }" @click="editor.chain().focus().toggleBulletList().run()">•</button>
        <button type="button" title="Ordered list" :class="{ active: editor.isActive('orderedList') }" @click="editor.chain().focus().toggleOrderedList().run()">1.</button>

        <template v-if="!compact">
          <span class="rte__sep" />
          <button type="button" title="Align left" :class="{ active: editor.isActive({ textAlign: 'left' }) }" @click="editor.chain().focus().setTextAlign('left').run()">⟸</button>
          <button type="button" title="Align center" :class="{ active: editor.isActive({ textAlign: 'center' }) }" @click="editor.chain().focus().setTextAlign('center').run()">⇔</button>
          <button type="button" title="Align right" :class="{ active: editor.isActive({ textAlign: 'right' }) }" @click="editor.chain().focus().setTextAlign('right').run()">⟹</button>
          <span class="rte__sep" />
          <label class="rte__color" title="Text color">
            A
            <input type="color" value="#101a19" @input="editor.chain().focus().setColor(($event.target as HTMLInputElement).value).run()">
          </label>
          <label class="rte__color" title="Highlight">
            ▮
            <input type="color" value="#fef08a" @input="editor.chain().focus().toggleHighlight({ color: ($event.target as HTMLInputElement).value }).run()">
          </label>
        </template>

        <span class="rte__sep" />
        <button type="button" title="Link" :class="{ active: editor.isActive('link') }" @click="setLink">Link</button>
        <button v-if="!compact" type="button" title="Image" @click="addImage">Img</button>
        <button v-if="!compact" type="button" title="Code block" :class="{ active: editor.isActive('codeBlock') }" @click="editor.chain().focus().toggleCodeBlock().run()">&lt;/&gt;</button>
        <button type="button" title="Clear formatting" @click="editor.chain().focus().unsetAllMarks().clearNodes().run()">✕</button>
      </div>

      <EditorContent class="rte__body" :style="bodyStyle" :editor="editor" />
    </div>

    <template #fallback>
      <div class="rte-skeleton" :style="{ height: compact ? '130px' : `calc(${height} + 42px)` }" />
    </template>
  </ClientOnly>
</template>

<style scoped>
.rte {
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  overflow: hidden;
}

.rte--readonly {
  background: color-mix(in srgb, var(--surface) 96%, transparent);
}

.rte__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  padding: 6px 8px;
  border-bottom: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 96%, transparent);
}

.rte--compact .rte__toolbar {
  padding: 4px 6px;
}

.rte__toolbar button,
.rte__color {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 30px;
  height: 30px;
  padding: 0 8px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--surface);
  color: var(--text);
  font-size: .8rem;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
}

.rte__toolbar button:hover,
.rte__color:hover {
  background: var(--surface-hover);
  border-color: var(--border-strong);
}

.rte__toolbar button.active {
  background: var(--brand-soft);
  border-color: var(--brand);
  color: var(--brand);
}

.rte__sep {
  width: 1px;
  height: 20px;
  margin: 0 2px;
  background: var(--border);
}

.rte__color {
  position: relative;
  overflow: hidden;
}

.rte__color input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
  border: 0;
  padding: 0;
}

.rte__body {
  min-width: 0;
}

.rte__body :deep(.rte__body-inner) {
  outline: none;
  min-height: inherit;
  padding: 10px 12px;
  font-size: .95rem;
  line-height: 1.55;
  color: var(--text);
  word-break: break-word;
}

.rte--compact .rte__body :deep(.rte__body-inner) {
  padding: 8px 10px;
  font-size: .9rem;
}

.rte__body :deep(.rte__body-inner p) {
  margin: 0 0 .4em;
}

.rte__body :deep(.rte__body-inner p:last-child) {
  margin-bottom: 0;
}

.rte__body :deep(.rte__body-inner h1),
.rte__body :deep(.rte__body-inner h2),
.rte__body :deep(.rte__body-inner h3) {
  margin: .5em 0 .35em;
  font-family: var(--font-display);
  line-height: 1.25;
}

.rte__body :deep(.rte__body-inner ul),
.rte__body :deep(.rte__body-inner ol) {
  margin: .3em 0;
  padding-left: 1.25em;
}

.rte__body :deep(.rte__body-inner img) {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
}

.rte__body :deep(.rte__body-inner a) {
  color: var(--brand);
  text-decoration: underline;
}

.rte__body :deep(.rte__body-inner pre) {
  margin: .4em 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: #17201f;
  color: #eef4f2;
  overflow-x: auto;
  font-size: .85rem;
}

.rte__body :deep(.rte__body-inner p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  float: left;
  height: 0;
  pointer-events: none;
  color: var(--text-muted);
}

.rte-skeleton {
  border: 1px solid var(--border);
  border-radius: 10px;
  background: color-mix(in srgb, var(--surface) 96%, transparent);
}
</style>
