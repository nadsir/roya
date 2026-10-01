<script setup>
import { ref } from 'vue';
import { catalog, categoryHref, categoryImage, loadCatalog } from '../../catalog-state.js';
defineProps({ productImage: { type: Function, required: true } });
const failed = ref([]);
function markFailed(source) { if (!failed.value.includes(source)) failed.value.push(source); }
</script>

<template>
    <section id="hp-categories" class="hp-section hp-categories" aria-labelledby="hp-categories-title">
        <div class="hp-section-heading"><div><p class="hp-eyebrow" lang="en" dir="ltr">EXPLORE YOUR STYLE</p><h2 id="hp-categories-title">هر انتخاب، یک روایت</h2></div><a href="/store" class="hp-underlined-link">همه محصولات <span aria-hidden="true">←</span></a></div>
        <div v-if="catalog.status === 'loading' || catalog.status === 'idle'" class="hp-category-grid" role="status" aria-label="در حال دریافت دسته‌ها"><div v-for="n in 5" :key="n" class="hp-category-skeleton sf-skeleton" /></div>
        <div v-else-if="catalog.status === 'error'" class="hp-dynamic-state" role="alert"><p>{{ catalog.error }}</p><button class="hp-underlined-link" @click="loadCatalog({ retry: true })">تلاش مجدد ←</button></div>
        <div v-else-if="!catalog.categories.length" class="hp-dynamic-state"><p>دسته‌بندی‌های تازه به‌زودی اینجا خواهند بود.</p><a href="/store" class="hp-underlined-link">مشاهده فروشگاه ←</a></div>
        <div v-else class="hp-category-grid" tabindex="0" role="group" aria-label="مرور دسته‌های گالری">
            <a v-for="(category, index) in catalog.categories.slice(0, 6)" :key="category.slug" :href="categoryHref(category.slug)" class="hp-category-tile">
                <span class="hp-category-number" aria-hidden="true">{{ String(index + 1).padStart(2, '0').replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]) }}</span>
                <div class="hp-category-image">
                    <img v-if="categoryImage(category) && !failed.includes(categoryImage(category))" :src="categoryImage(category)" :alt="category.name" width="400" height="520" loading="lazy" decoding="async" @error="markFailed(categoryImage(category))" />
                    <img v-else-if="productImage(category) && !failed.includes(productImage(category))" :src="productImage(category)" :alt="`انتخابی از ${category.name}`" width="400" height="520" loading="lazy" decoding="async" @error="markFailed(productImage(category))" />
                    <span v-else class="hp-category-letter" aria-hidden="true">{{ category.name.charAt(0) }}</span>
                </div>
                <div class="hp-category-title"><h3>{{ category.name }}</h3><span aria-hidden="true">↗</span></div>
            </a>
        </div>
    </section>
</template>
