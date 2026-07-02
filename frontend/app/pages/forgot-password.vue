<script setup lang="ts">
import AuthPageShell from '~/components/auth/AuthPageShell.vue'
import ForgotPasswordForm from '~/components/auth/ForgotPasswordForm.vue'

definePageMeta({ layout: false })
</script>

<template>
  <AuthPageShell
    panel-kicker="Hỗ trợ tài khoản"
    panel-title="Quên mật khẩu?"
    panel-description="Nhập email của bạn để nhận hướng dẫn đặt lại mật khẩu."
    foot-text="Nhớ lại mật khẩu?"
    foot-link-text="Quay lại đăng nhập"
    foot-link-to="/login"
    hero-title="Chào mừng bạn đến với hệ thống học tập trực tuyến"
    hero-subtitle="Chúng tôi sẽ giúp bạn lấy lại quyền truy cập tài khoản nhanh chóng."
  >
    <ForgotPasswordForm />
  </AuthPageShell>
</template>
