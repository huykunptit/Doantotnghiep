<script setup lang="ts">
import { dashboardFor } from '~/types/auth'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const { t, locale } = useI18n()
const loading = ref(false)
const error = ref('')
const remember = ref(true)
const showPassword = ref(false)
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

/** Không bao giờ hiện lỗi kỹ thuật kiểu POST /api/... 502 ra UI. */
function friendlyLoginError(err: any): string {
  const status = Number(err?.statusCode || err?.status || err?.response?.status || 0)
  const raw = String(err?.data?.message || err?.message || '')
  const looksTechnical = /POST\s+\/api|GET\s+\/api|\/api\/|status code|Failed to fetch|NetworkError|ECONNREFUSED|\b502\b|\b503\b|\b500\b|\b504\b|FetchError|ofetch/i.test(raw)

  if (status === 401 || status === 422) {
    return t('auth.login.errors.invalid')
  }
  if (status === 429) {
    return t('auth.login.errors.tooMany')
  }
  if (status >= 500 || status === 0 || looksTechnical || !raw) {
    return t('auth.login.errors.generic')
  }
  if (raw.length > 140) {
    return t('auth.login.errors.generic')
  }
  return raw
}

async function loginViaFetch(email: string, password: string) {
  const res = await fetch('/api/auth/login', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    const err: any = new Error(data?.message || t('auth.login.errors.failed'))
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
    error.value = t('auth.login.errors.required')
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
    error.value = friendlyLoginError(requestError)
  }
  finally {
    loading.value = false
  }
}

