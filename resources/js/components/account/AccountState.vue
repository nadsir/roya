<script setup>
// One loading / empty / failure block so no account page invents its own copy.
defineProps({
    icon: { type: String, default: 'fa-regular fa-box-open' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    retryLabel: { type: String, default: 'تلاش دوباره' },
    retryable: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['retry']);
</script>

<template>
    <div class="ac-state" :role="busy ? 'status' : undefined">
        <i :class="busy ? 'fa-solid fa-spinner fa-spin' : icon" aria-hidden="true"></i>
        <p v-if="title" class="sf-type-h3">{{ title }}</p>
        <p v-if="description">{{ description }}</p>
        <slot />
        <button v-if="retryable" type="button" class="sf-button" :disabled="busy" @click="emit('retry')">{{ retryLabel }}</button>
    </div>
</template>
