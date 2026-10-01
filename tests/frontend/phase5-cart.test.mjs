// Run: node --experimental-vm-modules tests/frontend/phase5-cart.test.mjs
// Behaviour checks against the real cart modules and components; no server mutations.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { setTimeout as sleep } from 'node:timers/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SourceTextModule, SyntheticModule, createContext } from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import postcss from 'postcss';
import * as Vue from 'vue';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

/* --------------------------------- fakes --------------------------------- */
const storage = new Map();
const timers = new Map();
let timerId = 0;
const location = { pathname: '/cart', href: '', search: '' };
const document = {
    title: '',
    body: { style: {} },
    activeElement: null,
    addEventListener() {},
    removeEventListener() {},
    getElementById: () => null,
    querySelector: () => null,
    createElement: () => ({ setAttribute() {}, appendChild() {} }),
    head: { appendChild() {} },
    documentElement: { style: {} },
};
const window = {
    location,
    history: { length: 1, back() {} },
    scrollY: 0,
    addEventListener() {},
    removeEventListener() {},
    dispatchEvent() {},
};
const context = createContext({
    console, AbortController, URLSearchParams, location, document, window,
    localStorage: {
        getItem: (key) => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
        removeItem: (key) => storage.delete(key),
    },
    sessionStorage: { getItem: () => null, setItem() {}, removeItem() {} },
    matchMedia: () => ({ matches: false, addEventListener() {}, removeEventListener() {} }),
    requestAnimationFrame: () => 0,
    setTimeout: (fn) => { const id = ++timerId; timers.set(id, fn); return id; },
    clearTimeout: (id) => timers.delete(id),
});

/* -------------------------------- fixtures ------------------------------- */
const sizeM = { id: 2, label: 'M', value: 'm' };
const colorBlack = { id: 10, label: 'مشکی', value: 'black', hex_color: '#141414' };

const coatItem = {
    key: 'variant_91',
    product_id: 7,
    variant_id: 91,
    name: 'مانتو کتی پشمی',
    price: 850000,
    quantity: 2,
    image: '/storage/coats/coat.jpg',
    attributes: { size: [sizeM], color: [colorBlack] },
    sku: 'MNT-1-S-BLK',
    stock: 2,
};
const scarfItem = {
    key: 'product_8',
    product_id: 8,
    variant_id: null,
    name: 'روسری ابریشمی',
    price: 320000,
    quantity: 1,
    image: null,
    attributes: null,
    sku: 'SCF-4',
    stock: undefined,
};

/* -------------------------------- loader --------------------------------- */
const cache = new Map();
let freshId = 0;
function stub(name, exports) {
    return new SyntheticModule(Object.keys(exports), function () {
        for (const [key, value] of Object.entries(exports)) this.setExport(key, value);
    }, { context, identifier: name });
}
cache.set('vue', stub('vue', Vue));
cache.set('axios', stub('axios', { default: { get: () => new Promise(() => {}), post: () => new Promise(() => {}), defaults: { headers: { common: {} } } } }));
const auth = Vue.reactive({ user: null, loading: false });
cache.set(resolve(root, 'resources/js/auth-state.js'), stub('auth', {
    state: auth,
    isLoggedIn: Vue.computed(() => Boolean(auth.user)),
    logout: async () => {},
}));
cache.set(resolve(root, 'resources/js/wishlist-state.js'), stub('wishlist', {
    wishlistCount: Vue.computed(() => 0),
    loadWishlist: () => {},
    resetWishlist: () => {},
}));

