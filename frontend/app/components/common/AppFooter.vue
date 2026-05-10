<script setup lang="ts">
const { brandMark, siteLogo, siteName, siteTagline, settings } = useSiteSettings()

const siteDescription = computed(
  () => settings.value?.site_description || 'Hệ thống học tập trực tuyến dành cho sinh viên, giảng viên và quản trị viên với trải nghiệm hiện đại, rõ ràng và dễ sử dụng.',
)
const supportEmail = computed(() => settings.value?.contact_email || 'support@example.com')
const supportPhone = computed(() => settings.value?.contact_phone || '1900 9999')
const footerCopyright = computed(
  () => settings.value?.footer_copyright || `© ${new Date().getFullYear()} ${siteName.value}. Đồ án tốt nghiệp.`,
)
</script>

<template>
  <footer class="cd-footer">
    <div class="cd-footer-inner">
      <div class="cd-footer-brand">
        <div class="cd-footer-logo-group">
          <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="cd-footer-logo">
          <div v-else class="cd-footer-icon">{{ brandMark }}</div>
          <div>
            <p class="cd-footer-title">{{ siteName }}</p>
            <p class="cd-footer-slogan">{{ siteTagline || 'Nền tảng học tập số' }}</p>
          </div>
        </div>
        <p class="cd-footer-desc">{{ siteDescription }}</p>
        <div class="cd-footer-tags">
          <span class="cd-tag">E-Learning</span>
          <span class="cd-tag">LMS</span>
          <span class="cd-tag">PTIT Graduation Project</span>
        </div>
      </div>

      <div class="cd-footer-links">
        <h3 class="cd-footer-heading">Khám phá</h3>
        <ul class="cd-footer-list">
          <li><NuxtLink to="/" class="cd-footer-link">Trang chủ</NuxtLink></li>
          <li><NuxtLink to="/courses" class="cd-footer-link">Danh sách khóa học</NuxtLink></li>
          <li><NuxtLink to="/career" class="cd-footer-link">Lộ trình nghề nghiệp</NuxtLink></li>
          <li><NuxtLink to="/register" class="cd-footer-link">Đăng ký tài khoản</NuxtLink></li>
        </ul>
      </div>

      <div class="cd-footer-links">
        <h3 class="cd-footer-heading">Hỗ trợ</h3>
        <ul class="cd-footer-list">
          <li><a :href="`mailto:${supportEmail}`" class="cd-footer-link">{{ supportEmail }}</a></li>
          <li><a :href="`tel:${supportPhone}`" class="cd-footer-link">{{ supportPhone }}</a></li>
          <li><NuxtLink to="/profile" class="cd-footer-link">Tài khoản của bạn</NuxtLink></li>
          <li><NuxtLink to="/orders" class="cd-footer-link">Lịch sử đơn hàng</NuxtLink></li>
        </ul>
      </div>

      <div class="cd-footer-cta">
        <p class="cd-cta-title">Sẵn sàng bắt đầu học?</p>
        <p class="cd-cta-desc">Tham gia hệ thống để khám phá khóa học, theo dõi tiến độ và xây dựng lộ trình phát triển của riêng bạn.</p>
        <div class="cd-cta-actions">
          <NuxtLink to="/courses" class="cd-btn-primary">Xem khóa học</NuxtLink>
          <NuxtLink to="/register" class="cd-btn-outline">Tạo tài khoản</NuxtLink>
        </div>
      </div>
    </div>

    <div class="cd-footer-bottom">
      <div class="cd-footer-bottom-inner">
        <p class="cd-copyright">{{ footerCopyright }}</p>
        <div class="cd-tech-stack">
          <span>Modern UI</span>
          <span>Responsive</span>
          <span>Nuxt + Vanilla CSS</span>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.cd-footer {
  margin-top: 6rem;
  border-top: 1px solid rgba(17, 17, 17, 0.08);
  background: #fff;
}

.cd-footer-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 3.5rem 1rem;
  display: grid;
  gap: 2.5rem;
}

