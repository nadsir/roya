<script setup>
import ProductCard from '../ProductCard.vue';
defineProps({ id: String, title: String, eyebrow: String, products: Array, status: String, error: String, rail: Boolean });
defineEmits(['retry']);
</script>
<template>
    <section :id="id" class="hp-section" :aria-labelledby="`${id}-title`">
        <div class="hp-section-heading"><div><p class="hp-eyebrow" lang="en" dir="ltr">{{ eyebrow }}</p><h2 :id="`${id}-title`">{{ title }}</h2><span v-if="status === 'ready' && products.length" class="hp-section-count">{{ products.length.toLocaleString('fa-IR') }} انتخاب از گالری</span></div><a :href="rail ? '/store' : '/store?sort=newest'" class="hp-underlined-link">مشاهده مجموعه <span aria-hidden="true">←</span></a></div>
        <div v-if="status === 'idle' || status === 'loading'" class="hp-product-grid" role="status" aria-label="در حال دریافت محصولات"><div v-for="n in 4" :key="n" class="hp-product-skeleton"><div class="sf-skeleton" /><span class="sf-skeleton" /></div></div>
        <div v-else-if="status === 'error'" class="hp-dynamic-state" role="alert"><p>{{ error }}</p><button class="hp-underlined-link" @click="$emit('retry')">تلاش مجدد ←</button></div>
        <div v-else-if="!products.length" class="hp-dynamic-state"><p>انتخاب‌های تازه به‌زودی به این مجموعه اضافه می‌شوند.</p><a href="/store" class="hp-underlined-link">دیدن فروشگاه ←</a></div>
        <div v-else :class="rail ? 'hp-product-rail' : 'hp-product-grid'" :tabindex="rail ? 0 : undefined" :aria-label="rail ? title : undefined"><ProductCard v-for="product in products" :key="product.id" :product="product" /></div>
    </section>
</template>
