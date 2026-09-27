// Shared presentation helpers; all catalog names, prices and images come from API data.
export function productHref(product) { return `/products/${product.id}`; }
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

export function cartItemFromProduct(product) {
    if (hasProductVariants(product) || product.in_stock !== true) return null;
    return {
        key: `product_${product.id}`, product_id: product.id, variant_id: null,
        name: product.name, price: Number(product.price), quantity: 1,
        image: imageUrl(productImages(product)[0]?.path) || null,
        attributes: null, sku: product.sku || null,
        // The list API provides in_stock, not a numeric stock count.
        // Checkout remains authoritative; do not invent a stock count in the UI.
        stock: product.stock ?? undefined,
    };
}
