<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({ open: Boolean, label: { type: String, required: true }, variant: { type: String, default: 'search' } });
const emit = defineEmits(['close']);
const dialog = ref(null);
let previousFocus = null;
let previousOverflow = null;

function restore() {
    if (previousOverflow !== null) {
        document.body.style.overflow = previousOverflow;
        previousOverflow = null;
    }
    if (previousFocus?.isConnected) previousFocus.focus();
    previousFocus = null;
}

watch(() => props.open, async (open) => {
    await nextTick();
    if (open && props.open && dialog.value && !dialog.value.open) {
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialog.value.showModal();
        const focusTarget = dialog.value.querySelector('[autofocus]')
            || dialog.value.querySelector('input, button, a');
        focusTarget?.focus();
    } else if (!props.open) {
        dialog.value?.close();
        restore();
    }
}, { immediate: true });

onBeforeUnmount(() => { dialog.value?.close(); restore(); });
</script>

<template>
    <dialog ref="dialog" :aria-label="label" class="sf-dialog" :class="`sf-dialog--${variant}`"
        @cancel.prevent="emit('close')" @click="($event.target === dialog) && emit('close')">
        <div class="sf-dialog-inner"><slot /></div>
    </dialog>
</template>
