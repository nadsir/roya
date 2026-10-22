<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ProductCard from '../ProductCard.vue';
import { createAutoplayController } from '../../homepage-carousel.js';

// Independent coverflow slider for «انتخاب‌های ویژه».
// The data source is untouched: `products` is the admin-managed `featured`
// selection from homepage-data.js (`GET /api/products?featured=1`). Card
// content (price, discount, stock, colors, wishlist, cart, image fallback)
// is the shared ProductCard, so every storefront interaction stays identical.
// Geometry is a grid-stack: every slide shares one grid cell and is offset
// from the active card by `--d` (signed) / `--ad` (absolute, clamped 0..2).
// RTL: higher indexes sit to the LEFT, so ArrowLeft / drag-right advances.
const props = defineProps({
    id: { type: String, default: 'hp-featured' },
    title: { type: String, default: 'انتخاب‌های ویژه' },
    eyebrow: { type: String, default: 'THE GALLERY SELECTION' },
    products: { type: Array, default: () => [] },
    status: { type: String, default: 'idle' },
    error: { type: String, default: '' },
    moreHref: { type: String, default: '/store' },
    moreLabel: { type: String, default: 'مشاهده مجموعه' },
});
defineEmits(['retry']);

const DRAG_THRESHOLD = 64;
const CLICK_SUPPRESS = 10;
// Movement below this is a tap, not a drag. Pointer capture must wait for a
// genuine drag: capturing on pointerdown retargets the following click to the
// viewport, which silently kills clicks on the floating arrows (and product
// links) inside it. The top arrows live outside the viewport, which is why
// only they kept working.
const DRAG_SLOP = 8;

// Selectors for interactive elements that should not initiate drag tracking.
// Reused in onPointerDown and onSlideClick for consistency.
const INTERACTIVE_SELECTOR = 'a,button,input,select,textarea,[role="button"]';

const viewport = ref(null);
const active = ref(0);
const autoDir = ref(1);
const reducedMotion = ref(true);
const hidden = ref(false);
const announcement = ref('');
const dragging = ref(false);
const dragDx = ref(0);
const suppressClick = ref(false);
let dragStartX = 0;
let tracking = false;
let motionQuery;

const digits = (value) => String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);
const busy = computed(() => props.status === 'idle' || props.status === 'loading');
const ready = computed(() => props.status === 'ready' && props.products.length > 0);
const failed = computed(() => props.status === 'error');
const total = computed(() => props.products.length);
const scrollable = computed(() => total.value > 1);
const atStart = computed(() => active.value <= 0);
const atEnd = computed(() => active.value >= total.value - 1);
const progress = computed(() => (total.value > 1 ? active.value / (total.value - 1) : 1));
const positionLabel = computed(() => (total.value ? `${digits(active.value + 1)} از ${digits(total.value)}` : ''));
// Per-slide coverflow coordinates. Beyond two steps the slide is parked far
// (CSS hides it); DOM order never changes, so there are no loops or clones.
function coords(index) {
    const d = index - active.value;
    const ad = Math.min(2, Math.abs(d));
    return { d, ad, far: Math.abs(d) > 2 };
}

