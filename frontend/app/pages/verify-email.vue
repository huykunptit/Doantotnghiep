<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const route = useRoute()
const email = computed(() => String(route.query.email || ''))
const justRegistered = computed(() => route.query.registered === '1')
const loading = ref(false)
const verifying = ref(false)
const error = ref('')
const success = ref('')
const resent = ref(false)

const hasSignedLink = computed(() => Boolean(route.params.id || route.query.id) && Boolean(route.query.hash || route.params.hash))

onMounted(async () => {
  const id = route.params.id || route.query.id
  const hash = route.query.hash || route.params.hash
  if (!id || !hash) return

  verifying.value = true
  try {
    const expires = route.query.expires
    const signature = route.query.signature
    const query = new URLSearchParams()
    if (expires) query.set('expires', String(expires))
    if (signature) query.set('signature', String(signature))
    await useApi(`/auth/verify-email/${id}/${hash}${query.toString() ? `?${query}` : ''}`, {
      token: null,
    })
    success.value = 'Email đã được xác minh. Bạn có thể đăng nhập ngay.'
  }
  catch (requestError: any) {
    error.value = requestError?.data?.message || 'Liên kết xác minh không hợp lệ hoặc đã hết hạn.'
  }
  finally {
    verifying.value = false
  }
})

async function resend() {
  error.value = ''
  if (!email.value) {
    error.value = 'Không tìm thấy email để gửi lại.'
    return
  }
  loading.value = true
  try {
    await useApi('/auth/resend-verification-email', {
      method: 'POST',
      body: { email: email.value },
      token: null,
    })
    resent.value = true
  }
  catch (requestError: any) {
    error.value = requestError?.data?.message || 'Không thể gửi lại email xác minh.'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-panel">
    <section class="auth-story">
      <span class="story-label">Xác minh tài khoản</span>
      <h1>Chỉ còn một bước để kích hoạt tài khoản học tập.</h1>
      <p>Kiểm tra hộp thư và mở liên kết xác minh để bắt đầu sử dụng Sylva LMS.</p>
    </section>

    <Card class="auth-card">
      <template #content>
        <div class="auth-heading">
          <span>Email</span>
          <h2>Xác minh email</h2>
          <p v-if="justRegistered">Tài khoản đã được tạo. Hãy xác minh email để đăng nhập.</p>
          <p v-else>Hoàn tất xác minh để bảo vệ tài khoản của bạn.</p>
        </div>

        <div v-if="verifying" class="success-state">
          <i class="pi pi-spin pi-spinner" />
          <h3>Đang xác minh...</h3>
          <p>Vui lòng chờ trong giây lát.</p>
        </div>

        <div v-else-if="success" class="success-state">
          <i class="pi pi-check-circle" />
          <h3>Xác minh thành công</h3>
          <p>{{ success }}</p>
          <Button label="Đăng nhập ngay" icon="pi pi-arrow-right" icon-pos="right" @click="navigateTo('/login')" />
        </div>

        <template v-else>
          <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
          <Message v-if="resent" severity="success" :closable="false">Đã gửi lại email xác minh.</Message>

          <div class="success-state">
            <i class="pi pi-inbox" />
            <h3>{{ email || 'Kiểm tra hộp thư của bạn' }}</h3>
            <p>
              {{ hasSignedLink
                ? 'Liên kết xác minh không hợp lệ. Bạn có thể yêu cầu gửi lại email.'
                : 'Chúng tôi đã gửi liên kết xác minh tới email của bạn. Nếu chưa nhận được, hãy gửi lại.' }}
            </p>
            <Button
              v-if="email"
              label="Gửi lại email xác minh"
              icon="pi pi-send"
              :loading="loading"
              @click="resend"
            />
            <Button label="Quay lại đăng nhập" severity="secondary" outlined @click="navigateTo('/login')" />
          </div>
        </template>
      </template>
    </Card>
  </div>
</template>
