<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { catalog, categoryHref, categoryImage, loadCatalog } from '../../catalog-state.js';
import { homeCategoryVisual } from '../../home-visuals.js';

defineProps({ productImage: { type: Function, required: true } });
const failed = ref([]);
const rail = ref(null);
const activeIndex = ref(0);
const atStart = ref(true);
const atEnd = ref(true);
const interacted = ref(false);
const dragging = ref(false);
const categories = computed(() => catalog.categories);
const numeral = (value) => String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);
function markFailed(source) { if (!failed.value.includes(source)) failed.value.push(source); }
let observer;
let frame = 0;
let gesture;
let suppressClick = false;

function measure() {
    const element = rail.value;
    if (!element) return;
    const distance = Math.abs(element.scrollLeft);
    const limit = Math.max(0, element.scrollWidth - element.clientWidth);
    atStart.value = distance <= 2;
    atEnd.value = distance >= limit - 2;
    const cards = element.querySelectorAll('.category-rail-card');
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
    const element = rail.value;
    const cards = element?.querySelectorAll('.category-rail-card');
    if (!cards?.length) return;
    const target = cards[Math.max(0, Math.min(index, cards.length - 1))];
    const delta = target.getBoundingClientRect().right - element.getBoundingClientRect().right;
    interacted.value = true;
    element.scrollBy({ left: delta, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
}
function navigate(direction) { goTo(activeIndex.value + direction); }
function onKeydown(event) {
    // Leave activation and modified browser shortcuts to the native links.
    if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
    const destinations = { ArrowLeft: activeIndex.value + 1, ArrowRight: activeIndex.value - 1, Home: 0, End: categories.value.length - 1 };
    if (!(event.key in destinations)) return;
    event.preventDefault();
    goTo(destinations[event.key]);
}
function startDrag(event) {
    suppressClick = false;
    interacted.value = true;
    if (event.pointerType !== 'mouse' || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    gesture = { id: event.pointerId, x: event.clientX, y: event.clientY, scroll: rail.value.scrollLeft };
}
function moveDrag(event) {
    if (!gesture || gesture.id !== event.pointerId) return;
    const delta = event.clientX - gesture.x;
    if (!dragging.value) {
        if (Math.abs(delta) < 6) return;
        if (Math.abs(event.clientY - gesture.y) > Math.abs(delta)) { gesture = null; return; }
        dragging.value = true;
        // Disable snapping before the first scroll, without waiting for Vue's render.
        rail.value.classList.add('is-dragging');
        rail.value.setPointerCapture(event.pointerId);
    }
    event.preventDefault();
    rail.value.scrollBy({ left: gesture.scroll - delta - rail.value.scrollLeft, behavior: 'instant' });
}
function endDrag(event) {
    if (!gesture || (event && gesture.id !== event.pointerId)) return;
    suppressClick = dragging.value;
    const id = gesture.id;
    gesture = null;
    dragging.value = false;
    if (rail.value?.hasPointerCapture(id)) rail.value.releasePointerCapture(id);
    onScroll();
}
function onClick(event) {
    if (!suppressClick || event.detail === 0) return;
    event.preventDefault();
    event.stopPropagation();
    suppressClick = false;
}
watch(rail, (element) => {
    observer?.disconnect();
    if (!element) return;
    observer = new ResizeObserver(onScroll);
    observer.observe(element);
    measure();
}, { flush: 'post' });
watch(() => categories.value.length, onScroll, { flush: 'post' });
onBeforeUnmount(() => {
    observer?.disconnect();
    if (frame) cancelAnimationFrame(frame);
});
</script>

<template>
    <section id="hp-categories" class="hp-section hp-categories category-editorial" dir="rtl" aria-labelledby="hp-categories-title">
        <header class="category-rail-heading">
            <div><p class="hp-eyebrow" lang="en" dir="ltr">THE COLLECTION</p><h2 id="hp-categories-title">دسته‌بندی‌های ROYA</h2></div>
            <a href="/store" class="hp-underlined-link">همه محصولات <span aria-hidden="true">←</span></a>
        </header>
        <div v-if="catalog.status === 'loading' || catalog.status === 'idle'" class="category-rail" role="status" aria-label="در حال دریافت دسته‌ها"><div v-for="n in 5" :key="n" class="category-rail-card"><div class="category-rail-image sf-skeleton" /></div></div>
        <div v-else-if="catalog.status === 'error'" class="hp-dynamic-state" role="alert"><p>{{ catalog.error }}</p><button class="hp-underlined-link" @click="loadCatalog({ retry: true })">تلاش مجدد ←</button></div>
        <div v-else-if="!categories.length" class="hp-dynamic-state"><p>دسته‌بندی‌های تازه به‌زودی اینجا خواهند بود.</p><a href="/store" class="hp-underlined-link">مشاهده فروشگاه ←</a></div>
        <template v-else>
            <div id="roya-category-rail" ref="rail" class="category-rail" :class="{ 'is-dragging': dragging }" tabindex="0" role="group" aria-label="مرور افقی دسته‌بندی‌ها" @scroll.passive="onScroll" @keydown="onKeydown" @pointerdown="startDrag" @pointermove="moveDrag" @pointerup="endDrag" @pointercancel="endDrag" @lostpointercapture="endDrag" @pointerleave="!dragging && endDrag($event)" @dragstart.prevent @click.capture="onClick" @wheel.passive="interacted = true">
                <a v-for="(category, index) in categories" :key="category.slug" :href="categoryHref(category.slug)" class="category-rail-card" draggable="false">
                    <div class="category-rail-image">
                        <img v-if="categoryImage(category) && !failed.includes(categoryImage(category))" :src="categoryImage(category)" :alt="category.name" width="400" height="520" loading="lazy" decoding="async" draggable="false" @error="markFailed(categoryImage(category))" />
                        <img v-else-if="productImage(category) && !failed.includes(productImage(category))" :src="productImage(category)" :alt="`انتخابی از ${category.name}`" width="400" height="520" loading="lazy" decoding="async" draggable="false" @error="markFailed(productImage(category))" />
                        <img v-else-if="!failed.includes(homeCategoryVisual(category).src)" :src="homeCategoryVisual(category).src" :alt="homeCategoryVisual(category).alt" width="400" height="520" loading="lazy" decoding="async" draggable="false" @error="markFailed(homeCategoryVisual(category).src)" />
                        <span v-else class="category-rail-fallback" aria-hidden="true">{{ category.name.charAt(0) }}</span>
                    </div>
                    <div class="category-rail-caption">
                        <span class="category-rail-number" aria-hidden="true">{{ numeral(String(index + 1).padStart(2, '0')) }}</span>
                        <h3>{{ category.name }}</h3>
                        <span class="category-rail-action">مشاهده مجموعه <span aria-hidden="true">←</span></span>
                    </div>
                </a>
            </div>
            <footer class="category-rail-footer">
                <span class="category-rail-hint" :class="{ 'is-dismissed': interacted }" aria-hidden="true"><span class="desktop-hint">برای کشف مجموعه‌ها بکشید</span><span class="mobile-hint">ورق بزنید</span> ←</span>
                <div class="category-rail-controls" role="group" aria-label="کنترل مرور دسته‌بندی‌ها">
                    <button type="button" aria-label="دسته‌بندی قبلی" aria-controls="roya-category-rail" :disabled="atStart" @click="navigate(-1)"><span aria-hidden="true">→</span></button>
                    <span class="category-rail-position" dir="ltr" aria-label="موقعیت مرور">{{ numeral(String(activeIndex + 1).padStart(2, '0')) }} / {{ numeral(String(categories.length).padStart(2, '0')) }}</span>
                    <button type="button" aria-label="دسته‌بندی بعدی" aria-controls="roya-category-rail" :disabled="atEnd" @click="navigate(1)"><span aria-hidden="true">←</span></button>
                </div>
            </footer>
        </template>
    </section>
</template>

<style scoped>
.category-editorial { min-width: 0; }
.category-rail-heading { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin-bottom: 30px; }
.category-rail-heading .hp-eyebrow { margin-bottom: 8px; font-size: .625rem; letter-spacing: .2em; text-align: right; }
.category-rail-heading h2 { margin: 0; font-size: clamp(1.35rem, 2.2vw, 2rem); font-weight: 500; line-height: 1.6; }
.category-rail-heading .hp-underlined-link { flex-shrink: 0; font-size: .75rem; }
.category-rail { --rail-gap: clamp(18px, 2vw, 28px); display: flex; flex-flow: row nowrap; gap: var(--rail-gap); width: 100%; max-width: 100%; overflow-x: auto; overscroll-behavior-x: contain; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; padding-block: 4px 12px; cursor: grab; }
.category-rail::-webkit-scrollbar { display: none; }
.category-rail-card { display: block; flex: 0 0 calc((100% - 3.5 * var(--rail-gap)) / 4.5); min-width: 0; scroll-snap-align: start; scroll-margin-inline: 0; -webkit-user-drag: none; }
.category-rail-card:focus-visible { outline-offset: -2px !important; }
.category-rail-image { width: 100%; aspect-ratio: 4 / 5.2; overflow: hidden; background: var(--sf-surface-soft); display: grid; place-items: center; }
.category-rail-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 650ms var(--sf-ease, ease); }
.category-rail-fallback { font-size: clamp(3rem, 7vw, 6rem); font-weight: 200; color: var(--sf-accent); opacity: .5; }
.category-rail-caption { padding-top: 18px; }
.category-rail-number { display: block; font-size: .625rem; color: var(--sf-text-muted); margin-bottom: 6px; }
.category-rail-caption h3 { margin: 0 0 12px; font-size: clamp(.95rem, 1.35vw, 1.25rem); font-weight: 500; line-height: 1.7; overflow-wrap: anywhere; transition: transform 300ms ease; }
.category-rail-action { display: inline-flex; align-items: center; gap: 14px; font-size: .6875rem; color: var(--sf-text-muted); }
.category-rail-action > span { transition: transform 300ms ease, opacity 300ms ease; }
.category-rail-footer { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-top: 18px; padding-top: 12px; border-top: 1px solid var(--sf-border); }
.category-rail-hint { font-size: .65rem; color: var(--sf-text-muted); transition: opacity 250ms ease; }
.category-rail-hint.is-dismissed { opacity: 0; }
.mobile-hint { display: none; }
.category-rail-controls { display: flex; align-items: center; gap: 10px; }
.category-rail-controls button { display: grid; place-items: center; width: 44px; height: 44px; padding: 0; border: 0; background: transparent; color: var(--sf-text); font-size: 1.25rem; }
.category-rail-controls button:disabled { opacity: .25; cursor: default; }
.category-rail-position { min-width: 54px; text-align: center; font-size: .6875rem; color: var(--sf-text-muted); font-variant-numeric: tabular-nums; }
.category-rail.is-dragging { cursor: grabbing; scroll-snap-type: none; scroll-behavior: auto; user-select: none; }
.category-rail.is-dragging a { cursor: grabbing; }
@media (hover: hover) and (pointer: fine) {
    .category-rail-action > span { opacity: 0; transform: translateX(5px); }
    .category-rail-card:hover .category-rail-image img { transform: scale(1.03); }
    .category-rail-card:hover h3 { transform: translateY(-2px); }
    .category-rail-card:hover .category-rail-action > span, .category-rail-card:focus-visible .category-rail-action > span { opacity: 1; transform: none; }
}
@media (min-width: 761px) and (max-width: 1100px) {
    .category-rail-card { flex-basis: calc((100% - 2.1 * var(--rail-gap)) / 3.1); }
}
@media (max-width: 760px) {
    .category-rail { --rail-gap: 16px; }
    .category-rail-card { flex-basis: calc((100% - .2 * var(--rail-gap)) / 1.2); }
    .category-rail-heading { gap: 16px; margin-bottom: 22px; }
    .category-rail-heading h2 { font-size: 1.25rem; }
    .category-rail-heading .hp-underlined-link { font-size: .65rem; gap: 8px; }
    .category-rail-caption { padding-top: 16px; }
    .category-rail-caption h3 { font-size: 1.15rem; }
    .category-rail-footer { margin-top: 12px; }
    .desktop-hint { display: none; }
    .mobile-hint { display: inline; }
}
@media (prefers-reduced-motion: reduce) {
    .category-rail { scroll-behavior: auto; }
    .category-rail-image img, .category-rail-caption h3, .category-rail-action > span, .category-rail-hint { transition: none; }
    .category-rail-card:hover .category-rail-image img, .category-rail-card:hover h3 { transform: none; }
}
</style>
