<script setup lang="ts">
import { dashboardFor } from '~/types/auth'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const loading = ref(false)
const error = ref('')
const remember = ref(true)
const form = reactive({ email: '', password: '' })

async function submit() {
  error.value = ''
  if (!form.email || !form.password) {
    error.value = 'Vui lòng nhập email và mật khẩu.'
    return
  }
  loading.value = true
  try {
    const response = await auth.login(form)
    await navigateTo(dashboardFor(response.user))
  }
  catch (requestError: any) {
    if (requestError?.statusCode === 403 && requestError?.data?.requires_verification) {
      await navigateTo(`/verify-email?email=${encodeURIComponent(form.email)}`)
      return
    }
    error.value = requestError?.data?.message || 'Email hoặc mật khẩu không chính xác.'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-panel">
    <section class="auth-story">
      <span class="story-label">Sylva Learning Ecosystem</span>
      <h1>Một không gian học tập được thiết kế để phát triển lâu dài.</h1>
      <p>Quản trị đào tạo, nội dung, khảo thí và dữ liệu học tập trên cùng một nền tảng.</p>
      <div class="story-points">
        <div><i class="pi pi-chart-line" /><span><strong>Dữ liệu tập trung</strong><small>Theo dõi hiệu quả đào tạo theo thời gian thực.</small></span></div>
        <div><i class="pi pi-shield" /><span><strong>Vận hành an toàn</strong><small>Vai trò và phân quyền rõ ràng cho từng bộ phận.</small></span></div>
        <div><i class="pi pi-sparkles" /><span><strong>Trải nghiệm hiện đại</strong><small>Gọn gàng, nhanh và nhất quán trên mọi thiết bị.</small></span></div>
      </div>
    </section>

    <Card class="auth-card">
      <template #content>
        <div class="auth-heading">
          <span>Chào mừng trở lại</span>
          <h2>Đăng nhập hệ thống</h2>
          <p>Sử dụng tài khoản Sylva LMS của bạn.</p>
        </div>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <form class="auth-form" @submit.prevent="submit">
          <label>
            <span>Email</span>
            <IconField>
              <InputIcon class="pi pi-envelope" />
              <InputText v-model="form.email" type="email" placeholder="name@example.com" autocomplete="email" fluid />
            </IconField>
          </label>
          <label>
            <span>Mật khẩu</span>
            <Password v-model="form.password" placeholder="Nhập mật khẩu" :feedback="false" toggle-mask autocomplete="current-password" fluid />
          </label>
          <div class="form-row">
            <label class="remember"><Checkbox v-model="remember" binary /><span>Ghi nhớ đăng nhập</span></label>
            <NuxtLink to="/forgot-password">Quên mật khẩu?</NuxtLink>
          </div>
          <Button type="submit" label="Đăng nhập" icon="pi pi-arrow-right" icon-pos="right" :loading="loading" fluid />
        </form>
        <p class="auth-foot">Chưa có tài khoản? <NuxtLink to="/register">Đăng ký ngay</NuxtLink></p>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.story-points {
  display: grid;
  gap: 16px;
  margin-top: 32px;
}

.story-points > div {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.story-points > div > i {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  border-radius: 9px;
  background: rgba(255, 255, 255, .12);
}

.story-points span {
  display: flex;
  flex-direction: column;
}

.story-points strong {
  font-size: .78rem;
}

.story-points small {
  margin-top: 3px;
  color: rgba(255, 255, 255, .62);
  font-size: .67rem;
  line-height: 1.5;
}

.form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: .7rem;
}

.remember {
  display: flex;
  align-items: center;
  gap: 7px;
  color: var(--text-muted);
}

.form-row a {
  color: var(--brand);
  font-weight: 700;
}
</style>
