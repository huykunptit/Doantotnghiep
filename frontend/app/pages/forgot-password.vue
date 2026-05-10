<script setup lang="ts">
import AuthPageShell from '~/components/auth/AuthPageShell.vue'
import ForgotPasswordForm from '~/components/auth/ForgotPasswordForm.vue'

definePageMeta({ layout: false })
</script>

<template>
  <AuthPageShell
    panel-kicker="Hỗ trợ tài khoản"
    panel-title="Quên mật khẩu"
    panel-description="Nhập email để nhận hướng dẫn đặt lại mật khẩu và truy cập lại hệ thống."
    foot-text="Nhớ lại mật khẩu?"
    foot-link-text="Quay lại đăng nhập"
    foot-link-to="/login"
  >
    <ForgotPasswordForm />
  </AuthPageShell>
</template>
