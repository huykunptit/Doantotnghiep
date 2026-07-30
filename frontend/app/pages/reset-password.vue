<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const route = useRoute()
const loading = ref(false)
const error = ref('')
const success = ref(false)
const form = reactive({
  email: String(route.query.email || ''),
  password: '',
  password_confirmation: '',
})

async function submit() {
  error.value = ''
  if (!route.query.token) {
    error.value = 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'
    return
  }
  if (form.password.length < 6) {
    error.value = 'Mật khẩu phải có ít nhất 6 ký tự.'
    return
  }
  if (form.password !== form.password_confirmation) {
    error.value = 'Mật khẩu xác nhận không khớp.'
    return
  }

  loading.value = true
  try {
    await useApi('/auth/reset-password', {
      method: 'POST',
      body: {
        token: route.query.token,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
      },
      token: null,
    })
    success.value = true
  }
  catch (requestError: any) {
    error.value = requestError?.data?.message || 'Cập nhật mật khẩu thất bại.'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-panel">
    <section class="auth-story">
      <span class="story-label">Bảo mật tài khoản</span>
      <h1>Tạo mật khẩu mới để tiếp tục học tập an toàn.</h1>
      <p>Chọn mật khẩu mạnh và không dùng chung với các dịch vụ khác.</p>
    </section>

    <AuthPanelCard>
        <AuthSideHeader />
        <div class="auth-heading">
          <span>Đặt lại mật khẩu</span>
          <h2>Mật khẩu mới</h2>
          <p>Nhập mật khẩu mới cho tài khoản của bạn.</p>
        </div>

        <div v-if="success" class="success-state">
          <i class="pi pi-check-circle" />
          <h3>Đã cập nhật mật khẩu</h3>
          <p>Bạn có thể đăng nhập ngay với mật khẩu mới.</p>
          <Button label="Đăng nhập ngay" icon="pi pi-arrow-right" icon-pos="right" @click="navigateTo('/login')" />
        </div>

        <template v-else>
          <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
          <form class="auth-form" @submit.prevent="submit">
            <label>
              <span>Email</span>
              <InputText v-model="form.email" type="email" disabled fluid />
            </label>
            <label>
              <span>Mật khẩu mới</span>
              <Password v-model="form.password" placeholder="Tối thiểu 6 ký tự" :feedback="false" toggle-mask autocomplete="new-password" fluid />
            </label>
            <label>
              <span>Xác nhận mật khẩu</span>
              <Password v-model="form.password_confirmation" placeholder="Nhập lại mật khẩu" :feedback="false" toggle-mask autocomplete="new-password" fluid />
            </label>
            <Button type="submit" label="Lưu mật khẩu mới" icon="pi pi-lock" :loading="loading" fluid />
          </form>
          <p class="auth-foot">Đã nhớ mật khẩu? <NuxtLink to="/login">Đăng nhập</NuxtLink></p>
        </template>
    </AuthPanelCard>
  </div>
</template>