async function load(id, expose = false, fresh = false) {
    const key = fresh ? `${id}:${++freshId}` : expose ? `${id}:expose` : id;
    if (cache.has(key)) return cache.get(key);
    if (id.endsWith('.css')) {
        const cssModule = stub(id, { default: {} });
        cache.set(key, cssModule);
        return cssModule;
    }
    let source = await readFile(id, 'utf8');
    if (id.endsWith('.vue')) {
        if (expose) source = source.replace('</script>', '\ndefineExpose({ increase, decrease });\n</script>');
        const { descriptor } = parse(source, { filename: id });
        if (expose) { descriptor.template = null; source = compileScript(descriptor, { id: 'phase5-expose' }).content; }
        else source = compileScript(descriptor, { id: 'phase5-test', inlineTemplate: true }).content;
    }
    const module = new SourceTextModule(source, { context, identifier: id });
    cache.set(key, module);
    await module.link((specifier, parent) => load(cache.has(specifier) ? specifier : resolve(dirname(parent.identifier), specifier)));
    return module;
}
async function namespace(path, expose = false) {
    const module = await load(resolve(root, path), expose);
    if (module.status !== 'evaluated') await module.evaluate();
    return module.namespace;
}
/* A second cart-state instance re-reads localStorage, which is how the
   corrupted-storage path is reached without touching the shared instance. */
async function reloadedCart() {
    const module = await load(resolve(root, 'resources/js/cart-state.js'), false, true);
    if (module.status !== 'evaluated') await module.evaluate();
    return module.namespace;
}
async function flush() { await Vue.nextTick(); await sleep(0); await Vue.nextTick(); }

/* ---------------------- 1. cart state stays the single source ------------- */
const cart = await namespace('resources/js/cart-state.js');
const presentation = await namespace('resources/js/product-presentation.js');
const helpers = await namespace('resources/js/cart-presentation.js');
const detail = await namespace('resources/js/product-detail-state.js');

assert.equal(detail.axisLabel('size'), presentation.axisLabel('size'), 'the shared axis label helper is still re-exported');
assert.equal(detail.axisLabel('size'), 'سایز');

cart.clearCart();
cart.addToCart(coatItem);
cart.addToCart(coatItem);
let items = cart.getCartItems();
assert.equal(items.length, 1, 'the same variant key merges into one line');
assert.equal(items[0].quantity, 2, 'the merged line is clamped to the stored stock');
assert.equal(items[0].stock, 2);
assert.equal(cart.cartCount.value, 2);
assert.equal(cart.cartTotal.value, 1700000);
assert.equal(JSON.parse(storage.get('turbopart-cart')).length, 1);

cart.updateQuantity('variant_91', 9);
assert.equal(cart.getCartItems()[0].quantity, 2, 'quantity is clamped to the stored stock');
cart.updateQuantity('variant_91', 0);
assert.equal(cart.getCartItems()[0].quantity, 1, 'quantity never drops below one');
cart.addToCart(scarfItem);
assert.equal(cart.cartCount.value, 2);
assert.equal(cart.cartTotal.value, 1170000, 'one coat plus one scarf after the quantity edits');
console.log('PASS existing cart state keys, clamping, totals and localStorage persistence are reused as-is');

/* corrupted localStorage must not break the cart UI */
storage.set('turbopart-cart', '{ not json');
let brokenCart = await reloadedCart();
assert.equal(brokenCart.getCartItems().length, 0, 'invalid json falls back to an empty cart');

storage.set('turbopart-cart', JSON.stringify([{ product_id: 9 }, 'nope', null, { key: 'product_9', product_id: 9, name: 'کت کلاسیک', price: 'bad', quantity: 0, attributes: 'broken', sku: null }]));
const partialCart = await reloadedCart();
items = partialCart.getCartItems();
assert.equal(items.length, 1, 'entries without a key or product id are dropped');
const messy = { ...items[0], key: 'product_9' };
assert.equal(helpers.cartItemLineTotal(messy), 0, 'a broken price renders as zero, never NaN');
assert.deepEqual(Array.from(helpers.cartItemAttributes(messy)), [], 'a broken attributes value renders as no variants');
assert.equal(helpers.cartItemQuantity(messy), 1);
assert.equal(helpers.cartItemMonogram(messy), 'ک');
console.log('PASS corrupted localStorage is tolerated without NaN or crashes');

