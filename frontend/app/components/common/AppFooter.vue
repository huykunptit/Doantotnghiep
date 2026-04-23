<template>
  <footer class="w-full py-12 px-4 sm:px-6 lg:px-8 border-t border-surface-dim bg-surface-lowest">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 max-w-7xl mx-auto">
      <div class="col-span-2 md:col-span-1">
        <div class="flex items-center gap-3 mb-6">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl cta-gradient text-white font-bold shadow-sm">E</div>
          <div>
            <span class="block font-headline text-lg font-bold tracking-tight text-on-surface leading-none">The Academic EduPress</span>
            <span class="block text-[10px] uppercase tracking-widest text-outline mt-1">EduPress LMS</span>
          </div>
        </div>
        <p class="text-sm text-on-surface-variant leading-relaxed mb-6">
          Nền tảng học tập chuẩn Editorial. Một không gian hội tụ tri thức  dành cho Học viên, Giảng viên và Quản trị viên.
        </p>
      </div>

      <div>
        <h6 class="text-xs font-bold text-on-surface mb-4 uppercase tracking-widest">Nền tảng</h6>
        <ul class="space-y-3">
          <li><NuxtLink to="/courses" class="text-sm text-on-surface-variant hover:text-primary transition-colors">Danh mục Khóa học</NuxtLink></li>
          <li><NuxtLink to="/pricing" class="text-sm text-on-surface-variant hover:text-primary transition-colors">Gói tài khoản</NuxtLink></li>
          <li><NuxtLink to="/register" class="text-sm text-on-surface-variant hover:text-primary transition-colors">Trở thành Giảng viên</NuxtLink></li>
        </ul>
      </div>

      <div>
        <h6 class="text-xs font-bold text-on-surface mb-4 uppercase tracking-widest">Thông tin</h6>
        <ul class="space-y-3">
          <li><NuxtLink to="/about" class="text-sm text-on-surface-variant hover:text-primary transition-colors">Về chúng tôi</NuxtLink></li>
          <li><NuxtLink to="/career" class="text-sm text-on-surface-variant hover:text-primary transition-colors">Định hướng Sự nghiệp</NuxtLink></li>
          <li><a href="#" class="text-sm text-on-surface-variant hover:text-primary transition-colors">Trung tâm Trợ giúp</a></li>
        </ul>
      </div>

      <div>
        <h6 class="text-xs font-bold text-on-surface mb-4 uppercase tracking-widest">Pháp lý</h6>
        <ul class="space-y-3">
          <li><a href="#" class="text-sm text-on-surface-variant hover:text-primary transition-colors">Chính sách Bảo mật</a></li>
          <li><a href="#" class="text-sm text-on-surface-variant hover:text-primary transition-colors">Điều khoản Sử dụng</a></li>
          <li><a href="#" class="text-sm text-on-surface-variant hover:text-primary transition-colors">Liên kết Cộng tác</a></li>
        </ul>
      </div>
    </div>

    <div class="max-w-7xl mx-auto mt-16 pt-8 border-t border-surface-dim/30 flex flex-col md:flex-row justify-between items-center gap-4">
      <p class="text-xs text-on-surface-variant">© {{ new Date().getFullYear() }} The Academic EduPress (EduPress). Đồ án tốt nghiệp PTIT.</p>
      <div class="flex gap-6">
        <a href="#" class="text-outline hover:text-primary transition-colors"><span class="material-symbols-outlined text-[20px]">public</span></a>
        <a href="#" class="text-outline hover:text-primary transition-colors"><span class="material-symbols-outlined text-[20px]">share</span></a>
        <a href="#" class="text-outline hover:text-primary transition-colors"><span class="material-symbols-outlined text-[20px]">mail</span></a>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
// Logic for Footer if required
</script>
