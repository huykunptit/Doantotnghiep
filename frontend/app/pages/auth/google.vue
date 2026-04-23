<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'

declare const definePageMeta: (meta: { layout?: false | string }) => void

definePageMeta({ layout: false })

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const queryString = new URLSearchParams(
      Object.entries(route.query).flatMap(([key, value]) => {
        if (Array.isArray(value)) {
          return value
            .filter((item): item is string => item !== null)
            .map((item) => [key, item] as [string, string])
        }

        return typeof value === 'string' ? [[key, value] as [string, string]] : []
      })
    ).toString()

    await auth.loginWithGoogleCallback(queryString)
    await router.replace('/')
  } catch (e: any) {
    error.value = e?.data?.message || 'Đăng nhập Google thất bại. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <NuxtLayout name="auth">
    <div class="w-full max-w-md mx-auto fade-in-up">
      <header class="mb-8">
        <h2 class="font-headline text-3xl font-bold text-on-surface mb-2">Google Sign In</h2>
        <p class="text-on-surface-variant">We are finalizing your authentication and loading your workspace.</p>
      </header>

      <div v-if="loading" class="rounded-xl bg-surface-high p-6 text-on-surface">
        <p class="font-medium">Signing you in with Google...</p>
      </div>

      <div v-else-if="error" class="rounded-xl bg-error-container p-6 text-error space-y-4">
        <p class="font-medium">{{ error }}</p>
        <NuxtLink to="/login" class="inline-flex items-center justify-center rounded-lg bg-surface-high px-4 py-2 text-sm font-medium text-on-surface">
          Quay lại trang đăng nhập
        </NuxtLink>
      </div>
    </div>
  </NuxtLayout>
</template>
