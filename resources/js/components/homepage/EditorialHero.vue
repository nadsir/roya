<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatPrice, productHref, productImages, imageUrl } from '../../product-presentation.js';
import { heroSlideImage } from '../../hero-slides.js';
import { nextImageSrc } from '../../image-fallback.js';

const props = defineProps({ product: { type: Object, default: null }, status: { type: String, default: 'idle' } });
// Gallery campaign copy; each slide owns its own image and the third follows the product selection.
const slides = computed(() => {
    const third = heroSlideImage(2);
    const productImage = third.allowProductImage ? imageUrl(productImages(props.product)[0]?.path) : '';
    return [
        { title: 'سبک، به روایت شما.', subtitle: 'انتخاب‌هایی برای هر روز؛ جزئیاتی برای خودِ شما.', art: heroSlideImage(0), image: heroSlideImage(0).src, cta: 'کشف تازه‌ها', target: '/store?sort=newest' },
        { title: 'سادگی، با جزئیات بیشتر.', subtitle: 'لباس و اکسسوری را کنار هم ببینید و ترکیب خودتان را پیدا کنید.', art: heroSlideImage(1), image: heroSlideImage(1).src, cta: 'دیدن مجموعه‌ها', target: '#hp-categories' },
        { title: props.product?.name || 'انتخاب بعدی شما.', subtitle: 'از میان انتخاب‌های گالری، چیزی نزدیک به سلیقه خودتان پیدا کنید.', art: third, image: productImage || third.src, alt: productImage ? props.product.name : third.alt, cta: props.product ? 'کشف این انتخاب' : 'ورود به گالری', target: props.product ? productHref(props.product) : '/store' },
    ];
});
const INTERVAL = 6500;
const RESUME_DELAY = 12000;
const active = ref(0);
const paused = ref(false);
const engaged = ref(false);
const hovered = ref(false);
const focused = ref(false);
const hidden = ref(false);
const reducedMotion = ref(true);
const failed = ref({});
const announcement = ref('');
const playing = computed(() => !paused.value && !engaged.value && !hovered.value && !focused.value && !hidden.value && !reducedMotion.value);
let timer = null;
let resumeTimer = null;
let motionQuery;
let touchStart;
let disposed = false;

function hold() {
    clearTimeout(resumeTimer);
    resumeTimer = setTimeout(() => { resumeTimer = null; engaged.value = false; }, RESUME_DELAY);
}
function select(index, manual = true) {
    active.value = (index + slides.value.length) % slides.value.length;
    if (manual) {
        engaged.value = true;
        hold();
        announcement.value = 'اسلاید ' + (active.value + 1).toLocaleString('fa-IR') + ' از ' + slides.value.length.toLocaleString('fa-IR') + ': ' + slides.value[active.value].title;
    }
}
function previous() { select(active.value - 1); }
function next() { select(active.value + 1); }
function schedule() {
    clearTimeout(timer);
    timer = null;
    if (disposed || !playing.value) return;
    timer = setTimeout(() => { timer = null; select(active.value + 1, false); schedule(); }, INTERVAL);
}
function togglePlayback() {
    if (!reducedMotion.value) paused.value = !paused.value;
}
function onFocusOut(event) { if (!event.currentTarget.contains(event.relatedTarget)) focused.value = false; }
function onKey(event) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    if (event.key === 'Home') select(0);
    else if (event.key === 'End') select(slides.value.length - 1);
    else select(active.value + (event.key === 'ArrowLeft' ? 1 : -1));
}
function startTouch(event) {
    if (event.touches.length !== 1) { touchStart = null; return; }
    const touch = event.touches[0];
    touchStart = { x: touch.clientX, y: touch.clientY };
}
function cancelTouch() { touchStart = null; }
function endTouch(event) {
    if (!touchStart || !event.changedTouches.length) return;
    const touch = event.changedTouches[0];
    const dx = touch.clientX - touchStart.x;
    const dy = touch.clientY - touchStart.y;
    touchStart = null;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.3) select(active.value + (dx > 0 ? 1 : -1));
}
// `currentSrc` is absolute, a configured `src` is root-relative, so compare the paths.
function samePath(value, path) {
    return String(value || '').replace(/^https?:\/\/[^/]+/i, '') === String(path || '').replace(/^https?:\/\/[^/]+/i, '');
}
function imageError(event, index) {
    const image = event.target;
    const slide = slides.value[index];
    const current = image.currentSrc || image.src || '';
    // A product photo that is not on disk must not cost the slide its own campaign image.
    if (slide.art.src && !samePath(current, slide.art.src)) {
        image.src = slide.art.src;
        return;
    }
    const next = nextImageSrc(current, 'gallery-slide-' + (index + 1));
    if (next) image.src = next;
    else failed.value = { ...failed.value, [index]: true };
}
function visibility() { hidden.value = document.hidden; }
function motion(event) { reducedMotion.value = event.matches; }
watch(playing, schedule);
watch(() => props.product?.id, () => { failed.value = { ...failed.value, 2: false }; });
onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    reducedMotion.value = motionQuery.matches;
    motionQuery.addEventListener('change', motion);
    document.addEventListener('visibilitychange', visibility);
    visibility();
    schedule();
});
onBeforeUnmount(() => {
    disposed = true;
    clearTimeout(timer);
    clearTimeout(resumeTimer);
    motionQuery?.removeEventListener('change', motion);
    document.removeEventListener('visibilitychange', visibility);
});
</script>

