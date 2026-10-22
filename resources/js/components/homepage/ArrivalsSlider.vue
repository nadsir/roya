<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { addToCart } from '../../cart-state.js';
import { showCartAdded } from '../../cart-notification-state.js';
import { isLoggedIn } from '../../auth-state.js';
import { state as wishlistState, isInWishlist, addToWishlist, removeFromWishlist } from '../../wishlist-state.js';
import {
    cartItemFromProduct,
    discountPercent,
    formatPrice,
    hasProductVariants,
    imageUrl,
    productHref,
    productImages
} from '../../product-presentation.js';
import { PLACEHOLDER_SRC, nextImageSrc } from '../../image-fallback.js';

// «تازه‌های گالری» — fanned coverflow slider.
//
// The data contract matches the homepage exactly: `products` is the
// `gallery_new` selection from homepage-data.js (`GET /api/products?gallery_new=1`).
// Every card is rendered from real API data (image, name, description, price,
// discount, stock, wishlist) so the section keeps the storefront behaviour.
//
// Geometry mirrors the reference design: every slide shares one grid cell and
// is offset from the active card by `--hp-arr-shift` (signed, in percent of the
// card width) / `--hp-arr-scale` / `--hp-arr-opacity`. The fan converges
// outwards, so the outer cards shrink, overlap and fade like the reference.
//
// RTL: higher indexes sit to the LEFT, so ArrowLeft / drag-right advances.
const props = defineProps({
    id: { type: String, default: 'hp-arrivals' },
    title: { type: String, default: 'تازه‌های گالری' },
    eyebrow: { type: String, default: 'NEW & CONSIDERED' },
    products: { type: Array, default: () => [] },
    status: { type: String, default: 'idle' },
    error: { type: String, default: '' },
    moreHref: { type: String, default: '/store?sort=newest' },
    moreLabel: { type: String, default: 'مشاهده مجموعه' }
});
defineEmits(['retry']);

const DRAG_SLOP = 8;
const DRAG_THRESHOLD = 56;
const CLICK_SUPPRESS = 10;

// Fan profile per distance from the active card. `shift` is the cumulative
// offset in percent of the card width, so the row converges towards the
// edges exactly like the reference (nine cards span ~5.6 card widths).
const FAN = [
    { shift: 0, scale: 1, opacity: 1 },
    { shift: 80, scale: .92, opacity: .92 },
    { shift: 142, scale: .84, opacity: .78 },
    { shift: 190, scale: .76, opacity: .62 },
    { shift: 228, scale: .68, opacity: .46 }
];
const FAN_STEPS = FAN.length - 1;

const viewport = ref(null);
const active = ref(0);
const announcement = ref('');
const dragging = ref(false);
const dragDx = ref(0);
const suppressClick = ref(false);
let dragStartX = 0;
let tracking = false;
let wheelTimer = 0;
let wheelDelta = 0;

const digits = (value) => String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);
const busy = computed(() => props.status === 'idle' || props.status === 'loading');
const failed = computed(() => props.status === 'error');
const ready = computed(() => props.status === 'ready' && props.products.length > 0);
const total = computed(() => props.products.length);
const scrollable = computed(() => total.value > 1);
const atStart = computed(() => active.value <= 0);
const atEnd = computed(() => active.value >= total.value - 1);

// Per-product UI state, keyed by product id so every card keeps its own
// image fallback chain, wishlist flag and feedback message.
const failedPaths = reactive({});
const pendingIds = reactive(new Set());
const feedbackById = reactive({});
const feedbackTimers = new Map();

function imagesFor(product) {
    const failed = failedPaths[product.id] || [];
    return productImages(product).filter((image) => !failed.includes(image.path));
}
function discountFor(product) { return discountPercent(product); }
function descFor(product) { return product.short_description || product.description || ''; }
function wishlistSaved(product) { return isLoggedIn.value && isInWishlist(product.id); }
function wishlistLabelFor(product) {
    if (!isLoggedIn.value) return `ورود برای ذخیره ${product.name}`;
    return wishlistSaved(product) ? `حذف ${product.name} از علاقه‌مندی‌ها` : `ذخیره ${product.name} در علاقه‌مندی‌ها`;
}
function addLabelFor(product) {
    if (!product.in_stock) return `${product.name} ناموجود است`;
    return hasProductVariants(product) ? `انتخاب تنوع ${product.name}` : `افزودن ${product.name} به سبد خرید`;
}

