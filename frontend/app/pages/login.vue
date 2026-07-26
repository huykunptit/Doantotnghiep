<script setup lang="ts">
import { dashboardFor } from '~/types/auth'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const loading = ref(false)
const error = ref('')
const remember = ref(true)
const form = reactive({ email: '', password: '' })

/**
 * Progressive login: works even when Vite-dev hydration never finishes
 * (common over Cloudflare tunnel). Vue path is preferred once mounted.
 */
function dashboardPath(user: { roles?: string[], role?: string | null }) {
  const roles = user?.roles || (user?.role ? [user.role] : [])
  if (roles.includes('admin')) return '/admin'
  if (roles.includes('instructor')) return '/instructor'
  return '/student'
}

async function loginViaFetch(email: string, password: string) {
  const res = await fetch('/api/auth/login', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    const err: any = new Error(data?.message || 'Đăng nhập thất bại')
    err.statusCode = res.status
    err.data = data
    throw err
  }
  // Persist the same cookies the Pinia store uses
  const maxAge = 60 * 60 * 24 * 7
  document.cookie = `eript-token=${encodeURIComponent(data.access_token)}; Path=/; Max-Age=${maxAge}; SameSite=Lax`
  document.cookie = `eript-user=${encodeURIComponent(JSON.stringify(data.user))}; Path=/; Max-Age=${maxAge}; SameSite=Lax`
  return data
}

async function submit(event?: Event) {
  event?.preventDefault()
  error.value = ''

  // Prefer Vue-bound values; fall back to native DOM if hydration failed
  const formEl = document.getElementById('eript-login-form') as HTMLFormElement | null
  const email
    = form.email
      || (formEl?.querySelector<HTMLInputElement>('input[name="email"]')?.value ?? '')
  const password
    = form.password
      || (formEl?.querySelector<HTMLInputElement>('input[name="password"]')?.value ?? '')

  if (!email || !password) {
    error.value = 'Vui lòng nhập email và mật khẩu.'
    return
  }

  loading.value = true
  try {
    // Prefer Pinia path when store is ready; otherwise plain fetch
    if (auth && typeof auth.login === 'function' && auth.ready !== false) {
      try {
        const response = await auth.login({ email, password })
        await navigateTo(dashboardFor(response.user))
        return
      }
      catch (piniaErr: any) {
        // If Pinia/useApi failed due to cookie race, fall through to fetch
        if (piniaErr?.statusCode && piniaErr.statusCode !== 401 && piniaErr.statusCode !== 422) {
          throw piniaErr
        }
        if (piniaErr?.statusCode === 401 || piniaErr?.statusCode === 422 || piniaErr?.statusCode === 403) {
          throw piniaErr
        }
      }
    }

    const data = await loginViaFetch(email, password)
    window.location.assign(dashboardPath(data.user))
  }
  catch (requestError: any) {
    if (requestError?.statusCode === 403 && requestError?.data?.requires_verification) {
      window.location.assign(`/verify-email?email=${encodeURIComponent(email)}`)
      return
    }
    error.value = requestError?.data?.message || requestError?.message || 'Email hoặc mật khẩu không chính xác.'
  }
  finally {
    loading.value = false
  }
}

// Attach native listener ASAP so login works before/without Vue hydration
onMounted(() => {
  const formEl = document.getElementById('eript-login-form')
  if (!formEl || (formEl as any)._eriptBound) return
  ;(formEl as any)._eriptBound = true
  formEl.addEventListener('submit', (e) => {
    e.preventDefault()
    void submit(e)
  })
})

// Inline bootstrap: runs even if Vue never mounts (hydration hang)
useHead({
  script: [
    {
      key: 'eript-login-boot',
      // Runs on every login page render; no-op once Vue takes over
      children: `
(function () {
  if (window.__eriptLoginBoot) return;
  window.__eriptLoginBoot = true;
  function boot() {
    var form = document.getElementById('eript-login-form');
    if (!form || form._eriptBound) return;
    form._eriptBound = true;
    var btn = form.querySelector('[data-login-btn]');
    var errEl = document.getElementById('eript-login-error');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var email = (form.querySelector('input[name="email"]') || {}).value || '';
      var password = (form.querySelector('input[name="password"]') || {}).value || '';
      if (!email || !password) {
        if (errEl) { errEl.hidden = false; errEl.textContent = 'Vui lòng nhập email và mật khẩu.'; }
        return;
      }
      if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); }
      if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
      fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, password: password })
      }).then(function (res) {
        return res.json().then(function (data) { return { res: res, data: data }; });
      }).then(function (r) {
        if (!r.res.ok) {
          if (r.res.status === 403 && r.data && r.data.requires_verification) {
            location.assign('/verify-email?email=' + encodeURIComponent(email));
            return;
          }
          throw new Error((r.data && r.data.message) || 'Email hoặc mật khẩu không chính xác.');
        }
        var maxAge = 60 * 60 * 24 * 7;
        document.cookie = 'eript-token=' + encodeURIComponent(r.data.access_token) + '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax';
        document.cookie = 'eript-user=' + encodeURIComponent(JSON.stringify(r.data.user)) + '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax';
        var roles = (r.data.user && r.data.user.roles) || [];
        var dest = '/student';
        if (roles.indexOf('admin') !== -1) dest = '/admin';
        else if (roles.indexOf('instructor') !== -1) dest = '/instructor';
        location.assign(dest);
      }).catch(function (err) {
        if (errEl) { errEl.hidden = false; errEl.textContent = err.message || 'Đăng nhập thất bại.'; }
        if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); }
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
      `,
    },
  ],
})
</script>

