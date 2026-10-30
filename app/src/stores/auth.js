import { defineStore } from 'pinia'
import { LocalStorage } from 'quasar'
import { api } from '@/boot/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: LocalStorage.getItem('token') || null,
    user: LocalStorage.getItem('user') || null,
    ready: false,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token && state.user),
    isAdmin: (state) => state.user?.role === 'admin',
    isSocio: (state) => state.user?.role === 'socio',
    fullName: (state) => state.user?.person?.full_name || state.user?.email || '',
  },
  actions: {
    persist() {
      if (this.token) {
        LocalStorage.set('token', this.token)
      } else {
        LocalStorage.remove('token')
      }

      if (this.user) {
        LocalStorage.set('user', this.user)
      } else {
        LocalStorage.remove('user')
      }
    },
    async login(email, password) {
      const { data } = await api.post('/login', { email, password })
      this.token = data.data.token
      this.user = data.data.user
      this.persist()
      return this.user
    },
    async fetchMe() {
      const { data } = await api.get('/me')
      this.user = data.data
      this.persist()
      return this.user
    },
    async restore() {
      if (this.token) {
        try {
          await this.fetchMe()
        } catch {
          this.clear()
        }
      }

      this.ready = true
    },
    async logout() {
      try {
        await api.post('/logout')
      } catch {
        // El cierre local aplica aunque el API no responda.
      }

      this.clear()
    },
    clear() {
      this.token = null
      this.user = null
      this.persist()
    },
    homeRoute() {
      return this.isAdmin ? '/admin' : '/socio'
    },
  },
})
