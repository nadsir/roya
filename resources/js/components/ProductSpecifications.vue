<script setup>
import { computed } from 'vue';

const props = defineProps({
    specifications: { type: Array, default: () => [] },
    description: { type: String, default: '' },
});

const rows = computed(() => (props.specifications || []).filter((row) => row?.label && row?.value));
const hasSpecs = computed(() => rows.value.length > 0);
const hasDescription = computed(() => Boolean(String(props.description || '').trim()));
</script>

<template>
    <section
        v-if="hasSpecs || hasDescription"
        class="pd-section"
        aria-labelledby="pd-specs-title"
    >
        <header class="pd-section-head">
            <span class="pd-section-rule" aria-hidden="true" />
            <div>
                <p class="pd-eyebrow" lang="en" dir="ltr">PRODUCT DETAILS</p>
                <h2 id="pd-specs-title">مشخصات محصول</h2>
                <p v-if="hasSpecs" class="pd-section-note">
                    {{ rows.length.toLocaleString('fa-IR') }} ویژگی ثبت‌شده برای این انتخاب
                </p>
            </div>
        </header>

        <div
            v-if="hasSpecs"
            class="overflow-hidden rounded-xl border border-[color:var(--sf-border)] bg-[color:var(--sf-surface)]"
        >
            <dl>
                <div
                    v-for="(row, index) in rows"
                    :key="row.key || `${row.label}-${index}`"
                    class="grid grid-cols-[minmax(7.5rem,0.8fr)_minmax(0,1.2fr)] items-start gap-4 border-b border-[color:var(--sf-border)] px-4 py-3.5 text-sm transition-colors last:border-b-0 sm:grid-cols-[minmax(11rem,0.55fr)_minmax(0,1.45fr)] sm:px-6"
                    :class="index % 2 === 0
                        ? 'bg-[color:var(--sf-surface)]'
                        : 'bg-[color:var(--sf-surface-soft)]'"
                >
                    <dt class="font-medium text-[color:var(--sf-text-secondary)]">
                        {{ row.label }}
                    </dt>
                    <dd class="m-0 font-semibold leading-7 text-[color:var(--sf-text)]">
                        {{ row.value }}
                    </dd>
                </div>
            </dl>
        </div>

        <div
            v-if="hasDescription"
            class="mt-6 rounded-xl border border-[color:var(--sf-border)] bg-[color:var(--sf-surface)] p-5 sm:p-6"
        >
            <div class="flex items-center gap-2.5">
                <span class="grid h-8 w-8 place-items-center rounded-full bg-amber-400/15 text-amber-500" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 6h16M4 12h16M4 18h10" />
                    </svg>
                </span>
                <h3 class="text-base font-black text-[color:var(--sf-text)]">توضیحات</h3>
            </div>
            <p class="mt-4 whitespace-pre-line text-sm leading-8 text-[color:var(--sf-text-secondary)]">
                {{ description }}
            </p>
        </div>
    </section>
</template>
