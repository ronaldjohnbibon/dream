<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  finishPushRemoval,
  pushHeaders,
  rememberPushEndpoint,
  removePushSubscription,
} from '@/lib/push-notifications'
import { BellRing } from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { route } from 'ziggy-js'

type PushStatus = 'ready' | 'enabled' | 'blocked' | 'unsupported' | 'needs_install' | 'error'
type IOSNavigator = Navigator & { standalone?: boolean }

const status = ref<PushStatus>('ready')
const registration = ref<ServiceWorkerRegistration | null>(null)
const saving = ref(false)
const error = ref<string | null>(null)
const open = ref(false)
const dismissed = ref(false)
let mounted = true

const isSupported = () =>
  window.isSecureContext &&
  'serviceWorker' in navigator &&
  'PushManager' in window &&
  'Notification' in window
const isIOS = () =>
  /iPad|iPhone|iPod/.test(navigator.userAgent) ||
  (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
const isStandalone = () =>
  window.matchMedia('(display-mode: standalone)').matches ||
  (navigator as IOSNavigator).standalone === true

const title = computed(() => {
  if (status.value === 'blocked') return 'Notifications are blocked'
  if (status.value === 'unsupported') return 'Notifications are not supported'
  if (status.value === 'needs_install') return 'Install aRICE to enable notifications'
  if (status.value === 'error') return 'Unable to prepare notifications'
  return 'Stay updated with aRICE'
})
const description = computed(() => {
  if (status.value === 'blocked') {
    return 'Notifications are blocked for aRICE. Open your browser settings, allow notifications for this site, then return to aRICE.'
  }
  if (status.value === 'unsupported') {
    return window.isSecureContext
      ? 'Your browser does not support push notifications. Try a supported browser to receive order, payment, and points updates.'
      : 'Push notifications require a secure HTTPS connection.'
  }
  if (status.value === 'needs_install') {
    return 'On iOS/iPadOS 16.4 or later, use Safari to add aRICE to your Home Screen. Open it from there to enable notifications.'
  }
  if (status.value === 'error') {
    return error.value ?? 'We could not prepare notifications on this device. Please try again.'
  }
  return 'Enable notifications to receive order, payment, and points updates on this device.'
})
const canEnable = computed(() => status.value === 'ready' || status.value === 'error')

const showPrompt = () => {
  if (dismissed.value) return
  open.value = true
}

const close = () => {
  dismissed.value = true
  open.value = false
}

const urlBase64ToUint8Array = (value: string) => {
  const padded = value.padEnd(value.length + ((4 - (value.length % 4)) % 4), '=')
  const raw = window.atob(padded.replace(/-/g, '+').replace(/_/g, '/'))
  return Uint8Array.from(raw, (character) => character.charCodeAt(0))
}

const registerServiceWorker = async () => {
  await navigator.serviceWorker.register('/push-service-worker.js', { scope: '/' })
  registration.value = await navigator.serviceWorker.ready
  return registration.value
}

const publicKey = async () => {
  const response = await fetch(route('push.vapid-public-key'), {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
    cache: 'no-store',
  })
  if (!response.ok)
    throw new Error('We could not retrieve the notification settings. Please try again.')
  const vapid = (await response.json()) as { publicKey: string | null }
  if (!vapid.publicKey)
    throw new Error('Notifications are not configured yet. Please try again later.')
  return urlBase64ToUint8Array(vapid.publicKey)
}

const matchingSubscription = async (worker: ServiceWorkerRegistration, key: Uint8Array) => {
  const subscription = await worker.pushManager.getSubscription()
  if (!subscription) return null
  const existingKey = subscription.options.applicationServerKey
  if (
    !existingKey ||
    !key.every((byte, index) => byte === new Uint8Array(existingKey)[index]) ||
    existingKey.byteLength !== key.length
  ) {
    await removePushSubscription(worker)
    return null
  }
  return subscription
}

const saveSubscription = async (subscription: PushSubscription) => {
  const serialized = subscription.toJSON()
  if (!serialized.keys?.p256dh || !serialized.keys?.auth)
    throw new Error('The browser did not provide a complete push subscription.')
  if (!mounted) return
  const response = await fetch(route('push-subscriptions.store'), {
    method: 'POST',
    credentials: 'same-origin',
    headers: pushHeaders(),
    body: JSON.stringify({ endpoint: subscription.endpoint, keys: serialized.keys }),
  })
  if (!response.ok)
    throw new Error('We could not save your notification settings. Please try again.')
  rememberPushEndpoint(subscription.endpoint)
}

const initialise = async () => {
  if (saving.value) return
  error.value = null
  if (isIOS() && !isStandalone()) {
    status.value = 'needs_install'
    showPrompt()
    return
  }
  if (!isSupported()) {
    status.value = 'unsupported'
    showPrompt()
    return
  }

  saving.value = true
  try {
    const worker = registration.value ?? (await registerServiceWorker())
    await finishPushRemoval(worker)
    if (Notification.permission === 'denied') {
      await removePushSubscription(worker)
      status.value = 'blocked'
      showPrompt()
      return
    }

    status.value = 'ready'
    if (Notification.permission === 'granted') {
      const subscription = await matchingSubscription(worker, await publicKey())
      if (subscription) {
        await saveSubscription(subscription)
        status.value = 'enabled'
        open.value = false
        return
      }
    }
    showPrompt()
  } catch (caught) {
    status.value = 'error'
    error.value =
      caught instanceof Error
        ? caught.message
        : 'We could not prepare notifications on this device. Please try again.'
    showPrompt()
  } finally {
    saving.value = false
  }
}

const enableNotifications = async () => {
  if (saving.value || !isSupported()) return
  saving.value = true
  error.value = null
  try {
    if (Notification.permission === 'default') {
      // Keep this before any other await so iOS receives the direct button gesture.
      const permission = await Notification.requestPermission()
      if (permission !== 'granted') {
        status.value = permission === 'denied' ? 'blocked' : 'ready'
        return
      }
    }
    if (Notification.permission !== 'granted') {
      status.value = 'blocked'
      return
    }

    const worker = registration.value ?? (await registerServiceWorker())
    await finishPushRemoval(worker)
    const key = await publicKey()
    const subscription =
      (await matchingSubscription(worker, key)) ??
      (await worker.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: key,
      }))
    await saveSubscription(subscription)
    status.value = 'enabled'
    open.value = false
  } catch (caught) {
    status.value = 'error'
    error.value =
      caught instanceof Error
        ? caught.message
        : 'We could not enable notifications. Please try again.'
  } finally {
    saving.value = false
  }
}

