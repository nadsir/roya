// Run: node --experimental-vm-modules tests/frontend/cart-notification.test.mjs
// Verifies the add-to-cart success notification: state behaviour, integration
// points, component markup, and design-system scoping. No server mutations.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { setTimeout as sleep } from 'node:timers/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { renderToString } from '@vue/server-renderer';
import { parse, compileScript } from '@vue/compiler-sfc';
import * as Vue from 'vue';
import { cartNotification, showCartAdded, hideCartNotification } from '../../resources/js/cart-notification-state.js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

async function readSource(path) {
    return readFile(resolve(root, path), 'utf8');
}

/* Compile an SFC with an inlined template and evaluate it outside a bundler. */
async function compileComponent(path) {
    const { descriptor } = parse(await readSource(path), { filename: path });
    let code = compileScript(descriptor, { id: 'cart-notification-test', inlineTemplate: true }).content;
    const flipAliases = (names) => names.split(',').map((pair) => {
        const [orig, alias] = pair.trim().split(/\s+as\s+/);
        return alias ? `${orig.trim()}: ${alias.trim()}` : orig.trim();
    }).join(', ');
    code = code.replace(/import \{([^}]*)\} from ['"]vue['"];?/, (_m, names) => `const { ${flipAliases(names)} } = Vue;`);
    code = code.replace(/import \{([^}]*)\} from ['"][^'"]*cart-notification-state\.js['"];?/, (_m, names) => `const { ${flipAliases(names)} } = __state;`);
    code = code.replace(/export default \{/, 'return {');
    const factory = new Function('Vue', '__state', code);
    return factory(Vue, { cartNotification, hideCartNotification });
}

const results = [];
async function check(name, fn) {
    try {
        await fn();
        results.push(['PASS', name]);
    } catch (error) {
        results.push(['FAIL', name, error.message]);
    }
}

/* ------------------------------ state module ------------------------------ */

await check('state module: showCartAdded reveals toast with product content', () => {
    showCartAdded({ name: 'کت بلند زنانه', image: '/images/product-1.webp' });
    assert.equal(cartNotification.visible, true);
    assert.equal(cartNotification.name, 'کت بلند زنانه');
    assert.equal(cartNotification.image, '/images/product-1.webp');
});

await check('state module: hideCartNotification hides the toast', () => {
    hideCartNotification();
    assert.equal(cartNotification.visible, false);
});

await check('state module: auto-dismiss is 3.5s (3-4s range)', async () => {
    const source = await readSource('resources/js/cart-notification-state.js');
    assert.match(source, /AUTO_DISMISS_MS\s*=\s*3500/);
});

await check('state module: re-adding restarts a single toast (no stacking)', () => {
    showCartAdded({ name: 'کیف چرم', image: null });
    showCartAdded({ name: 'کفش پاشنه‌دار', image: '/images/product-2.webp' });
    assert.equal(cartNotification.visible, true);
    assert.equal(cartNotification.name, 'کفش پاشنه‌دار');
    assert.equal(cartNotification.image, '/images/product-2.webp');
    hideCartNotification();
});

await check('state module: stays visible until hidden or timeout', async () => {
    showCartAdded({ name: 'تست', image: null });
    await sleep(120);
    assert.equal(cartNotification.visible, true, 'still visible before timeout');
    hideCartNotification();
});

/* --------------------------- integration points --------------------------- */

await check('ProductCard: shows notification only after successful addToCart', async () => {
    const source = await readSource('resources/js/components/ProductCard.vue');
    assert.match(source, /import \{ showCartAdded \} from '\.\.\/cart-notification-state\.js'/);
    const quickAdd = source.match(/function quickAdd\(\) \{[\s\S]*?\n\}/);
    assert.ok(quickAdd, 'quickAdd found');
    const addToCartAt = quickAdd[0].indexOf('addToCart(item)');
    const showAt = quickAdd[0].indexOf('showCartAdded(item)');
    assert.ok(addToCartAt !== -1 && showAt !== -1, 'both calls present');
    assert.ok(showAt > addToCartAt, 'notification fires after cart update');
});

await check('product-detail-state: shows notification only after successful addToCart', async () => {
    const source = await readSource('resources/js/product-detail-state.js');
    assert.match(source, /import \{ showCartAdded \} from '\.\/cart-notification-state\.js'/);
    const add = source.match(/function add\(\) \{[\s\S]*?\n    \}/);
    assert.ok(add, 'add() found');
    const addToCartAt = add[0].indexOf('addToCart({');
    const showAt = add[0].indexOf('showCartAdded({');
    assert.ok(addToCartAt !== -1 && showAt !== -1, 'both calls present');
    assert.ok(showAt > addToCartAt, 'notification fires after cart update');
    assert.ok(add[0].includes('return false'), 'failure paths still return false');
});

await check('SiteHeader: mounts CartNotification and opens the existing cart drawer', async () => {
    const source = await readSource('resources/js/SiteHeader.vue');
    assert.match(source, /import CartNotification from '\.\/components\/CartNotification\.vue'/);
    assert.match(source, /<CartNotification @view-cart="viewCart" \/>/);
    assert.match(source, /function viewCart\(\) \{\s*cartOpen = true;\s*hideCartNotification\(\);\s*\}/);
});

/* ------------------------------- component -------------------------------- */

const CartNotification = await compileComponent('resources/js/components/CartNotification.vue');

await check('component: renders status role, message, product, action and close', async () => {
    showCartAdded({ name: 'کت بلند زنانه', image: '/images/product-1.webp' });
    const html = await renderToString(Vue.h(CartNotification));
    assert.match(html, /role="status"/);
    assert.match(html, /محصول به سبد خرید اضافه شد/);
    assert.match(html, /کت بلند زنانه/);
    assert.match(html, /مشاهده سبد خرید/);
    assert.match(html, /aria-label="بستن اعلان"/);
    assert.match(html, /src="\/images\/product-1\.webp"/);
    hideCartNotification();
});

await check('component: placeholder monogram when product has no image', async () => {
    showCartAdded({ name: 'محصول بدون تصویر', image: null });
    const html = await renderToString(Vue.h(CartNotification));
    assert.match(html, /sf-cart-toast-ph/);
    assert.doesNotMatch(html, /<img/);
    hideCartNotification();
});

await check('component: view-cart action hides toast and notifies parent', async () => {
    showCartAdded({ name: 'کیف چرم', image: null });
    let emitted = false;
    const html = await renderToString(Vue.h(CartNotification, { 'onView-cart': () => { emitted = true; } }));
    assert.match(html, /مشاهده سبد خرید/);
    assert.equal(emitted, false, 'no emit before interaction');
    hideCartNotification();
});

/* --------------------------------- styles --------------------------------- */

await check('styles: toast is scoped, positioned below header, responsive', async () => {
    const source = await readSource('resources/css/storefront.css');
    assert.match(source, /\.storefront-theme \.sf-cart-toast-wrap \{[^}]*position: fixed;[^}]*top: 108px;[^}]*z-index: 70;/s);
    assert.match(source, /\.storefront-theme \.sf-cart-toast \{[^}]*width: min\(420px, calc\(100% - 32px\)\);/);
    assert.ok(source.includes('.sf-cart-toast-wrap { top: 84px; }'), 'mobile top offset below 68px header');
    assert.match(source, /@keyframes sf-toast-icon-pop/);
    assert.match(source, /@keyframes sf-toast-image-in/);
    assert.match(source, /\.sf-cart-toast-enter-active \{[^}]*300ms/);
    assert.match(source, /\.sf-cart-toast-leave-active \{[^}]*220ms/);
});

/* --------------------------------- report ---------------------------------- */

for (const [status, name, error] of results) {
    console.log(`${status} ${name}${error ? ' — ' + error : ''}`);
}
const failed = results.filter(([status]) => status === 'FAIL');
console.log(`${results.length - failed.length}/${results.length} verification groups passed.`);
process.exit(failed.length ? 1 : 0);