// Shared chain: real image -> temporary external fallback -> neutral monogram.
function onImageError(event, product) {
    const target = event.target;
    const next = nextImageSrc(target.currentSrc || target.src, product.id);
    if (next && next !== PLACEHOLDER_SRC) { target.src = next; return; }
    const path = target.dataset.imagePath;
    if (!path) return;
    const failed = failedPaths[product.id] ? [...failedPaths[product.id]] : [];
    if (!failed.includes(path)) failedPaths[product.id] = [...failed, path];
}

function notify(product, message, error = false) {
    feedbackById[product.id] = { message, error };
    clearTimeout(feedbackTimers.get(product.id));
    feedbackTimers.set(product.id, setTimeout(() => {
        delete feedbackById[product.id];
        feedbackTimers.delete(product.id);
    }, 4500));
}
function quickAdd(product) {
    if (!product.in_stock) return;
    if (hasProductVariants(product)) { location.href = productHref(product); return; }
    const item = cartItemFromProduct(product);
    if (!item) return;
    addToCart(item);
    showCartAdded(item);
    notify(product, 'به سبد خرید اضافه شد.');
}
async function toggleWishlist(product) {
    if (!isLoggedIn.value) { location.href = '/login?redirect=%2F'; return; }
    if (pendingIds.has(product.id) || wishlistState.loading) return;
    pendingIds.add(product.id);
    const wasSaved = wishlistSaved(product);
    try {
        const result = wasSaved ? await removeFromWishlist(product.id) : await addToWishlist(product.id);
        if (!result) { notify(product, 'ذخیره علاقه‌مندی انجام نشد؛ دوباره تلاش کنید.', true); return; }
        notify(product, wasSaved ? 'از علاقه‌مندی‌ها حذف شد.' : 'در علاقه‌مندی‌ها ذخیره شد.');
    } catch {
        notify(product, 'ذخیره علاقه‌مندی انجام نشد؛ دوباره تلاش کنید.', true);
    } finally {
        pendingIds.delete(product.id);
    }
}

