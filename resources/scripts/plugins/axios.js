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

  // Автоматическая очистка старых cookies перед каждым запросом
  cleanupOldCookies()

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
    } else {
      // Если токен отсутствует, пытаемся получить его с сервера
      console.warn('CSRF token not found, request might fail')
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

/**
 * Автоматическая очистка старых/недействительных cookies
 */
function cleanupOldCookies() {
  const allowedCookies = ['crater_session', 'XSRF-TOKEN']
  const cookies = document.cookie.split(';')
  const domain = window.location.hostname
  const domainParts = domain.split('.')

  // Получаем базовый домен (например, crater.test из test.crater.test)
  const baseDomain = domainParts.length > 1
    ? '.' + domainParts.slice(-2).join('.')
    : domain

  let cleaned = false

  cookies.forEach((cookie) => {
    const cookieName = cookie.split('=')[0].trim()

    // Удаляем старые cookies, которые похожи на session_id (32-40 символов)
    if (!allowedCookies.includes(cookieName) &&
      /^[a-zA-Z0-9]{32,40}$/.test(cookieName) &&
      !cookieName.startsWith('remember_')) {
      // Удаляем для текущего домена
      document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=${domain}`

      // Удаляем для базового домена (с точкой)
      if (baseDomain.startsWith('.')) {
        document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=${baseDomain}`
      }

      cleaned = true
    }
  })

  if (cleaned) {
    console.log('Cleaned up old cookies automatically')
  }
}

// Очищаем старые cookies при загрузке страницы
if (typeof window !== 'undefined') {
  // Выполняем очистку после небольшой задержки, чтобы не мешать загрузке
  setTimeout(cleanupOldCookies, 100)
}
