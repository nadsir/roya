<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { addToCart } from '../cart-state.js';
import { isLoggedIn } from '../auth-state.js';
import { state as wishlistState, isInWishlist, addToWishlist, removeFromWishlist } from '../wishlist-state.js';
import { cartItemFromProduct, discountPercent, formatPrice, hasProductVariants, imageUrl, productColors, productHref, productImages } from '../product-presentation.js';

const props = defineProps({ product: { type: Object, required: true } });
const failedPaths = ref([]);
const pending = ref(false);
const feedback = ref('');
const feedbackError = ref(false);
let feedbackTimer;
const images = computed(() => productImages(props.product).filter((image) => !failedPaths.value.includes(image.path)));
const colors = computed(() => productColors(props.product));
const discount = computed(() => discountPercent(props.product));
const inWishlist = computed(() => isLoggedIn.value && isInWishlist(props.product.id));
const hasVariants = computed(() => hasProductVariants(props.product));
function markImageFailed(event) {
    const path = event.target.dataset.imagePath;
    if (path && !failedPaths.value.includes(path)) failedPaths.value.push(path);
}
const wishlistLabel = computed(() => !isLoggedIn.value ? `ورود برای ذخیره ${props.product.name}` : inWishlist.value ? `حذف ${props.product.name} از علاقه‌مندی‌ها` : `ذخیره ${props.product.name} در علاقه‌مندی‌ها`);
watch(() => props.product.id, () => { failedPaths.value = []; feedback.value = ''; clearTimeout(feedbackTimer); });

function notify(message, error = false) {
    feedback.value = message; feedbackError.value = error;
    clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => { feedback.value = ''; }, 4500);
}
function quickAdd() {
    if (!props.product.in_stock) return;
    if (hasVariants.value) { location.href = productHref(props.product); return; }
    const item = cartItemFromProduct(props.product);
    if (!item) return;
    addToCart(item);
    notify('به سبد خرید اضافه شد.');
}
async function toggleWishlist() {
    if (!isLoggedIn.value) { location.href = '/login?redirect=%2F'; return; }
    if (pending.value || wishlistState.loading) return;
    pending.value = true;
    const wasSaved = inWishlist.value;
    try {
        const result = wasSaved ? await removeFromWishlist(props.product.id) : await addToWishlist(props.product.id);
        if (!result) { notify('ذخیره علاقه‌مندی انجام نشد؛ دوباره تلاش کنید.', true); return; }
        notify(wasSaved ? 'از علاقه‌مندی‌ها حذف شد.' : 'در علاقه‌مندی‌ها ذخیره شد.');
    } catch { notify('ذخیره علاقه‌مندی انجام نشد؛ دوباره تلاش کنید.', true); }
    finally { pending.value = false; }
}
onBeforeUnmount(() => clearTimeout(feedbackTimer));
</script>

<template>
    <article class="hp-product-card">
        <div class="hp-product-media">
            <a :href="productHref(product)" class="hp-product-image-link" :aria-label="product.name">
                <img v-if="images[0]" :key="images[0].path" :data-image-path="images[0].path" class="hp-product-image" :src="imageUrl(images[0].path)" :alt="images[0].alt_text || product.name" width="600" height="750" loading="lazy" decoding="async" @error="markImageFailed" />
                <img v-if="images[1]" :key="images[1].path" :data-image-path="images[1].path" class="hp-product-image hp-product-image--alternate" :src="imageUrl(images[1].path)" alt="" width="600" height="750" loading="lazy" decoding="async" @error="markImageFailed" />
                <span v-if="!images.length" class="hp-image-placeholder"><span lang="en" aria-hidden="true">G</span><small>تصویر محصول به‌زودی</small></span>
            </a>
            <span v-if="discount" class="hp-product-badge">{{ discount.toLocaleString('fa-IR') }}٪ تخفیف</span>
            <button class="hp-wishlist-button" :class="{ 'is-saved': inWishlist }" :aria-label="wishlistLabel" :aria-pressed="inWishlist" :aria-busy="pending" :disabled="pending || wishlistState.loading" @click="toggleWishlist"><i :class="pending ? 'fa-solid fa-spinner' : inWishlist ? 'fa-solid fa-heart' : 'fa-regular fa-heart'" aria-hidden="true" /></button>
            <button class="hp-quick-add" :disabled="!product.in_stock" :aria-label="`${hasVariants ? 'انتخاب تنوع' : 'افزودن به سبد'} ${product.name}`" @click="quickAdd"><span>{{ !product.in_stock ? 'ناموجود' : hasVariants ? 'انتخاب سایز و جزئیات' : 'افزودن به سبد' }}</span><span v-if="product.in_stock" aria-hidden="true">{{ hasVariants ? '←' : '+' }}</span></button>
        </div>
        <div class="hp-product-copy">
            <div class="hp-product-colors" v-if="colors.length" aria-label="رنگ‌های محصول"><span v-for="color in colors.slice(0, 5)" :key="color.hex_color" :style="{ '--swatch': color.hex_color }" :title="color.label" role="img" :aria-label="color.label" /><span v-if="colors.length > 5" class="hp-more-colors">+{{ (colors.length - 5).toLocaleString('fa-IR') }}</span></div>
            <h3><a :href="productHref(product)">{{ product.name }}</a></h3>
            <p class="hp-product-price"><span>{{ formatPrice(product.price) }} <small>تومان</small></span><del v-if="discount" :aria-label="`قیمت پیشین ${formatPrice(product.compare_at_price)} تومان`">{{ formatPrice(product.compare_at_price) }}</del></p>
            <p class="hp-product-feedback" :class="{ 'is-error': feedbackError }" :role="feedbackError ? 'alert' : 'status'">{{ feedback }}</p>
        </div>
    </article>
</template>
