<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const email = ref('')
const loading = ref(false)
const error = ref('')
const sent = ref(false)

async function submit() {
  error.value = ''
  if (!email.value.trim()) {
    error.value = 'Vui lòng nhập email.'
    return
  }
  loading.value = true
  try {
    await useApi('/auth/forgot-password', {
      method: 'POST',
      body: { email: email.value.trim() },
      token: null,
    })
    sent.value = true
  }
  catch (requestError: any) {
    error.value = requestError?.data?.message || 'Không thể gửi email đặt lại mật khẩu.'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-panel">
    <section class="auth-story">
      <span class="story-label">Khôi phục truy cập</span>
      <h1>Chúng tôi sẽ giúp bạn lấy lại quyền truy cập nhanh chóng.</h1>
      <p>Nhập email đã đăng ký để nhận hướng dẫn đặt lại mật khẩu an toàn.</p>
    </section>

    <Card class="auth-card">
      <template #content>
        <div class="auth-heading">
          <span>Hỗ trợ tài khoản</span>
          <h2>Quên mật khẩu?</h2>
          <p>Chúng tôi sẽ gửi liên kết đặt lại mật khẩu tới email của bạn.</p>
        </div>

        <div v-if="sent" class="success-state">
          <i class="pi pi-envelope" />
          <h3>Đã gửi hướng dẫn</h3>
          <p>Nếu email tồn tại trong hệ thống, bạn sẽ nhận được liên kết đặt lại mật khẩu trong vài phút.</p>
          <Button label="Quay lại đăng nhập" icon="pi pi-arrow-left" @click="navigateTo('/login')" />
        </div>

        <template v-else>
          <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
          <form class="auth-form" @submit.prevent="submit">
            <label>
              <span>Email</span>
              <IconField>
                <InputIcon class="pi pi-envelope" />
                <InputText v-model="email" type="email" placeholder="name@example.com" autocomplete="email" fluid />
              </IconField>
            </label>
            <Button type="submit" label="Gửi liên kết đặt lại" icon="pi pi-send" :loading="loading" fluid />
          </form>
          <p class="auth-foot">Nhớ lại mật khẩu? <NuxtLink to="/login">Đăng nhập</NuxtLink></p>
        </template>
      </template>
    </Card>
  </div>
</template>
