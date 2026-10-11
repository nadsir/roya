<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ProductCard from '../ProductCard.vue';

const props = defineProps({ id: String, title: String, eyebrow: String, products: { type: Array, default: () => [] }, status: String, error: String, rail: Boolean });
defineEmits(['retry']);

// Four cards fill a desktop row; anything beyond that becomes a slider so no
// selection is ever hidden when the admin picks more than a full row.
const VISIBLE_CARDS = 4;
const isCarousel = computed(() => props.products.length > VISIBLE_CARDS);
const scrollArea = ref(null);
const activeIndex = ref(0);
const atStart = ref(true);
const atEnd = ref(true);
const numeral = (value) => String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);
let observer;
let frame = 0;

function measure() {
    const element = scrollArea.value;
    if (!element) return;
    const distance = Math.abs(element.scrollLeft);
    const limit = Math.max(0, element.scrollWidth - element.clientWidth);
    atStart.value = distance <= 2;
    atEnd.value = distance >= limit - 2;
    const cards = element.querySelectorAll('.hp-product-card');
    const edge = element.getBoundingClientRect().right;
    let nearest = 0;
    let gap = Infinity;
    cards.forEach((card, index) => {
        const delta = Math.abs(card.getBoundingClientRect().right - edge);
        if (delta < gap) { gap = delta; nearest = index; }
    });
    activeIndex.value = nearest;
}
function onScroll() {
    if (frame) return;
    frame = requestAnimationFrame(() => { frame = 0; measure(); });
}
function goTo(index) {
    const element = scrollArea.value;
    const cards = element?.querySelectorAll('.hp-product-card');
    if (!cards?.length) return;
    const target = cards[Math.max(0, Math.min(index, cards.length - 1))];
    const delta = target.getBoundingClientRect().right - element.getBoundingClientRect().right;
    element.scrollBy({ left: delta, behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
}
function navigate(direction) { goTo(activeIndex.value + direction); }
function onKeydown(event) {
    // Leave activation and modified browser shortcuts to the native links.
    if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
    const destinations = { ArrowLeft: activeIndex.value + 1, ArrowRight: activeIndex.value - 1, Home: 0, End: props.products.length - 1 };
    if (!(event.key in destinations)) return;
    event.preventDefault();
    goTo(destinations[event.key]);
}
watch(scrollArea, (element) => {
    observer?.disconnect();
    if (!element) return;
    observer = new ResizeObserver(onScroll);
    observer.observe(element);
    measure();
}, { flush: 'post' });
watch(() => props.products.length, () => {
    activeIndex.value = 0;
    atStart.value = true;
    atEnd.value = true;
    onScroll();
}, { flush: 'post' });
onBeforeUnmount(() => {
    observer?.disconnect();
    if (frame) cancelAnimationFrame(frame);
});
</script>
<template>
    <section :id="id" class="hp-section" :aria-labelledby="`${id}-title`">
        <div class="hp-section-heading"><div><p class="hp-eyebrow" lang="en" dir="ltr">{{ eyebrow }}</p><h2 :id="`${id}-title`">{{ title }}</h2><span v-if="status === 'ready' && products.length" class="hp-section-count">{{ products.length.toLocaleString('fa-IR') }} انتخاب از گالری</span></div><a :href="rail ? '/store' : '/store?sort=newest'" class="hp-underlined-link">مشاهده مجموعه <span aria-hidden="true">←</span></a></div>
        <div v-if="status === 'idle' || status === 'loading'" class="hp-product-grid" role="status" aria-label="در حال دریافت محصولات"><div v-for="n in 4" :key="n" class="hp-product-skeleton"><div class="sf-skeleton" /><span class="sf-skeleton" /></div></div>
        <div v-else-if="status === 'error'" class="hp-dynamic-state" role="alert"><p>{{ error }}</p><button class="hp-underlined-link" @click="$emit('retry')">تلاش مجدد ←</button></div>
        <div v-else-if="!products.length" class="hp-dynamic-state"><p>انتخاب‌های تازه به‌زودی به این مجموعه اضافه می‌شوند.</p><a href="/store" class="hp-underlined-link">دیدن فروشگاه ←</a></div>
        <template v-else>
            <div :id="`${id}-rail`" ref="scrollArea" :class="isCarousel ? 'hp-product-rail' : 'hp-product-grid'" :tabindex="isCarousel ? 0 : undefined" :role="isCarousel ? 'group' : undefined" :aria-label="isCarousel ? title : undefined" @scroll.passive="onScroll" @keydown="onKeydown"><ProductCard v-for="product in products" :key="product.id" :product="product" /></div>
            <footer v-if="isCarousel" class="hp-product-controls">
                <span class="hp-product-hint" aria-hidden="true"><span class="desktop-hint">برای دیدن بقیه بکشید</span><span class="mobile-hint">ورق بزنید</span> ←</span>
                <div class="hp-product-arrows" role="group" :aria-label="`کنترل مرور ${title}`">
                    <button type="button" :aria-label="`محصول قبلی در ${title}`" :aria-controls="`${id}-rail`" :disabled="atStart" @click="navigate(-1)"><span aria-hidden="true">→</span></button>
                    <span class="hp-product-position" dir="ltr" aria-label="موقعیت مرور">{{ numeral(activeIndex + 1) }} / {{ numeral(products.length) }}</span>
                    <button type="button" :aria-label="`محصول بعدی در ${title}`" :aria-controls="`${id}-rail`" :disabled="atEnd" @click="navigate(1)"><span aria-hidden="true">←</span></button>
                </div>
            </footer>
        </template>
    </section>
</template>
