<template>
  <main class="auth-wrapper">
    <div class="auth-card">
      <!-- Left panel: branding + testimonial -->
      <section class="auth-left">
        <NuxtLink to="/" class="auth-logo-block">
          <p class="auth-eyebrow">Learning Platform</p>
          <h1 class="auth-headline">Học tập thích nghi, vững tri thức.</h1>
          <p class="auth-tagline">
            Một không gian học tập thông minh, nuôi dưỡng kỹ năng thực tế bền vững theo tiến trình của chính bạn.
          </p>
        </NuxtLink>

        <div class="auth-stats">
          <div class="auth-stat-card">
            <p class="auth-stat-label">Khóa học</p>
            <p class="auth-stat-value">120+</p>
          </div>
          <div class="auth-stat-card">
            <p class="auth-stat-label">Lớp đang học</p>
            <p class="auth-stat-value">24/7</p>
          </div>
        </div>

        <div class="auth-testimonial">
          <p class="auth-testimonial-label">Trải nghiệm</p>
          <p class="auth-testimonial-quote">
            "Giao diện mới giúp sinh viên vào bài nhanh hơn, còn giảng viên theo dõi tiến độ thuận mắt hơn hẳn."
          </p>
          <div class="auth-testimonial-author">
            <div class="auth-avatar">SY</div>
            <div>
              <p class="auth-author-name">Hệ sinh thái đào tạo</p>
              <p class="auth-author-role">Tối ưu hóa khả năng thích nghi</p>
            </div>
          </div>
        </div>

        <div class="auth-bg-blur"></div>
      </section>

      <!-- Right panel: form slot -->
      <section class="auth-right">
        <div class="auth-mobile-header">
          <NuxtLink to="/" class="auth-mobile-logo">Learning Platform</NuxtLink>
          <h1 class="auth-mobile-title">Đăng nhập để tiếp tục</h1>
          <p class="auth-mobile-desc">Một không gian học trực tuyến thích nghi, tập trung vào khóa học và tiến độ của bạn.</p>
        </div>

        <div class="auth-form-container">
          <div class="auth-form-card">
            <slot />
          </div>
        </div>

        <footer class="auth-footer">
          <p>&copy; 2026 Learning Platform</p>
          <div class="auth-footer-links">
            <NuxtLink to="/">Chính sách</NuxtLink>
            <NuxtLink to="/">Điều khoản</NuxtLink>
            <NuxtLink to="/">Hỗ trợ</NuxtLink>
          </div>
        </footer>
      </section>
    </div>
  </main>
</template>

<style scoped>
.auth-wrapper {
  min-height: 100vh;
  padding: 1rem;
  background: #f9fafb;
  display: flex;
  align-items: center;
  justify-content: center;
}
.auth-card {
  width: 100%;
  max-width: 1200px;
  display: grid;
  grid-template-columns: 1fr;
  border-radius: 24px;
  overflow: hidden;
  background: #ffffff;
  box-shadow: 0 20px 60px -20px rgba(0, 0, 0, 0.12);
  border: 1px solid rgba(0, 0, 0, 0.06);
}

