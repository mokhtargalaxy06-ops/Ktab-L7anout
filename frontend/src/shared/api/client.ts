import axios from 'axios'

export interface ApiError {
  code: string
  message: string
  errors?: Record<string, string[]>
  request_id: string
}

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || window.location.origin,
  withCredentials: true,
  withXSRFToken: true,
  timeout: 10000,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use(config => {
  config.headers.set('Accept-Language', document.documentElement.lang || 'fr')
  return config
})

export async function getCsrfCookie() {
  await api.get('/sanctum/csrf-cookie')
}