function goTo(index, spoken = true) {
    if (!total.value) return;
    active.value = Math.max(0, Math.min(index, total.value - 1));
    if (spoken) announcement.value = `${digits(active.value + 1)} از ${digits(total.value)}`;
}
// Autoplay ping-pongs between the ends: no wrap jump, no blank slides, no
// duplicate cards, and the disabled end states stay truthful.
function advance() {
    if (tracking || dragging.value || total.value < 2) return;
    if (active.value >= total.value - 1) autoDir.value = -1;
    else if (active.value <= 0) autoDir.value = 1;
    goTo(active.value + autoDir.value, false);
}
const autoplay = createAutoplayController({ onAdvance: advance });
const autoplayEnabled = computed(() => ready.value && total.value > 1 && !reducedMotion.value && !hidden.value);
watch(autoplayEnabled, (value) => autoplay.setEnabled(value), { immediate: true });
function pause() { autoplay.pauseForInteraction(); }
function navigate(direction) {
    pause();
    goTo(active.value + direction);
}
function onKeydown(event) {
    if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
    // RTL: ArrowLeft moves forward (toward higher indexes, visually left).
    const destinations = { ArrowLeft: active.value + 1, ArrowRight: active.value - 1, Home: 0, End: total.value - 1 };
    if (!(event.key in destinations)) return;
    event.preventDefault();
    pause();
    goTo(destinations[event.key]);
}
// A click on a non-active slide focuses it — unless it started on a link or
// button inside the card, which keeps its own behavior (detail, wishlist…).
function onSlideClick(event, index) {
    if (index === active.value) return;
    if (event.target.closest(INTERACTIVE_SELECTOR)) return;
    pause();
    goTo(index);
}
// Pointer drag with `touch-action: pan-y`: vertical page scroll stays native,
// horizontal drags preview the neighbour with end resistance and snap.
// Capture is deferred until past DRAG_SLOP so plain taps keep their native
// click target (floating arrows, product links, wishlist buttons).
function onPointerDown(event) {
    if (!ready.value || !scrollable.value || !event.isPrimary) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    // Ignore pointerdown on interactive elements so their clicks work normally.
    if (event.target.closest(INTERACTIVE_SELECTOR)) return;
    tracking = true;
    dragging.value = false;
    dragStartX = event.clientX;
    dragDx.value = 0;
    suppressClick.value = false;
    pause();
}
function onPointerMove(event) {
    if (!tracking || !event.isPrimary) return;
    const dx = event.clientX - dragStartX;
    if (!dragging.value) {
        if (Math.abs(dx) < DRAG_SLOP) return;
        dragging.value = true;
        try { viewport.value?.setPointerCapture?.(event.pointerId); } catch { /* noop */ }
    }
    let live = dx;
    // RTL: dragging right reveals the next (left-positioned) card.
    if ((atStart.value && live > 0) || (atEnd.value && live < 0)) live *= 0.35;
    dragDx.value = live;
}
function endDrag(cancelled) {
    if (!tracking) return;
    tracking = false;
    const wasDragging = dragging.value;
    dragging.value = false;
    const dx = dragDx.value;
    dragDx.value = 0;
    if (wasDragging && !cancelled && Math.abs(dx) > DRAG_THRESHOLD) goTo(active.value + (dx > 0 ? 1 : -1));
    suppressClick.value = wasDragging && Math.abs(dx) > CLICK_SUPPRESS;
    if (cancelled) suppressClick.value = false;
}
// Swallow the click that follows a real drag so card links don't misfire.
function onCaptureClick(event) {
    if (!suppressClick.value) return;
    suppressClick.value = false;
    event.preventDefault();
    event.stopPropagation();
}
function onMotionChange(event) { reducedMotion.value = event.matches; }
function onVisibility() { hidden.value = document.hidden; }

// Initial focal point: the middle product, so neighbours peek on both sides
// wherever the layout allows. floor(n/2) is safe for odd, even and short
// lists (n=1 -> 0, n=2 -> 1); empty stays 0. Applied before first paint of a
// fresh list, so there is no visible jump; CSS transitions don't run on
// initial render.
function middleIndex() {
    const n = total.value;
    return n > 0 ? Math.floor(n / 2) : 0;
}

watch(() => props.products, async () => {
    active.value = middleIndex();
    autoDir.value = 1;
    announcement.value = '';
    dragDx.value = 0;
    dragging.value = false;
    tracking = false;
    await nextTick();
}, { immediate: true });

onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    reducedMotion.value = motionQuery.matches;
    motionQuery.addEventListener('change', onMotionChange);
    document.addEventListener('visibilitychange', onVisibility);
    onVisibility();
});
onBeforeUnmount(() => {
    autoplay.dispose();
    motionQuery?.removeEventListener('change', onMotionChange);
    document.removeEventListener('visibilitychange', onVisibility);
});
</script>