/* Left branding panel */
.auth-left {
  position: relative;
  display: none;
  flex-direction: column;
  justify-content: space-between;
  padding: 3rem;
  background: linear-gradient(135deg, var(--theme-primary, #0ea5e9) 0%, var(--theme-primary-dark, #0284c7) 100%);
  color: #ffffff;
}
.auth-logo-block { display: block; text-decoration: none; color: inherit; z-index: 10; position: relative; }
.auth-eyebrow {
  font-size: 0.75rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 1rem;
}
.auth-headline {
  font-size: 2rem; font-weight: 800;
  line-height: 1.2; margin-bottom: 1rem;
}
.auth-tagline {
  font-size: 1rem; line-height: 1.7;
  color: rgba(255, 255, 255, 0.85);
  max-width: 28rem;
}
.auth-stats {
  display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
  z-index: 10; position: relative;
}
.auth-stat-card {
  padding: 1rem; border-radius: 16px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(8px);
}
.auth-stat-label { font-size: 0.875rem; color: rgba(255, 255, 255, 0.7); }
.auth-stat-value { font-size: 1.75rem; font-weight: 700; margin-top: 0.5rem; }

.auth-testimonial {
  padding: 1.5rem; border-radius: 20px;
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(12px);
  z-index: 10; position: relative;
}
.auth-testimonial-label {
  font-size: 0.75rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: rgba(255, 255, 255, 0.6);
}
.auth-testimonial-quote {
  font-size: 1.125rem; line-height: 1.7;
  margin-top: 1rem; color: rgba(255, 255, 255, 0.9);
}
.auth-testimonial-author {
  display: flex; align-items: center; gap: 0.75rem; margin-top: 1.5rem;
}
.auth-avatar {
  width: 44px; height: 44px; border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.875rem;
}
.auth-author-name { font-size: 0.875rem; font-weight: 600; }
.auth-author-role { font-size: 0.8125rem; color: rgba(255, 255, 255, 0.65); }

.auth-bg-blur {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(circle at 10% 20%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
    radial-gradient(circle at 90% 10%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 50% 90%, rgba(255, 255, 255, 0.08) 0%, transparent 50%);
}

/* Right form panel */
.auth-right {
  display: flex; flex-direction: column;
  background: #fafafa;
}
.auth-mobile-header {
  display: block;
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}
.auth-mobile-logo {
  font-size: 0.75rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: var(--theme-primary, #0ea5e9);
  text-decoration: none;
}
.auth-mobile-title {
  font-size: 1.5rem; font-weight: 700;
  margin-top: 0.75rem; color: #1f2937;
}
.auth-mobile-desc {
  font-size: 0.875rem; line-height: 1.6;
  margin-top: 0.5rem; color: #6b7280;
  max-width: 36rem;
}
.auth-form-container {
  flex: 1;
  display: flex; align-items: center; justify-content: center;
  padding: 2rem 1.5rem;
}
.auth-form-card {
  width: 100%;
  max-width: 480px;
  padding: 1.5rem;
  border-radius: 20px;
  background: #ffffff;
  border: 1px solid rgba(0, 0, 0, 0.06);
  box-shadow: 0 10px 30px -15px rgba(0, 0, 0, 0.1);
}
.auth-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(0, 0, 0, 0.06);
  font-size: 0.875rem; color: #6b7280;
  display: flex; flex-direction: column; gap: 0.75rem;
}
.auth-footer-links {
  display: flex; flex-wrap: wrap; gap: 1rem;
}
.auth-footer-links a {
  color: #6b7280;
  text-decoration: none;
  transition: color 150ms;
}
.auth-footer-links a:hover { color: var(--theme-primary, #0ea5e9); }

/* Desktop layout */
@media (min-width: 1024px) {
  .auth-card { grid-template-columns: 0.95fr 1.05fr; }
  .auth-left { display: flex; }
  .auth-mobile-header { display: none; }
  .auth-form-container { padding: 3rem; }
  .auth-form-card { padding: 2rem; }
  .auth-footer {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 3rem;
  }
}

/* Dark mode */
:global(.dark) .auth-wrapper { background: #0a0a0b; }
:global(.dark) .auth-card { background: #18181b; border-color: rgba(255, 255, 255, 0.08); }
:global(.dark) .auth-right { background: #18181b; }
:global(.dark) .auth-mobile-header { border-color: rgba(255, 255, 255, 0.08); }
:global(.dark) .auth-mobile-title { color: #f9fafb; }
:global(.dark) .auth-mobile-desc { color: #9ca3af; }
:global(.dark) .auth-form-card { background: #27272a; border-color: rgba(255, 255, 255, 0.08); }
:global(.dark) .auth-footer { border-color: rgba(255, 255, 255, 0.08); color: #9ca3af; }
:global(.dark) .auth-footer-links a { color: #9ca3af; }
:global(.dark) .auth-footer-links a:hover { color: var(--theme-primary-light, #7dd3fc); }
</style>
