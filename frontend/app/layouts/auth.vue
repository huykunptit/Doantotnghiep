<template>
  <div class="min-h-screen w-full flex bg-white sm:bg-gray-50/50">
    <!-- Left Side: Form Area -->
    <div class="w-full lg:w-[45%] xl:w-[40%] flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-16 xl:px-24 bg-white relative z-10 lg:shadow-[20px_0_40px_-20px_rgba(0,0,0,0.1)]">
      
      <!-- Top nav/logo -->
      <div class="absolute top-8 left-6 sm:left-12 lg:left-16 xl:left-24">
        <NuxtLink to="/" class="flex items-center gap-2.5 group">
          <div class="w-10 h-10 bg-gradient-to-br from-primary to-primary-dark rounded-xl flex items-center justify-center shadow-lg shadow-primary/30 group-hover:scale-105 transition-transform duration-300">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <span class="text-xl font-bold tracking-tight text-gray-900">EduPress</span>
        </NuxtLink>
      </div>

      <!-- Main form wrapper -->
      <div class="w-full max-w-[420px] mx-auto mt-12 lg:mt-0 animate-fade-in-up">
        <slot />
      </div>

      <!-- Footer bits -->
      <div class="absolute bottom-8 left-6 sm:left-12 lg:left-16 xl:left-24">
        <p class="text-xs text-gray-400 font-medium tracking-wide">© 2026 EduPress. All rights reserved.</p>
      </div>
    </div>

    <!-- Right Side: Beautiful Branding Showcase -->
    <div class="hidden lg:flex flex-1 relative overflow-hidden bg-primary-dark">
      <!-- Dynamic background effects -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-dark via-[#0a3a2a] to-black opacity-90 z-0"></div>
      
      <!-- Decorative circles -->
      <div class="absolute -top-40 -right-40 w-[600px] h-[600px] rounded-full bg-primary/20 blur-3xl z-0"></div>
      <div class="absolute -bottom-40 -left-20 w-[500px] h-[500px] rounded-full bg-[#10b981]/10 blur-3xl z-0"></div>
      
      <!-- Glassmorphic Content Container -->
      <div class="relative z-10 w-full flex items-center justify-center p-12 lg:p-24">
        <div class="max-w-2xl text-center">
          <!-- Floating UI Elements Illustration -->
          <div class="relative w-full h-80 mb-12 select-none pointer-events-none">
            <!-- Center Card -->
            <div class="absolute top-[10%] left-[50%] -translate-x-[50%] w-[320px] h-[220px] backdrop-blur-xl bg-white/10 rounded-2xl border border-white/20 shadow-2xl p-6 flex flex-col gap-4 animate-float">
              <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-[#3b82f6] to-[#2563eb] mb-2 flex items-center justify-center shadow-lg shadow-blue-500/30">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
              </div>
              <div class="h-4 w-3/4 rounded-full bg-white/30"></div>
              <div class="h-3 w-1/2 rounded-full bg-white/20"></div>
              <div class="mt-auto flex justify-between items-center">
                <div class="flex -space-x-2">
                  <div class="w-8 h-8 rounded-full bg-white/20 border-2 border-white/10"></div>
                  <div class="w-8 h-8 rounded-full bg-white/30 border-2 border-white/10"></div>
                  <div class="w-8 h-8 rounded-full bg-white/40 border-2 border-white/10"></div>
                </div>
                <div class="text-xs font-semibold text-white bg-white/20 px-3 py-1 rounded-full">Pro</div>
              </div>
            </div>

            <!-- Left floating element -->
            <div class="absolute top-[40%] left-[10%] w-[160px] h-[70px] backdrop-blur-xl bg-white/10 rounded-xl border border-white/20 shadow-xl p-3 flex items-center gap-3 animate-float-delayed">
              <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#10b981] to-[#059669] flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              </div>
              <div>
                <div class="h-2 w-16 mb-2 rounded-full bg-white/40"></div>
                <div class="h-2 w-10 rounded-full bg-white/20"></div>
              </div>
            </div>

            <!-- Right floating element -->
            <div class="absolute top-[20%] right-[5%] w-[180px] h-[100px] backdrop-blur-xl bg-white/10 rounded-xl border border-white/20 shadow-xl p-4 flex flex-col gap-3 animate-float-slow">
              <div class="flex justify-between items-center">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#f59e0b] to-[#d97706] flex items-center justify-center">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <span class="text-white text-xs font-bold">+240 XP</span>
              </div>
              <div class="h-2 w-full rounded-full bg-white/20 mt-1"><div class="h-full w-2/3 bg-white/60 rounded-full"></div></div>
            </div>
          </div>

          <!-- Text block -->
          <h2 class="text-4xl lg:text-5xl font-extrabold text-white mb-6 leading-tight">
            Nền tảng tri thức <br/><span class="text-transparent bg-clip-text bg-gradient-to-r from-[#34d399] to-[#10b981]">dành riêng cho bạn</span>
          </h2>
          <p class="text-lg text-gray-300 font-medium leading-relaxed max-w-xl mx-auto mb-10">
            Hàng ngàn khóa học chất lượng cao, bài giảng trực quan và cộng đồng học tập năng động đang chờ đón bạn tham gia khám phá mỗi ngày.
          </p>
          
          <div class="flex items-center justify-center gap-6 text-sm text-gray-300 font-medium">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#10b981]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              Học trọn đời
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#10b981]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              Có chứng chỉ
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#10b981]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              Đội ngũ chuyên gia
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<style>
@keyframes float {
  0% { transform: translate(-50%, 0px); }
  50% { transform: translate(-50%, -15px); }
  100% { transform: translate(-50%, 0px); }
}
@keyframes float-delayed {
  0% { transform: translate(0px, 0px); }
  50% { transform: translate(0px, -10px); }
  100% { transform: translate(0px, 0px); }
}
@keyframes float-slow {
  0% { transform: translate(0px, 0px) rotate(0deg); }
  50% { transform: translate(0px, 15px) rotate(2deg); }
  100% { transform: translate(0px, 0px) rotate(0deg); }
}
@keyframes fade-in-up {
  0% { opacity: 0; transform: translateY(20px); }
  100% { opacity: 1; transform: translateY(0); }
}

.animate-float { animation: float 6s ease-in-out infinite; }
.animate-float-delayed { animation: float-delayed 5s ease-in-out infinite 2s; }
.animate-float-slow { animation: float-slow 8s ease-in-out infinite 1s; }
.animate-fade-in-up { animation: fade-in-up 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
</style>
