<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import EditorialHero from './components/homepage/EditorialHero.vue';
import CategoryShowcase from './components/homepage/CategoryShowcase.vue';
import ProductSection from './components/homepage/ProductSection.vue';
import EditorialCollection from './components/homepage/EditorialCollection.vue';
import { createHomepageData } from './homepage-data.js';
import { categoryHref } from './catalog-state.js';
import '../css/homepage.css';

const { state, catalog, arrivals, featured, heroProduct, load, loadProducts, dispose, categoryProductImage } = createHomepageData();
const collectionHref = computed(() => catalog.categories.find((category) => category.slug === 'clothing') ? categoryHref('clothing') : '/store');
onMounted(load);
onBeforeUnmount(dispose);
</script>

<template>
    <SiteHeader />
    <main class="hp-home" dir="rtl">
        <EditorialHero :product="heroProduct" :status="state.status" />
        <nav class="hp-service-links hp-content" aria-label="همراه شما در گالری"><a href="/store"><i class="fa-solid fa-layer-group" aria-hidden="true" /><span>کشف مجموعه‌ها<small>انتخاب از میان دسته‌های گالری</small></span></a><a href="/wishlist"><i class="fa-regular fa-heart" aria-hidden="true" /><span>انتخاب‌های شما<small>علاقه‌مندی‌ها را کنار هم ببینید</small></span></a><a href="/orders"><i class="fa-solid fa-box" aria-hidden="true" /><span>سفارش‌های من<small>مشاهده وضعیت سفارش‌ها</small></span></a></nav>
        <div class="hp-content">
            <ProductSection id="hp-arrivals" title="تازه‌های گالری" eyebrow="NEW &amp; CONSIDERED" :products="arrivals" :status="state.status" :error="state.error" @retry="loadProducts" />
        </div>
        <EditorialCollection :href="collectionHref" />
        <div class="hp-content">
            <CategoryShowcase :product-image="categoryProductImage" />
            <ProductSection id="hp-featured" title="انتخاب‌های ویژه" eyebrow="THE GALLERY SELECTION" :products="featured" :status="state.status" :error="state.error" rail @retry="loadProducts" />
        </div>
        <section class="hp-story hp-content" aria-labelledby="hp-story-title"><p class="hp-eyebrow" lang="en" dir="ltr">A NOTE FROM GALLERY</p><h2 id="hp-story-title">کمد شما،<br /><span>روایت خودتان.</span></h2><div><p>در گالری، لباس و اکسسوری را کنار هم می‌بینیم؛ برای کشف ترکیب‌هایی که با سلیقه و زندگی روزمره شما هماهنگ‌اند.</p><p class="hp-story-note">این آغاز روایت گالری است؛ داستان کامل برند به‌زودی اینجا شکل می‌گیرد.</p></div><span class="hp-story-signature" lang="en" aria-hidden="true">Gallery.</span></section>
        <section class="hp-closing" aria-labelledby="hp-closing-title"><span class="hp-eyebrow" lang="en" dir="ltr">YOUR NEXT CHAPTER</span><h2 id="hp-closing-title">انتخاب بعدی،<br />به سلیقه شما.</h2><a href="/store" class="hp-primary-link">ورود به گالری <span aria-hidden="true">←</span></a><span class="hp-closing-number" aria-hidden="true">G</span></section>
    </main>
    <SiteFooter />
</template>
