<script setup lang="ts">
import { dashboardFor } from '~/types/auth'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const error = ref('')
const status = ref('Đang đăng nhập bằng Google…')

onMounted(async () => {
  const params = new URLSearchParams(window.location.search)
  const oauthError = params.get('error')
  const token = params.get('token')
  const code = params.get('code')

  if (oauthError) {
    error.value = oauthError
    return
  }

  try {
    // Preferred flow: backend already exchanged the code and redirected with token.
    if (token) {
      status.value = 'Đang hoàn tất đăng nhập…'
      const response = await auth.loginWithToken(token)
      await navigateTo(dashboardFor(response.user), { replace: true })
      return
    }

    // Legacy fallback: frontend exchanges Google auth code via API.
    if (!code) {
      error.value = 'Thiếu mã xác thực từ Google. Vui lòng đăng nhập lại.'
      return
    }

    status.value = 'Đang xác thực với máy chủ…'
    const query: Record<string, string> = {}
    params.forEach((value, key) => {
      query[key] = value
    })
    const response = await auth.loginWithGoogle(query)
    await navigateTo(dashboardFor(response.user), { replace: true })
  }
  catch (requestError: any) {
    error.value = requestError?.data?.message
      || requestError?.message
      || 'Đăng nhập Google thất bại. Vui lòng thử lại.'
  }
})
</script>

<template>
  <div class="google-callback">
    <Card class="cb-card">
      <template #content>
        <template v-if="!error">
          <i class="pi pi-spin pi-spinner cb-spinner" />
          <h2>{{ status }}</h2>
          <p>Vui lòng đợi trong giây lát.</p>
        </template>
        <template v-else>
          <i class="pi pi-times-circle cb-error-icon" />
          <h2>Không đăng nhập được</h2>
          <Message severity="error" :closable="false">{{ error }}</Message>
          <Button label="Quay lại đăng nhập" icon="pi pi-arrow-left" text @click="navigateTo('/login')" />
        </template>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.google-callback {
  min-height: 60vh;
  display: grid;
  place-items: center;
  padding: 24px;
}

.cb-card {
  max-width: 420px;
  width: 100%;
  text-align: center;
}

.cb-card h2 {
  margin: 12px 0 6px;
  font-size: 1.15rem;
}

.cb-card p {
  margin: 0 0 8px;
  color: var(--text-muted);
  font-size: .85rem;
}

.cb-spinner {
  font-size: 2rem;
  color: var(--brand);
}

.cb-error-icon {
  font-size: 2rem;
  color: #dc2626;
}
</style>
