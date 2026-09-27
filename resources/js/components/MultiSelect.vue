<script setup>
import {
    ref,
    computed,
    onMounted,
    onUnmounted,
} from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    optionLabel: { type: String, default: 'name' },
    optionValue: { type: String, default: 'id' },
    placeholder: { type: String, default: 'انتخاب کنید...' },
    searchable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const open = ref(false);
const inputRef = ref(null);

const filteredOptions = computed(() => {
    if (!props.searchable || !search.value) return props.options;
    const query = search.value.toLowerCase();
    return props.options.filter(opt =>
        opt[props.optionLabel]?.toLowerCase().includes(query)
    );
});

const selectedOptions = computed(() => {
    const selectedValues = new Set(props.modelValue.map(v => String(v)));
    return props.options.filter(opt =>
        selectedValues.has(String(opt[props.optionValue]))
    );
});

function toggle(option) {
    const value = option[props.optionValue];
    const valueStr = String(value);
    const index = props.modelValue.findIndex(v => String(v) === valueStr);
    const newValue = [...props.modelValue];
    if (index === -1) {
        newValue.push(value);
    } else {
        newValue.splice(index, 1);
    }
    emit('update:modelValue', newValue);
}

function removeSelected(value) {
    emit('update:modelValue', props.modelValue.filter(v => v !== value));
}

function handleClickOutside(event) {
    const isInside = inputRef.value && inputRef.value.contains(event.target);
    if (!isInside) {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="multi-select" ref="inputRef" :class="{ open }">
        <div class="multi-select-tags">
            <span
                v-for="opt in selectedOptions"
                :key="opt[optionValue]"
                class="multi-select-tag"
            >
                {{ opt[optionLabel] }}
                <button type="button" class="tag-remove" @click.stop="removeSelected(opt[optionValue])">×</button>
            </span>
            <input
                v-if="searchable"
                type="text"
                class="multi-select-search"
                v-model="search"
                :placeholder="selectedOptions.length ? '' : placeholder"
                @focus="open = true"
                @input="open = true"
            >
        </div>
        <div v-if="open && searchable" class="multi-select-dropdown">
            <div
                v-for="opt in filteredOptions"
                :key="opt[optionValue]"
                class="multi-select-option"
                :class="{ selected: props.modelValue.some(v => String(v) === String(opt[optionValue])) }"
                @click="toggle(opt)"
            >
                {{ opt[optionLabel] }}
            </div>
            <div v-if="filteredOptions.length === 0" class="multi-select-empty">
                گزینه‌ای یافت نشد
            </div>
        </div>
    </div>
</template>

<style scoped>
/* MultiSelect Component Styles */
.multi-select {
    position: relative;
    background: #fff;
    border: 1px solid #e0e0ea;
    border-radius: 8px;
    transition: all 0.15s;
    min-height: 44px;
}

.multi-select:hover {
    border-color: #c0c0d0;
}

.multi-select.open {
    border-color: #6563d9;
    box-shadow: 0 0 0 3px rgba(101, 99, 217, 0.15);
}

.multi-select-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px;
    min-height: 44px;
    align-items: center;
}

.multi-select-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #efefff;
    color: #6563d9;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.multi-select-tag .tag-remove {
    background: none;
    border: none;
    color: #6563d9;
    font-size: 14px;
    line-height: 1;
    cursor: pointer;
    padding: 0 2px;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.multi-select-tag .tag-remove:hover {
    background: rgba(101, 99, 217, 0.1);
}

.multi-select-search {
    flex: 1;
    min-width: 120px;
    border: none;
    outline: none;
    background: transparent;
    font-size: 13px;
    font-family: inherit;
    padding: 4px 0;
}

.multi-select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e0e0ea;
    border-top: none;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.1);
    max-height: 280px;
    overflow-y: auto;
    z-index: 100;
}

.multi-select-option {
    padding: 10px 14px;
    cursor: pointer;
    font-size: 13px;
    color: #36384b;
    transition: background 0.1s;
}

.multi-select-option:hover,
.multi-select-option.selected {
    background: #f5f5fa;
    color: #6563d9;
}

.multi-select-empty {
    padding: 16px;
    text-align: center;
    color: #999;
    font-size: 13px;
}

/* Dark mode */
.dark .multi-select {
    background: #1e1e1e;
    border-color: #2a2a2a;
    color: #e5e5e5;
}

.dark .multi-select:hover {
    border-color: #3a3a4a;
}

.dark .multi-select.open {
    border-color: #6563d9;
}

.dark .multi-select-tag {
    background: #2a2a3a;
    color: #a5a3ff;
}

.dark .multi-select-tag .tag-remove {
    color: #a5a3ff;
}

.dark .multi-select-tag .tag-remove:hover {
    background: rgba(101, 99, 217, 0.2);
}

.dark .multi-select-search {
    color: #e5e5e5;
}

.dark .multi-select-dropdown {
    background: #1e1e1e;
    border-color: #2a2a2a;
}

.dark .multi-select-option {
    color: #e5e5e5;
}

.dark .multi-select-option:hover,
.dark .multi-select-option.selected {
    background: #2a2a3a;
    color: #a5a3ff;
}

.dark .multi-select-empty {
    color: #888;
}
</style>