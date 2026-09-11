import { route } from 'ziggy-js'

const pendingRemovalKey = 'arice.push.pending-removal'
const savedEndpointKey = 'arice.push.endpoint'

export const pushHeaders = () => ({
  Accept: 'application/json',
  'Content-Type': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
  'X-XSRF-TOKEN': decodeURIComponent(
    document.cookie
      .split('; ')
      .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
      ?.slice(11) ?? ''
  ),
})

export const rememberPushEndpoint = (endpoint: string) =>
  localStorage.setItem(savedEndpointKey, endpoint)

export const finishPushRemoval = async (registration?: ServiceWorkerRegistration) => {
  const endpoint = localStorage.getItem(pendingRemovalKey)
  if (!endpoint) return

  const subscription = await registration?.pushManager.getSubscription()
  if (subscription?.endpoint === endpoint) {
    await subscription.unsubscribe()
    if (await registration?.pushManager.getSubscription()) {
      throw new Error('We could not disable notifications on this device. Please try again.')
    }
  }

  const response = await fetch(route('push-subscriptions.destroy'), {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: pushHeaders(),
    body: JSON.stringify({ endpoint }),
  })
  if (!response.ok)
    throw new Error('We could not remove your notification settings. Please try again.')

  localStorage.removeItem(pendingRemovalKey)
  if (localStorage.getItem(savedEndpointKey) === endpoint) localStorage.removeItem(savedEndpointKey)
}

export const removePushSubscription = async (registration: ServiceWorkerRegistration) => {
  const subscription = await registration.pushManager.getSubscription()
  const endpoint = subscription?.endpoint ?? localStorage.getItem(savedEndpointKey)
  if (endpoint) localStorage.setItem(pendingRemovalKey, endpoint)
  await finishPushRemoval(registration)
}

export const preparePushLogout = async (): Promise<string | undefined> => {
  let endpoint: string | undefined
  try {
    endpoint = localStorage.getItem(savedEndpointKey) ?? undefined
    if ('serviceWorker' in navigator) {
      const registration = await navigator.serviceWorker.getRegistration('/push-service-worker.js')
      const subscription = await registration?.pushManager.getSubscription()
      endpoint = subscription?.endpoint ?? endpoint
      if (endpoint) localStorage.setItem(pendingRemovalKey, endpoint)
      await subscription?.unsubscribe()
    }
  } catch {
    // The logout request still removes the saved endpoint on the server.
  }
  return endpoint
}
