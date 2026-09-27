<script setup>
import {
    ref,
    watch,
    onMounted,
    onBeforeUnmount,
    nextTick,
} from 'vue';
import StarRatingInput from './StarRatingInput.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    kind: { type: String, default: 'product' },
    section: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

const isProduct = props.kind === 'product';
const ratingId = 'composer-rating';

const firstField = ref(null);
const titleId = 'composer-title';
const bodyId = 'composer-body';

function close() {
    if (props.section.composerSubmitting) return;
    emit('update:modelValue', false);
}

function onKeydown(e) {
    if (e.key === 'Escape' && props.modelValue) {
        close();
    }
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            document.body.style.overflow = 'hidden';
            nextTick(() => {
                if (firstField.value) firstField.value.focus();
            });
        } else {
            document.body.style.overflow = '';
            if (props.section) props.section.closeComposer();
        }
    },
);

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="modelValue"
                class="fixed inset-0 z-[90] bg-black/50 backdrop-blur-sm md:flex md:items-center md:justify-center md:p-6"
                @click.self="close"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="md:opacity-0 md:translate-y-2 md:scale-[0.985] translate-y-full"
                    enter-to-class="md:opacity-100 md:translate-y-0 md:scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="md:opacity-100 translate-y-0"
                    leave-to-class="md:opacity-0 md:translate-y-2 translate-y-full"
                >
                    <div
                        v-if="modelValue"
                        role="dialog"
                        aria-modal="true"
                        :aria-label="isProduct ? 'ثبت تجربه' : 'افزودن دیدگاه'"
                        class="fixed inset-x-0 bottom-0 md:static md:inset-auto z-[95] max-h-[92dvh] overflow-y-auto rounded-t-2xl md:max-w-lg md:rounded-2xl bg-cream dark:bg-[#161616] shadow-[0_-8px_40px_rgba(0,0,0,0.18)] md:shadow-[0_24px_60px_rgba(0,0,0,0.28)]"
                    >
                        <div class="mx-auto flex max-w-2xl flex-col gap-6 px-5 py-6 sm:px-8 sm:py-8">
                            <div
                                v-if="section.composerSuccess"
                                class="flex flex-col items-center py-6 text-center"
                            >
                                <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-accent/15">
                                    <svg class="h-6 w-6 text-brand-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                </span>
                                <h3 class="mt-4 text-lg font-black text-ink dark:text-slate-50">
                                    {{
                                        isProduct
                                            ? 'تجربه شما ثبت شد'
                                            : 'دیدگاه شما ثبت شد'
                                    }}
                                </h3>
                                <p class="mt-2 max-w-xs text-sm leading-6 text-slate-500 dark:text-slate-400">
                                    پس از بررسی تیم ما منتشر خواهد شد.
                                </p>
                                <button
                                    type="button"
                                    class="mt-6 rounded-md bg-ink px-6 py-3 text-sm font-bold text-cream transition hover:bg-black dark:bg-slate-100 dark:text-ink"
                                    @click="close"
                                >
                                    بستن
                                </button>
                            </div>

                            <form
                                v-else
                                @submit.prevent="section.submitComposer()"
                            >
                                <div class="flex items-start justify-between gap-6">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">
                                            {{
                                                isProduct
                                                    ? 'Ship a Review'
                                                    : 'Join the Discussion'
                                            }}
                                        </p>
                                        <h2 class="mt-1.5 serif text-2xl leading-none text-ink dark:text-slate-50">
                                            {{
                                                isProduct
                                                    ? 'تجربه\u200cات را بنویس'
                                                    : 'در گفتگو شرکت کن'
                                            }}
                                        </h2>
                                    </div>
                                    <button
                                        type="button"
                                        :aria-label="'بستن'"
                                        class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:text-ink dark:border-slate-700 dark:text-slate-400 dark:hover:text-slate-100"
                                        @click="close"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                            <path d="M18 6L6 18M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div v-if="isProduct" class="mt-7 border-t border-slate-200 pt-6 dark:border-slate-700">
                                    <p class="text-sm font-bold text-ink dark:text-slate-100">
                                        این قطعه را چگونه ارزیابی می‌کنید؟
                                    </p>
                                    <div class="mt-3 flex items-center gap-3">
                                        <StarRatingInput
                                            v-model="section.composer.rating"
                                            :id="ratingId"
                                            size="lg"
                                        />
                                        <span
                                            v-if="section.composerFieldErrors.rating"
                                            class="text-xs font-bold text-red-500"
                                        >
                                            {{ section.composerFieldErrors.rating }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="mt-6 space-y-5"
                                    :class="isProduct ? '' : 'border-t border-slate-200 pt-6 dark:border-slate-700'"
                                >
                                    <div>
                                        <label
                                            :for="titleId"
                                            class="mb-2 block text-sm font-bold text-ink dark:text-slate-100"
                                        >
                                            {{ isProduct ? 'عنوان تجربه' : 'عنوان دیدگاه' }}
                                            <span class="text-slate-400 dark:text-slate-500">(دلخواه)</span>
                                        </label>
                                        <input
                                            :id="titleId"
                                            ref="firstField"
                                            v-model="section.composer.title"
                                            type="text"
                                            maxlength="120"
                                            placeholder="مثلاً: جایگزین مناسبی بود"
                                            class="w-full rounded-md border border-slate-300 bg-white px-4 py-3 text-sm text-ink placeholder:text-slate-400 focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/40 dark:border-slate-600 dark:bg-black/30 dark:text-slate-100 dark:placeholder:text-slate-500"
                                        />
                                        <p
                                            v-if="section.composerFieldErrors.title"
                                            class="mt-1.5 text-xs font-bold text-red-500"
                                        >
                                            {{ section.composerFieldErrors.title }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            :for="bodyId"
                                            class="mb-2 block text-sm font-bold text-ink dark:text-slate-100"
                                        >
                                            {{ isProduct ? 'متن تجربه' : 'متن دیدگاه' }}
                                        </label>
                                        <textarea
                                            :id="bodyId"
                                            v-model="section.composer.body"
                                            :rows="5"
                                            maxlength="1500"
                                            placeholder="نظر خود را بنویسید؛ پس از بررسی تیم ما منتشر می‌شود…"
                                            class="w-full resize-y rounded-md border border-slate-300 bg-white px-4 py-3 text-sm leading-7 text-ink placeholder:text-slate-400 focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/40 dark:border-slate-600 dark:bg-black/30 dark:text-slate-100 dark:placeholder:text-slate-500"
                                        />
                                        <p
                                            v-if="section.composerFieldErrors.body"
                                            class="mt-1.5 text-xs font-bold text-red-500"
                                        >
                                            {{ section.composerFieldErrors.body }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-7 flex items-center justify-between gap-4 border-t border-slate-200 pt-5 dark:border-slate-700">
                                    <span class="text-xs leading-5 text-slate-400 dark:text-slate-500">
                                        نظر شما پس از بررسی تیم ما <br class="sm:hidden" />
                                        در این صفحه نمایش داده خواهد شد.
                                    </span>
                                    <button
                                        type="submit"
                                        :disabled="section.composerSubmitting"
                                        class="inline-flex shrink-0 items-center gap-2 rounded-md bg-brand-accent px-6 py-3 text-sm font-black text-ink transition hover:bg-brand-hover disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <svg
                                            v-if="section.composerSubmitting"
                                            class="h-4 w-4 animate-spin"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                        </svg>
                                        {{
                                            section.composerSubmitting
                                                ? 'در حال ثبت…'
                                                : isProduct
                                                    ? 'ثبت تجربه'
                                                    : 'ارسال دیدگاه'
                                        }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>