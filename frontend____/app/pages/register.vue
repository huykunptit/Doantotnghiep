<script setup lang="ts">
import AuthPageShell from '~/components/auth/AuthPageShell.vue'
import RegisterForm from '~/components/auth/RegisterForm.vue'

definePageMeta({ layout: false })
</script>

<template>
  <AuthPageShell
    panel-kicker="Bắt đầu"
    panel-title="Tạo tài khoản mới"
    panel-description="Điền thông tin để tạo tài khoản và bắt đầu hành trình học tập của bạn."
    foot-text="Đã có tài khoản?"
    foot-link-text="Đăng nhập"
    foot-link-to="/login"
    hero-title="Bắt đầu hành trình học tập cùng chúng tôi"
    hero-subtitle="Đăng ký miễn phí và tiếp cận hàng trăm khóa học chất lượng cao."
  >
    <RegisterForm />
  </AuthPageShell>
</template>