<template>
    <section class="hp-hero" role="region" aria-roledescription="اسلایدر" aria-label="مجموعه‌های گالری"
        @focusin="focused = true" @focusout="onFocusOut" @keydown="onKey">
        <div class="hp-slides" @touchstart.passive="startTouch" @touchend.passive="endTouch" @touchcancel="cancelTouch">
            <article v-for="(slide, index) in slides" :key="index" class="hp-slide" :class="['hp-slide--' + (index + 1), { 'is-active': active === index }]"
                role="group" aria-roledescription="اسلاید" :aria-label="(index + 1) + ' از ' + slides.length" :aria-hidden="active !== index" :inert="active !== index">
                <div class="hp-slide-visual">
                    <img v-if="!failed[index]" :key="slide.image" :src="slide.image" :srcset="slide.art.srcset" :sizes="slide.art.srcset ? slide.art.sizes : undefined"
                        :alt="slide.alt || slide.art.alt" :width="slide.art.width" :height="slide.art.height"
                        :loading="index === 0 ? 'eager' : 'lazy'" :fetchpriority="index === 0 ? 'high' : 'auto'" decoding="async" @error="imageError($event, index)" />
                    <div v-else class="hp-hero-art"><span lang="en" aria-hidden="true">G.</span><small>روایت تصویری گالری</small></div>
                </div>
                <div class="hp-slide-copy">
                    <p class="hp-eyebrow" lang="en" dir="ltr">GALLERY / {{ String(index + 1).padStart(2, '0') }}</p>
                    <h1 v-if="index === 0">{{ slide.title }}</h1><h2 v-else>{{ slide.title }}</h2>
                    <p class="hp-slide-description">{{ slide.subtitle }}</p>
                    <p v-if="index === 2 && product" class="hp-slide-price">{{ formatPrice(product.price) }} <small>تومان</small></p>
                    <a :href="slide.target" class="hp-primary-link" :tabindex="active === index ? 0 : -1">{{ slide.cta }} <span aria-hidden="true">←</span></a>
                </div>
                <span class="hp-slide-word" lang="en" aria-hidden="true">{{ ['Gallery.', 'Everyday.', 'Your edit.'][index] }}</span>
            </article>
        </div>
        <div class="hp-slider-controls" @mouseenter="hovered = true" @mouseleave="hovered = false">
            <div class="hp-slider-arrows"><button type="button" aria-label="اسلاید قبلی" @click="previous">→</button><button type="button" aria-label="اسلاید بعدی" @click="next">←</button></div>
            <div class="hp-slider-indicators" role="group" aria-label="انتخاب اسلاید"><button v-for="(slide, index) in slides" :key="index" type="button" :aria-label="'نمایش اسلاید ' + (index + 1) + ': ' + slide.title" :aria-current="active === index ? 'true' : undefined" @click="select(index)"><span /></button></div>
            <button class="hp-slider-pause" type="button" :disabled="reducedMotion" :aria-label="reducedMotion ? 'پخش خودکار به دلیل تنظیم کاهش حرکت خاموش است' : paused ? 'شروع پخش خودکار' : 'توقف پخش خودکار'" @click="togglePlayback"><span aria-hidden="true">{{ paused || reducedMotion ? '▷' : 'Ⅱ' }}</span><span class="hp-slider-count">{{ (active + 1).toLocaleString('fa-IR') }} / {{ slides.length.toLocaleString('fa-IR') }}</span></button>
        </div>
        <p class="hp-sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</p>
    </section>
</template>