const refreshWhenVisible = () => {
  if (document.visibilityState === 'visible') void initialise()
}

onMounted(() => {
  document.addEventListener('visibilitychange', refreshWhenVisible)
  void initialise()
})
onUnmounted(() => {
  mounted = false
  document.removeEventListener('visibilitychange', refreshWhenVisible)
})
</script>

<template>
  <Dialog :open="open" @update:open="close">
    <DialogContent class="max-w-md">
      <DialogHeader class="gap-3 text-left">
        <div class="w-fit rounded-lg bg-primary/10 p-2 text-primary">
          <BellRing class="size-5" aria-hidden="true" />
        </div>
        <div>
          <DialogTitle>{{ title }}</DialogTitle>
          <DialogDescription class="mt-2 leading-6">{{ description }}</DialogDescription>
        </div>
      </DialogHeader>
      <p v-if="error && status !== 'error'" class="text-sm text-destructive">{{ error }}</p>
      <DialogFooter class="gap-2 sm:gap-2">
        <Button variant="outline" type="button" :disabled="saving" @click="close">Not now</Button>
        <Button
          v-if="canEnable"
          type="button"
          :loading="saving"
          loading-text="Enabling"
          @click="enableNotifications"
        >
          {{ status === 'error' ? 'Try again' : 'Enable notifications' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
