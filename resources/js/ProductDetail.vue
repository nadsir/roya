<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import ProductCard from './components/ProductCard.vue';
import { createProductDetailState } from './product-detail-state.js';
import { formatPrice } from './product-presentation.js';
import { state as authState, isLoggedIn } from './auth-state.js';
import {
    state as wishlistState,
    loadWishlist,
    isInWishlist,
    addToWishlist,
    removeFromWishlist,
} from './wishlist-state.js';
import { createCommentSection, faNum } from './comment-state.js';
import CommentSummary from './components/CommentSummary.vue';
import CommentList from './components/CommentList.vue';
import CommentComposer from './components/CommentComposer.vue';
import '../css/homepage.css';
import '../css/product.css';

const props = defineProps({ model: { type: Object, default: null } });

// The page owns its state by default; the prop exists so the same markup can be
// rendered with an already-loaded product (mirrors DiscoveryFilters' `model` prop).
const model = props.model || createProductDetailState();
const ownsModel = !props.model;

const {
    product, result, notice, quantity, activeIndex, selectedVariants, related, relatedLoading,
    images, activeImage, activeImageSrc, activeImageFailed, attributeAxes, hasVariants,
    displayPrice, displayCompareAtPrice, displayDiscount, displaySku, displayInStock, displayStock,
    needsVariantSelection, displayAttributes, customAttributes, categories, brand,
    maxQuantity, canIncreaseQuantity, load, dispose, add, addLabel, addHint, showNotice, clearNotice,
    setQuantity, increaseQuantity, decreaseQuantity, thumbSrc, markImageFailed, imageErrorSrc,
    selectImage, nextImage, prevImage, isValueSelectable, isSelected, selectedLabel, toggleAxisValue,
} = model;

let seoCleanup = null;

function getProductionBaseUrl() {
    return 'https://royafashion.ir';
}

function toProductionUrl(path) {
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) {
        try {
            const url = new URL(path);
            if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') {
                return `${getProductionBaseUrl()}${url.pathname}${url.search}${url.hash}`;
            }
            return path;
        } catch {
            return path;
        }
    }
    if (path.startsWith('/')) {
        return `${getProductionBaseUrl()}${path}`;
    }
    return `${getProductionBaseUrl()}/${path}`;
}

function setMeta(name, content) {
    if (content === null || content === undefined || content === '') {
        removeMeta(name);
        return;
    }
    const isTwitter = name.startsWith('twitter:');
    const isProperty = !isTwitter && (name.startsWith('og:') || name === 'product:price:amount' || name === 'product:price:currency');
    const attr = isProperty ? 'property' : 'name';
    let el = document.querySelector(`meta[${attr}="${name}"]`);
    if (!el) {
        el = document.createElement('meta');
        el.setAttribute(attr, name);
        document.head.appendChild(el);
    }
    el.content = String(content);
}

function removeMeta(name) {
    document.querySelectorAll(`meta[name="${name}"], meta[property="${name}"]`).forEach((el) => el.remove());
}

function setCanonical(url) {
    let link = document.querySelector('link[rel="canonical"]');
    if (!link) {
        link = document.createElement('link');
        link.rel = 'canonical';
        document.head.appendChild(link);
    }
    link.href = url;
}

function removeCanonical() {
    const link = document.querySelector('link[rel="canonical"]');
    if (link) link.remove();
}

function injectJsonLd(obj) {
    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.dataset.seo = 'product';
    script.textContent = JSON.stringify(obj);
    document.head.appendChild(script);
    return script;
}

function clearSeoJsonLd() {
    document.querySelectorAll('script[type="application/ld+json"][data-seo="product"]').forEach((el) => el.remove());
}

