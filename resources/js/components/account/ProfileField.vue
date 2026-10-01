<script setup>
// One labelled control so every profile field keeps the same label wiring,
// required state, ltr direction and announced error.
defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="ac-field">
        <template v-if="readonly">
            <span class="ac-field-readonly">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <span>
                    <span class="sf-type-caption">{{ field.label }}</span>
                    <span :dir="field.dir || 'auto'">{{ modelValue || '—' }}</span>
                </span>
            </span>
        </template>

        <template v-else>
            <label :for="`ac-${field.key}`">{{ field.label }}</label>
            <input
                :id="`ac-${field.key}`"
                :type="field.type || 'text'"
                :value="modelValue"
                :dir="field.dir || 'auto'"
                :autocomplete="field.autocomplete"
                :maxlength="field.maxlength"
                :required="field.required"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `ac-${field.key}-error` : (field.hint ? `ac-${field.key}-hint` : undefined)"
                @input="emit('update:modelValue', $event.target.value)"
            />
        </template>

        <p v-if="field.hint" :id="`ac-${field.key}-hint`" class="ac-field-hint">{{ field.hint }}</p>
        <p v-if="error" :id="`ac-${field.key}-error`" class="ac-field-error" role="alert">{{ error }}</p>
    </div>
</template>