<template>
  <div class="auth-panel">
    <section class="auth-story">
      <span class="story-label">Eript Learning Ecosystem</span>
      <h1>Một không gian học tập được thiết kế để phát triển lâu dài.</h1>
      <p>Quản trị đào tạo, nội dung, khảo thí và dữ liệu học tập trên cùng một nền tảng.</p>
      <div class="story-points">
        <div><i class="pi pi-chart-line" /><span><strong>Dữ liệu tập trung</strong><small>Theo dõi hiệu quả đào tạo theo thời gian thực.</small></span></div>
        <div><i class="pi pi-shield" /><span><strong>Vận hành an toàn</strong><small>Vai trò và phân quyền rõ ràng cho từng bộ phận.</small></span></div>
        <div><i class="pi pi-sparkles" /><span><strong>Trải nghiệm hiện đại</strong><small>Gọn gàng, nhanh và nhất quán trên mọi thiết bị.</small></span></div>
      </div>
    </section>

    <Card class="auth-card">
      <template #content>
        <div class="auth-heading">
          <span>Chào mừng trở lại</span>
          <h2>Đăng nhập hệ thống</h2>
          <p>Sử dụng tài khoản Eript LMS của bạn.</p>
        </div>

        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <div id="eript-login-error" class="boot-error" hidden />

        <!-- Native controls so login works before PrimeVue/Vue hydrate -->
        <form id="eript-login-form" class="auth-form" method="post" action="#" onsubmit="return false;" @submit.prevent="submit">
          <label>
            <span>Email</span>
            <input
              v-model="form.email"
              class="native-input"
              name="email"
              type="email"
              placeholder="name@example.com"
              autocomplete="email"
              required
            >
          </label>
          <label>
            <span>Mật khẩu</span>
            <input
              v-model="form.password"
              class="native-input"
              name="password"
              type="password"
              placeholder="Nhập mật khẩu"
              autocomplete="current-password"
              required
            >
          </label>
          <div class="form-row">
            <label class="remember">
              <input v-model="remember" type="checkbox">
              <span>Ghi nhớ đăng nhập</span>
            </label>
            <a href="/forgot-password">Quên mật khẩu?</a>
          </div>
          <button
            data-login-btn
            type="submit"
            class="native-submit"
            :disabled="loading"
            :aria-busy="loading ? 'true' : 'false'"
          >
            <span v-if="loading" class="spin" aria-hidden="true" />
            Đăng nhập
          </button>
        </form>

        <AuthGoogleButton label="Đăng nhập bằng Google" @error="error = $event" />

        <p class="auth-foot">Chưa có tài khoản? <a href="/register">Đăng ký ngay</a></p>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.story-points {
  display: grid;
  gap: 16px;
  margin-top: 32px;
}

.story-points > div {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.story-points > div > i {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  border-radius: 9px;
  background: rgba(255, 255, 255, .12);
}

.story-points span {
  display: flex;
  flex-direction: column;
}

.story-points strong { font-size: .78rem; }

.story-points small {
  margin-top: 3px;
  color: rgba(255, 255, 255, .62);
  font-size: .67rem;
  line-height: 1.5;
}

.form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: .7rem;
}

.remember {
  display: flex;
  align-items: center;
  gap: 7px;
  color: var(--text-muted);
}

.form-row a,
.auth-foot a {
  color: var(--brand);
  font-weight: 700;
}

.native-input {
  width: 100%;
  height: 42px;
  padding: 0 12px;
  border: 1px solid var(--border, #d4d4d8);
  border-radius: 10px;
  background: #fff;
  font: inherit;
  color: inherit;
}

.native-input:focus {
  outline: 2px solid color-mix(in srgb, var(--brand, #2f6b4f) 35%, transparent);
  border-color: var(--brand, #2f6b4f);
}

.native-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 44px;
  border: 0;
  border-radius: 10px;
  background: var(--brand, #2f6b4f);
  color: #fff;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.native-submit:disabled {
  opacity: .75;
  cursor: wait;
}

.spin {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, .35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.boot-error {
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: .85rem;
}

.boot-error[hidden] { display: none !important; }
</style>
