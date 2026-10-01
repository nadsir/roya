<script setup>
import { formatPrice, imageUrl, axisLabel } from '../../product-presentation.js';
import { nextImageSrc } from '../../image-fallback.js';

// Read only: the order is a record of what was bought, so the quantity and the
// line total are never editable from the order page.
defineProps({ item: { type: Object, required: true } });

// Shared chain: real image -> temporary external fallback -> placeholder -> monogram.
function fallBackImage(event, item) {
    const next = nextImageSrc(event.target.currentSrc || event.target.src, item?.product_id ?? item?.id);
    if (next) event.target.src = next;
}

function attributes(item) {
    return Object.entries(item.attributes || {}).map(([axis, values]) => ({
        axis: axisLabel(axis),
        labels: (Array.isArray(values) ? values : [values])
            .map((value) => value?.label ?? value?.value ?? '')
            .filter(Boolean),
    })).filter((entry) => entry.labels.length);
}
</script>

<template>
    <article class="ac-line">
        <span class="ac-line-media">
            <img v-if="item.image" :src="imageUrl(item.image)" :alt="item.product_name" loading="lazy" @error="fallBackImage($event, item)" />
        </span>
        <div class="ac-line-copy">
            <h3>
                <a v-if="item.product_id" :href="`/products/${item.product_id}`">{{ item.product_name }}</a>
                <template v-else>{{ item.product_name }}</template>
            </h3>
            <p class="ac-line-meta">
                <span>تعداد: {{ item.quantity?.toLocaleString('fa-IR') }}</span>
                <span>قیمت واحد: {{ formatPrice(item.unit_price) }} تومان</span>
                <span v-if="item.sku" dir="ltr">SKU: {{ item.sku }}</span>
            </p>
            <p v-for="entry in attributes(item)" :key="entry.axis" class="ac-line-meta">
                <span>{{ entry.axis }}: {{ entry.labels.join('، ') }}</span>
            </p>
            <p class="ac-line-total">جمع: {{ formatPrice(item.subtotal) }} تومان</p>
        </div>
    </article>
</template>
