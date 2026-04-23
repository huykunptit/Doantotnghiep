<template>
  <main class="min-h-screen overflow-x-hidden bg-[radial-gradient(circle_at_top_left,_rgba(23,78,166,0.14),_transparent_34%),linear-gradient(180deg,_#f8fbff_0%,_#eef4ff_46%,_#f7f9fc_100%)] px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8">
    <div class="mx-auto grid min-h-[calc(100vh-2.5rem)] w-full max-w-6xl overflow-hidden rounded-[28px] border border-white/70 bg-white/88 shadow-[0_24px_80px_-32px_rgba(23,78,166,0.28)] backdrop-blur lg:min-h-[calc(100vh-4rem)] lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
      <section class="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between bg-[linear-gradient(160deg,#0f3f91_0%,#174ea6_40%,#2b6ce2_100%)] p-12 text-white">
        <NuxtLink to="/" class="relative z-10 block space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.32em] text-white/70">PTIT LMS</p>
          <div>
            <h1 class="font-headline text-4xl font-extrabold tracking-[-0.04em] text-white">Học tập trực tuyến, gọn và rõ.</h1>
            <p class="mt-4 max-w-sm text-base leading-7 text-white/80">
              Một không gian học tập tập trung, nơi nội dung, tiến độ và giảng viên được kết nối trong cùng một trải nghiệm.
            </p>
          </div>
        </NuxtLink>

        <div class="relative z-10 max-w-md space-y-6">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
              <p class="text-white/65">Khóa học</p>
              <p class="mt-2 text-2xl font-bold tracking-tight">120+</p>
            </div>
            <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
              <p class="text-white/65">Lớp đang học</p>
              <p class="mt-2 text-2xl font-bold tracking-tight">24/7</p>
            </div>
          </div>

          <div class="rounded-[24px] border border-white/15 bg-white/12 p-6 backdrop-blur-md">
            <p class="text-sm uppercase tracking-[0.24em] text-white/55">Trải nghiệm</p>
            <p class="mt-4 text-lg leading-8 text-white/88">
              "Giao diện mới giúp sinh viên vào bài nhanh hơn, còn giảng viên theo dõi tiến độ thuận mắt hơn hẳn."
            </p>
            <div class="mt-6 flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-full bg-white/18 text-sm font-bold text-white">PT</div>
              <div>
                <p class="text-sm font-semibold text-white">Nền tảng đào tạo PTIT</p>
                <p class="text-sm text-white/60">Tối ưu cho cả giảng viên và sinh viên</p>
              </div>
            </div>
          </div>
        </div>

        <div class="pointer-events-none absolute inset-0">
          <div class="absolute -left-24 top-20 h-64 w-64 rounded-full bg-white/12 blur-3xl"></div>
          <div class="absolute right-0 top-0 h-72 w-72 rounded-full bg-sky-300/20 blur-3xl"></div>
          <div class="absolute bottom-0 left-1/3 h-56 w-56 rounded-full bg-cyan-200/10 blur-3xl"></div>
        </div>
      </section>

      <section class="flex min-h-full flex-col bg-surface-lowest/95">
        <div class="border-b border-outline-variant/20 px-5 py-5 sm:px-8 lg:hidden">
          <div>
            <NuxtLink to="/" class="inline-block">
              <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">PTIT LMS</p>
            </NuxtLink>
            <h1 class="mt-2 font-headline text-2xl font-bold tracking-[-0.03em] text-on-surface">Đăng nhập để tiếp tục học tập</h1>
            <p class="mt-2 max-w-lg text-sm leading-6 text-on-surface-variant">Một không gian học trực tuyến gọn gàng, tập trung vào khóa học và tiến độ của bạn.</p>
          </div>
        </div>

        <div class="flex flex-1 flex-col justify-center px-5 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-12">
          <div class="mx-auto w-full max-w-[560px]">
            <div class="rounded-[28px] border border-outline-variant/20 bg-surface-lowest p-6 shadow-[0_18px_40px_-24px_rgba(15,63,145,0.22)] sm:p-8">
              <slot />
            </div>
          </div>
        </div>

        <footer class="border-t border-outline-variant/20 px-5 py-4 sm:px-8 lg:px-14">
          <div class="flex flex-col gap-3 text-sm text-on-surface-variant sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; 2026 PTIT LMS.</p>
            <div class="flex flex-wrap gap-4">
              <NuxtLink to="/" class="transition-colors hover:text-primary">Chính sách</NuxtLink>
              <NuxtLink to="/" class="transition-colors hover:text-primary">Điều khoản</NuxtLink>
              <NuxtLink to="/" class="transition-colors hover:text-primary">Hỗ trợ</NuxtLink>
            </div>
          </div>
        </footer>
      </section>
    </div>
  </main>
</template>