<template>
    <section :id="id" class="hp-section hp-fsel" aria-labelledby="`${id}-title`">
        <div class="hp-fsel-head">
            <div class="hp-fsel-headings">
                <p class="hp-eyebrow" lang="en" dir="ltr">{{ eyebrow }}</p>
                <h2 :id="`${id}-title`">{{ title }}</h2>
                <p v-if="ready" class="hp-fsel-count">{{ products.length.toLocaleString('fa-IR') }} انتخاب از گالری</p>
            </div>
            <div class="hp-fsel-tools">
                <a :href="moreHref" class="hp-underlined-link hp-fsel-more">{{ moreLabel }} <span aria-hidden="true">←</span></a>
                <div v-if="ready && scrollable" class="hp-fsel-arrows" role="group" :aria-label="`کنترل مرور ${title}`">
                    <button type="button" :aria-label="`محصول قبلی در ${title}`" :aria-controls="`${id}-stage`" :disabled="atStart" @click="navigate(-1)"><i class="fa-solid fa-chevron-right" aria-hidden="true" /></button>
                    <button type="button" :aria-label="`محصول بعدی در ${title}`" :aria-controls="`${id}-stage`" :disabled="atEnd" @click="navigate(1)"><i class="fa-solid fa-chevron-left" aria-hidden="true" /></button>
                </div>
            </div>
        </div>

        <div v-if="busy" class="hp-fsel-loading" role="status" aria-label="در حال دریافت محصولات">
            <div v-for="slot in 3" :key="slot" class="hp-fsel-skeleton"><span class="sf-skeleton" /><span class="sf-skeleton hp-fsel-sk-line" /></div>
        </div>
        <div v-else-if="failed" class="hp-fsel-state hp-dynamic-state" role="alert">
            <p>{{ error || 'محصولات دریافت نشدند.' }}</p>
            <button class="hp-underlined-link" @click="$emit('retry')">تلاش مجدد <span aria-hidden="true">←</span></button>
        </div>
        <div v-else-if="!products.length" class="hp-fsel-state hp-dynamic-state">
            <p>انتخاب‌های ویژه به‌زودی به این مجموعه اضافه می‌شوند.</p>
            <a :href="moreHref" class="hp-underlined-link">دیدن فروشگاه <span aria-hidden="true">←</span></a>
        </div>
        <template v-else>
            <div
                ref="viewport"
                class="hp-fsel-viewport"
                :data-dragging="dragging || undefined"
                :style="{ '--ffs-drag': `${dragDx}px` }"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="endDrag(false)"
                @pointercancel="endDrag(true)"
                @click.capture="onCaptureClick"
                @mouseenter="pause"
                @focusin="pause"
            >
                <ul
                    :id="`${id}-stage`"
                    class="hp-fsel-stage"
                    role="group"
                    :aria-label="title"
                    aria-roledescription="اسلایدر"
                    :aria-describedby="`${id}-hint`"
                    tabindex="0"
                    @keydown="onKeydown"
                >
                    <li
                        v-for="(product, index) in products"
                        :key="product.id"
                        class="hp-fsel-slide"
                        :data-active="index === active || undefined"
                        :data-far="coords(index).far || undefined"
                        :style="{ '--d': coords(index).d, '--ad': coords(index).ad }"
                        @click="onSlideClick($event, index)"
                    >
                        <ProductCard :product="product" />
                    </li>
                </ul>
                <button v-if="scrollable" type="button" class="hp-fsel-float hp-fsel-float--prev" :aria-label="`محصول قبلی در ${title}`" :aria-controls="`${id}-stage`" :disabled="atStart" @click="navigate(-1)"><i class="fa-solid fa-chevron-right" aria-hidden="true" /></button>
                <button v-if="scrollable" type="button" class="hp-fsel-float hp-fsel-float--next" :aria-label="`محصول بعدی در ${title}`" :aria-controls="`${id}-stage`" :disabled="atEnd" @click="navigate(1)"><i class="fa-solid fa-chevron-left" aria-hidden="true" /></button>
            </div>
            <div class="hp-fsel-foot">
                <p :id="`${id}-hint`" class="hp-fsel-hint">{{ scrollable ? 'برای دیدن بقیه بکشید یا ورق بزنید' : 'انتخاب گالری' }}</p>
                <div v-if="scrollable" class="hp-fsel-progress" aria-hidden="true"><span :style="{ transform: `scaleX(${progress})` }" /></div>
                <div class="hp-fsel-status"><span class="hp-fsel-position">{{ positionLabel }}</span><a :href="moreHref" class="hp-underlined-link hp-fsel-more">{{ moreLabel }} <span aria-hidden="true">←</span></a></div>
            </div>
        </template>
        <p class="hp-sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</p>
    </section>
</template>
