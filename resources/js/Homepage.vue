<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import EditorialHero from './components/homepage/EditorialHero.vue';
import HomeStories from './components/homepage/HomeStories.vue';
import CategoryShowcase from './components/homepage/CategoryShowcase.vue';
import ArrivalsSlider from './components/homepage/ArrivalsSlider.vue';
import EditorialCollection from './components/homepage/EditorialCollection.vue';
import EditorialJournal from './components/homepage/EditorialJournal.vue';
import NextChapter from './components/homepage/NextChapter.vue';
import SocialCommunity from './components/homepage/SocialCommunity.vue';
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
        <HomeStories />
        <nav class="hp-service-links hp-content" aria-label="همراه شما در گالری"><a href="/store"><i class="fa-solid fa-layer-group" aria-hidden="true" /><span>کشف مجموعه‌ها<small>انتخاب از میان دسته‌های گالری</small></span></a><a href="/wishlist"><i class="fa-regular fa-heart" aria-hidden="true" /><span>انتخاب‌های شما<small>علاقه‌مندی‌ها را کنار هم ببینید</small></span></a><a href="/orders"><i class="fa-solid fa-box" aria-hidden="true" /><span>سفارش‌های من<small>مشاهده وضعیت سفارش‌ها</small></span></a></nav>
        <div class="hp-content">
            <ArrivalsSlider id="hp-arrivals" title="تازه‌های گالری" eyebrow="NEW &amp; CONSIDERED" :products="arrivals" :status="state.status" :error="state.error" @retry="loadProducts" />
        </div>
        <EditorialCollection :href="collectionHref" />
        <div class="hp-content">
            <CategoryShowcase :product-image="categoryProductImage" />
            <ArrivalsSlider id="hp-featured" title="انتخاب‌های ویژه" eyebrow="THE GALLERY SELECTION" :products="featured" :status="state.status" :error="state.error" more-href="/store" @retry="loadProducts" />
        </div>
        <div class="hp-content">
            <EditorialJournal />
        </div>
        <NextChapter />
        <SocialCommunity />
    </main>
    <SiteFooter />
</template>
