import { defineStore } from 'pinia'
import type { AuthResponse, AuthUser, RegisterResponse } from '~/composables/useAuthSession'
import { useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    token: null as string | null,
    isReady: false,
  }),

  getters: {
    isLoggedIn: (state) => !!state.token,
  },

  actions: {
    setUser(user: AuthUser | null) {
      this.user = user
      useAuthUserCookie().value = user
    },

    setToken(token: string | null) {
      this.token = token
      useAuthTokenCookie().value = token
    },

    async register(payload: { name: string; email: string; password: string; password_confirmation: string }) {
      const data = await useApi<RegisterResponse>('/auth/register', {
        method: 'POST',
        body: payload,
      })

      this.setToken(null)
      this.setUser(null)
      this.isReady = true
      return data
    },

    async login(payload: { email: string; password: string }) {
      const data = await useApi<AuthResponse>('/auth/login', {
        method: 'POST',
        body: payload,
      })

      this.setToken(data.access_token)
      this.setUser(data.user)
      this.isReady = true
      return data
    },

    async getGoogleLoginUrl() {
      const data = await useApi<{ url: string }>('/auth/google/url', {
        method: 'GET',
      })

      return data.url
    },

    async loginWithGoogleCallback(queryString: string) {
      const path = queryString ? `/auth/google/callback?${queryString}` : '/auth/google/callback'
      const data = await useApi<AuthResponse>(path, {
        method: 'GET',
      })

      this.setToken(data.access_token)
      this.setUser(data.user)
      this.isReady = true
    },

    async fetchMe() {
      if (!this.token) {
        this.user = null
        return
      }

      try {
        const user = await useApi<AuthUser>('/auth/me', {
          method: 'GET',
          headers: { Authorization: `Bearer ${this.token}` },
        })
        this.user = user
        useAuthUserCookie().value = user
      } catch {
        this.setToken(null)
        this.setUser(null)
      }
    },

    async updateProfile(payload: { name: string; avatar?: string | null; student_code?: string | null; class_name?: string | null; department?: string | null }) {
      const data = await useApi<{ user: 'user' }>('/auth/profile', {
        method: 'PUT',
        body: payload,
        headers: { Authorization: `Bearer ${this.token}` },
      })
      this.setUser(data.user)
    },

    async changePassword(payload: {
      current_password: string
      password: string
      password_confirmation: string
    }) {
      await useApi('/auth/change-password', {
        method: 'PUT',
        body: payload,
        headers: { Authorization: `Bearer ${this.token}` },
      })
    },

    initFromStorage() {
      const tokenCookie = useAuthTokenCookie()
      const userCookie = useAuthUserCookie()
      this.token = tokenCookie.value || null
      this.user = userCookie.value || null
      this.isReady = true
    },

    async logout() {
      if (this.token) {
        try {
          await useApi('/auth/logout', {
            method: 'POST',
            headers: { Authorization: `Bearer ${this.token}` },
          })
        } catch {
          // Ignore failed logout call and clear local state anyway.
        }
      }

      this.setUser(null)
      this.setToken(null)
      this.isReady = true
    },
  }
})

export const useAuth = useAuthStore