// ---------- slider mechanics ----------
function goTo(index, spoken = true) {
    if (!total.value) return;
    const next = Math.max(0, Math.min(index, total.value - 1));
    if (next === active.value && !spoken) return;
    active.value = next;
    if (spoken) announcement.value = `${digits(next + 1)} از ${digits(total.value)}`;
}
function navigate(direction) { goTo(active.value + direction); }
function onKeydown(event) {
    if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
    // RTL: ArrowLeft moves forward (toward higher indexes, visually left).
    const destinations = { ArrowLeft: active.value + 1, ArrowRight: active.value - 1, Home: 0, End: total.value - 1 };
    if (!(event.key in destinations)) return;
    event.preventDefault();
    goTo(destinations[event.key]);
}
// A click on a non-active card brings it to the front of the fan. The two
// round action buttons keep their own behaviour; the rest of the card (image,
// title, description) focuses the card instead of following the product link.
function onSlideClick(event, index) {
    if (index === active.value) return;
    if (event.target.closest('button')) return;
    event.preventDefault();
    goTo(index);
}
// Pointer drag (mouse, touch and trackpad click-drag). Capture is deferred
// until past the slop so plain taps keep their native click target. The whole
// card is covered by the product image link, so the drag must not be blocked
// by interactive elements — only the floating arrows own their pointer.
function onPointerDown(event) {
    if (!ready.value || !scrollable.value || !event.isPrimary) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    if (event.target.closest('.hp-arr-nav')) return;
    tracking = true;
    dragging.value = false;
    dragStartX = event.clientX;
    dragDx.value = 0;
    suppressClick.value = false;
}
function onPointerMove(event) {
    if (!tracking || !event.isPrimary) return;
    const dx = event.clientX - dragStartX;
    if (!dragging.value) {
        if (Math.abs(dx) < DRAG_SLOP) return;
        dragging.value = true;
        try { viewport.value?.setPointerCapture?.(event.pointerId); } catch { /* noop */ }
    }
    // RTL: dragging right reveals the next (left-positioned) card.
    let live = dx;
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
// Trackpad horizontal swipe: accumulate the delta and step once the gesture
// is decisive. A vertical wheel is left alone so the page keeps scrolling.
function onWheel(event) {
    if (!ready.value || !scrollable.value) return;
    if (!event.deltaX || Math.abs(event.deltaX) <= Math.abs(event.deltaY)) return;
    event.preventDefault();
    wheelDelta += event.deltaX;
    clearTimeout(wheelTimer);
    wheelTimer = setTimeout(() => {
        if (Math.abs(wheelDelta) > 28) navigate(wheelDelta > 0 ? 1 : -1);
        wheelDelta = 0;
    }, 120);
}

// Geometry per slide, expressed as CSS custom properties so the browser
// animates the fan without any JavaScript layout work.
function slideStyle(index) {
    const d = index - active.value;
    const distance = Math.abs(d);
    const ad = Math.min(distance, FAN_STEPS);
    const step = FAN[ad];
    const far = distance > FAN_STEPS;
    return {
        '--hp-arr-shift': far ? 0 : step.shift * (d === 0 ? 0 : Math.sign(d)),
        '--hp-arr-scale': far ? .6 : step.scale,
        '--hp-arr-opacity': far ? 0 : step.opacity,
        '--hp-arr-z': 20 - ad
    };
}
function slideClass(index) {
    return {
        'is-active': index === active.value,
        'is-far': Math.abs(index - active.value) > FAN_STEPS
    };
}

// The reference opens on a centred card with neighbours on both sides, so the
// first paint starts in the middle of the selection.
function middleIndex() {
    const n = total.value;
    return n > 0 ? Math.floor(n / 2) : 0;
}

watch(() => props.products, () => {
    active.value = middleIndex();
    announcement.value = '';
    dragDx.value = 0;
    dragging.value = false;
    tracking = false;
}, { immediate: true });

watch(total, () => { if (active.value > total.value - 1) active.value = Math.max(0, total.value - 1); });

onBeforeUnmount(() => {
    clearTimeout(wheelTimer);
    feedbackTimers.forEach((timer) => clearTimeout(timer));
    feedbackTimers.clear();
});
</script>

<template>
    <section :id="id" class="hp-section hp-arrivals" aria-labelledby="`${id}-title`">
        <div class="hp-section-heading">
            <div>
               <!-- <p class="hp-eyebrow" lang="en" dir="ltr">{{ eyebrow }}</p> -->
                 <h2 :id="`${id}-title`">{{ title }}</h2>
               <!-- <span v-if="ready" class="hp-section-count">{{ products.length.toLocaleString('fa-IR') }} انتخاب از گالری</span> -->
            </div>
         <!--   <a :href="moreHref" class="hp-underlined-link">{{ moreLabel }} <span aria-hidden="true">←</span></a> -->
        </div>

        <div v-if="busy" class="hp-arr-viewport hp-arr-viewport--holding" role="status" aria-label="در حال دریافت محصولات">
            <ul class="hp-arr-stage">
                <li v-for="slot in 5" :key="slot" class="hp-arr-slide" :style="slideStyle(slot - 3)">
                    <div class="hp-arr-card hp-arr-card--skeleton">
                        <span class="sf-skeleton hp-arr-sk-frame" />
                        <span class="sf-skeleton hp-arr-sk-line" />
                        <span class="sf-skeleton hp-arr-sk-line hp-arr-sk-line--short" />
                    </div>
                </li>
            </ul>
        </div>
        <div v-else-if="failed" class="hp-dynamic-state" role="alert">
            <p>{{ error || 'محصولات دریافت نشدند.' }}</p>
            <button class="hp-underlined-link" @click="$emit('retry')">تلاش مجدد <span aria-hidden="true">←</span></button>
        </div>
        <div v-else-if="!products.length" class="hp-dynamic-state">
            <p>انتخاب‌های تازه به‌زودی به این مجموعه اضافه می‌شوند.</p>
            <a :href="moreHref" class="hp-underlined-link">دیدن فروشگاه <span aria-hidden="true">←</span></a>
        </div>
        <template v-else>
            <div
                ref="viewport"
                class="hp-arr-viewport"
                :data-dragging="dragging || undefined"
                :style="{ '--hp-arr-drag': `${dragDx}px` }"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="endDrag(false)"
                @pointercancel="endDrag(true)"
                @dragstart.prevent
                @click.capture="onCaptureClick"
                @wheel="onWheel"
            >
                <ul
                    :id="`${id}-track`"
                    class="hp-arr-stage"
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
                        class="hp-arr-slide"
                        :class="slideClass(index)"
                        :style="slideStyle(index)"
                        @click="onSlideClick($event, index)"
                    >
                        <article class="hp-arr-card">
                            <a :href="productHref(product)" class="hp-arr-media" :aria-label="product.name">
                                <img
                                    v-if="imagesFor(product)[0]"
                                    :key="imagesFor(product)[0].path"
                                    :data-image-path="imagesFor(product)[0].path"
                                    class="hp-arr-image"
                                    :src="imageUrl(imagesFor(product)[0].path)"
                                    :alt="imagesFor(product)[0].alt_text || product.name"
                                    width="600"
                                    height="900"
                                    :loading="index < 3 ? 'eager' : 'lazy'"
                                    decoding="async"
                                    draggable="false"
                                    @error="(event) => onImageError(event, product)"
                                />
                                <span v-else class="hp-arr-placeholder"><span lang="en" aria-hidden="true">G</span><small>تصویر محصول به‌زودی</small></span>
                            </a>

                            <div class="hp-arr-info">
                                <h3 class="hp-arr-title"><a :href="productHref(product)">{{ product.name }}</a></h3>
                                <p v-if="descFor(product)" class="hp-arr-desc">{{ descFor(product) }}</p>

                                <div class="hp-arr-buy">
                                    <p class="hp-arr-price">
                                        <span>{{ formatPrice(product.price) }} <small>تومان</small></span>
                                        <del v-if="discountFor(product)" :aria-label="`قیمت پیشین ${formatPrice(product.compare_at_price)} تومان`">{{ formatPrice(product.compare_at_price) }}</del>
                                        <span v-if="discountFor(product)" class="hp-arr-badge">{{ discountFor(product).toLocaleString('fa-IR') }}٪ تخفیف</span>
                                    </p>

                                    <div class="hp-arr-actions">
                                        <button
                                            type="button"
                                            class="hp-arr-add"
                                            :disabled="!product.in_stock"
                                            :aria-label="addLabelFor(product)"
                                            @click="quickAdd(product)"
                                        ><span>{{ !product.in_stock ? 'ناموجود' : hasProductVariants(product) ? 'انتخاب سایز و جزئیات' : 'افزودن به سبد' }}</span><span v-if="product.in_stock" aria-hidden="true">{{ hasProductVariants(product) ? '←' : '+' }}</span></button>
                                        <button
                                            type="button"
                                            class="hp-arr-wish"
                                            :class="{ 'is-saved': wishlistSaved(product) }"
                                            :aria-label="wishlistLabelFor(product)"
                                            :aria-pressed="wishlistSaved(product)"
                                            :aria-busy="pendingIds.has(product.id)"
                                            :disabled="pendingIds.has(product.id) || wishlistState.loading"
                                            @click="toggleWishlist(product)"
                                        ><i :class="pendingIds.has(product.id) ? 'fa-solid fa-spinner' : wishlistSaved(product) ? 'fa-solid fa-heart' : 'fa-regular fa-heart'" aria-hidden="true" /></button>
                                    </div>
                                </div>

                                <p v-if="feedbackById[product.id]?.message" class="hp-arr-feedback" :class="{ 'is-error': feedbackById[product.id]?.error }" :role="feedbackById[product.id]?.error ? 'alert' : 'status'">{{ feedbackById[product.id].message }}</p>
                            </div>
                        </article>
                    </li>
                </ul>

                <button
                    v-if="scrollable"
                    type="button"
                    class="hp-arr-nav hp-arr-nav--prev"
                    :aria-label="`محصول قبلی در ${title}`"
                    :aria-controls="`${id}-track`"
                    :disabled="atStart"
                    @click="navigate(-1)"
                ><i class="fa-solid fa-chevron-right" aria-hidden="true" /></button>
                <button
                    v-if="scrollable"
                    type="button"
                    class="hp-arr-nav hp-arr-nav--next"
                    :aria-label="`محصول بعدی در ${title}`"
                    :aria-controls="`${id}-track`"
                    :disabled="atEnd"
                    @click="navigate(1)"
                ><i class="fa-solid fa-chevron-left" aria-hidden="true" /></button>
            </div>

        </template>

        <p class="hp-sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</p>
    </section>
</template>
