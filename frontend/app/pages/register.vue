<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const loading = ref(false)
const error = ref('')
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

async function submit() {
  error.value = ''
  if (!form.name.trim() || !form.email.trim()) {
    error.value = 'Vui lòng nhập họ tên và email.'
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
    const result = await auth.register(form)
    await navigateTo(`/verify-email?email=${encodeURIComponent(result.email)}&registered=1`)
  }
  catch (requestError: any) {
    error.value = requestError?.data?.message || 'Đăng ký thất bại. Vui lòng thử lại.'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-panel">
    <section class="auth-story">
      <span class="story-label">Bắt đầu hành trình</span>
      <h1>Tạo tài khoản và mở khóa lộ trình học tập của bạn.</h1>
      <p>Một tài khoản để truy cập khóa học, bài kiểm tra, tiến độ và chứng chỉ trên Sylva LMS.</p>
    </section>

    <Card class="auth-card">
      <template #content>
        <div class="auth-heading">
          <span>Đăng ký</span>
          <h2>Tạo tài khoản mới</h2>
          <p>Điền thông tin để bắt đầu học ngay hôm nay.</p>
        </div>

        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

        <form class="auth-form" @submit.prevent="submit">
          <label>
            <span>Họ và tên</span>
            <InputText v-model="form.name" placeholder="Nguyễn Văn A" autocomplete="name" fluid />
          </label>
          <label>
            <span>Email</span>
            <IconField>
              <InputIcon class="pi pi-envelope" />
              <InputText v-model="form.email" type="email" placeholder="name@example.com" autocomplete="email" fluid />
            </IconField>
          </label>
          <label>
            <span>Mật khẩu</span>
            <Password v-model="form.password" placeholder="Tối thiểu 6 ký tự" :feedback="false" toggle-mask autocomplete="new-password" fluid />
          </label>
          <label>
            <span>Xác nhận mật khẩu</span>
            <Password v-model="form.password_confirmation" placeholder="Nhập lại mật khẩu" :feedback="false" toggle-mask autocomplete="new-password" fluid />
          </label>
          <p class="notice">Bằng việc tạo tài khoản, bạn đồng ý sử dụng hệ thống học tập và theo dõi tiến độ của mình.</p>
          <Button type="submit" label="Tạo tài khoản" icon="pi pi-user-plus" :loading="loading" fluid />
        </form>

        <AuthGoogleButton label="Đăng ký / Đăng nhập bằng Google" @error="error = $event" />

        <p class="auth-foot">Đã có tài khoản? <NuxtLink to="/login">Đăng nhập</NuxtLink></p>
      </template>
    </Card>
  </div>
</template>