function onGoogleError(msg: string) {
  error.value = friendlyLoginError({ message: msg })
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

const bootMessages = computed(() => ({
  required: t('auth.login.errors.required'),
  invalid: t('auth.login.errors.invalid'),
  generic: t('auth.login.errors.generic'),
}))

// Inline bootstrap: runs even if Vue never mounts (hydration hang)
useHead(() => ({
  script: [
    {
      key: 'eript-login-boot',
      // Runs on every login page render; no-op once Vue takes over
      children: `
(function () {
  var MSG = ${JSON.stringify(bootMessages.value)};
  window.__eriptLoginMsg = MSG;
  if (window.__eriptLoginBoot) return;
  window.__eriptLoginBoot = true;
  function msgs() { return window.__eriptLoginMsg || MSG; }
  function friendly(msg, status) {
    var s = String(msg || '');
    var st = Number(status || 0);
    var m = msgs();
    if (st === 401 || st === 422) return m.invalid;
    if (/POST\\s+\\/api|\\/api\\/|status code|Failed to fetch|NetworkError|\\b502\\b|\\b503\\b|\\b500\\b|FetchError/i.test(s) || st >= 500 || !s) {
      return m.generic;
    }
    if (s.length > 140) return m.generic;
    return s;
  }
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
        if (errEl) { errEl.hidden = false; errEl.textContent = msgs().required; }
        return;
      }
      if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); }
      if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
      fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, password: password })
      }).then(function (res) {
        return res.json().then(function (data) { return { res: res, data: data }; }).catch(function () {
          return { res: res, data: {} };
        });
      }).then(function (r) {
        if (!r.res.ok) {
          if (r.res.status === 403 && r.data && r.data.requires_verification) {
            location.assign('/verify-email?email=' + encodeURIComponent(email));
            return;
          }
          throw { status: r.res.status, message: (r.data && r.data.message) || '' };
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
        if (errEl) { errEl.hidden = false; errEl.textContent = friendly(err && err.message, err && err.status); }
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
}))

watch(locale, () => {
  if (import.meta.client) {
    ;(window as any).__eriptLoginMsg = bootMessages.value
  }
})
</script>

<template>
  <div class="auth-panel">
    <section class="auth-story">
      <span class="story-label">{{ t('auth.login.storyLabel') }}</span>
      <h1>
        {{ t('auth.login.storyTitle1') }}<br>
        {{ t('auth.login.storyTitle2') }}
      </h1>
      <p>{{ t('auth.login.storyDesc') }}</p>
      <div class="story-points">
        <div><i class="pi pi-chart-line" /><span><strong>{{ t('auth.login.point1Title') }}</strong><small>{{ t('auth.login.point1Desc') }}</small></span></div>
        <div><i class="pi pi-shield" /><span><strong>{{ t('auth.login.point2Title') }}</strong><small>{{ t('auth.login.point2Desc') }}</small></span></div>
        <div><i class="pi pi-sparkles" /><span><strong>{{ t('auth.login.point3Title') }}</strong><small>{{ t('auth.login.point3Desc') }}</small></span></div>
      </div>
    </section>

    <AuthPanelCard>
        <AuthSideHeader />
        <div class="auth-heading">
          <span>{{ t('auth.login.welcome') }}</span>
          <h2>{{ t('auth.login.title') }}</h2>
          <p>{{ t('auth.login.subtitle') }}</p>
        </div>

        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <div id="eript-login-error" class="boot-error" hidden />

        <!-- Native controls so login works before PrimeVue/Vue hydrate -->
        <form id="eript-login-form" class="auth-form" method="post" action="#" onsubmit="return false;" @submit.prevent="submit">
          <label>
            <span>{{ t('auth.login.email') }}</span>
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
            <span>{{ t('auth.login.password') }}</span>
            <div class="password-wrap">
              <input
                v-model="form.password"
                class="native-input"
                name="password"
                :type="showPassword ? 'text' : 'password'"
                :placeholder="t('auth.login.passwordPlaceholder')"
                autocomplete="current-password"
                required
              >
              <button
                type="button"
                class="toggle-password"
                :aria-label="showPassword ? t('auth.login.hidePassword') : t('auth.login.showPassword')"
                @click="showPassword = !showPassword"
              >
                <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'" />
              </button>
            </div>
          </label>
          <div class="form-row">
            <label class="remember">
              <input v-model="remember" type="checkbox">
              <span>{{ t('auth.login.remember') }}</span>
            </label>
            <a href="/forgot-password">{{ t('auth.login.forgot') }}</a>
          </div>
          <button
            data-login-btn
            type="submit"
            class="native-submit"
            :disabled="loading"
            :aria-busy="loading ? 'true' : 'false'"
          >
            <span v-if="loading" class="spin" aria-hidden="true" />
            {{ t('auth.login.submit') }}
          </button>
        </form>

        <AuthGoogleButton :label="t('auth.login.google')" @error="onGoogleError" />

        <p class="auth-foot">{{ t('auth.login.noAccount') }} <a href="/register">{{ t('auth.login.register') }}</a></p>
    </AuthPanelCard>
  </div>
</template>

<style scoped>
.story-points {
  display: grid;
  gap: 18px;
  margin-top: 36px;
}

.story-points > div {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.story-points > div > i {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  flex: 0 0 40px;
  margin-top: 1px;
  border-radius: 10px;
  background: rgba(255, 255, 255, .14);
  font: inherit;
  font-size: 1rem;
}

.story-points span {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.story-points strong {
  font-size: 1.02rem;
  font-weight: 650;
  letter-spacing: -.01em;
  line-height: 1.25;
}

.story-points small {
  margin: 0;
  color: rgba(255, 255, 255, .8);
  font-size: .9rem;
  line-height: 1.5;
}

.form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: .88rem;
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
  height: 38px;
  padding: 0 12px;
  border: 1px solid var(--border, #d4d4d8);
  border-radius: 10px;
  background: #fff;
  font: inherit;
  color: inherit;
}

.native-input:focus {
  outline: 2px solid color-mix(in srgb, var(--brand, #0f766e) 35%, transparent);
  border-color: var(--brand, #0f766e);
}

.native-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 40px;
  border: 0;
  border-radius: 10px;
  background: var(--brand, #0f766e);
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

.password-wrap {
  position: relative;
  display: block;
}
.password-wrap .native-input {
  width: 100%;
  padding-right: 44px;
  box-sizing: border-box;
}
.toggle-password {
  position: absolute;
  top: 50%;
  right: 8px;
  transform: translateY(-50%);
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #64748b;
  cursor: pointer;
}
.toggle-password:hover {
  color: #0f172a;
  background: rgba(15, 23, 42, .06);
}
</style>
