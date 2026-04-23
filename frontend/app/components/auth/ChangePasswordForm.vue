<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

const auth = useAuthStore()

const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const loading = ref(false)
const success = ref('')
const error = ref('')

const strength = computed(() => {
  const password = form.password
  let score = 0
  if (password.length >= 6) score++
  if (password.length >= 10) score++
  if (/[A-Z]/.test(password)) score++
  if (/[0-9]/.test(password)) score++
  if (/[^A-Za-z0-9]/.test(password)) score++
  if (score <= 1) return { label: 'Rất yếu', width: '20%', color: 'bg-error' }
  if (score === 2) return { label: 'Yếu', width: '40%', color: 'bg-orange-500' }
  if (score === 3) return { label: 'Trung bình', width: '60%', color: 'bg-amber-500' }
  if (score === 4) return { label: 'Mạnh', width: '80%', color: 'bg-secondary' }
  return { label: 'Rất mạnh', width: '100%', color: 'bg-secondary' }
})

async function handleSubmit() {
  if (form.password !== form.password_confirmation) {
    error.value = 'Mật khẩu xác nhận không khớp.'
    return
  }
  loading.value = true
  success.value = ''
  error.value = ''
  try {
    await auth.changePassword({ ...form })
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    success.value = 'Đổi mật khẩu thành công.'
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể đổi mật khẩu.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form class="space-y-5" @submit.prevent="handleSubmit">
    <UiInput v-model="form.current_password" label="Mật khẩu hiện tại" type="password" placeholder="••••••••" />
    <UiInput v-model="form.password" label="Mật khẩu mới" type="password" placeholder="Tối thiểu 6 ký tự" />
    <UiInput v-model="form.password_confirmation" label="Xác nhận mật khẩu" type="password" placeholder="Nhập lại mật khẩu" />

    <div v-if="form.password" class="space-y-2">
      <div class="h-2 rounded-full bg-surface-dim/20">
        <div :class="['h-2 rounded-full transition-all', strength.color]" :style="{ width: strength.width }" />
      </div>
      <p class="text-xs font-semibold text-on-surface-variant">Độ mạnh: {{ strength.label }}</p>
    </div>

    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
      <p class="font-semibold">Gợi ý mật khẩu mạnh</p>
      <ul class="mt-2 list-disc space-y-1 pl-5 text-sky-700">
        <li>Ít nhất 8 ký tự</li>
        <li>Kết hợp chữ hoa, chữ thường và số</li>
        <li>Thêm ký tự đặc biệt như @, #, !</li>
      </ul>
    </div>

    <div v-if="success" class="rounded-2xl border border-secondary/20 bg-secondary-50 px-4 py-3 text-sm text-secondary">{{ success }}</div>
    <div v-if="error" class="rounded-2xl border border-error/20 bg-error-container px-4 py-3 text-sm text-error">{{ error }}</div>

    <div class="flex justify-end">
      <UiButton type="submit" :disabled="loading">{{ loading ? 'Đang đổi...' : 'Đổi mật khẩu' }}</UiButton>
    </div>
  </form>
</template>