/* ---------------------- 2. cart presentation helpers --------------------- */
assert.equal(helpers.cartItemMaxQuantity(coatItem), 2);
assert.equal(helpers.cartItemMaxQuantity(scarfItem), helpers.CART_FALLBACK_STOCK, 'unknown stock keeps the cart default ceiling');
assert.equal(helpers.cartItemMaxQuantity({ stock: 0 }), helpers.CART_FALLBACK_STOCK);
assert.equal(helpers.cartItemQuantity({ quantity: 3 }), 3);
assert.equal(helpers.cartItemQuantity({ quantity: -2 }), 1);
assert.equal(helpers.cartItemQuantity({ quantity: 'x' }), 1);
assert.equal(helpers.cartItemReachedMax(coatItem), true);
assert.equal(helpers.cartItemReachedMax(scarfItem), false);
assert.equal(helpers.cartItemLineTotal(coatItem), 1700000);
assert.equal(helpers.cartItemLineTotal(scarfItem), 320000);

const variantLabels = Array.from(helpers.cartItemAttributes(coatItem));
assert.deepEqual(variantLabels.map((entry) => entry.label), ['سایز', 'رنگ'], 'variant axes read as Persian labels, not raw slugs');
assert.deepEqual(variantLabels.map((entry) => entry.value), ['M', 'مشکی']);
assert.deepEqual(Array.from(helpers.cartItemAttributes(scarfItem)), []);
assert.deepEqual(Array.from(helpers.cartItemAttributes({ attributes: { size: ['s', { label: 'L' }] } })).map((entry) => entry.value), ['s، L']);
assert.deepEqual(Array.from(helpers.cartItemAttributes({ attributes: [] })), []);

assert.equal(helpers.cartItemComparePrice(coatItem), 0, 'no compare price is invented for cart data that has none');
assert.equal(helpers.cartItemDiscountPercent(coatItem), 0);
assert.equal(helpers.cartItemComparePrice({ ...coatItem, compare_at_price: 1000000 }), 1000000);
assert.equal(helpers.cartItemDiscountPercent({ ...coatItem, compare_at_price: 1000000 }), 15);
assert.equal(helpers.cartItemComparePrice({ ...coatItem, compare_at_price: 100 }), 0, 'a lower compare price is not a discount');
console.log('PASS quantity ceiling, line totals, Persian variant labels and opt-in discount helpers');

/* --------------------- 3. cart item row rendering ------------------------ */
const { default: rowComponent } = await namespace('resources/js/components/cart/CartItemRow.vue');
const row = (item, compact = false) => renderToString(Vue.createSSRApp({
    render: () => Vue.h(rowComponent, { item, compact }),
}));

let html = await row(coatItem);
assert.ok(html.includes('مانتو کتی پشمی'));
assert.ok(html.includes(presentation.formatPrice(1700000)), 'the row total is the line total, not the unit price');
assert.ok(!html.includes(presentation.formatPrice(850000)));
assert.ok(html.includes('/products/7'), 'the line links back to the product');
assert.ok(html.includes('MNT-1-S-BLK'));
assert.ok(html.includes('مشکی'));
assert.ok(html.includes('رنگ'));
assert.ok(!html.includes('color:'), 'no raw attribute slug is printed');
assert.ok(html.includes('افزایش تعداد مانتو کتی پشمی'));
assert.ok(html.includes('حذف مانتو کتی پشمی از سبد خرید'));
assert.ok(html.includes('disabled'), 'a line at its ceiling cannot be increased');
assert.ok(html.includes('حداکثر'), 'the stored stock ceiling is shown');
assert.ok(html.includes('ct-item'));

html = await row(scarfItem, true);
assert.ok(html.includes('روسری ابریشمی'));
assert.ok(html.includes('ct-item--compact'), 'the drawer reuses the same row in a compact mode');
assert.ok(html.includes('/images/placeholder.svg'), 'a line without an image falls back to the shared placeholder');
assert.ok(html.includes('حذف روسری ابریشمی از سبد خرید'), 'a single unit is offered as a removal');
assert.ok(html.includes(presentation.formatPrice(320000)));
assert.ok((await row({ ...scarfItem, quantity: 2 })).includes('aria-label="کاهش تعداد روسری ابریشمی"'), 'above one unit the button decrements instead of removing');

