<script setup lang="ts">
import { computed, ref } from 'vue'

definePageMeta({ layout: 'admin' })

interface EmailTemplate {
  id: string
  name: string
  trigger: string
  subject: string
  description: string
  variables: string[]
  bodyHtml: string
}

const TEMPLATES: EmailTemplate[] = [
  {
    id: 'welcome',
    name: 'Chào mừng đăng ký',
    trigger: 'Khi người dùng đăng ký tài khoản mới',
    subject: 'Chào mừng bạn đến với Sylva LMS!',
    description: 'Gửi ngay sau khi đăng ký thành công, hướng dẫn người dùng xác minh email.',
    variables: ['{{user_name}}', '{{verify_url}}', '{{site_name}}'],
    bodyHtml: `<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
  <div style="background:#16a34a;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#fff;margin:0;font-size:22px;">Chào mừng {{user_name}}!</h1>
  </div>
  <div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;">
    <p>Cảm ơn bạn đã đăng ký tài khoản tại <strong>{{site_name}}</strong>.</p>
    <p>Vui lòng xác minh địa chỉ email để hoàn tất quá trình đăng ký:</p>
    <div style="text-align:center;margin:24px 0;">
      <a href="{{verify_url}}" style="background:#16a34a;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;">Xác minh Email</a>
    </div>
    <p style="font-size:13px;color:#6b7280;">Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.</p>
  </div>
</div>`,
  },
  {
    id: 'enrollment',
    name: 'Ghi danh thành công',
    trigger: 'Khi học viên đăng ký khoá học (sau thanh toán)',
    subject: 'Bạn đã đăng ký khoá học {{course_title}}',
    description: 'Xác nhận ghi danh, cung cấp link truy cập khoá học.',
    variables: ['{{user_name}}', '{{course_title}}', '{{course_url}}', '{{instructor_name}}'],
    bodyHtml: `<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
  <div style="background:#2563eb;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#fff;margin:0;font-size:20px;">Đăng ký khoá học thành công!</h1>
  </div>
  <div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;">
    <p>Xin chào <strong>{{user_name}}</strong>,</p>
    <p>Bạn đã đăng ký thành công khoá học <strong>{{course_title}}</strong> do <strong>{{instructor_name}}</strong> giảng dạy.</p>
    <div style="text-align:center;margin:24px 0;">
      <a href="{{course_url}}" style="background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;">Bắt đầu học ngay</a>
    </div>
  </div>
</div>`,
  },
  {
    id: 'certificate',
    name: 'Cấp chứng chỉ',
    trigger: 'Khi học viên hoàn thành khoá học',
    subject: 'Chúc mừng! Bạn đã nhận chứng chỉ {{course_title}}',
    description: 'Thông báo nhận chứng chỉ kèm link xác minh.',
    variables: ['{{user_name}}', '{{course_title}}', '{{credential_id}}', '{{verify_url}}'],
    bodyHtml: `<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
  <div style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:24px;text-align:center;border-radius:12px 12px 0 0;">
    <div style="font-size:48px;">🏆</div>
    <h1 style="color:#fff;margin:8px 0 0;font-size:20px;">Chứng chỉ được cấp!</h1>
  </div>
  <div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;">
    <p>Xin chào <strong>{{user_name}}</strong>,</p>
    <p>Chúc mừng bạn đã hoàn thành khoá học <strong>{{course_title}}</strong>!</p>
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:16px 0;text-align:center;">
      <p style="margin:0;font-size:13px;color:#6b7280;">Mã chứng nhận</p>
      <p style="margin:8px 0;font-size:18px;font-weight:700;font-family:monospace;">{{credential_id}}</p>
    </div>
    <div style="text-align:center;">
      <a href="{{verify_url}}" style="background:#f59e0b;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;">Xem chứng chỉ</a>
    </div>
  </div>
</div>`,
  },
  {
    id: 'reset_password',
    name: 'Đặt lại mật khẩu',
    trigger: 'Khi người dùng yêu cầu reset mật khẩu',
    subject: 'Yêu cầu đặt lại mật khẩu tài khoản',
    description: 'Cung cấp link reset mật khẩu có thời hạn 60 phút.',
    variables: ['{{user_name}}', '{{reset_url}}', '{{expires_in}}'],
    bodyHtml: `<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
  <div style="background:#ef4444;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#fff;margin:0;font-size:20px;">Đặt lại mật khẩu</h1>
  </div>
  <div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;">
    <p>Xin chào <strong>{{user_name}}</strong>,</p>
    <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Link sẽ hết hạn sau <strong>{{expires_in}}</strong>.</p>
    <div style="text-align:center;margin:24px 0;">
      <a href="{{reset_url}}" style="background:#ef4444;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;">Đặt lại mật khẩu</a>
    </div>
    <p style="font-size:13px;color:#6b7280;">Nếu bạn không yêu cầu điều này, hãy bỏ qua email và bảo mật tài khoản của bạn.</p>
  </div>
</div>`,
  },
  {
    id: 'course_approved',
    name: 'Khoá học được duyệt',
    trigger: 'Admin duyệt khoá học của giảng viên',
    subject: 'Khoá học của bạn đã được phê duyệt!',
    description: 'Thông báo giảng viên khoá học đã được duyệt và xuất bản.',
    variables: ['{{instructor_name}}', '{{course_title}}', '{{course_url}}'],
    bodyHtml: `<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
  <div style="background:#16a34a;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
    <div style="font-size:40px;">✅</div>
    <h1 style="color:#fff;margin:8px 0 0;font-size:20px;">Khoá học được duyệt!</h1>
  </div>
  <div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;">
    <p>Xin chào <strong>{{instructor_name}}</strong>,</p>
    <p>Khoá học <strong>{{course_title}}</strong> của bạn đã được phê duyệt và hiển thị trên hệ thống.</p>
    <div style="text-align:center;margin:24px 0;">
      <a href="{{course_url}}" style="background:#16a34a;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;">Xem khoá học</a>
    </div>
  </div>
</div>`,
  },
  {
    id: 'order_receipt',
    name: 'Hoá đơn thanh toán',
    trigger: 'Khi đơn hàng được xác nhận thanh toán',
    subject: 'Xác nhận đơn hàng #{{order_id}} — {{course_title}}',
    description: 'Gửi biên lai sau khi thanh toán thành công.',
    variables: ['{{user_name}}', '{{order_id}}', '{{course_title}}', '{{amount}}', '{{payment_method}}', '{{paid_at}}'],
    bodyHtml: `<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
  <div style="background:#111827;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#fff;margin:0;font-size:20px;">Biên lai thanh toán</h1>
    <p style="color:#9ca3af;margin:6px 0 0;font-size:14px;">Đơn hàng #{{order_id}}</p>
  </div>
  <div style="background:#fff;padding:28px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;">
    <p>Xin chào <strong>{{user_name}}</strong>,</p>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <tr><td style="padding:8px 0;color:#6b7280;">Khoá học</td><td style="padding:8px 0;text-align:right;font-weight:700;">{{course_title}}</td></tr>
      <tr><td style="padding:8px 0;color:#6b7280;">Số tiền</td><td style="padding:8px 0;text-align:right;font-weight:700;color:#16a34a;">{{amount}}</td></tr>
      <tr><td style="padding:8px 0;color:#6b7280;">Phương thức</td><td style="padding:8px 0;text-align:right;">{{payment_method}}</td></tr>
      <tr><td style="padding:8px 0;color:#6b7280;">Ngày thanh toán</td><td style="padding:8px 0;text-align:right;">{{paid_at}}</td></tr>
    </table>
  </div>
</div>`,
  },
]

