<script setup>
import { formatPrice, imageUrl, productImages } from '../../product-presentation.js';
import { nextImageSrc } from '../../image-fallback.js';

// The wishlist endpoint returns the full product, so the card shows the real
// price, the real primary image and the real stock flag.
defineProps({
    item: { type: Object, required: true },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['remove']);

function image(item) {
    return imageUrl(productImages(item?.product || {})[0]?.path || '');
}

// Shared chain: real image -> temporary external fallback -> placeholder -> monogram.
function fallBackImage(event, item) {
    const next = nextImageSrc(event.target.currentSrc || event.target.src, item?.product_id ?? item?.product?.id);
    if (next) event.target.src = next;
}
</script>

<template>
    <article class="ac-item">
        <a class="ac-item-media" :href="`/products/${item.product_id}`" :aria-label="item.product?.name || 'مشاهده محصول'">
            <img v-if="image(item)" :src="image(item)" :alt="item.product?.images?.[0]?.alt_text || item.product?.name || 'تصویر محصول'" loading="lazy" @error="fallBackImage($event, item)" />
            <span v-else class="ac-item-badge sf-type-caption">تصویر محصول به‌زودی</span>
        </a>

        <div class="ac-item-body">
            <h3>
                <a :href="`/products/${item.product_id}`">{{ item.product?.name || 'مشاهده محصول' }}</a>
            </h3>
            <p class="ac-item-price">{{ formatPrice(item.product?.price) }} <small>تومان</small></p>
            <p v-if="item.product?.in_stock === false" class="sf-type-caption">ناموجود</p>
            <p v-else class="sf-type-caption">موجود</p>
            <div class="ac-item-foot">
                <a class="sf-text-link" :href="`/products/${item.product_id}`">مشاهده محصول</a>
                <button
                    type="button"
                    class="ac-item-remove"
                    :disabled="busy"
                    :aria-label="`حذف ${item.product?.name || 'محصول'} از علاقه‌مندی‌ها`"
                    @click="emit('remove', item.product_id)"
                >
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                    <span>حذف</span>
                </button>
            </div>
        </div>
    </article>
</template>
