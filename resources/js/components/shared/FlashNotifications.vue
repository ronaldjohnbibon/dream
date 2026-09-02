<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage<SharedData>();
const dismissed = ref<string | null>(null);
const message = computed(() => page.props.flash.success);

watch(message, () => {
    dismissed.value = null;
});
</script>

<template>
    <div v-if="message && dismissed !== message" class="fixed right-4 top-4 z-50 flex max-w-sm items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-lg">
        <span>{{ message }}</span>
        <button type="button" class="font-semibold" aria-label="Dismiss notification" @click="dismissed = message">×</button>
    </div>
</template>
