<script setup lang="ts">
import { computed, ref } from 'vue'
import Card from 'primevue/card'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Tag from 'primevue/tag'

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
    subject: 'Chào mừng bạn đến với Eript LMS!',
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
  <div class="page-stack">
    <header class="page-header"><div><h1>Mẫu Email</h1><p>Xem trước các email hệ thống và kiểm tra dữ liệu biến theo từng sự kiện.</p></div></header>
    <Message severity="info" :closable="false">Nội dung thực tế được quản lý bởi Laravel Mail. Trang này chỉ dùng để xem trước và kiểm tra biến mẫu.</Message>

    <Card>
      <template #title>Danh sách template</template>
      <template #content>
        <div class="filters"><span class="p-input-icon-left"><i class="pi pi-search" /><InputText v-model="search" placeholder="Tìm theo tên hoặc sự kiện..." /></span><Tag :value="`${filteredTemplates.length} template`" severity="secondary" /></div>
        <DataTable :value="filteredTemplates" data-key="id" striped-rows scrollable selection-mode="single" :selection="selectedTemplate" @row-select="selectTemplate($event.data.id)">
          <template #empty>Không tìm thấy template phù hợp.</template>
          <Column field="name" header="Template"><template #body="{ data }"><strong>{{ data.name }}</strong></template></Column>
          <Column field="trigger" header="Sự kiện kích hoạt" />
          <Column field="subject" header="Tiêu đề email" />
          <Column header="Biến"><template #body="{ data }"><Tag :value="`${data.variables.length} biến`" severity="info" /></template></Column>
        </DataTable>
      </template>
    </Card>

    <div v-if="selectedTemplate" class="detail-grid">
      <Card>
        <template #title>{{ selectedTemplate.name }}</template>
        <template #subtitle>{{ selectedTemplate.trigger }}</template>
        <template #content>
          <div class="field"><span>Tiêu đề email</span><InputText :model-value="selectedTemplate.subject" readonly fluid /></div>
          <div class="variables"><h3>Biến mẫu</h3><p>Nhập giá trị để cập nhật bản xem trước.</p><div class="variable-grid"><label v-for="variable in selectedTemplate.variables" :key="variable" class="field"><code>{{ variable }}</code><InputText v-model="previewVars[variable]" :placeholder="variable" fluid /></label></div></div>
        </template>
      </Card>
      <Card>
        <template #title>Xem trước email</template>
        <template #subtitle>Các biến được đánh dấu màu vàng.</template>
        <template #content><div class="preview-surface"><div v-html="renderedPreview" /></div></template>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.page-stack{display:flex;flex-direction:column;gap:1.25rem}.page-header h1{margin:0;color:var(--p-text-color);font-size:1.5rem;font-weight:700}.page-header p{margin:.3rem 0 0;color:var(--p-text-muted-color);font-size:.875rem}.filters{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.filters :deep(.p-inputtext){width:min(24rem,70vw);padding-left:2.25rem}.p-input-icon-left{position:relative}.p-input-icon-left>i{position:absolute;z-index:1;left:.8rem;top:50%;transform:translateY(-50%);color:var(--p-text-muted-color)}.detail-grid{display:grid;grid-template-columns:minmax(18rem,24rem) minmax(0,1fr);gap:1.25rem;align-items:start}.field{display:flex;flex-direction:column;gap:.4rem}.field>span,.field>code{color:var(--p-text-muted-color);font-size:.75rem;font-weight:600}.variables{margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--p-content-border-color)}.variables h3{margin:0;color:var(--p-text-color);font-size:.9rem}.variables p{margin:.25rem 0 1rem;color:var(--p-text-muted-color);font-size:.75rem}.variable-grid{display:grid;grid-template-columns:1fr;gap:.85rem}.preview-surface{overflow:auto;padding:1.25rem;border:1px solid var(--p-content-border-color);border-radius:var(--p-border-radius-lg);background:var(--p-surface-100);color:#111827}.dark .preview-surface{background:var(--p-surface-800)}
@media(max-width:900px){.detail-grid{grid-template-columns:1fr}}@media(max-width:560px){.filters{align-items:stretch;flex-direction:column}.filters :deep(.p-inputtext){width:100%}}
</style>
