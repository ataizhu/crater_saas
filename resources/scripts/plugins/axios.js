import axios from 'axios'
import Ls from '@/scripts/services/ls.js'

window.Ls = Ls
window.axios = axios
axios.defaults.withCredentials = true

axios.defaults.headers.common = {
  'X-Requested-With': 'XMLHttpRequest',
}

/**
 * Interceptors
 */

axios.interceptors.request.use(function (config) {
  // Pass selected company to header on all requests
  const companyId = Ls.get('selectedCompany')

  const authToken = Ls.get('auth.token')

  if (authToken) {
    config.headers.common.Authorization = authToken
  }

  if (companyId) {
    config.headers.common['company'] = companyId
  }

  // Добавляем CSRF токен для POST/PUT/PATCH/DELETE запросов
  const method = config.method?.toUpperCase()
  if (method && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    // Пробуем получить токен из cookie XSRF-TOKEN (Laravel автоматически устанавливает его)
    let csrfToken = getCookie('XSRF-TOKEN')

    // Если не нашли в cookie, пробуем из meta тега (fallback)
    if (!csrfToken) {
      const metaToken = document.querySelector('meta[name="csrf-token"]')
      if (metaToken) {
        csrfToken = metaToken.getAttribute('content')
      }
    }

    if (csrfToken) {
      // Добавляем в заголовок X-XSRF-TOKEN (стандартная практика для SPA)
      config.headers.common['X-XSRF-TOKEN'] = csrfToken
    }
  }

  return config
})

/**
 * Получить значение cookie по имени
 */
function getCookie(name) {
  if (!document.cookie) return null

  const value = `; ${document.cookie}`
  const parts = value.split(`; ${name}=`)
  if (parts.length === 2) {
    return parts.pop().split(';').shift()
  }
  return null
}
