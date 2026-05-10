<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import MediaUpload from '~/components/common/MediaUpload.vue'

definePageMeta({ layout: 'admin' })

interface CertificateTemplate {
  id: number
  name: string
  background_image_url: string | null
  created_at: string
}

const templates = ref<CertificateTemplate[]>([])
const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const isCreating = ref(false)
const formName = ref('')
const formBackgroundUrl = ref<string | null>(null)
const formBackgroundPath = ref<string | null>(null)

async function fetchTemplates() {
  loading.value = true
  try {
    templates.value = await useApi<CertificateTemplate[]>('/admin/certificates', {
      headers: { Authorization: `Bearer ${useAuthTokenCookie().value}` }
    })
  } catch (e: any) {
    errorMessage.value = 'Lỗi tải danh sách chứng chỉ'
  } finally {
    loading.value = false
  }
}

async function createTemplate() {
  if (!formName.value || !formBackgroundPath.value) return
  saving.value = true
  errorMessage.value = ''
  try {
    await useApi('/admin/certificates', {
      method: 'POST',
      headers: { Authorization: `Bearer ${useAuthTokenCookie().value}` },
      body: {
        name: formName.value,
        background_image_url: formBackgroundPath.value
      }
    })
    isCreating.value = false
    formName.value = ''
    formBackgroundUrl.value = null
    formBackgroundPath.value = null
    fetchTemplates()
    successMessage.value = 'Tạo chứng chỉ thành công'
  } catch (e: any) {
    errorMessage.value = 'Có lỗi xảy ra khi tạo chứng chỉ'
  } finally {
    saving.value = false
  }
}

function onBackgroundUploaded(payload: { url: string; path: string }) {
  formBackgroundPath.value = payload.path
}

async function deleteTemplate(id: number) {
  if (!confirm('Bạn có chắc muốn xoá chứng chỉ này?')) return
  try {
    await useApi(`/admin/certificates/${id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${useAuthTokenCookie().value}` }
    })
    fetchTemplates()
  } catch (e: any) {
    errorMessage.value = 'Không thể xoá chứng chỉ'
  }
}

onMounted(fetchTemplates)
</script>

<template>
  <AdminWorkspaceShell title="Quản lý chứng chỉ" :breadcrumb="['Trang chủ', 'Quản trị hệ thống', 'Chứng chỉ']">
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <h3>Mẫu chứng chỉ</h3>
        <button class="crud-primary-btn" @click="isCreating = !isCreating">
          {{ isCreating ? 'Huỷ' : '+ Thêm mẫu mới' }}
        </button>
      </div>

      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

      <div v-if="isCreating" class="crud-form-grid" style="margin-bottom: 20px;">
        <label class="crud-field crud-field-full">
          <span>Tên chứng chỉ</span>
          <input v-model="formName" type="text" placeholder="Ví dụ: Chứng chỉ Hoàn thành Khoá học">
        </label>
        <div class="crud-field crud-field-full">
          <span>Phôi chứng chỉ (Hình ảnh)</span>
          <MediaUpload
            v-model="formBackgroundUrl"
            folder="settings"
            variant="banner"
            label="Phôi chứng chỉ"
            hint="JPG/PNG, tỉ lệ ngang khuyến nghị 1600×1131. Tối đa 5MB."
            placeholder-initial="CT"
            @uploaded="onBackgroundUploaded"
            @error="(msg) => errorMessage = msg"
          />
        </div>
        <button class="crud-primary-btn" :disabled="saving || !formName || !formBackgroundPath" @click="createTemplate">
          {{ saving ? 'Đang lưu...' : 'Lưu mẫu' }}
        </button>
      </div>

      <table class="crud-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tên</th>
            <th>Hình ảnh</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="4" class="crud-empty">Đang tải...</td></tr>
          <tr v-else-if="templates.length === 0"><td colspan="4" class="crud-empty">Chưa có mẫu chứng chỉ nào.</td></tr>
          <tr v-for="t in templates" :key="t.id">
            <td>{{ t.id }}</td>
            <td>{{ t.name }}</td>
            <td><img v-if="t.background_image_url" :src="t.background_image_url" alt="" style="height: 60px; border-radius: 4px;"></td>
            <td>
              <button class="action-btn is-danger" @click="deleteTemplate(t.id)">Xoá</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </AdminWorkspaceShell>
</template>
