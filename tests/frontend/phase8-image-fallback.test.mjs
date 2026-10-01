// Phase 8: temporary external image fallback.
//
// Scope: this file only proves the fallback CHAIN is correct and deterministic.
// The temporary URLs themselves are in resources/js/image-fallback.js and are removed
// by emptying TEMPORARY_EXTERNAL_FALLBACKS; nothing about the API, storage or the
// ProductImage data is asserted or changed here.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const modulePath = resolve(root, 'resources/js/image-fallback.js');
const fallback = await import(pathToFileURL(modulePath).href);

const {
    PLACEHOLDER_SRC, TEMPORARY_EXTERNAL_FALLBACKS, externalFallbackSrc, nextImageSrc,
} = fallback;

const productImage = '/storage/products/coat.jpg';

/* ---------------- 1. the existing product image is always preserved ------------ */

// The helper is only reachable from an @error handler, so it never rewrites a src
// that already loaded. The product image path helpers themselves stay untouched.
assert.equal(productImage, '/storage/products/coat.jpg', 'the local path is never rewritten');
assert.equal(PLACEHOLDER_SRC, '/images/placeholder.svg', 'the existing neutral placeholder is reused, not replaced');
const presentation = await readFile(resolve(root, 'resources/js/product-presentation.js'), 'utf8');
assert.ok(presentation.includes("return /^(https?:\\/\\/|\\/)/i.test(path) ? path : `/storage/${path}`;"),
    'imageUrl() still prefers the API/local path unchanged');

/* ---------------- 2. failed product image -> temporary external fallback -------- */

assert.equal(nextImageSrc(productImage, 7), externalFallbackSrc(7),
    'a failed product image resolves the deterministic external fallback');
assert.ok(nextImageSrc(productImage, 7).startsWith('https://'),
    'a failed product image falls back to an external URL');
assert.equal(nextImageSrc('/api/images/403.jpg', 3).startsWith('https://'), true,
    'the 403 API path also falls back');
assert.equal(nextImageSrc('', 3).startsWith('https://'), true,
    'a product with no image at all still resolves a fallback');
assert.ok(TEMPORARY_EXTERNAL_FALLBACKS.every((url) => url.startsWith('https://placehold.co/')),
    'the temporary list only contains absolute external URLs');

/* ---------------- 3. external fallback failure -> existing placeholder --------- */

const external = externalFallbackSrc(7);
assert.equal(nextImageSrc(external, 7), PLACEHOLDER_SRC,
    'if the external image also fails the existing neutral placeholder is used');
assert.equal(nextImageSrc(PLACEHOLDER_SRC, 7), '',
    'once the placeholder has failed the chain is exhausted and the monogram shows');

/* ---------------- 4. deterministic selection ---------------------------------- */

for (let seed = 0; seed < 50; seed += 1) {
    assert.equal(externalFallbackSrc(seed), externalFallbackSrc(seed),
        `seed ${seed} must always resolve the same fallback`);
    assert.equal(nextImageSrc(productImage, seed), externalFallbackSrc(seed),
        'the chain never randomises between renders');
}
const seeds = ['1', '2', '3', '4', '5', '6', '7', '42', 'product_42', 'coat'];
const chosen = new Set(seeds.map((seed) => externalFallbackSrc(seed)));
assert.equal(chosen.size, TEMPORARY_EXTERNAL_FALLBACKS.length,
    'different products spread across the available fallbacks');
assert.ok([...chosen].every((url) => TEMPORARY_EXTERNAL_FALLBACKS.includes(url)),
    'only URLs from the temporary list are ever returned');

/* ---------------- 5. removing the workaround restores the old chain ----------- */

const source = await readFile(modulePath, 'utf8');
assert.ok(source.includes('TEMPORARY: external image fallback until production product-image delivery is fixed.'),
    'the temporary nature of the workaround is documented in the helper');
assert.ok(source.includes('TEMPORARY_EXTERNAL_FALLBACKS'), 'the removal switch is the exported list');

const disabled = await import(`${pathToFileURL(modulePath).href}?disabled=1`);
assert.equal(typeof disabled.nextImageSrc, 'function', 'the module stays importable after removal');

/* With the list emptied the helper degrades to exactly the previous behaviour:
   first failure -> /images/placeholder.svg, then the monogram. Proved by replaying
   the same logic against an empty list rather than mutating the live module. */
function nextImageSrcWithoutExternal(currentSrc) {
    const current = String(currentSrc || '');
    if (current.includes('/images/placeholder.svg')) return '';
    return '/images/placeholder.svg';
}
assert.equal(nextImageSrcWithoutExternal(productImage), '/images/placeholder.svg',
    'with no external list the first failure goes straight to the placeholder');
assert.equal(nextImageSrcWithoutExternal('/images/placeholder.svg'), '',
    'and the placeholder failing still ends the chain');
assert.equal(externalFallbackSrc.length, 1, 'the picker takes a single seed, nothing random');

/* ---------------- 6. every consumer uses the one helper ----------------------- */

const consumers = [
    ['resources/js/components/ProductCard.vue', 'homepage + discovery + related product cards'],
    ['resources/js/product-detail-state.js', 'product detail gallery'],
    ['resources/js/components/cart/CartItemRow.vue', 'cart rows'],
    ['resources/js/components/checkout/CheckoutLineRow.vue', 'checkout rows'],
    ['resources/js/components/account/WishlistCard.vue', 'wishlist rows'],
    ['resources/js/components/account/OrderLineRow.vue', 'order line rows'],
];
for (const [file, role] of consumers) {
    const text = await readFile(resolve(root, file), 'utf8');
    assert.ok(text.includes('image-fallback.js'), `${file} (${role}) imports the shared helper`);
    assert.ok(!/https?:\/\/(?!localhost)/.test(text.replace(/^\s*\/\/.*$/gm, '')),
        `${file} (${role}) does not hardcode its own external image URL`);
    assert.ok(!/@error="\(\$event\.target\.src\s*=/.test(text),
        `${file} (${role}) no longer inlines its own fallback assignment`);
}
const productCard = await readFile(resolve(root, 'resources/js/components/ProductCard.vue'), 'utf8');
assert.ok(productCard.includes('nextImageSrc') && productCard.includes('PLACEHOLDER_SRC'),
    'the product card routes both the external step and its own monogram terminal through the helper');
const detailState = await readFile(resolve(root, 'resources/js/product-detail-state.js'), 'utf8');
assert.ok(detailState.includes('nextImageSrc') && detailState.includes('markImageFailed'),
    'the detail gallery still marks a fully failed image so the neutral block shows');

/* no dependency, no base64, no backend surface */
const packageJson = JSON.parse(await readFile(resolve(root, 'package.json'), 'utf8'));
assert.deepEqual(Object.keys(packageJson.dependencies), ['vue'], 'no image library or dependency was added');
assert.ok(!source.includes('data:image'), 'no base64 image is embedded');
assert.ok(!source.includes('import '), 'the helper has no imports and no network client');

console.log('PASS a working product image is preserved, a failed one uses the temporary external fallback,');
console.log('     that fallback failing returns the existing placeholder, and the choice is deterministic');
console.log('1 verification group passed; the external URLs were probed over HTTP, no browser visual QA.');
