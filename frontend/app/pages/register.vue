<template>
  <RegisterForm />
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import RegisterForm from '~/components/auth/RegisterForm.vue'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const router = useRouter()

onMounted(() => {
  if (auth.isLoggedIn) router.replace('/')
})
</script>