const selectedId = ref<string>('welcome')
const search = ref('')
const previewVars = ref<Record<string, string>>({})

const selectedTemplate = computed(() => TEMPLATES.find(t => t.id === selectedId.value))

const filteredTemplates = computed(() => {
  if (!search.value.trim()) return TEMPLATES
  const q = search.value.toLowerCase()
  return TEMPLATES.filter(t =>
    t.name.toLowerCase().includes(q) || t.trigger.toLowerCase().includes(q)
  )
})

function selectTemplate(id: string) {
  selectedId.value = id
  const tpl = TEMPLATES.find(t => t.id === id)
  if (tpl) {
    previewVars.value = Object.fromEntries(
      tpl.variables.map(v => [v, v.replace(/[{}]/g, '').replace(/_/g, ' ').toUpperCase()])
    )
  }
}

const renderedPreview = computed(() => {
  if (!selectedTemplate.value) return ''
  let html = selectedTemplate.value.bodyHtml
  for (const [key, val] of Object.entries(previewVars.value)) {
    html = html.replaceAll(key, `<mark style="background:#fef08a;padding:0 2px;border-radius:2px;">${val}</mark>`)
  }
  return html
})

selectTemplate('welcome')
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Cấu hình hệ thống</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Mẫu Email</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Xem và tuỳ chỉnh các template email hệ thống gửi tự động cho người dùng theo từng sự kiện.</p>
      </div>
    </div>

    <!-- Notice -->
    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-2xl p-5 flex gap-3">
      <i class="pi pi-info-circle text-lg shrink-0 mt-0.5" />
      <p class="text-xs leading-relaxed">
        Đây là các template email mặc định. Để chỉnh sửa nội dung thực tế, cần cấu hình template engine trong backend (Laravel Mail).
        Trang này cho phép <strong>xem trước</strong> và <strong>kiểm tra biến</strong> cho mỗi template.
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-5 items-start">
      <!-- Sidebar: template list -->
      <aside class="w-full lg:w-70 bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-[var(--line)] flex flex-col gap-2">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Hệ thống</p>
            <h3 class="text-sm font-semibold text-[var(--text)] mt-0.5">Danh sách template</h3>
          </div>
          <div class="relative">
            <i class="pi pi-search absolute left-3 top-2.5 text-xs text-[var(--muted)]" />
            <input
              v-model="search"
              type="text"
              class="h-8 pl-8 pr-3 rounded-lg border border-[var(--line)] bg-[var(--surface)] text-xs text-[var(--text)] placeholder:text-[var(--muted)] focus:outline-none focus:border-[#1d9e75] w-full"
              placeholder="Tìm template..."
            >
          </div>
        </div>
        <div class="flex flex-col">
          <button
            v-for="tpl in filteredTemplates"
            :key="tpl.id"
            type="button"
            class="w-full text-left p-4 border-b border-[var(--line)] last:border-0 hover:bg-[rgba(29,158,117,0.04)] transition-colors"
            :class="{ 'bg-[rgba(29,158,117,0.08)] border-l-4 border-l-[#1d9e75] pl-3': selectedId === tpl.id }"
            @click="selectTemplate(tpl.id)"
          >
            <span class="block text-xs font-bold text-[var(--text)]">{{ tpl.name }}</span>
            <span class="block text-[10px] text-[var(--muted)] mt-1 leading-relaxed">{{ tpl.trigger }}</span>
          </button>
        </div>
      </aside>

      <!-- Right: template detail + preview -->
      <div v-if="selectedTemplate" class="flex flex-col gap-5">
        <!-- Meta -->
        <section class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">{{ selectedTemplate.trigger }}</p>
            <h3 class="text-base font-bold text-[var(--text)] mt-0.5">{{ selectedTemplate.name }}</h3>
          </div>
          
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Tiêu đề email (Subject line)</span>
            <input 
              :value="selectedTemplate.subject" 
              type="text" 
              readonly 
              class="h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm text-[var(--text)] opacity-70 w-full outline-none"
            >
          </div>

          <!-- Variables -->
          <div class="mt-2 border-t border-[var(--line)] pt-4 flex flex-col gap-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">
              Biến mẫu — nhập giá trị xem trước
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div v-for="v in selectedTemplate.variables" :key="v" class="flex flex-col gap-1.5">
                <label class="text-[10px] text-[var(--muted)] font-mono">{{ v }}</label>
                <input
                  v-model="previewVars[v]"
                  type="text"
                  :placeholder="v"
                  class="h-8 px-2.5 rounded-lg border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full"
                >
              </div>
            </div>
          </div>
        </section>

        <!-- Preview -->
        <section class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--text)]">Xem trước email</h3>
            <span class="text-[10px] text-[var(--muted)]">Các biến được highlight bằng màu vàng</span>
          </div>
          <div class="border border-[var(--line)] rounded-2xl p-5 bg-[#f9fafb] overflow-auto">
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div v-html="renderedPreview" />
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
