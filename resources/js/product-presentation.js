// Shared presentation helpers; all catalog names, prices and images come from API data.
// Canonical product URLs use the semantic slug-based structure.
export function productHref(product) {
    if (product?.slug) return `/product/${encodeURIComponent(product.slug)}`;
    // Compatibility for cached/legacy payloads that predate the slug field.
    return product?.id ? `/products/${encodeURIComponent(product.id)}` : '/store';
}
export function imageUrl(path) {
    if (!path) return '';
    return /^(https?:\/\/|\/)/i.test(path) ? path : `/storage/${path}`;
}
export function productImages(product) {
    return [...(product?.images || [])].filter((image) => image.path)
        .sort((a, b) => Number(Boolean(b.is_primary)) - Number(Boolean(a.is_primary)) || (a.sort_order ?? 0) - (b.sort_order ?? 0))
        .filter((image, index, all) => all.findIndex((entry) => entry.path === image.path) === index);
}
export function formatPrice(price) { return Number(price || 0).toLocaleString('fa-IR'); }
export function discountPercent(product) {
    const price = Number(product?.price);
    const original = Number(product?.compare_at_price);
    return original > price && price >= 0 ? Math.round((1 - price / original) * 100) : 0;
}
export function productColors(product) {
    const values = [...Object.values(product?.attributes || {}).flat(),
        ...(product?.variants || []).flatMap((variant) => Object.values(variant.attributes || {}).flat())];
    return values.filter((value) => /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(value?.hex_color || ''))
        .filter((value, index, all) => all.findIndex((entry) => entry.hex_color.toLowerCase() === value.hex_color.toLowerCase()) === index);
}
export function hasProductVariants(product) { return Boolean(product.has_variants || product.variants?.length); }

const AXIS_LABELS = {
    size: 'سایز',
    color: 'رنگ',
    colour: 'رنگ',
    material: 'جنس',
    style: 'استایل',
    fit: 'فرم',
    length: 'قد',
    brand: 'برند',
};

// Persian labels for the product specification table. Keys are normalised to
// lower-case kebab-case so both `country_of_origin` and `country-of-origin`
// resolve to the same row.
const SPEC_LABELS = {
    brand: 'برند',
    material: 'جنس / متریال',
    'country-of-origin': 'کشور سازنده',
    country: 'کشور سازنده',
    origin: 'کشور سازنده',
    'made-in': 'کشور سازنده',
    size: 'سایزبندی',
    'size-type': 'نوع سایز',
    'shoe-size': 'سایز کفش',
    season: 'فصل',
    'care-instructions': 'دستورالعمل نگهداری',
    'washing-instructions': 'دستورالعمل نگهداری',
    color: 'رنگ',
    colour: 'رنگ',
    fit: 'فرم / تن‌خور',
    style: 'استایل',
    pattern: 'طرح',
    'bag-type': 'نوع کیف',
    'shoe-type': 'نوع کفش',
    'frame-material': 'جنس فریم',
    'frame-shape': 'شکل فریم',
    'sleeve-length': 'آستین',
    length: 'قد',
    closure: 'نوع بسته‌شدن',
    lining: 'آستر',
    weight: 'وزن',
    'warranty-months': 'گارانتی',
    'part-condition': 'وضعیت قطعه',
    'installation-side': 'محل نصب',
    'axle-position': 'محور',
    'vehicle-type': 'نوع خودرو',
    'fuel-type': 'نوع سوخت',
    voltage: 'ولتاژ',
    amperage: 'آمپراژ',
    standard: 'استاندارد',
    'part-number': 'شماره فنی',
    'oem-number': 'شماره OEM',
    'engine-code': 'کد موتور',
};

// The storefront is a women's fashion gallery. Legacy automotive rows are
// filtered out so an old record can never leak "محور" or "ولتاژ" into the
// clothing specification table.
const LEGACY_AUTOMOTIVE_SPECS = new Set([
    'part-condition',
    'installation-side',
    'axle-position',
    'vehicle-type',
    'fuel-type',
    'voltage',
    'amperage',
    'standard',
    'part-number',
    'oem-number',
    'engine-code',
    'warranty-months',
]);

function normalizeSpecKey(slug) {
    return String(slug || '').toLowerCase().replace(/_/g, '-').trim();
}

export function specificationLabel(slug, fallback = '') {
    const key = normalizeSpecKey(slug);
    return SPEC_LABELS[key] || fallback || key;
}

export function isLegacyAutomotiveSpec(slug) {
    return LEGACY_AUTOMOTIVE_SPECS.has(normalizeSpecKey(slug));
}

// Shared by the product detail page and the cart so a variant axis reads the same everywhere.
export function axisLabel(slug) {
    const key = String(slug || '').toLowerCase();
    return AXIS_LABELS[key] || String(slug || '');
}

export function cartItemFromProduct(product) {
    if (hasProductVariants(product) || product.in_stock !== true) return null;
    return {
        key: `product_${product.id}`, product_id: product.id,
        product_slug: product.slug || null, variant_id: null,
        name: product.name, price: Number(product.price), quantity: 1,
        image: imageUrl(productImages(product)[0]?.path) || null,
        attributes: null, sku: product.sku || null,
        // The list API provides in_stock, not a numeric stock count.
        // Checkout remains authoritative; do not invent a stock count in the UI.
        stock: product.stock ?? undefined,
    };
}
