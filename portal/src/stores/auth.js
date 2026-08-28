import { defineStore } from 'pinia'
import client from '../api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('dominues_token') || null,
    loading: false
  }),
  getters: {
    isAuthenticated: (s) => !!s.token
  },
  actions: {
    setSession(token, user) {
      this.token = token
      this.user = user
      localStorage.setItem('dominues_token', token)
    },
    async login(email, password) {
      this.loading = true
      try {
        const { data } = await client.post('/api/login', { email, password })
        this.setSession(data.token, data.user)
        return data.user
      } finally {
        this.loading = false
      }
    },
    async register(payload) {
      this.loading = true
      try {
        const { data } = await client.post('/api/register', payload)
        this.setSession(data.token, data.user)
        return data.user
      } finally {
        this.loading = false
      }
    },
    async fetchMe() {
      const { data } = await client.get('/api/me')
      this.user = data.user
      return data.user
    },
    logout() {
      this.user = null
      this.token = null
      localStorage.removeItem('dominues_token')
    }
  }
})