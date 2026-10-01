<script setup>
import { computed, ref } from 'vue';
import { formatPrice, imageUrl } from '../../product-presentation.js';
import { PLACEHOLDER_SRC, nextImageSrc } from '../../image-fallback.js';
import { cartItemAttributes, cartItemLineTotal, cartItemMonogram, cartItemQuantity } from '../../cart-presentation.js';

const PLACEHOLDER = PLACEHOLDER_SRC;

const props = defineProps({
    item: { type: Object, required: true },
    errors: { type: Array, default: () => [] },
});

const quantity = computed(() => cartItemQuantity(props.item));
const lineTotal = computed(() => cartItemLineTotal(props.item));
const variants = computed(() => cartItemAttributes(props.item));
const monogram = computed(() => cartItemMonogram(props.item));
const href = computed(() => {
    const id = Number(props.item?.product_id);
    return Number.isInteger(id) && id > 0 ? `/products/${id}` : '';
});

const imageSrc = ref(imageUrl(props.item?.image) || PLACEHOLDER);
const imageBroken = ref(false);

// Shared chain: real image -> temporary external fallback -> placeholder -> monogram.
function fallBackImage() {
    const next = nextImageSrc(imageSrc.value, props.item?.product_id ?? props.item?.key);
    if (!next) { imageBroken.value = true; return; }
    imageSrc.value = next;
}
</script>

<template>
    <li class="co-line">
        <div class="co-line-media">
            <img v-if="!imageBroken" :src="imageSrc" :alt="item.name || 'تصویر محصول'" width="72" height="90"
                loading="lazy" decoding="async" @error="fallBackImage" />
            <span v-else class="co-line-placeholder" aria-hidden="true">
                <span lang="en">{{ monogram }}</span>
                <small>بدون تصویر</small>
            </span>
        </div>

        <div class="co-line-body">
            <h3 class="co-line-name">
                <a v-if="href" :href="href">{{ item.name || 'محصول' }}</a>
                <span v-else>{{ item.name || 'محصول' }}</span>
            </h3>
            <p v-if="variants.length || item.sku" class="co-line-meta sf-type-caption">
                <span v-for="variant in variants" :key="variant.slug" class="co-line-variant">
                    <b>{{ variant.label }}:</b> <span>{{ variant.value }}</span>
                </span>
                <span v-if="item.sku" class="co-line-sku">کد کالا: <b dir="ltr">{{ item.sku }}</b></span>
            </p>
            <p class="co-line-meta sf-type-caption">
                تعداد: {{ quantity.toLocaleString('fa-IR') }} · قیمت واحد: {{ formatPrice(item.price) }} تومان
            </p>
            <p v-for="entry in errors" :key="entry.field" class="co-line-error" role="alert">{{ entry.message }}</p>
        </div>

        <p class="co-line-total sf-type-price">
            {{ formatPrice(lineTotal) }} <small>تومان</small>
        </p>
    </li>
</template>
