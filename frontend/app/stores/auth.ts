import { defineStore } from 'pinia'
import type { AuthResponse, AuthUser, RegisterResponse } from '~/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(null)
  const ready = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value && user.value))
  const roles = computed(() => user.value?.roles || (user.value?.role ? [user.value.role] : []))

  function persist() {
    useCookie<string | null>('sylva-token', {
      path: '/',
      maxAge: 60 * 60 * 24 * 7,
      sameSite: 'lax',
    }).value = token.value
    useCookie<AuthUser | null>('sylva-user', {
      path: '/',
      maxAge: 60 * 60 * 24 * 7,
      sameSite: 'lax',
    }).value = user.value
  }

  function hydrate() {
    token.value = useCookie<string | null>('sylva-token').value || null
    user.value = useCookie<AuthUser | null>('sylva-user').value || null
    ready.value = true
  }

  async function login(payload: { email: string; password: string }) {
    const response = await useApi<AuthResponse>('/auth/login', {
      method: 'POST',
      body: payload,
      token: null,
    })
    token.value = response.access_token
    user.value = response.user
    ready.value = true
    persist()
    return response
  }

  async function googleLoginUrl() {
    const response = await useApi<{ url: string }>('/auth/google/url', { token: null })
    return response.url
  }

  async function loginWithToken(accessToken: string) {
    // Validate before persisting: the cookie write is async, so the token must be
    // passed explicitly instead of relying on `useApi` reading it back.
    const profile = await useApi<AuthUser>('/auth/me', { token: accessToken })
    token.value = accessToken
    user.value = profile
    ready.value = true
    persist()
    return { access_token: accessToken, token_type: 'Bearer', user: profile } satisfies AuthResponse
  }

  async function loginWithGoogle(query: Record<string, string> | string) {
    const params = typeof query === 'string'
      ? Object.fromEntries(new URLSearchParams(query.startsWith('?') ? query.slice(1) : query))
      : query

    const response = await useApi<AuthResponse>('/auth/google/callback', {
      token: null,
      query: { ...params, format: 'json' },
    })
    token.value = response.access_token
    user.value = response.user
    ready.value = true
    persist()
    return response
  }

  async function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
  }) {
    return await useApi<RegisterResponse>('/auth/register', {
      method: 'POST',
      body: payload,
      token: null,
    })
  }

  async function fetchMe() {
    if (!token.value) return null
    try {
      user.value = await useApi<AuthUser>('/auth/me', { token: token.value })
      persist()
      return user.value
    }
    catch {
      clear()
      return null
    }
  }

  function clear() {
    token.value = null
    user.value = null
    ready.value = true
    persist()
  }

  async function logout() {
    try {
      if (token.value) await useApi('/auth/logout', { method: 'POST' })
    }
    finally {
      clear()
    }
  }

  return {
    user,
    token,
    ready,
    roles,
    isAuthenticated,
    hydrate,
    login,
    googleLoginUrl,
    loginWithToken,
    loginWithGoogle,
    register,
    fetchMe,
    logout,
  }
})
