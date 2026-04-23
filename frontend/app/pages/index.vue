<script setup lang="ts">
const token = useAuthTokenCookie()
const user = useAuthUserCookie()

if (token.value && user.value) {
  await navigateTo(getDashboardPath(user.value.role), { replace: true })
} else {
  await navigateTo('/login', { replace: true })
}
</script>

<template>
  <div />
</template>