const messyHtml = await row(messy);
assert.ok(!messyHtml.includes('NaN'), 'a malformed price never reaches the markup as NaN');
assert.ok(!messyHtml.includes('undefined'));
const orphanHtml = await row({ ...messy, product_id: 'x' });
assert.ok(!orphanHtml.includes('href="/products/'), 'a line without a usable product id drops the link');
assert.ok(orphanHtml.includes('کت کلاسیک'), 'the product name still renders without a link');
console.log('PASS cart line markup: product link, Persian variants, stock ceiling, removal and image fallback');

/* quantity controls delegate to the shared cart state */
const { default: exposedRow } = await namespace('resources/js/components/cart/CartItemRow.vue', true);
exposedRow.render = () => null;
const renderer = Vue.createRenderer({
    createComment: () => ({}),
    createText: (text) => ({ text }),
    createElement: (tag) => ({ tag, children: [] }),
    insert: (node, parent) => { node.parent = parent; },
    remove: () => {},
    parentNode: (node) => node.parent,
    nextSibling: () => null,
    setText: () => {},
    setElementText: () => {},
    patchProp: () => {},
});
const ceilingRow = Vue.ref();
const singleRow = Vue.ref();
const ceilingApp = renderer.createApp({ render: () => Vue.h(exposedRow, { item: { ...coatItem }, ref: ceilingRow }) });
const singleApp = renderer.createApp({ render: () => Vue.h(exposedRow, { item: { ...coatItem, quantity: 1 }, ref: singleRow }) });
cart.clearCart();
cart.addToCart(coatItem);
ceilingApp.mount({});
singleApp.mount({});
await flush();

ceilingRow.value.increase();
assert.equal(cart.getCartItems()[0].quantity, 2, 'the ceiling blocks a third unit');
ceilingRow.value.decrease();
assert.equal(cart.getCartItems()[0].quantity, 1);
singleRow.value.decrease();
assert.equal(cart.getCartItems().length, 0, 'decrement at one removes the line');
assert.equal(cart.cartCount.value, 0);
ceilingApp.unmount();
singleApp.unmount();
console.log('PASS the stepper delegates to the existing cart state and never exceeds the ceiling');

/* --------------------- 4. cart drawer and cart page ----------------------- */
const { default: drawerComponent } = await namespace('resources/js/components/cart/CartDrawer.vue');
const { default: pageComponent } = await namespace('resources/js/CartPage.vue');
const drawer = (open) => renderToString(Vue.createSSRApp({
    render: () => Vue.h(drawerComponent, { open }),
}));
const page = () => renderToString(Vue.createSSRApp({ render: () => Vue.h(pageComponent) }));

cart.clearCart();
html = await drawer(true);
assert.ok(html.includes('سبد خرید شما خالی است'), 'an empty cart explains itself inside the drawer');
assert.ok(html.includes('مشاهده محصولات'));
assert.ok(!html.includes('مشاهده سبد خرید'), 'there is no order call to action without items');

cart.addToCart(coatItem);
html = await drawer(true);
assert.ok(html.includes('aria-label="سبد خرید"'), 'the drawer is a labelled dialog');
assert.ok(html.includes('sf-dialog--cart'), 'the drawer uses the shared dialog shell with a cart variant');
assert.ok(html.includes('مانتو کتی پشمی'));
assert.ok(html.includes('مشاهده سبد خرید'));
assert.ok(html.includes('href="/cart"'), 'the primary action targets the existing cart page');
assert.ok(html.includes('ادامه خرید'));
assert.ok(html.includes('بستن سبد خرید'));
assert.ok(html.includes(presentation.formatPrice(1700000)), 'the drawer footer shows the cart subtotal');
assert.ok(html.includes('ct-item--compact'), 'the drawer reuses the shared line markup');
console.log('PASS cart drawer: labelled dialog, live lines, subtotal, cart page call to action and empty state');

