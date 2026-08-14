<script setup lang="ts">
interface FieldConfig {
  key: string
  visible?: boolean
  x?: number
  y?: number
  font_size?: number
  font_family?: string
  color?: string
  font_weight?: string
  text_align?: string
}

interface Template {
  name?: string | null
  background_image_url?: string | null
  fields_config?: FieldConfig[] | null
}

const props = withDefaults(defineProps<{
  studentName?: string
  courseTitle?: string
  issuedAt?: string | null
  credentialId?: string
  template?: Template | null
  compact?: boolean
}>(), {
  studentName: 'Học viên',
  courseTitle: 'Khóa học',
  issuedAt: null,
  credentialId: 'ERIPT-XXXX',
  compact: false,
})

const { locale } = useI18n()

type Theme = 'classic' | 'excellence' | 'academic' | 'professional'

const theme = computed<Theme>(() => {
  const url = (props.template?.background_image_url || '').toLowerCase()
  const name = (props.template?.name || '').toLowerCase()
  if (url.includes('excellence') || name.includes('xuất sắc') || name.includes('xuat sac') || name.includes('excellence')) {
    return 'excellence'
  }
  if (url.includes('academic') || name.includes('chính quy') || name.includes('chinh quy') || name.includes('ptit')) {
    return 'academic'
  }
  if (url.includes('professional') || name.includes('kỹ năng') || name.includes('ky nang') || name.includes('nghề') || name.includes('nghe')) {
    return 'professional'
  }
  return 'classic'
})

const kicker = computed(() => {
  switch (theme.value) {
    case 'excellence': return 'Chứng nhận xuất sắc'
    case 'academic': return 'Chứng chỉ chính quy'
    case 'professional': return 'Chứng nhận kỹ năng nghề'
    default: return 'Chứng chỉ hoàn thành'
  }
})

const issuedLabel = computed(() => {
  if (!props.issuedAt) return '—'
  return new Date(props.issuedAt).toLocaleDateString(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
})
</script>

<template>
  <article class="cert" :class="[`theme-${theme}`, { compact }]" :aria-label="kicker">
    <div class="frame">
      <div class="corner tl" />
      <div class="corner tr" />
      <div class="corner bl" />
      <div class="corner br" />

      <div class="seal">
        <span>ERIPT</span>
      </div>

      <p class="kicker">{{ kicker }}</p>
      <p class="org">Hệ thống học tập trực tuyến · PTIT</p>

      <p class="name">{{ studentName }}</p>

      <p class="awarded">đã hoàn thành khóa học</p>

      <p class="course">{{ courseTitle }}</p>

      <div class="meta">
        <div>
          <span>Ngày cấp</span>
          <strong>{{ issuedLabel }}</strong>
        </div>
        <div>
          <span>Mã xác nhận</span>
          <strong class="code">{{ credentialId }}</strong>
        </div>
      </div>

      <div class="signs">
        <div>
          <i />
          <span>Giảng viên phụ trách</span>
        </div>
        <div>
          <i />
          <span>Ban đào tạo</span>
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
.cert {
  --ink: #14213d;
  --muted: #5b6f6b;
  --accent: #0f766e;
  --paper: #fbf7ef;
  --line: color-mix(in srgb, var(--accent) 55%, #c4a574);
  position: relative;
  width: 100%;
  aspect-ratio: 1.414 / 1;
  background: var(--paper);
  color: var(--ink);
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 18px 40px -24px rgba(15, 23, 42, .45);
}
.cert.theme-excellence { --accent: #b8860b; --paper: #fff8e8; --ink: #3f2d04; }
.cert.theme-academic { --accent: #1e3a8a; --paper: #f4f7fb; --ink: #0f172a; }
.cert.theme-professional { --accent: #d4af37; --paper: #121826; --ink: #f8fafc; --muted: #cbd5e1; }

.frame {
  position: absolute; inset: 10px;
  border: 2px solid var(--line);
  padding: 6%;
  display: flex; flex-direction: column; align-items: center; text-align: center;
  background:
    radial-gradient(circle at 50% 0%, color-mix(in srgb, var(--accent) 12%, transparent), transparent 42%),
    var(--paper);
}
.frame::before {
  content: '';
  position: absolute; inset: 8px;
  border: 1px solid color-mix(in srgb, var(--line) 55%, transparent);
  pointer-events: none;
}

.corner {
  position: absolute; width: 28px; height: 28px;
  border-color: var(--accent); border-style: solid; border-width: 0;
}
.corner.tl { top: 4px; left: 4px; border-top-width: 3px; border-left-width: 3px; }
.corner.tr { top: 4px; right: 4px; border-top-width: 3px; border-right-width: 3px; }
.corner.bl { bottom: 4px; left: 4px; border-bottom-width: 3px; border-left-width: 3px; }
.corner.br { bottom: 4px; right: 4px; border-bottom-width: 3px; border-right-width: 3px; }

.seal {
  width: 52px; height: 52px; border-radius: 50%;
  display: grid; place-items: center;
  border: 2px solid var(--accent);
  color: var(--accent); font-size: .62rem; font-weight: 800; letter-spacing: .12em;
  margin-bottom: 6px;
}
.compact .seal { width: 32px; height: 32px; font-size: .42rem; }

.kicker {
  margin: 0; text-transform: uppercase; letter-spacing: .22em;
  font-size: clamp(.72rem, 1.6vw, .92rem); font-weight: 800; color: var(--accent);
}
.org { margin: 4px 0 0; font-size: .78rem; color: var(--muted); }
.compact .org { display: none; }

.name {
  margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif;
  font-size: clamp(1.35rem, 3.4vw, 2.15rem); font-weight: 700; line-height: 1.15;
}
.awarded { margin: 8px 0 0; font-size: .85rem; color: var(--muted); font-style: italic; }
.course {
  margin: 6px 0 0; max-width: 46ch;
  font-size: clamp(.95rem, 2vw, 1.15rem); font-weight: 650;
}

.meta {
  margin-top: auto; display: flex; gap: 32px; justify-content: center; flex-wrap: wrap;
  width: 100%; padding-top: 16px;
}
.meta span { display: block; font-size: .68rem; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
.meta strong { font-size: .88rem; }
.code { font-family: ui-monospace, 'Courier New', monospace; font-size: .78rem; }

.signs {
  display: grid; grid-template-columns: 1fr 1fr; gap: 40px; width: min(70%, 420px);
  margin-top: 18px;
}
.signs i {
  display: block; height: 1px; background: color-mix(in srgb, var(--ink) 28%, transparent);
  margin-bottom: 6px;
}
.signs span { font-size: .7rem; color: var(--muted); }

.compact .name { margin-top: 8px; }
.compact .awarded, .compact .signs { display: none; }
.compact .meta { gap: 12px; padding-top: 8px; }
.compact .meta span { font-size: .55rem; }
.compact .meta strong { font-size: .7rem; }
.compact .kicker { letter-spacing: .14em; }
</style>
