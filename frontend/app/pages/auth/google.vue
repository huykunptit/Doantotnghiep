<script setup lang="ts">
import { ref } from 'vue'

const route = useRoute()
const errorMessage = ref('')

const queryString = new URLSearchParams(
  Object.entries(route.query).flatMap(([key, value]) => {
    if (Array.isArray(value)) {
      return value
        .filter((item): item is string => typeof item === 'string')
        .map((item) => [key, item] as [string, string])
    }

    return typeof value === 'string' ? [[key, value] as [string, string]] : []
  }),
).toString()

try {
  const data = await useApi<AuthResponse>(queryString ? `/auth/google/callback?${queryString}` : '/auth/google/callback')
  setAuthSession(data)
  await navigateTo(getDashboardPath(data.user.role), { replace: true })
} catch (error: any) {
  errorMessage.value = error?.data?.message || 'Dang nhap bang Google that bai.'
}
</script>

<template>
  <main class="placeholder-shell">
    <div class="placeholder-card">
      <p class="placeholder-eyebrow">Google login</p>
      <h1 v-if="errorMessage">Khong the hoan tat dang nhap bang Google.</h1>
      <h1 v-else>Dang xac thuc tai khoan Google...</h1>
      <p v-if="errorMessage">{{ errorMessage }}</p>
      <p v-else>He thong dang kiem tra vai tro va chuyen den dashboard phu hop.</p>
      <NuxtLink to="/login">Quay lai dang nhap</NuxtLink>
    </div>
  </main>
</template>
