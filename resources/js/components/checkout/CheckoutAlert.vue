<script setup>
defineProps({
    message: { type: String, default: '' },
    errors: { type: Array, default: () => [] },
});
</script>

<template>
    <div v-if="message" class="co-alert" role="alert">
        <p class="co-alert-title">{{ message }}</p>
        <ul v-if="errors.length" class="co-alert-list">
            <li v-for="(entry, index) in errors" :key="entry.key || index">{{ entry.message || entry }}</li>
        </ul>
    </div>
</template>
