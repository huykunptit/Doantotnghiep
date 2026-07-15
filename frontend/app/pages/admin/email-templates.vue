<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

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
  <AdminWorkspaceShell
    title="Mẫu Email"
    description="Xem và tuỳ chỉnh các template email hệ thống gửi tự động cho người dùng theo từng sự kiện."
    :breadcrumb="['Trang chủ', 'Hỗ trợ', 'Mẫu Email']"
  >
    <!-- Notice -->
    <div class="dashboard-card" style="margin-bottom: 20px; padding: 14px 18px; border-left: 4px solid #3b82f6; background: #eff6ff; display: flex; gap: 12px;">
      <i class="pi pi-info-circle" style="font-size:1.125rem" />
      <p style="font-size: 0.875rem; color: #1e40af; margin: 0; line-height: 1.6;">
        Đây là các template email mặc định. Để chỉnh sửa nội dung thực tế, cần cấu hình template engine trong backend (Laravel Mail).
        Trang này cho phép <strong>xem trước</strong> và <strong>kiểm tra biến</strong> cho mỗi template.
      </p>
    </div>

    <div class="email-layout">
      <!-- Sidebar: template list -->
      <aside class="email-sidebar">
        <div class="dashboard-card" style="padding: 0; overflow: hidden;">
          <div style="padding: 14px 16px; border-bottom: 1px solid var(--line);">
            <p class="section-kicker">Hệ thống</p>
            <h3 style="margin: 4px 0 12px;">Danh sách template</h3>
            <input
              v-model="search"
              type="text"
              class="crud-search"
              style="width: 100%;"
              placeholder="Tìm template..."
            >
          </div>
          <div>
            <button
              v-for="tpl in filteredTemplates"
              :key="tpl.id"
              type="button"
              class="tpl-list-item"
              :class="{ 'is-active': selectedId === tpl.id }"
              @click="selectTemplate(tpl.id)"
            >
              <strong style="font-size: 0.875rem;">{{ tpl.name }}</strong>
              <p style="font-size: 0.72rem; color: var(--muted); margin: 3px 0 0; text-align: left; line-height: 1.4;">{{ tpl.trigger }}</p>
            </button>
          </div>
        </div>
      </aside>

      <!-- Right: template detail + preview -->
      <div v-if="selectedTemplate" class="email-main">
        <!-- Meta -->
        <section class="dashboard-card" style="margin-bottom: 16px;">
          <div class="card-head" style="margin-bottom: 16px;">
            <div>
              <p class="section-kicker">{{ selectedTemplate.trigger }}</p>
              <h3>{{ selectedTemplate.name }}</h3>
            </div>
          </div>
          <div class="crud-form-grid">
            <div class="crud-field crud-field-full">
              <span>Subject line</span>
              <input :value="selectedTemplate.subject" type="text" readonly style="opacity: 0.7;">
            </div>
          </div>
          <!-- Variables -->
          <div style="margin-top: 16px;">
            <p style="font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px;">
              Biến mẫu — nhập giá trị xem trước
            </p>
            <div class="vars-grid">
              <div v-for="v in selectedTemplate.variables" :key="v" class="var-field">
                <label style="font-size: 0.72rem; color: var(--muted); font-family: monospace;">{{ v }}</label>
                <input
                  v-model="previewVars[v]"
                  type="text"
                  :placeholder="v"
                  style="font-size: 0.8rem; padding: 6px 10px;"
                >
              </div>
            </div>
          </div>
        </section>

        <!-- Preview -->
        <section class="dashboard-card">
          <div class="crud-toolbar" style="margin-bottom: 16px;">
            <h3>Xem trước email</h3>
            <span style="font-size: 0.78rem; color: var(--muted);">Các biến được highlight bằng màu vàng</span>
          </div>
          <div class="email-preview-frame">
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div v-html="renderedPreview" />
          </div>
        </section>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
.email-layout {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 900px) {
  .email-layout { grid-template-columns: 1fr; }
}

.tpl-list-item {
  display: block;
  width: 100%;
  padding: 12px 16px;
  border: none;
  background: transparent;
  border-bottom: 1px solid var(--line);
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}
.tpl-list-item:last-child { border-bottom: none; }
.tpl-list-item:hover { background: rgba(var(--green-rgb), 0.04); }
.tpl-list-item.is-active {
  background: rgba(var(--green-rgb), 0.08);
  border-left: 3px solid var(--green);
}

.vars-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 10px;
}
.var-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.var-field input {
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 0.8rem;
  font-family: inherit;
  outline: none;
}
.var-field input:focus { border-color: var(--green); }

.email-preview-frame {
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 20px;
  background: #f9fafb;
  overflow: auto;
}
</style>
