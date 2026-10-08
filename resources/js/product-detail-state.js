// Product detail page state, extracted from ProductDetail.vue so the behaviour can be
// verified directly (same pattern as discovery-state.js). No new global store, no new API.
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import { addToCart } from './cart-state.js';
import { showCartAdded } from './cart-notification-state.js';
import { imageUrl, productImages, axisLabel } from './product-presentation.js';
import { PLACEHOLDER_SRC, nextImageSrc } from './image-fallback.js';

export { axisLabel };

export function productIdFromPath(pathname) {
    const match = String(pathname || '').match(/\/products\/(\d+)/);
    return match ? Number(match[1]) : null;
}

export function createProductDetailState(browser) {
    const env = browser || (typeof window !== 'undefined' ? window : { location: { pathname: '' } });

    const product = ref(null);
    const result = reactive({ loading: true, error: '', notFound: false });
    const notice = reactive({ message: '', type: 'success' });
    const quantity = ref(1);
    const activeIndex = ref(0);
    const failedPaths = ref([]);
    const selectedVariants = reactive({});
    const related = ref([]);
    const relatedLoading = ref(false);

    const controllers = { product: null, related: null };
    const versions = { product: 0, related: 0 };
    let active = true;
    let noticeTimer = null;

    function isCurrent(kind, version, controller) {
        return active && versions[kind] === version && !controller.signal.aborted;
    }

    function clearNotice() {
        if (noticeTimer) {
            clearTimeout(noticeTimer);
            noticeTimer = null;
        }
        notice.message = '';
    }

    function showNotice(message, type = 'success') {
        notice.message = message;
        notice.type = type;
        if (noticeTimer) clearTimeout(noticeTimer);
        noticeTimer = setTimeout(clearNotice, 5000);
    }

    function resetSelection() {
        for (const key of Object.keys(selectedVariants)) delete selectedVariants[key];
        activeIndex.value = 0;
        failedPaths.value = [];
        quantity.value = 1;
        related.value = [];
        relatedLoading.value = false;
        clearNotice();
    }

    async function loadRelated() {
        const owner = product.value;
        const slug = owner?.categories?.[0]?.slug;
        related.value = [];
        if (!slug) {
            relatedLoading.value = false;
            return;
        }
        if (controllers.related) controllers.related.abort();
        const controller = new AbortController();
        controllers.related = controller;
        const version = ++versions.related;
        relatedLoading.value = true;
        try {
            // Reuses the existing filtered-products endpoint; no recommendation backend.
            const { data } = await axios.get('/api/products', {
                params: { category: slug, per_page: 9 },
                signal: controller.signal,
            });
            if (!isCurrent('related', version, controller)) return;
            related.value = (data?.data || []).filter((item) => item && item.id !== owner.id);
        } catch {
            if (!isCurrent('related', version, controller)) return;
            related.value = [];
        } finally {
            if (isCurrent('related', version, controller)) relatedLoading.value = false;
        }
    }

    async function load() {
        const id = productIdFromPath(env.location?.pathname);
        if (!id) {
            product.value = null;
            related.value = [];
            result.loading = false;
            result.error = '';
            result.notFound = true;
            return;
        }

        if (controllers.product) controllers.product.abort();
        const controller = new AbortController();
        controllers.product = controller;
        const version = ++versions.product;

        result.loading = true;
        result.error = '';
        result.notFound = false;

        try {
            const { data } = await axios.get(`/api/products/${id}`, { signal: controller.signal });
            if (!isCurrent('product', version, controller)) return;
            product.value = data?.data || data || null;
            resetSelection();
            if (!product.value) {
                result.notFound = true;
                result.loading = false;
                return;
            }
            result.loading = false;
            await loadRelated();
        } catch (error) {
            if (!isCurrent('product', version, controller)) return;
            if (error?.response?.status === 404) {
                product.value = null;
                result.notFound = true;
            } else {
                result.error = 'خطا در دریافت اطلاعات محصول. لطفاً دوباره تلاش کنید.';
            }
            result.loading = false;
        }
    }

    function dispose() {
        active = false;
        if (noticeTimer) {
            clearTimeout(noticeTimer);
            noticeTimer = null;
        }
        Object.values(controllers).forEach((controller) => controller?.abort());
    }

    /* ---------------------------------- gallery --------------------------------- */

    const images = computed(() => productImages(product.value));
    const activeImage = computed(() => images.value[activeIndex.value] || images.value[0] || null);
    const activeImageFailed = computed(() => {
        const image = activeImage.value;
        return Boolean(image?.path && failedPaths.value.includes(image.path));
    });
    const activeImageSrc = computed(() => {
        const image = activeImage.value;
        if (!image?.path || failedPaths.value.includes(image.path)) return '';
        return imageUrl(image.path);
    });

    function thumbSrc(image) {
        if (!image?.path || failedPaths.value.includes(image.path)) return '';
        return imageUrl(image.path);
    }

    function markImageFailed(path) {
        if (path && !failedPaths.value.includes(path)) failedPaths.value.push(path);
    }

    // Shared fallback chain: product image -> temporary external fallback ->
    // /images/placeholder.svg -> neutral block. `currentSrc` tells which step failed,
    // so one image can never loop the handler.
    function imageErrorSrc(path, currentSrc) {
        if (!path) return '';
        if (String(currentSrc || '').includes(PLACEHOLDER_SRC)) {
            markImageFailed(path);
            return '';
        }
        return nextImageSrc(currentSrc, product.value?.id ?? path);
    }

    function selectImage(index) {
        if (index >= 0 && index < images.value.length) activeIndex.value = index;
    }
    function nextImage() {
        if (images.value.length > 1) activeIndex.value = (activeIndex.value + 1) % images.value.length;
    }
    function prevImage() {
        if (images.value.length > 1) activeIndex.value = (activeIndex.value - 1 + images.value.length) % images.value.length;
    }

    /* --------------------------------- variants --------------------------------- */

    const attributeAxes = computed(() => {
        const variants = product.value?.variants || [];
        if (!variants.length) return [];
        const axisMap = new Map();
        for (const variant of variants) {
            for (const [slug, values] of Object.entries(variant.attributes || {})) {
                if (!axisMap.has(slug)) axisMap.set(slug, { slug, label: axisLabel(slug), values: [] });
                const axis = axisMap.get(slug);
                for (const value of values) {
                    if (!axis.values.some((entry) => entry.id === value.id)) axis.values.push(value);
                }
            }
        }
        return Array.from(axisMap.values());
    });

    const hasVariants = computed(() => Boolean(product.value?.variants?.length));

    function buildVariantLookup() {
        const lookup = new Map();
        for (const variant of product.value?.variants || []) {
            if (!variant.is_active) continue;
            const keys = [];
            for (const [slug, values] of Object.entries(variant.attributes || {})) {
                for (const value of values) keys.push(`${slug}:${value.id}`);
            }
            keys.sort();
            lookup.set(keys.join('|'), variant);
        }
        return lookup;
    }

    const matchedVariant = computed(() => {
        if (!hasVariants.value) return null;
        const keys = [];
        for (const [slug, valueId] of Object.entries(selectedVariants)) {
            if (valueId) keys.push(`${slug}:${valueId}`);
        }
        if (!keys.length) return null;
        keys.sort();
        return buildVariantLookup().get(keys.join('|')) || null;
    });

    // A value stays selectable while some active variant still combines with the other axes.
    function isValueSelectable(axisSlug, valueId) {
        if (!hasVariants.value) return true;
        for (const variant of product.value?.variants || []) {
            if (!variant.is_active) continue;
            let matches = true;
            for (const [slug, selectedId] of Object.entries(selectedVariants)) {
                if (slug === axisSlug || !selectedId) continue;
                const variantValues = variant.attributes?.[slug];
                if (!variantValues?.some((value) => String(value.id) === String(selectedId))) {
                    matches = false;
                    break;
                }
            }
            if (!matches) continue;
            if (variant.attributes?.[axisSlug]?.some((value) => String(value.id) === String(valueId))) return true;
        }
        return false;
    }

    function isSelected(axis, value) {
        return String(selectedVariants[axis.slug]) === String(value.id);
    }

    function selectedLabel(axis) {
        const id = selectedVariants[axis.slug];
        const value = axis.values.find((entry) => String(entry.id) === String(id));
        return value?.label || '';
    }

    function toggleAxisValue(axisSlug, valueId) {
        if (!isValueSelectable(axisSlug, valueId)) return false;
        const key = String(valueId);
        if (selectedVariants[axisSlug] === key) delete selectedVariants[axisSlug];
        else selectedVariants[axisSlug] = key;
        activeIndex.value = 0;
        clampQuantity();
        return true;
    }

    /* --------------------------------- display ---------------------------------- */

    const displayPrice = computed(() => matchedVariant.value?.price ?? product.value?.price ?? 0);
    const displayCompareAtPrice = computed(() => matchedVariant.value?.compare_at_price ?? product.value?.compare_at_price ?? null);
    const displayDiscount = computed(() => {
        const price = Number(displayPrice.value);
        const compare = Number(displayCompareAtPrice.value);
        return compare > price && price >= 0 ? Math.round((1 - price / compare) * 100) : 0;
    });
    const displaySku = computed(() => matchedVariant.value?.sku || product.value?.sku || '');
    const displayInStock = computed(() => (matchedVariant.value ? matchedVariant.value.stock > 0 : product.value?.in_stock ?? false));
    const displayStock = computed(() => matchedVariant.value?.stock ?? null);

    // A variant product is purchasable only once a real variant is resolved. Choosing
    // every axis is not enough when that combination does not exist: without this the
    // button would enable and `add` would file the item at the plain product price.
    const needsVariantSelection = computed(() => {
        if (!hasVariants.value || !attributeAxes.value.length) return false;
        return !matchedVariant.value;
    });

    // Both purchase buttons read this one label, so the call to action always states
    // why it cannot run instead of staying a dead `disabled` button that says nothing.
    const addLabel = computed(() => {
        if (needsVariantSelection.value) return 'انتخاب گزینه‌ها';
        if (!displayInStock.value) return 'ناموجود';
        return 'افزودن به سبد خرید';
    });

    const missingAxes = computed(() => attributeAxes.value
        .filter((axis) => !selectedVariants[axis.slug])
        .map((axis) => axis.label));

    // One sentence for both the inline hint and the `add` guard, so the page always
    // says which option is missing instead of leaving a dead button unexplained.
    const addHint = computed(() => {
        if (needsVariantSelection.value) {
            return missingAxes.value.length
                ? `پیش از افزودن به سبد خرید، ${missingAxes.value.join(' و ')} را انتخاب کنید.`
                : 'ترکیب انتخاب‌شده موجود نیست؛ گزینه‌های دیگری را انتخاب کنید.';
        }
        if (!displayInStock.value) return 'این محصول در حال حاضر ناموجود است.';
        return '';
    });

    const displayAttributes = computed(() => {
        const attributes = product.value?.attributes;
        if (!attributes || typeof attributes !== 'object') return [];
        const axisSlugs = new Set(attributeAxes.value.map((axis) => axis.slug));
        return Object.entries(attributes)
            .filter(([slug]) => !axisSlugs.has(slug))
            .map(([slug, values]) => ({ slug, label: axisLabel(slug), values: Array.isArray(values) ? values : [] }));
    });

    const customAttributes = computed(() => product.value?.custom_attributes || []);
    const categories = computed(() => product.value?.categories || []);

    const brand = computed(() => {
        const current = product.value;
        if (!current) return '';
        const custom = (current.custom_attributes || []).find((attr) => /^(brand|برند)$/i.test(String(attr?.slug || '')) || /^(brand|برند)$/i.test(String(attr?.name || '')));
        if (custom?.value !== null && custom?.value !== undefined && custom?.value !== '') return String(custom.value);
        const entry = Object.entries(current.attributes || {}).find(([slug]) => /^brand$/i.test(String(slug)));
        if (entry) return entry[1].map((value) => value.label).join('، ');
        return '';
    });

    /* --------------------------------- quantity --------------------------------- */

    // Unknown stock (the detail API only exposes in_stock) falls back to the cart's 999 ceiling.
    const maxQuantity = computed(() => {
        const stock = displayStock.value;
        return Number.isFinite(stock) && stock > 0 ? stock : 999;
    });
    const canIncreaseQuantity = computed(() => quantity.value < maxQuantity.value);

    function setQuantity(value) {
        const next = Math.floor(Number(value));
        quantity.value = Math.min(Math.max(1, Number.isFinite(next) ? next : 1), maxQuantity.value);
    }
    function increaseQuantity() { setQuantity(quantity.value + 1); }
    function decreaseQuantity() { setQuantity(quantity.value - 1); }
    function clampQuantity() { setQuantity(quantity.value); }

    /* -------------------------------- add to cart -------------------------------- */

    function add() {
        const current = product.value;
        if (!current || result.loading) return false;
        if (addHint.value) {
            showNotice(addHint.value, 'error');
            return false;
        }

        const variant = matchedVariant.value;
        const image = images.value[0];

        // Reuses the existing cart module: same key scheme, same localStorage entry.
        addToCart({
            key: variant ? `variant_${variant.id}` : `product_${current.id}`,
            product_id: current.id,
            variant_id: variant?.id || null,
            name: current.name,
            price: Number(displayPrice.value),
            quantity: quantity.value,
            image: image?.path ? imageUrl(image.path) : null,
            attributes: variant?.attributes ? { ...variant.attributes } : null,
            sku: displaySku.value || null,
            stock: variant ? variant.stock : current.stock,
        });
        showCartAdded({ name: current.name, image: image?.path ? imageUrl(image.path) : null });

        showNotice(`${current.name} به سبد خرید اضافه شد.`, 'success');
        return true;
    }

    return {
        product,
        result,
        notice,
        quantity,
        activeIndex,
        failedPaths,
        selectedVariants,
        related,
        relatedLoading,
        images,
        activeImage,
        activeImageSrc,
        activeImageFailed,
        attributeAxes,
        hasVariants,
        matchedVariant,
        displayPrice,
        displayCompareAtPrice,
        displayDiscount,
        displaySku,
        displayInStock,
        displayStock,
        needsVariantSelection,
        missingAxes,
        addLabel,
        addHint,
        displayAttributes,
        customAttributes,
        categories,
        brand,
        maxQuantity,
        canIncreaseQuantity,
        load,
        dispose,
        add,
        showNotice,
        clearNotice,
        setQuantity,
        increaseQuantity,
        decreaseQuantity,
        thumbSrc,
        markImageFailed,
        imageErrorSrc,
        selectImage,
        nextImage,
        prevImage,
        isValueSelectable,
        isSelected,
        selectedLabel,
        toggleAxisValue,
    };
}