cart.clearCart();
html = await page();
assert.ok(html.includes('سبد خرید شما'), 'the empty page keeps its heading');
assert.ok(html.includes('سبد خرید شما خالی است'));
assert.ok(html.includes('مشاهده محصولات'));
assert.ok(!html.includes('خلاصه سبد خرید'), 'no order summary without items');
assert.ok(!html.includes('ct-sticky-inner'), 'no sticky bar without items');

cart.addToCart(coatItem);
cart.addToCart({ ...scarfItem, quantity: 3 });
await flush();
html = await page();
assert.ok(html.includes('کالاهای سبد خرید'));
assert.ok(html.includes('خلاصه سبد خرید'));
assert.ok(html.includes('تعداد کل کالاها'));
assert.ok(html.includes('جمع کل'));
assert.ok(html.includes(presentation.formatPrice(2660000)), 'the summary total comes from cartTotal');
assert.ok(html.includes('href="/checkout"'), 'the existing checkout route stays reachable');
assert.ok(html.includes('خالی کردن سبد خرید'));
assert.ok(html.includes('ct-sticky'), 'mobile keeps a sticky summary');
assert.ok(html.includes('aria-haspopup="dialog"'), 'the header bag opens the drawer');
assert.ok(html.includes('باز کردن سبد خرید'));
assert.ok(html.includes('aria-label="سبد خرید"'), 'the closed drawer still ships with the header');
assert.ok(!html.includes('bg-cream'), 'the legacy tailwind theme classes are gone');
assert.ok(!html.includes('rounded-xl'));
assert.ok(!html.includes('text-ink'));
assert.ok(!html.includes('قطعه'));
assert.ok(!html.includes('خودرو'));
console.log('PASS cart page layout, summary, sticky mobile bar, header drawer trigger and no legacy classes');

/* --------------------- 5. css isolation and wiring ----------------------- */
const pageSource = await readFile(resolve(root, 'resources/js/CartPage.vue'), 'utf8');
const drawerSource = await readFile(resolve(root, 'resources/js/components/cart/CartDrawer.vue'), 'utf8');
const rowSource = await readFile(resolve(root, 'resources/js/components/cart/CartItemRow.vue'), 'utf8');
const headerSource = await readFile(resolve(root, 'resources/js/SiteHeader.vue'), 'utf8');
const helpersSource = await readFile(resolve(root, 'resources/js/cart-presentation.js'), 'utf8');

assert.ok(pageSource.includes("import '../css/cart.css'"));
assert.ok(drawerSource.includes("import '../../../css/cart.css'"), 'the drawer ships with the header on every page');
assert.ok(drawerSource.includes('variant="cart"'), 'the drawer reuses the shared dialog shell');
assert.ok(headerSource.includes('CartDrawer') && headerSource.includes('aria-haspopup="dialog"'));
for (const [name, source] of [['CartPage', pageSource], ['CartDrawer', drawerSource], ['CartItemRow', rowSource], ['cart-presentation', helpersSource]]) {
    assert.ok(!source.includes('axios'), `${name} must not add an API client`);
    assert.ok(!source.includes('localStorage'), `${name} must not duplicate the cart storage`);
}
const css = postcss.parse(await readFile(resolve(root, 'resources/css/cart.css'), 'utf8'));
css.walkRules((rule) => {
    if (rule.parent.type === 'atrule' && rule.parent.name.endsWith('keyframes')) return;
    for (const selector of postcss.list.comma(rule.selector)) assert.ok(selector.startsWith('.storefront-theme '), selector);
});
const cssText = css.toString();
assert.ok(cssText.includes('prefers-reduced-motion'));
assert.ok(cssText.includes('.sf-dialog--cart'));
assert.ok(cssText.includes('margin-inline-end: auto'), 'the drawer is pinned to the right edge in RTL');
assert.ok(cssText.includes('.cd-cta'));
assert.ok(cssText.includes('.ct-sticky'));
console.log('PASS CSS isolation, RTL drawer anchoring, reduced motion and cart wiring');
console.log('5 verification groups passed; no browser layout claim.');