function productImageUrl() {
    const img = images.value?.[0];
    if (!img?.path) return '';
    if (/^https?:\/\//i.test(img.path)) return img.path;
    const path = img.path.startsWith('/') ? img.path : '/' + img.path;
    return `${getProductionBaseUrl()}${path}`;
}

function applyProductSeo(p) {
    if (!p) return;
    const siteName = 'ROYA GALLERY';
    const title = p.meta_title || `${p.name} | ${siteName}`;
    const rawDesc = p.meta_description || p.short_description || '';
    const description = rawDesc ? String(rawDesc).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 160) : p.name;
    document.title = title;
    setMeta('description', description);
    setMeta('robots', 'index,follow');
    setMeta('og:type', 'product');
    setMeta('og:title', title);
    setMeta('og:description', description);
    const canonical = (p.canonical_url && /^https?:\/\//i.test(p.canonical_url)) ? toProductionUrl(p.canonical_url) : toProductionUrl(`/products/${p.id}`);
    setMeta('og:url', canonical);
    setMeta('og:site_name', siteName);
    setMeta('og:locale', 'fa_IR');
    const img = productImageUrl();
    if (img) setMeta('og:image', img);
    setMeta('twitter:card', 'summary_large_image');
    setMeta('twitter:title', title);
    setMeta('twitter:description', description);
    if (img) setMeta('twitter:image', img);
    if (displayPrice.value !== null && displayPrice.value !== undefined) {
        setMeta('product:price:amount', String(displayPrice.value));
        setMeta('product:price:currency', 'IRR');
    } else {
        removeMeta('product:price:amount');
        removeMeta('product:price:currency');
    }
    setCanonical(canonical);

    const breadcrumbs = [
        { '@type': 'ListItem', position: 1, name: 'خانه', item: `${getProductionBaseUrl()}/` },
        { '@type': 'ListItem', position: 2, name: 'فروشگاه', item: `${getProductionBaseUrl()}/store` },
    ];
    if (categories.value?.length) {
        const last = categories.value[categories.value.length - 1];
        breadcrumbs.push({ '@type': 'ListItem', position: breadcrumbs.length + 1, name: last.name, item: toProductionUrl(`/c/${last.slug}`) });
    }
    breadcrumbs.push({ '@type': 'ListItem', position: breadcrumbs.length + 1, name: p.name, item: canonical });

    const productLd = {
        '@context': 'https://schema.org',
        '@type': 'Product',
        name: p.name,
        description,
    };
    if (displaySku.value || p.sku) productLd.sku = displaySku.value || p.sku;
    if (img) productLd.image = [img];
    if (brand.value) productLd.brand = { '@type': 'Brand', name: brand.value };
    productLd.offers = {
        '@type': 'Offer',
        url: canonical,
        priceCurrency: 'IRR',
        price: String(displayPrice.value ?? 0),
        availability: displayInStock.value ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        itemCondition: 'https://schema.org/NewCondition',
    };

    const breadcrumbLd = {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: breadcrumbs,
    };

    clearSeoJsonLd();
    const nodes = [injectJsonLd(productLd), injectJsonLd(breadcrumbLd)];
    seoCleanup = () => {
        nodes.forEach((n) => {
            if (n && n.parentNode) n.parentNode.removeChild(n);
        });
        clearSeoJsonLd();
        removeMeta('description');
        removeMeta('robots');
        removeMeta('og:type');
        removeMeta('og:title');
        removeMeta('og:description');
        removeMeta('og:url');
        removeMeta('og:site_name');
        removeMeta('og:locale');
        removeMeta('og:image');
        removeMeta('twitter:card');
        removeMeta('twitter:title');
        removeMeta('twitter:description');
        removeMeta('twitter:image');
        removeMeta('product:price:amount');
        removeMeta('product:price:currency');
        removeCanonical();
        document.title = 'ROYA GALLERY | فروشگاه لباس و اکسسوری زنانه';
    };
}

watch(product, (p) => {
    if (seoCleanup) {
        try {
            seoCleanup();
        } catch (e) {}
        seoCleanup = null;
    }
    if (p) {
        // Remove any server-rendered product JSON-LD from the initial HTML so
        // the freshly hydrated state never produces duplicate Product entities.
        clearSeoJsonLd();
        applyProductSeo(p);
    } else if (!result.loading) {
        document.title = 'ROYA GALLERY | فروشگاه لباس و اکسسوری زنانه';
    }
}, { immediate: false });

const comments = createCommentSection('product');
const panels = reactive({ description: true, specs: true });
const hasSpecs = computed(() => displayAttributes.value.length > 0 || customAttributes.value.length > 0);
const galleryLabel = computed(() => `گالری تصاویر ${product.value?.name || ''}`.trim());
const touchStartX = ref(null);
const PLACEHOLDER = '/images/placeholder.svg';

const inWishlist = computed(() => (isLoggedIn.value && product.value ? isInWishlist(product.value.id) : false));
const wishlistLabel = computed(() => {
    if (!isLoggedIn.value) return 'ورود برای ذخیره در علاقه‌مندی‌ها';
    if (wishlistState.loading) return 'در حال به‌روزرسانی علاقه‌مندی‌ها';
    return inWishlist.value ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها';
});

// Wishlist state follows the signed-in user, including a login that finishes after the product loads.
watch([() => product.value?.id, () => authState.user?.id], ([productId, userId]) => {
    if (productId && userId) loadWishlist();
}, { immediate: true });

watch(() => wishlistState.error, (wishlistError) => {
    if (!wishlistError || !isLoggedIn.value || !product.value) return;
    showNotice(wishlistError.message || 'خطای علاقه‌مندی‌ها', 'error');
});

async function toggleWishlist() {
    if (!product.value) return;
    if (!isLoggedIn.value) {
        location.href = `/login?redirect=${encodeURIComponent(location.pathname)}`;
        return;
    }
    if (wishlistState.loading) return;
    const saved = isInWishlist(product.value.id);
    const done = saved ? await removeFromWishlist(product.value.id) : await addToWishlist(product.value.id);
    if (!done) {
        showNotice('ذخیره علاقه‌مندی انجام نشد؛ دوباره تلاش کنید.', 'error');
        return;
    }
    showNotice(saved ? 'از علاقه‌مندی‌ها حذف شد.' : 'در علاقه‌مندی‌ها ذخیره شد.', 'success');
}

// Reviews are loaded once the product id is known; guests are sent to login by comment-state.
watch([() => product.value?.id, () => authState.user?.id], async ([productId]) => {
    if (!productId) return;
    if (!comments.section.ownerId) {
        comments.section.ownerId = productId;
        await comments.loadFirstPage();
    }
    comments.restoreIntent();
});

function onImgError(event, path) {
    const target = event.target;
    if (!target) return;
    const next = imageErrorSrc(path, target.currentSrc || target.src);
    if (next) target.src = next;
}

function onTouchStart(event) {
    touchStartX.value = event.changedTouches?.[0]?.clientX ?? null;
}
function onTouchEnd(event) {
    if (touchStartX.value === null) return;
    const endX = event.changedTouches?.[0]?.clientX ?? touchStartX.value;
    const delta = endX - touchStartX.value;
    touchStartX.value = null;
    if (Math.abs(delta) < 40) return;
    if (delta < 0) nextImage();
    else prevImage();
}

function goBack() {
    if (window.history.length > 1) window.history.back();
    else window.location.href = '/store';
}

onMounted(() => { if (ownsModel) load(); });
onBeforeUnmount(() => {
    if (seoCleanup) {
        try {
            seoCleanup();
        } catch (e) {}
        seoCleanup = null;
    }
    if (ownsModel) dispose();
});
</script>

<template>
    <div class="pd-page" dir="rtl">
        <SiteHeader />

        <!-- Loading -->
        <div v-if="result.loading" class="pd-container pd-skeleton" role="status" aria-busy="true">
            <span class="pd-sr-only">در حال بارگذاری محصول…</span>
            <div class="pd-skeleton-media"><span class="sf-skeleton" /></div>
            <div class="pd-skeleton-copy">
                <span class="sf-skeleton pd-skeleton-line pd-skeleton-line--sm" />
                <span class="sf-skeleton pd-skeleton-line pd-skeleton-line--lg" />
                <span class="sf-skeleton pd-skeleton-line pd-skeleton-line--sm" />
                <span class="sf-skeleton pd-skeleton-line pd-skeleton-line--price" />
                <span class="sf-skeleton pd-skeleton-line pd-skeleton-line--options" />
                <span class="sf-skeleton pd-skeleton-line pd-skeleton-line--options" />
                <span class="sf-skeleton pd-skeleton-line pd-skeleton-line--cta" />
            </div>
        </div>

        <!-- Product not found -->
        <div v-else-if="result.notFound" class="pd-container pd-state" role="alert">
            <p class="pd-eyebrow" lang="en" dir="ltr">NOT IN THE EDIT</p>
            <h1>این محصول دیگر در دسترس نیست.</h1>
            <p>شاید این انتخاب از مجموعه گالری خارج شده باشد. نگاهی به تازه‌های مجموعه بیندازید.</p>
            <div class="pd-state-actions">
                <a href="/store" class="pd-button">مشاهده همه محصولات</a>
                <button type="button" class="pd-text-button" @click="goBack">بازگشت</button>
            </div>
        </div>

        <!-- API error -->
        <div v-else-if="result.error" class="pd-container pd-state" role="alert">
            <p class="pd-eyebrow" lang="en" dir="ltr">A MOMENT AWAY</p>
            <h1>کمی بعد دوباره ببینیم.</h1>
            <p>{{ result.error }}</p>
            <div class="pd-state-actions">
                <button type="button" class="pd-button" @click="load">تلاش مجدد</button>
                <a href="/store" class="pd-text-button">بازگشت به فروشگاه</a>
            </div>
        </div>

        <!-- Product -->
        <main v-else-if="product" class="pd-main">
            <div class="pd-container">
                <nav class="pd-breadcrumb" aria-label="مسیر صفحه">
                    <a href="/">خانه</a>
                    <span aria-hidden="true">/</span>
                    <a href="/store">گالری</a>
                    <template v-for="cat in categories" :key="cat.id">
                        <span aria-hidden="true">/</span>
                        <a :href="`/c/${cat.slug}`">{{ cat.name }}</a>
                    </template>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page">{{ product.name }}</span>
                </nav>

                <div class="pd-hero">
                    <!-- Gallery -->
                    <section class="pd-gallery" :aria-label="galleryLabel">
                        <div v-if="images.length > 1" class="pd-thumbs no-scrollbar" role="group" aria-label="انتخاب تصویر">
                            <button
                                v-for="(image, index) in images"
                                :key="image.path"
                                type="button"
                                class="pd-thumb"
                                :class="{ 'is-active': index === activeIndex }"
                                :aria-current="index === activeIndex ? 'true' : undefined"
                                :aria-label="`نمای ${faNum(index + 1)} از ${faNum(images.length)}`"
                                @click="selectImage(index)"
                            >
                                <img
                                    v-if="thumbSrc(image)"
                                    :src="thumbSrc(image)"
                                    :alt="image.alt_text || ''"
                                    decoding="async"
                                    @error="onImgError($event, image.path)"
                                />
                                <span v-else class="pd-thumb-fallback" aria-hidden="true" lang="en">G</span>
                            </button>
                        </div>

                        <div class="pd-stage-wrap">
                            <span class="pd-stage-frame" aria-hidden="true" />
                            <div
                                class="pd-stage"
                                @touchstart.passive="onTouchStart"
                                @touchend.passive="onTouchEnd"
                            >
                                <img
                                    v-if="activeImageSrc"
                                    :key="activeImage?.path"
                                    :src="activeImageSrc"
                                    :alt="activeImage?.alt_text || product.name"
                                    class="pd-stage-image"
                                    decoding="async"
                                    @error="onImgError($event, activeImage?.path)"
                                />
                                <div v-else class="pd-fallback">
                                    <span aria-hidden="true" lang="en">G</span>
                                    <small>{{ activeImageFailed ? 'تصویر در دسترس نیست' : 'تصویر محصول به‌زودی' }}</small>
                                </div>
                                <button
                                    v-if="images.length > 1"
                                    type="button"
                                    class="pd-nav pd-nav--prev"
                                    aria-label="تصویر قبلی"
                                    @click="prevImage"
                                >
                                    <i class="fa-solid fa-chevron-right" aria-hidden="true" />
                                </button>
                                <button
                                    v-if="images.length > 1"
                                    type="button"
                                    class="pd-nav pd-nav--next"
                                    aria-label="تصویر بعدی"
                                    @click="nextImage"
                                >
                                    <i class="fa-solid fa-chevron-left" aria-hidden="true" />
                                </button>
                                <span v-if="images.length > 1" class="pd-counter" aria-hidden="true">
                                    {{ faNum(activeIndex + 1) }} / {{ faNum(images.length) }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <!-- Information -->
                    <div class="pd-info">
                        <p v-if="brand || categories.length" class="pd-eyebrow">
                            <template v-if="brand">{{ brand }}</template>
                            <template v-if="brand && categories.length"> · </template>
                            <template v-if="categories.length">{{ categories[categories.length - 1].name }}</template>
                        </p>

                        <h1 class="pd-title">{{ product.name }}</h1>

                        <p v-if="displaySku" class="pd-sku">کد کالا: <span lang="en">{{ displaySku }}</span></p>

                        <p v-if="product.short_description" class="pd-lead">{{ product.short_description }}</p>

                        <div class="pd-price-block">
                            <span class="pd-price">{{ formatPrice(displayPrice) }}<small>تومان</small></span>
                            <del v-if="displayCompareAtPrice" class="pd-price-old">{{ formatPrice(displayCompareAtPrice) }}</del>
                            <span v-if="displayDiscount" class="pd-discount">{{ faNum(displayDiscount) }}٪ تخفیف</span>
                        </div>

                        <p class="pd-stock" :class="displayInStock ? 'is-in' : 'is-out'">
                            <span class="pd-stock-dot" aria-hidden="true" />
                            <span v-if="displayInStock && displayStock !== null">{{ faNum(displayStock) }} عدد در انبار</span>
                            <span v-else-if="displayInStock">موجود در گالری</span>
                            <span v-else>ناموجود</span>
                        </p>

                        <div v-if="hasVariants && attributeAxes.length" class="pd-variants">
                            <div v-for="axis in attributeAxes" :key="axis.slug" class="pd-variant-axis">
                                <div class="pd-variant-head">
                                    <span class="pd-variant-label">{{ axis.label }}</span>
                                    <span class="pd-variant-value">{{ selectedLabel(axis) || 'انتخاب کنید' }}</span>
                                </div>
                                <div class="pd-variant-options" role="group" :aria-label="`انتخاب ${axis.label}`">
                                    <button
                                        v-for="value in axis.values"
                                        :key="value.id"
                                        type="button"
                                        class="pd-option"
                                        :class="{
                                            'is-selected': isSelected(axis, value),
                                            'is-unavailable': !isValueSelectable(axis.slug, value.id),
                                        }"
                                        :disabled="!isValueSelectable(axis.slug, value.id)"
                                        :aria-pressed="isSelected(axis, value)"
                                        @click="toggleAxisValue(axis.slug, value.id)"
                                    >
                                        <span
                                            v-if="value.hex_color"
                                            class="pd-swatch"
                                            :style="{ '--swatch': value.hex_color }"
                                            role="img"
                                            :aria-label="value.label"
                                        />
                                        <span>{{ value.label }}</span>
                                        <i v-if="isSelected(axis, value)" class="fa-solid fa-check" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-if="addHint" class="pd-hint" role="status">
                            <i class="fa-solid fa-circle-info" aria-hidden="true" />
                            <span>{{ addHint }}</span>
                        </p>

                        <div class="pd-purchase">
                            <div class="pd-quantity" role="group" aria-label="تعداد">
                                <button type="button" aria-label="کاهش تعداد" :disabled="quantity <= 1" @click="decreaseQuantity">−</button>
                                <output class="pd-quantity-value" aria-live="polite">{{ faNum(quantity) }}</output>
                                <button type="button" aria-label="افزودن تعداد" :disabled="!canIncreaseQuantity" @click="increaseQuantity">+</button>
                            </div>
                            <button
                                type="button"
                                class="pd-cta"
                                :disabled="!displayInStock || needsVariantSelection"
                                @click="add"
                            >
                                <i class="fa-solid fa-bag-shopping" aria-hidden="true" />
                                {{ addLabel }}
                            </button>
                        </div>

                        <button
                            type="button"
                            class="pd-wishlist"
                            :class="{ 'is-saved': inWishlist }"
                            :disabled="wishlistState.loading"
                            :aria-pressed="inWishlist"
                            :aria-busy="wishlistState.loading"
                            :aria-label="wishlistLabel"
                            @click="toggleWishlist"
                        >
                            <i
                                :class="wishlistState.loading ? 'fa-solid fa-spinner fa-spin' : inWishlist ? 'fa-solid fa-heart' : 'fa-regular fa-heart'"
                                aria-hidden="true"
                            />
                            <span>{{ wishlistLabel }}</span>
                        </button>
                    </div>
                </div>

                <!-- Details -->
                <section v-if="product.description || hasSpecs" class="pd-section pd-details" aria-labelledby="pd-details-title">
                    <header class="pd-section-head">
                        <span class="pd-section-rule" aria-hidden="true" />
                        <div>
                            <p class="pd-eyebrow" lang="en" dir="ltr">THE DETAILS</p>
                            <h2 id="pd-details-title">درباره این انتخاب</h2>
                        </div>
                    </header>

                    <div class="pd-accordion">
                        <details v-if="product.description" class="pd-panel" :open="panels.description" @toggle="panels.description = $event.target.open">
                            <summary>توضیحات</summary>
                            <div class="pd-panel-body"><p class="pd-prose">{{ product.description }}</p></div>
                        </details>

                        <details v-if="hasSpecs" class="pd-panel" :open="panels.specs" @toggle="panels.specs = $event.target.open">
                            <summary>مشخصات</summary>
                            <div class="pd-panel-body">
                                <dl class="pd-specs">
                                    <div v-for="attr in displayAttributes" :key="attr.slug" class="pd-spec-row">
                                        <dt>{{ attr.label }}</dt>
                                        <dd>{{ attr.values.map((value) => value.label).join('، ') }}</dd>
                                    </div>
                                    <div v-for="attr in customAttributes" :key="attr.id" class="pd-spec-row">
                                        <dt>{{ attr.name }}</dt>
                                        <dd>{{ attr.value }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </details>
                    </div>
                </section>

                <!-- Reviews -->
                <section class="pd-section pd-reviews" aria-labelledby="pd-reviews-title">
                    <header class="pd-section-head">
                        <span class="pd-section-rule" aria-hidden="true" />
                        <div>
                            <p class="pd-eyebrow" lang="en" dir="ltr">REVIEWS</p>
                            <h2 id="pd-reviews-title">تجربه‌های واقعی</h2>
                            <p v-if="comments.section.total" class="pd-section-note">
                                ثبت‌شده توسط {{ faNum(comments.section.total) }} خریدار گالری
                            </p>
                        </div>
                    </header>

                    <div class="pd-reviews-layout">
                        <aside class="pd-reviews-aside">
                            <CommentSummary
                                :average="comments.section.ratingSummary.average"
                                :count="comments.section.ratingSummary.count"
                                :distribution="comments.section.ratingSummary.distribution"
                            />
                            <div class="pd-reviews-cta">
                                <p class="pd-reviews-cta-title">تجربه‌ات را با این محصول ثبت کن</p>
                                <p class="pd-section-note">نگاه شما به خریدارانی که همین انتخاب را می‌سنجند کمک می‌کند.</p>
                                <button type="button" class="pd-button pd-button--block" @click="comments.openComposer()">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true" />
                                    ثبت تجربه خرید
                                </button>
                            </div>
                        </aside>

                        <CommentList :section="comments.section" kind="product" />
                    </div>
                </section>

                <!-- Related -->
                <section v-if="related.length || relatedLoading" class="pd-section pd-related" aria-labelledby="pd-related-title" :aria-busy="relatedLoading">
                    <header class="pd-section-head">
                        <span class="pd-section-rule" aria-hidden="true" />
                        <div>
                            <p class="pd-eyebrow" lang="en" dir="ltr">MORE TO EXPLORE</p>
                            <h2 id="pd-related-title">شاید بپسندید</h2>
                        </div>
                    </header>
                    <div v-if="relatedLoading" class="pd-related-grid" role="status" aria-label="در حال دریافت محصولات مشابه">
                        <div v-for="n in 4" :key="n" class="pd-card-skeleton" aria-hidden="true"><span class="sf-skeleton pd-card-skeleton-media" /><span class="sf-skeleton pd-card-skeleton-line" /></div>
                    </div>
                    <div v-else class="pd-related-grid">
                        <ProductCard v-for="item in related" :key="item.id" :product="item" />
                    </div>
                </section>
            </div>
        </main>

        <!-- Sticky purchase action (mobile) -->
        <div v-if="product && !result.loading && !result.error && !result.notFound" class="pd-sticky">
            <span class="pd-sticky-price">{{ formatPrice(displayPrice) }} <small>تومان</small></span>
            <button
                type="button"
                class="pd-sticky-cta"
                :disabled="!displayInStock || needsVariantSelection"
                @click="add"
            >
                {{ addLabel }}
            </button>
        </div>

        <!-- Confirmation / validation message -->
        <transition name="pd-fade">
            <div
                v-if="notice.message"
                class="pd-toast"
                :class="notice.type === 'error' ? 'pd-toast--error' : 'pd-toast--success'"
                :role="notice.type === 'error' ? 'alert' : 'status'"
            >
                <i :class="notice.type === 'error' ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-check'" aria-hidden="true" />
                <span>{{ notice.message }}</span>
                <button type="button" class="pd-toast-close" aria-label="بستن پیام" @click="clearNotice">×</button>
            </div>
        </transition>

        <CommentComposer v-model="comments.section.composerOpen" kind="product" :section="comments.section" />

        <SiteFooter />
    </div>
</template>
