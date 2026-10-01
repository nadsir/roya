<script setup>
import { computed, ref } from 'vue';
import { removeFromCart, updateQuantity } from '../../cart-state.js';
import { formatPrice, imageUrl } from '../../product-presentation.js';
import { PLACEHOLDER_SRC, nextImageSrc } from '../../image-fallback.js';
import {
    cartItemAttributes,
    cartItemComparePrice,
    cartItemDiscountPercent,
    cartItemLineTotal,
    cartItemMaxQuantity,
    cartItemMonogram,
    cartItemQuantity,
    cartItemReachedMax,
} from '../../cart-presentation.js';

const PLACEHOLDER = PLACEHOLDER_SRC;

const props = defineProps({
    item: { type: Object, required: true },
    compact: { type: Boolean, default: false },
});

const quantity = computed(() => cartItemQuantity(props.item));
const maxQuantity = computed(() => cartItemMaxQuantity(props.item));
const atMax = computed(() => cartItemReachedMax(props.item));
const lineTotal = computed(() => cartItemLineTotal(props.item));
const comparePrice = computed(() => cartItemComparePrice(props.item));
const discountPercent = computed(() => cartItemDiscountPercent(props.item));
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

function increase() {
    if (atMax.value) return;
    updateQuantity(props.item.key, quantity.value + 1);
}

// Decrementing the last unit removes the line, matching the previous cart page.
function decrease() {
    if (quantity.value <= 1) {
        removeFromCart(props.item.key);
        return;
    }
    updateQuantity(props.item.key, quantity.value - 1);
}
</script>

<template>
    <li class="ct-item" :class="{ 'ct-item--compact': compact }">
        <div class="ct-item-media">
            <img v-if="!imageBroken" :src="imageSrc" :alt="item.name || 'تصویر محصول'" width="120" height="150"
                loading="lazy" decoding="async" @error="fallBackImage" />
            <span v-else class="ct-item-placeholder" aria-hidden="true">
                <span lang="en">{{ monogram }}</span>
                <small>بدون تصویر</small>
            </span>
        </div>

        <div class="ct-item-body">
            <div class="ct-item-head">
                <h3 class="ct-item-name">
                    <a v-if="href" :href="href">{{ item.name || 'محصول' }}</a>
                    <span v-else>{{ item.name || 'محصول' }}</span>
                </h3>
                <p class="ct-item-price sf-type-price">
                    <span>{{ formatPrice(lineTotal) }} <small>تومان</small></span>
                    <del v-if="comparePrice" :aria-label="`قیمت پیشین ${formatPrice(comparePrice)} تومان`">
                        {{ formatPrice(comparePrice) }}
                    </del>
                </p>
            </div>

            <p v-if="variants.length || item.sku" class="ct-item-meta sf-type-caption">
                <span v-for="variant in variants" :key="variant.slug" class="ct-item-variant">
                    <b>{{ variant.label }}:</b> <span>{{ variant.value }}</span>
                </span>
                <span v-if="item.sku" class="ct-item-sku">کد کالا: <b dir="ltr">{{ item.sku }}</b></span>
            </p>

            <span v-if="discountPercent" class="ct-item-discount">{{ discountPercent.toLocaleString('fa-IR') }}٪ تخفیف</span>

            <div class="ct-item-actions">
                <div class="ct-stepper" role="group" :aria-label="`تعداد ${item.name || 'محصول'}`">
                    <button type="button" class="ct-stepper-button" :aria-label="`افزایش تعداد ${item.name || 'محصول'}`"
                        :disabled="atMax" :title="atMax ? 'بیشتر از موجودی قابل انتخاب نیست' : 'افزایش تعداد'"
                        @click="increase">
                        <i class="fa-solid fa-plus" aria-hidden="true" />
                    </button>
                    <span class="ct-stepper-value" aria-live="polite">{{ quantity.toLocaleString('fa-IR') }}</span>
                    <button type="button" class="ct-stepper-button" :aria-label="quantity > 1 ? `کاهش تعداد ${item.name || 'محصول'}` : `حذف ${item.name || 'محصول'} از سبد خرید`"
                        :title="quantity > 1 ? 'کاهش تعداد' : 'حذف محصول'" @click="decrease">
                        <i class="fa-solid" :class="quantity > 1 ? 'fa-minus' : 'fa-trash-can'" aria-hidden="true" />
                    </button>
                </div>
                <p class="ct-item-stock sf-type-caption">حداکثر {{ maxQuantity.toLocaleString('fa-IR') }}</p>
                <button type="button" class="ct-item-remove" :aria-label="`حذف ${item.name || 'محصول'} از سبد خرید`" @click="removeFromCart(item.key)">
                    <i class="fa-regular fa-trash-can" aria-hidden="true" />
                    <span>حذف</span>
                </button>
            </div>
        </div>
    </li>
</template>
