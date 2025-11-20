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
let csrfTokenValidated = false
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

  // Проверяем валидность CSRF токена при первой загрузке или периодически
  const now = Date.now()
  if (!csrfTokenValidated || (now - lastCleanupTime) > CLEANUP_INTERVAL) {
    const metaToken = document.querySelector('meta[name="csrf-token"]')
    const cookieToken = getCookie('XSRF-TOKEN')

    // Если токены не совпадают, очищаем cookies и используем токен из meta тега
    if (metaToken && cookieToken && metaToken.getAttribute('content') !== cookieToken) {
      console.warn('CSRF token mismatch detected (meta vs cookie), cleaning old cookies')
      cleanupAllOldCookies()
      cookiesCleaned = true
      csrfTokenValidated = true
      lastCleanupTime = now
    } else {
      csrfTokenValidated = true
    }
  }

  // Проверяем cookies только периодически или при первой загрузке
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
    // Сначала пробуем получить токен из meta тега (самый актуальный)
    const metaToken = document.querySelector('meta[name="csrf-token"]')
    let csrfToken = metaToken ? metaToken.getAttribute('content') : null

    // Если не нашли в meta, пробуем из cookie XSRF-TOKEN
    if (!csrfToken) {
      csrfToken = getCookie('XSRF-TOKEN')
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
    // Обновляем CSRF токен из ответа, если он был обновлен
    const newToken = response.headers['x-xsrf-token'] || response.headers['xsrf-token']
    if (newToken) {
      updateCsrfToken(newToken)
    }
    return response
  },
  function (error) {
    // Если ошибка CSRF (419), очищаем cookies и получаем новый токен
    if (error.response && error.response.status === 419) {
      console.warn('CSRF token mismatch detected, cleaning cookies and refreshing token')
      cleanupAllOldCookies()
      cookiesCleaned = true
      csrfTokenValidated = false
      lastCleanupTime = Date.now()

      // Получаем новый CSRF токен с сервера
      return axios.get('/sanctum/csrf-cookie').then(() => {
        // Обновляем токен в meta теге
        const metaToken = document.querySelector('meta[name="csrf-token"]')
        const newToken = getCookie('XSRF-TOKEN')
        if (metaToken && newToken) {
          metaToken.setAttribute('content', newToken)
        }

        // Пробуем повторить запрос
        if (error.config) {
          return axios.request(error.config)
        }
        return Promise.reject(error)
      }).catch(() => {
        return Promise.reject(error)
      })
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

/**
 * Агрессивная очистка всех старых cookies (кроме разрешенных)
 * Используется при обнаружении несоответствия CSRF токенов
 */
function cleanupAllOldCookies() {
  // Удаляем все cookies, включая crater_session и старые XSRF-TOKEN
  // Новые будут установлены сервером автоматически
  const cookies = document.cookie.split(';')
  const domain = window.location.hostname
  const domainParts = domain.split('.')

  // Получаем базовый домен
  const baseDomain = domainParts.length > 1
    ? '.' + domainParts.slice(-2).join('.')
    : domain

  // Все возможные варианты домена для удаления
  const domains = []
  if (domain) domains.push(domain)
  if (baseDomain.startsWith('.')) domains.push(baseDomain)
  domains.push('.' + domain)
  domains.push('')

  let cleaned = false

  cookies.forEach((cookie) => {
    const cookieName = cookie.split('=')[0].trim()

    // Удаляем все cookies, включая crater_session и XSRF-TOKEN
    // Они будут заменены новыми от сервера
    domains.forEach(d => {
      // Удаляем с разными комбинациями path и domain
      document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/${d ? `; domain=${d}` : ''}`
      document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax${d ? `; domain=${d}` : ''}`
    })
    cleaned = true
  })

  if (cleaned) {
    console.log('Cleaned up all cookies (CSRF token mismatch detected)')
  }
}

/**
 * Обновить CSRF токен в meta теге и cookie
 */
function updateCsrfToken(token) {
  const metaToken = document.querySelector('meta[name="csrf-token"]')
  if (metaToken) {
    metaToken.setAttribute('content', token)
  }
  // Cookie будет обновлен автоматически Laravel при следующем запросе
}

// Проверяем и очищаем старые cookies при загрузке страницы
if (typeof window !== 'undefined') {
  // Выполняем проверку сразу при загрузке
  window.addEventListener('DOMContentLoaded', function () {
    const metaToken = document.querySelector('meta[name="csrf-token"]')
    const cookieToken = getCookie('XSRF-TOKEN')

    // Если токены не совпадают, агрессивно очищаем cookies
    if (metaToken && cookieToken && metaToken.getAttribute('content') !== cookieToken) {
      console.warn('CSRF token mismatch on page load, cleaning cookies')
      cleanupAllOldCookies()
      cookiesCleaned = true
      csrfTokenValidated = false
    } else {
      // Обычная очистка старых cookies
      cleanupOldCookies()
    }
  }, { once: true })
}
