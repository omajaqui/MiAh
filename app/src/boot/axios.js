import { defineBoot } from '#q-app'
import axios from 'axios'
import { LocalStorage, Notify } from 'quasar'
import { errorMessage } from '@/utils/format'

const api = axios.create({
  baseURL: `${import.meta.env.API_URL || 'http://127.0.0.1:8000'}/api`,
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = LocalStorage.getItem('token')

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !error.config?.url?.includes('/login')) {
      LocalStorage.remove('token')
      LocalStorage.remove('user')

      if (typeof window !== 'undefined' && !window.location.hash.includes('/login')) {
        window.location.hash = '#/login'
      }
    }

    if (error.response?.status >= 500) {
      Notify.create({
        type: 'negative',
        message: errorMessage(error, 'Error del servidor. Intente de nuevo.'),
      })
    }

    return Promise.reject(error)
  },
)

export default defineBoot(({ app }) => {
  app.config.globalProperties.$api = api
})

export { api }
