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
      console.warn('CSRF token mismatch detected (419), cleaning cookies and refreshing token')
      cleanupAllOldCookies()
      cookiesCleaned = true
      csrfTokenValidated = false
      lastCleanupTime = Date.now()

      // Получаем новый CSRF токен с сервера
      // Используем текущий URL для правильного домена
      const csrfUrl = window.location.origin + '/sanctum/csrf-cookie'
      return axios.get(csrfUrl, { withCredentials: true }).then(() => {
        // Обновляем токен в meta теге
        const metaToken = document.querySelector('meta[name="csrf-token"]')
        const newToken = getCookie('XSRF-TOKEN')
        if (metaToken && newToken) {
          metaToken.setAttribute('content', newToken)
        }

        // Пробуем повторить запрос с новым токеном
        if (error.config) {
          // Обновляем токен в заголовке запроса
          if (newToken) {
            error.config.headers.common['X-XSRF-TOKEN'] = newToken
          }
          return axios.request(error.config)
        }
        return Promise.reject(error)
      }).catch((csrfError) => {
        console.error('Failed to refresh CSRF token:', csrfError)
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
 * Получить базовый домен для cookies (работает с многоуровневыми поддоменами)
 * Например: test1.dev.crater.billing.mycloud.kg -> .dev.crater.billing.mycloud.kg
 */
function getBaseDomain() {
  const hostname = window.location.hostname
  const parts = hostname.split('.')

  // Для доменов типа test1.dev.crater.billing.mycloud.kg
  // Базовый домен должен быть .dev.crater.billing.mycloud.kg
  // Ищем паттерн: поддомен.основной_домен
  if (parts.length >= 3) {
    // Проверяем, есть ли известный основной домен
    const knownDomains = [
      'dev.crater.billing.mycloud.kg',
      'crater.billing.mycloud.kg',
      'crater.test'
    ]

    for (const knownDomain of knownDomains) {
      const knownParts = knownDomain.split('.')
      if (hostname.endsWith('.' + knownDomain) || hostname === knownDomain) {
        return '.' + knownDomain
      }
    }

    // Если не нашли известный домен, используем последние 3 части
    // Для test1.dev.crater.billing.mycloud.kg -> .dev.crater.billing.mycloud.kg
    if (parts.length >= 4) {
      return '.' + parts.slice(-4).join('.')
    }

    // Для обычных доменов используем последние 2 части
    return '.' + parts.slice(-2).join('.')
  }

  return hostname
}

/**
 * Автоматическая очистка старых/недействительных cookies
 */
function cleanupOldCookies() {
  const allowedCookies = ['crater_session', 'XSRF-TOKEN']
  const cookies = document.cookie.split(';')
  const domain = window.location.hostname
  const baseDomain = getBaseDomain()

  let cleaned = false

  cookies.forEach((cookie) => {
    const cookieName = cookie.split('=')[0].trim()

    // Удаляем старые cookies, которые похожи на session_id (32-40 символов)
    if (!allowedCookies.includes(cookieName) &&
      /^[a-zA-Z0-9]{32,40}$/.test(cookieName) &&
      !cookieName.startsWith('remember_')) {

      // Удаляем для всех возможных вариантов домена
      const domainsToClean = [
        domain,
        baseDomain,
        '.' + domain,
        baseDomain.startsWith('.') ? baseDomain : '.' + baseDomain
      ].filter((d, i, arr) => arr.indexOf(d) === i) // Уникальные значения

      domainsToClean.forEach(d => {
        // Пробуем удалить с разными path
        document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=${d}`
        document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/admin; domain=${d}`
        document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/api; domain=${d}`
      })

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
  const baseDomain = getBaseDomain()

  // Все возможные варианты домена для удаления
  const domains = [
    domain,
    baseDomain,
    '.' + domain,
    baseDomain.startsWith('.') ? baseDomain : '.' + baseDomain,
    '' // Без domain (для cookies без domain атрибута)
  ].filter((d, i, arr) => arr.indexOf(d) === i) // Уникальные значения

  let cleaned = false

  cookies.forEach((cookie) => {
    const cookieName = cookie.split('=')[0].trim()

    // Удаляем все cookies, включая crater_session и XSRF-TOKEN
    // Они будут заменены новыми от сервера
    domains.forEach(d => {
      const domainPart = d ? `; domain=${d}` : ''
      // Удаляем с разными комбинациями path и domain
      document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/${domainPart}`
      document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/admin${domainPart}`
      document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/api${domainPart}`
      document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax${domainPart}`
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
  let lastHostname = window.location.hostname

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

  // Очищаем cookies при переходе на другой поддомен (например, при переключении тенанта)
  window.addEventListener('focus', function () {
    const currentHostname = window.location.hostname
    if (currentHostname !== lastHostname) {
      console.log('Domain changed detected, cleaning old cookies')
      cleanupOldCookies()
      lastHostname = currentHostname
      cookiesCleaned = false
      csrfTokenValidated = false
    }
  })

  // Также проверяем при изменении URL (для SPA навигации)
  let lastUrl = window.location.href
  const checkUrlChange = () => {
    if (window.location.href !== lastUrl) {
      const currentHostname = window.location.hostname
      if (currentHostname !== lastHostname) {
        console.log('URL changed with different domain, cleaning old cookies')
        cleanupOldCookies()
        lastHostname = currentHostname
        cookiesCleaned = false
        csrfTokenValidated = false
      }
      lastUrl = window.location.href
    }
  }

  // Проверяем каждые 500ms (для SPA)
  setInterval(checkUrlChange, 500)
}
