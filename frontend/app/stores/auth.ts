import { defineStore } from 'pinia'

interface User {
  id: number
  name: string
  email: string
  avatar?: string | null
  role?: string
  roles?: string[]
}

interface AuthPayload {
  access_token: string
  user: User
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    token: null as string | null,
    isReady: false,
  }),

  getters: {
    isLoggedIn: (state) => !!state.token,
  },

  actions: {
    setUser(user: User | null) {
      this.user = user
    },

    setToken(token: string | null) {
      this.token = token
      if (import.meta.client) {
        if (token) {
          localStorage.setItem('token', token)
        } else {
          localStorage.removeItem('token')
        }
      }
    },

    async register(payload: { name: string; email: string; password: string }) {
      const data = await useApi<AuthPayload>('/auth/register', {
        method: 'POST',
        body: payload,
      })

      this.setToken(data.access_token)
      this.setUser(data.user)
      this.isReady = true
    },

    async login(payload: { email: string; password: string }) {
      const data = await useApi<AuthPayload>('/auth/login', {
        method: 'POST',
        body: payload,
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
        const user = await useApi<User>('/auth/me', {
          method: 'GET',
          token: this.token,
        })
        this.user = user
      } catch {
        this.setToken(null)
        this.user = null
      }
    },

    async updateProfile(payload: { name: string; avatar?: string | null }) {
      const data = await useApi<{ user: User }>('/auth/profile', {
        method: 'PUT',
        body: payload,
        token: this.token,
      })
      this.user = data.user
    },

    async changePassword(payload: {
      current_password: string
      password: string
      password_confirmation: string
    }) {
      await useApi('/auth/change-password', {
        method: 'PUT',
        body: payload,
        token: this.token,
      })
    },

    initFromStorage() {
      if (import.meta.client && !this.token) {
        this.token = localStorage.getItem('token')
      }
      this.isReady = true
    },

    async logout() {
      if (this.token) {
        try {
          await useApi('/auth/logout', {
            method: 'POST',
            token: this.token,
          })
        } catch {
          // Ignore failed logout call and clear local state anyway.
        }
      }

      this.user = null
      this.setToken(null)
      this.isReady = true
    },
  }
})

export const useAuth = useAuthStore
