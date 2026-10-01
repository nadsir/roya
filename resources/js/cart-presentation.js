// Cart presentation helpers shared by the cart page and the cart drawer.
// Every value is read from the existing cart state; nothing here stores or invents data.
import { axisLabel } from './product-presentation.js';

// cart-state clamps quantities with `item.stock || 999`; mirror it so the UI never
// promises a quantity the cart would refuse to store.
export const CART_FALLBACK_STOCK = 999;

export function cartItemMaxQuantity(item) {
    const stock = Math.floor(Number(item?.stock));
    return Number.isFinite(stock) && stock > 0 ? stock : CART_FALLBACK_STOCK;
}

export function cartItemQuantity(item) {
    const quantity = Math.floor(Number(item?.quantity));
    return Number.isFinite(quantity) && quantity > 0 ? quantity : 1;
}

export function cartItemUnitPrice(item) {
    const price = Number(item?.price);
    return Number.isFinite(price) && price > 0 ? price : 0;
}

export function cartItemLineTotal(item) {
    return cartItemUnitPrice(item) * cartItemQuantity(item);
}

// Rendered only when a cart item actually carries a compare price; the current
// cart data has no compare price, so no discount is invented.
export function cartItemComparePrice(item) {
    const compare = Number(item?.compare_at_price);
    return Number.isFinite(compare) && compare > cartItemUnitPrice(item) ? compare : 0;
}

export function cartItemDiscountPercent(item) {
    const compare = cartItemComparePrice(item);
    return compare > 0 ? Math.round((1 - cartItemUnitPrice(item) / compare) * 100) : 0;
}

export function cartItemReachedMax(item) {
    return cartItemQuantity(item) >= cartItemMaxQuantity(item);
}

function cartAttributeText(value) {
    return [].concat(value ?? [])
        .map((entry) => (entry && typeof entry === 'object' ? entry.label || entry.value : entry))
        .map((entry) => String(entry ?? '').trim())
        .filter(Boolean)
        .join('، ');
}

export function cartItemAttributes(item) {
    const attributes = item?.attributes;
    if (!attributes || typeof attributes !== 'object' || Array.isArray(attributes)) return [];
    return Object.entries(attributes)
        .map(([slug, value]) => ({ slug, label: axisLabel(slug), value: cartAttributeText(value) }))
        .filter((entry) => entry.value);
}

export function cartItemMonogram(item) {
    const name = String(item?.name || '').trim();
    return name ? name.charAt(0) : '؟';
}