@media (min-width: 640px) { .cd-footer-inner { padding-left: 1.5rem; padding-right: 1.5rem; } }
@media (min-width: 1024px) {
  .cd-footer-inner { grid-template-columns: 1.4fr 1fr 1fr 1.1fr; }
}

/* Brand */
.cd-footer-brand { display: flex; flex-direction: column; gap: 1.25rem; }
.cd-footer-logo-group { display: flex; align-items: center; gap: 12px; }
.cd-footer-icon {
  display: flex; align-items: center; justify-content: center;
  width: 48px; height: 48px; border-radius: 16px;
  background: var(--green);
  color: #fff; font-size: 1.125rem; font-weight: 900;
  box-shadow: 0 8px 20px rgba(var(--green-rgb), 0.2);
}
.cd-footer-logo {
  width: 48px; height: 48px; border-radius: 16px;
  object-fit: cover;
  box-shadow: 0 8px 20px rgba(var(--green-rgb), 0.2);
}
.cd-footer-title { margin: 0; font-size: 1.125rem; font-weight: 900; letter-spacing: -0.02em; color: var(--on-surface, #0f172a); }
.cd-footer-slogan { margin: 0; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.28em; color: var(--outline, #64748b); }
.cd-footer-desc { margin: 0; max-width: 28rem; font-size: 0.875rem; line-height: 1.75; color: var(--on-surface-variant, #475569); }

.cd-footer-tags { display: flex; flex-wrap: wrap; gap: 12px; }
.cd-tag {
  padding: 8px 16px; border-radius: 999px;
  background: var(--surface, #f8fafc);
  font-size: 0.75rem; font-weight: 600; color: var(--on-surface-variant, #475569);
}

/* Links */
.cd-footer-heading {
  margin: 0 0 1rem; font-size: 0.875rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.22em; color: var(--on-surface, #0f172a);
}
.cd-footer-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
.cd-footer-link {
  font-size: 0.875rem; color: var(--on-surface-variant, #475569); text-decoration: none; transition: color 0.2s;
}
.cd-footer-link:hover { color: var(--primary, var(--green)); }

/* CTA */
.cd-footer-cta {
  padding: 1.5rem; border-radius: 24px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  background: var(--surface, #f8fafc);
}
.cd-cta-title { margin: 0; font-size: 0.875rem; font-weight: 700; color: var(--on-surface, #0f172a); }
.cd-cta-desc { margin: 12px 0 0; font-size: 0.875rem; line-height: 1.75; color: var(--on-surface-variant, #475569); }
.cd-cta-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 1.25rem; }

.cd-btn-primary {
  padding: 10px 20px; border-radius: 999px;
  background: var(--primary, var(--green)); color: #fff;
  font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: opacity 0.2s;
}
.cd-btn-primary:hover { opacity: 0.9; }

.cd-btn-outline {
  padding: 10px 20px; border-radius: 999px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  color: var(--on-surface, #0f172a);
  font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: all 0.2s;
}
.cd-btn-outline:hover { border-color: rgba(var(--green-rgb), 0.4); color: var(--primary, var(--green)); }

/* Bottom */
.cd-footer-bottom { border-top: 1px solid rgba(17, 17, 17, 0.08); }
.cd-footer-bottom-inner {
  max-width: 1200px; margin: 0 auto;
  padding: 1.25rem 1rem;
  display: flex; flex-direction: column; gap: 12px;
  font-size: 0.875rem; color: var(--on-surface-variant, #475569);
}
@media (min-width: 640px) { .cd-footer-bottom-inner { padding-left: 1.5rem; padding-right: 1.5rem; } }
@media (min-width: 768px) {
  .cd-footer-bottom-inner { flex-direction: row; align-items: center; justify-content: space-between; }
}

.cd-copyright { margin: 0; }
.cd-tech-stack { display: flex; align-items: center; gap: 20px; }
.cd-tech-stack span { transition: color 0.2s; cursor: default; }
.cd-tech-stack span:hover { color: var(--primary, var(--green)); }
</style>
