const defaultDestination = new URL('/dashboard', self.location.origin).href

const asObject = (value) =>
  value !== null && typeof value === 'object' && !Array.isArray(value) ? value : {}

const destinationFrom = (payload, data) => {
  const candidate = data.url ?? data.action_url ?? payload.url ?? payload.action_url

  try {
    const destination = new URL(
      typeof candidate === 'string' ? candidate : defaultDestination,
      self.location.origin
    )
    return destination.origin === self.location.origin &&
      !destination.username &&
      !destination.password
      ? destination.href
      : defaultDestination
  } catch {
    return defaultDestination
  }
}

self.addEventListener('push', (event) => {
  let payload = {}

  try {
    payload = event.data ? event.data.json() : {}
  } catch {
    payload = {}
  }

  payload = asObject(payload)
  const payloadData = payload.data
  const data = asObject(payloadData)
  const destination = destinationFrom(payload, data)
  const notificationData = {
    ...(Object.keys(data).length ? data : { payload: payloadData }),
    url: destination,
  }
  const options = {
    body: typeof payload.body === 'string' ? payload.body : '',
    data: notificationData,
    icon: '/images/arice-icon-192.png',
  }

  if (typeof data.event_key === 'string') options.tag = data.event_key

  if (typeof payload.icon === 'string' && payload.icon) {
    options.icon = payload.icon
  }

  if (typeof payload.badge === 'string' && payload.badge) {
    options.badge = payload.badge
  }

  event.waitUntil(
    self.registration.showNotification(
      typeof payload.title === 'string' && payload.title ? payload.title : 'aRICE',
      options
    )
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()

  event.waitUntil(
    (async () => {
      const destination = destinationFrom({}, asObject(event.notification.data))
      const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      const riceWindow = windows.find((client) => {
        try {
          return new URL(client.url).origin === self.location.origin
        } catch {
          return false
        }
      })

      if (riceWindow) {
        try {
          if (riceWindow.url !== destination) {
            const navigated = await riceWindow.navigate(destination)
            if (!navigated) return self.clients.openWindow(destination)
            return await navigated.focus()
          }
          return await riceWindow.focus()
        } catch {
          return self.clients.openWindow(destination)
        }
      }

      return self.clients.openWindow(destination)
    })()
  )
})
