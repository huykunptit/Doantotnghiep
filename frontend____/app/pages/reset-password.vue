<script setup lang="ts">
import AuthPageShell from '~/components/auth/AuthPageShell.vue'
import ResetPasswordForm from '~/components/auth/ResetPasswordForm.vue'

definePageMeta({ layout: false })
</script>

<template>
  <AuthPageShell
    panel-kicker="Bảo mật"
    panel-title="Đặt lại mật khẩu"
    panel-description="Tạo mật khẩu mới để đăng nhập lại và bảo vệ tài khoản của bạn."
    foot-text="Đã nhớ mật khẩu?"
    foot-link-text="Quay lại đăng nhập"
    foot-link-to="/login"
  >
    <ResetPasswordForm />
  </AuthPageShell>
</template>

