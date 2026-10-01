// One shared image-fallback chain for the whole storefront, so no component ever
// hardcodes a fallback URL of its own.
//
//   product image (API / local storage)  ->  TEMPORARY external fallback  ->  /images/placeholder.svg  ->  neutral monogram
//
// Every `@error` handler in the storefront asks `nextImageSrc()` what to try next.
// The handler that started on the real product image is untouched, so a working
// product image is always preserved and the fallback is only ever a reaction to a
// load error.

// TEMPORARY: external image fallback until production product-image delivery is fixed.
// To remove the whole workaround, empty this array. `nextImageSrc` then returns
// /images/placeholder.svg on the first failure, which is exactly the previous
// behaviour, and no component needs to be touched.
export const TEMPORARY_EXTERNAL_FALLBACKS = [
    'https://placehold.co/600x750/f7f6f2/1a1a1a.png?text=G.',
    'https://placehold.co/600x750/efece4/6b6a66.png?text=Edit',
    'https://placehold.co/600x750/f2e9dd/8a6a4a.png?text=Atelier',
    'https://placehold.co/600x750/e9e9e6/4a4a48.png?text=Look',
    'https://placehold.co/600x750/f5f1ea/9a8a70.png?text=Style',
    'https://placehold.co/600x750/eceff1/455a64.png?text=G.',
];

// The existing, already-shipped neutral placeholder. Nothing about it changes.
export const PLACEHOLDER_SRC = '/images/placeholder.svg';

/* Deterministic pick: the same product (or cart line, or order line) always maps to
   the same fallback, so re-renders and re-visits never reshuffle the imagery. */
function seedHash(seed) {
    const text = String(seed ?? '');
    let hash = 0;
    for (let index = 0; index < text.length; index += 1) {
        hash = (hash * 31 + text.charCodeAt(index)) | 0;
    }
    return Math.abs(hash);
}

export function externalFallbackSrc(seed) {
    if (!TEMPORARY_EXTERNAL_FALLBACKS.length) return '';
    return TEMPORARY_EXTERNAL_FALLBACKS[seedHash(seed) % TEMPORARY_EXTERNAL_FALLBACKS.length];
}

/* `currentSrc` is what the browser is showing right now, so it reveals which step of
   the chain failed and one image can never re-enter the handler. Returns '' once the
   chain is exhausted, which is the caller's signal to render its neutral monogram. */
export function nextImageSrc(currentSrc, seed) {
    const current = String(currentSrc || '');
    if (current.includes(PLACEHOLDER_SRC)) return '';

    const external = externalFallbackSrc(seed);
    if (!external) return PLACEHOLDER_SRC;
    if (current.includes(external)) return PLACEHOLDER_SRC;

    return external;
}
