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

// Флаг для отслеживания, была ли уже выполнена очистка cookies в этой сессии
let cookiesCleaned = false
let lastCleanupTime = 0
const CLEANUP_INTERVAL = 5 * 60 * 1000 // Проверяем не чаще раза в 5 минут

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

  // Проверяем cookies только периодически или при первой загрузке
  const now = Date.now()
  if (!cookiesCleaned || (now - lastCleanupTime) > CLEANUP_INTERVAL) {
    // Быстрая проверка: если cookies много (больше 4), выполняем очистку
    const cookieCount = document.cookie.split(';').filter(c => c.trim()).length
    if (cookieCount > 4) {
      cleanupOldCookies()
      cookiesCleaned = true
      lastCleanupTime = now
    }
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
    } else {
      // Если токен отсутствует, пытаемся получить его с сервера
      console.warn('CSRF token not found, request might fail')
    }
  }

  return config
})

// Перехватчик ответов для очистки cookies при CSRF ошибке
axios.interceptors.response.use(
  function (response) {
    return response
  },
  function (error) {
    // Если ошибка CSRF (419), очищаем cookies и пробуем снова
    if (error.response && error.response.status === 419) {
      console.warn('CSRF token mismatch detected, cleaning cookies')
      cleanupOldCookies()
      cookiesCleaned = true
      lastCleanupTime = Date.now()
    }
    return Promise.reject(error)
  }
)

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
