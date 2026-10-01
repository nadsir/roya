<script setup>
import { computed } from 'vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number], default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const controlId = computed(() => `checkout-${props.field.key}`);
const errorId = computed(() => `${controlId.value}-error`);
const hintId = computed(() => `${controlId.value}-hint`);
const describedBy = computed(() => [props.hint ? hintId.value : '', props.error ? errorId.value : ''].filter(Boolean).join(' ') || null);
const isTextarea = computed(() => props.field.control === 'textarea');
const value = computed({
    get: () => props.modelValue,
    set: (next) => emit('update:modelValue', next),
});
</script>

<template>
    <div class="co-field" :class="{ 'co-field--wide': field.span === 2 }">
        <label class="co-label" :for="controlId">
            <span>{{ field.label }}</span>
            <span v-if="field.optional" class="co-label-hint">اختیاری</span>
        </label>
        <textarea v-if="isTextarea" :id="controlId" v-model="value" class="co-input co-input--textarea"
            :rows="field.rows || 3" :maxlength="field.maxlength" :autocomplete="field.autocomplete"
            :required="!field.optional" :aria-invalid="error ? 'true' : undefined" :aria-describedby="describedBy" />
        <input v-else :id="controlId" v-model="value" class="co-input" :type="field.type || 'text'"
            :maxlength="field.maxlength" :inputmode="field.inputmode" :dir="field.dir" :autocomplete="field.autocomplete"
            :required="!field.optional" :aria-invalid="error ? 'true' : undefined" :aria-describedby="describedBy" />
        <p v-if="hint" :id="hintId" class="co-hint">{{ hint }}</p>
        <p v-if="error" :id="errorId" class="co-error" role="alert">{{ error }}</p>
    </div>
</template>
