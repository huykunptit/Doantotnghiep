<script setup lang="ts">
import AuthPageShell from '~/components/auth/AuthPageShell.vue'
import RegisterForm from '~/components/auth/RegisterForm.vue'

definePageMeta({ layout: false })
</script>

<template>
  <AuthPageShell
    panel-kicker="Xin chào"
    panel-title="Đăng ký"
    panel-description="Điền thông tin để tạo tài khoản mới và bắt đầu sử dụng nền tảng học tập."
    foot-text="Đã có tài khoản?"
    foot-link-text="Đăng nhập ngay"
    foot-link-to="/login"
  >
    <RegisterForm />
  </AuthPageShell>
</template>
