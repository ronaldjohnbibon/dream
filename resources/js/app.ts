import '../css/app.css'

import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import type { DefineComponent } from 'vue'
import { createApp, h } from 'vue'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'

const appName = import.meta.env.VITE_APP_NAME || 'ARice'
const pageComponents = {
  ...import.meta.glob<DefineComponent>('./pages/**/*.vue'),
  ...import.meta.glob<DefineComponent>('./modules/**/*.vue'),
}

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => {
    const path = name.startsWith('modules/') ? `./${name}.vue` : `./pages/${name}.vue`

    return resolvePageComponent(path, pageComponents)
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue)
      .mount(el)
  },
  progress: {
    color: '#4B5563',
  },
})
