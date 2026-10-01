<script setup>
// The pager only mirrors the Laravel meta the list endpoint returned; it never
// invents a page count and it locks while a page is in flight.
const props = defineProps({
    meta: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    label: { type: String, default: 'سفارش‌ها' },
});

const emit = defineEmits(['change']);

function page() {
    return Number(props.meta?.current_page || 1);
}

function last() {
    return Number(props.meta?.last_page || 1);
}
</script>

<template>
    <nav v-if="last() > 1" class="ac-pager" :aria-label="`صفحه‌بندی ${label}`">
        <button type="button" :disabled="busy || page() <= 1" @click="emit('change', page() - 1)">قبلی</button>
        <span aria-live="polite">
            صفحه {{ page().toLocaleString('fa-IR') }} از {{ last().toLocaleString('fa-IR') }}
        </span>
        <button type="button" :disabled="busy || page() >= last()" @click="emit('change', page() + 1)">بعدی</button>
    </nav>
</template>
