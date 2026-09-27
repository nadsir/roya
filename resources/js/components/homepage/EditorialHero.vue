<script setup>
import { formatPrice, productHref } from '../../product-presentation.js';
import { ref } from 'vue';
import { editorialArt } from '../../editorial-art.js';
const failed = ref(false);
defineProps({ product: { type: Object, default: null }, status: { type: String, default: 'idle' } });
</script>

<template>
    <section class="hp-hero" aria-labelledby="hp-hero-title">
        <div class="hp-hero-copy">
            <p class="hp-eyebrow hp-enter" lang="en" dir="ltr">THE CONSIDERED EDIT</p>
            <h1 id="hp-hero-title" class="hp-enter">سبک،<br /><span>به روایتِ</span><br />شما.</h1>
            <div class="hp-hero-description hp-enter"><p>انتخاب‌هایی برای هر روز؛<br />جزئیاتی برای خودِ شما.</p><a href="/store?sort=newest" class="hp-primary-link">کشف تازه‌ها <span aria-hidden="true">←</span></a></div>
        </div>
        <div class="hp-hero-visual hp-enter">
            <picture v-if="!failed"><img :src="editorialArt.src" :srcset="editorialArt.srcset" :sizes="editorialArt.sizes" :width="editorialArt.width" :height="editorialArt.height" :alt="editorialArt.alt" fetchpriority="high" decoding="async" @error="failed = true" /></picture>
            <div v-else class="hp-hero-art" aria-label="تصویر Editorial هنوز در دسترس نیست"><span lang="en" aria-hidden="true">G.</span><small>روایت تصویری گالری</small></div>
            <span class="hp-hero-image-label" lang="en" dir="ltr">FORM / FEELING / YOU</span>
        </div>
        <span class="hp-hero-outline" aria-hidden="true" />
        <div class="hp-hero-product-slot"><a v-if="product" :href="productHref(product)" class="hp-hero-product hp-enter"><span class="hp-eyebrow">از انتخاب‌های گالری</span><strong>{{ product.name }}</strong><span class="hp-hero-product-bottom"><span>{{ formatPrice(product.price) }} <small>تومان</small></span><span class="hp-round-arrow" aria-hidden="true">↗</span></span></a><div v-else class="hp-hero-product"><span class="hp-eyebrow">از انتخاب‌های گالری</span><span v-if="status === 'loading' || status === 'idle'" class="sf-skeleton" aria-label="در حال دریافت پیشنهاد گالری" role="status" /><a v-else href="/store" class="hp-underlined-link">کشف مجموعه گالری ←</a></div></div>
        <div class="hp-hero-footer"><span lang="en" dir="ltr">A wardrobe with intention.</span><a href="#hp-categories">انتخاب از میان دسته‌ها <span aria-hidden="true">↓</span></a></div>
    </section>
</template>
