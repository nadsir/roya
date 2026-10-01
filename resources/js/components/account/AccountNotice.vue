<script setup>
// A section level message. The backend message is shown verbatim; nothing here
// ever renders a raw status code, stack trace or query string.
defineProps({
    tone: { type: String, default: 'info' },
    icon: { type: String, default: 'fa-circle-info' },
    errors: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="ac-notice" :class="`ac-notice--${tone}`" :role="tone === 'error' ? 'alert' : 'status'">
        <i :class="['fa-solid', icon]" aria-hidden="true"></i>
        <div>
            <slot />
            <ul v-if="errors.length">
                <li v-for="(message, index) in errors" :key="index">{{ message }}</li>
            </ul>
        </div>
    </div>
</template>
