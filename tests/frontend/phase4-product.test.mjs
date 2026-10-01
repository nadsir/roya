// Run: node --experimental-vm-modules tests/frontend/phase4-product.test.mjs
// Behaviour checks against the real product detail modules; no server mutations.
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
const session = new Map();
const listeners = new Map();
const timers = new Map();
let timerId = 0;
const location = { pathname: '/products/7', href: '', search: '' };
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
    addEventListener: (name, fn) => listeners.set(name, fn),
    removeEventListener: (name) => listeners.delete(name),
    dispatchEvent() {},
};
const context = createContext({
    console, AbortController, URLSearchParams, location, document, window,
    localStorage: {
        getItem: (key) => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
        removeItem: (key) => storage.delete(key),
    },
    sessionStorage: {
        getItem: (key) => session.get(key) ?? null,
        setItem: (key, value) => session.set(key, value),
        removeItem: (key) => session.delete(key),
    },
    matchMedia: () => ({ matches: false, addEventListener() {}, removeEventListener() {} }),
    requestAnimationFrame: () => 0,
    setTimeout: (fn) => { const id = ++timerId; timers.set(id, fn); return id; },
    clearTimeout: (id) => timers.delete(id),
});

/* -------------------------------- fixtures ------------------------------- */
const sizeS = { id: 1, label: 'S', value: 's' };
const sizeM = { id: 2, label: 'M', value: 'm' };
const sizeL = { id: 3, label: 'L', value: 'l' };
const colorBlack = { id: 10, label: 'مشکی', value: 'black', hex_color: '#141414' };
const colorIvory = { id: 11, label: 'شیری', value: 'ivory', hex_color: '#f2ece1' };

const coat = {
    id: 7,
    name: 'مانتو کتی پشمی',
    short_description: 'پارچه کرپ با آستر نرم',
    description: 'دوخت تمیز، مناسب استفاده روزمره.',
    price: 900000,
    compare_at_price: 1200000,
    sku: 'MNT-1',
    in_stock: true,
    categories: [{ id: 4, slug: 'coats', name: 'کت و مانتو' }],
    images: [{ path: 'second.jpg', sort_order: 2 }, { path: 'first.jpg', is_primary: true }],
    attributes: {
        brand: [{ id: 55, label: 'گالری' }],
        size: [sizeS, sizeM, sizeL],
        color: [colorBlack, colorIvory],
    },
    custom_attributes: [{ id: 9, name: 'برند', slug: 'brand', value: 'نوین' }],
    variants: [
        { id: 91, is_active: true, price: 850000, compare_at_price: 1000000, sku: 'MNT-1-S-BLK', stock: 2, attributes: { size: [sizeS], color: [colorBlack] } },
        { id: 92, is_active: true, price: 870000, compare_at_price: 1000000, sku: 'MNT-1-M-BLK', stock: 5, attributes: { size: [sizeM], color: [colorBlack] } },
        { id: 93, is_active: false, price: 100000, compare_at_price: null, sku: 'MNT-1-L-BLK-OLD', stock: 9, attributes: { size: [sizeL], color: [colorBlack] } },
        { id: 94, is_active: true, price: 990000, compare_at_price: null, sku: 'MNT-1-M-IVORY', stock: 0, attributes: { size: [sizeM], color: [colorIvory] } },
    ],
};

const scarf = {
    id: 8,
    name: 'روسری ابریشمی',
    price: 320000,
    compare_at_price: null,
    sku: 'SCF-4',
    in_stock: true,
    categories: [{ id: 4, slug: 'coats', name: 'کت و مانتو' }],
    images: [{ path: 'scarf.jpg', is_primary: true }],
    attributes: {},
    custom_attributes: [],
    variants: [],
};

const tee = {
    id: 12,
    name: 'تاپ نخی ساده',
    price: 450000,
    compare_at_price: null,
    sku: 'TOP-2',
    in_stock: true,
    categories: [{ id: 6, slug: 'tops', name: 'تاپ' }],
    images: [],
    attributes: { brand: [{ id: 55, label: 'گالری' }] },
    custom_attributes: [],
    variants: [],
};

const categoriesTree = [{ name: 'پوشاک', slug: 'clothing', children: [{ name: 'کت و مانتو', slug: 'coats', children: [] }] }];
const commentsPage = { data: [], current_page: 1, last_page: 1, total: 0, rating_summary: { average: null, count: 0, distribution: {} } };
const fixtures = { 7: coat, 12: tee };

/* --------------------------------- axios --------------------------------- */
const queue = [];
let behavior = 'fixture';
const axios = {
    isCancel: (error) => error?.code === 'ERR_CANCELED',
    defaults: { headers: { common: {} } },
    get(url, options) {
        if (url.includes('/comments')) return Promise.resolve({ data: commentsPage });
        if (behavior === 'manual') return new Promise((resolve, reject) => queue.push({ url, options, resolve, reject }));
        if (behavior === 'error') return Promise.reject(new Error('private technical failure'));
        if (behavior === '404') return Promise.reject(Object.assign(new Error('missing'), { response: { status: 404 } }));
        if (url === '/api/categories/tree') return Promise.resolve({ data: { data: categoriesTree } });
        if (url === '/api/products') return Promise.resolve({ data: { data: [coat, scarf] } });
        const match = url.match(/^\/api\/products\/(\d+)$/);
        if (match && fixtures[Number(match[1])]) return Promise.resolve({ data: { data: fixtures[Number(match[1])] } });
        return Promise.reject(new Error(`unexpected request ${url}`));
    },
};

/* -------------------------------- stubs ---------------------------------- */
const auth = Vue.reactive({ user: null, loading: false });
const authMock = {
    state: auth,
    isLoggedIn: Vue.computed(() => Boolean(auth.user)),
    getToken: () => null,
    loadUser: async () => {},
    logout: async () => {},
};
const wishlist = Vue.reactive({ items: [], loading: false, error: null });
let wishlistSaved = false;
const wishlistMock = {
    state: wishlist,
    isInWishlist: () => wishlistSaved,
    addToWishlist: async () => { wishlistSaved = true; return true; },
    removeFromWishlist: async () => { wishlistSaved = false; return true; },
    loadWishlist: () => {},
    resetWishlist: () => {},
    wishlistCount: Vue.computed(() => wishlist.items.length),
};

/* -------------------------------- loader --------------------------------- */
const cache = new Map();
function stub(name, exports) {
    return new SyntheticModule(Object.keys(exports), function () {
        for (const [key, value] of Object.entries(exports)) this.setExport(key, value);
    }, { context, identifier: name });
}
cache.set('vue', stub('vue', Vue));
cache.set('axios', stub('axios', { default: axios }));
cache.set(resolve(root, 'resources/js/auth-state.js'), stub('auth', authMock));
cache.set(resolve(root, 'resources/js/wishlist-state.js'), stub('wishlist', wishlistMock));

async function load(id, expose = false) {
    const key = expose ? `${id}:expose` : id;
    if (cache.has(key)) return cache.get(key);
    if (id.endsWith('.css')) {
        const cssModule = stub(id, { default: {} });
        cache.set(key, cssModule);
        return cssModule;
    }
    let source = await readFile(id, 'utf8');
    if (id.endsWith('.vue')) {
        if (expose) source = source.replace('</script>', '\ndefineExpose({ toggleWishlist, add, clearNotice });\n</script>');
        const { descriptor } = parse(source, { filename: id });
        if (expose) { descriptor.template = null; source = compileScript(descriptor, { id: 'phase4-expose' }).content; }
        else source = compileScript(descriptor, { id: 'phase4-test', inlineTemplate: true }).content;
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
async function flush() { await Vue.nextTick(); await sleep(0); await Vue.nextTick(); }

/* ------------------------- 1. product detail state ----------------------- */
const detail = await namespace('resources/js/product-detail-state.js');
const cart = await namespace('resources/js/cart-state.js');
const helpers = await namespace('resources/js/product-presentation.js');

assert.equal(detail.axisLabel('colour'), 'رنگ');
assert.equal(detail.axisLabel('material'), 'جنس');
assert.equal(detail.axisLabel('weave'), 'weave');
assert.equal(detail.productIdFromPath('/products/7'), 7);
assert.equal(detail.productIdFromPath('/store'), null);
assert.equal(detail.productIdFromPath('/product/7'), null);

const model = detail.createProductDetailState({ location: { pathname: '/products/7' } });
const loading = model.load();
assert.equal(model.result.loading, true);
await loading;
assert.equal(model.result.loading, false);
assert.equal(model.product.value.id, 7);
assert.equal(model.result.error, '');
assert.equal(model.result.notFound, false);
assert.deepEqual(Array.from(model.related.value).map((item) => item.id), [8]);
assert.equal(model.relatedLoading.value, false);
assert.deepEqual(Array.from(model.categories.value).map((item) => item.slug), ['coats']);

assert.deepEqual(Array.from(model.images.value).map((image) => image.path), ['first.jpg', 'second.jpg']);
assert.equal(model.activeImageSrc.value, '/storage/first.jpg');
assert.equal(model.activeImageFailed.value, false);
model.selectImage(1);
assert.equal(model.activeIndex.value, 1);
model.nextImage();
assert.equal(model.activeIndex.value, 0);
model.prevImage();
assert.equal(model.activeIndex.value, 1);
model.selectImage(9);
assert.equal(model.activeIndex.value, 1);
model.selectImage(0);
model.markImageFailed('first.jpg');
assert.equal(model.activeImageFailed.value, true);
assert.equal(model.activeImageSrc.value, '');
assert.equal(model.thumbSrc({ path: 'first.jpg' }), '');
// Fallback chain (Phase 8): real image -> temporary external fallback ->
// /images/placeholder.svg -> neutral block. The final two steps are unchanged.
const { nextImageSrc, externalFallbackSrc, PLACEHOLDER_SRC } = await namespace('resources/js/image-fallback.js');
assert.equal(model.imageErrorSrc('second.jpg', '/storage/second.jpg'), externalFallbackSrc(model.product.value.id));
assert.equal(model.imageErrorSrc('second.jpg', externalFallbackSrc(model.product.value.id)), PLACEHOLDER_SRC);
assert.equal(model.imageErrorSrc('second.jpg', '/images/placeholder.svg'), '');
assert.equal(model.thumbSrc({ path: 'second.jpg' }), '');
console.log('PASS load, related reuse of the existing products endpoint, gallery selection and image fallback chain');

assert.equal(model.hasVariants.value, true);
assert.equal(model.attributeAxes.value.length, 2);
assert.deepEqual(Array.from(model.attributeAxes.value).map((axis) => axis.label), ['سایز', 'رنگ']);
assert.ok(Array.from(model.attributeAxes.value[0].values).some((value) => value.label === 'L'));
assert.equal(model.isValueSelectable('size', 3), false, 'inactive variant values stay visible but unselectable');
assert.equal(model.needsVariantSelection.value, true);
assert.equal(model.displayPrice.value, 900000);
assert.equal(model.displayCompareAtPrice.value, 1200000);
assert.equal(model.displayDiscount.value, 25);
assert.equal(model.displaySku.value, 'MNT-1');
assert.equal(model.displayInStock.value, true);
assert.equal(model.displayStock.value, null);
assert.equal(model.maxQuantity.value, 999);

cart.clearCart();
assert.equal(model.add(), false);
assert.match(model.notice.message, /رنگ/);
assert.equal(model.notice.type, 'error');
assert.equal(cart.getCartItems().length, 0);

assert.equal(model.toggleAxisValue('size', 1), true);
assert.equal(model.isSelected(model.attributeAxes.value[0], sizeS), true);
assert.equal(model.selectedLabel(model.attributeAxes.value[0]), 'S');
assert.equal(model.isValueSelectable('color', 11), false, 'ivory exists only with size M');
assert.equal(model.matchedVariant.value, null);
assert.equal(model.add(), false);

assert.equal(model.toggleAxisValue('color', 10), true);
assert.equal(model.matchedVariant.value.id, 91);
assert.equal(model.displayPrice.value, 850000);
assert.equal(model.displayCompareAtPrice.value, 1000000);
assert.equal(model.displayDiscount.value, 15);
assert.equal(model.displaySku.value, 'MNT-1-S-BLK');
assert.equal(model.displayStock.value, 2);
assert.equal(model.displayInStock.value, true);
assert.equal(model.needsVariantSelection.value, false);
assert.equal(model.maxQuantity.value, 2);
model.setQuantity(10);
assert.equal(model.quantity.value, 2);
assert.equal(model.canIncreaseQuantity.value, false);
model.increaseQuantity();
assert.equal(model.quantity.value, 2);
model.decreaseQuantity();
assert.equal(model.quantity.value, 1);

assert.equal(model.add(), true);
let items = cart.getCartItems();
assert.equal(items.length, 1);
assert.equal(items[0].key, 'variant_91');
assert.equal(items[0].variant_id, 91);
assert.equal(items[0].price, 850000);
assert.equal(items[0].stock, 2);
assert.equal(items[0].quantity, 1);
assert.equal(JSON.parse(storage.get('turbopart-cart'))[0].key, 'variant_91');
assert.ok(model.notice.message.includes('به سبد خرید اضافه شد'));
assert.equal(model.notice.type, 'success');

assert.equal(model.toggleAxisValue('size', 2), true);
assert.equal(model.isValueSelectable('color', 11), true);
assert.equal(model.toggleAxisValue('color', 11), true);
assert.equal(model.matchedVariant.value.id, 94);
assert.equal(model.displayInStock.value, false);
assert.equal(model.add(), false);
assert.match(model.notice.message, /ناموجود/);
assert.equal(model.toggleAxisValue('color', 11), true, 'tapping the selected value clears it');
assert.equal(model.matchedVariant.value, null);
assert.equal(model.needsVariantSelection.value, true);

assert.equal(model.displayAttributes.value.length, 1);
assert.equal(model.displayAttributes.value[0].label, 'برند');
assert.equal(model.displayAttributes.value[0].values[0].label, 'گالری');
assert.equal(model.customAttributes.value.length, 1);
assert.equal(model.brand.value, 'نوین');
console.log('PASS variant axes, disabled combinations, matched price/stock/sku, quantity ceiling and cart keying');

/* simple product without variants */
const simple = detail.createProductDetailState({ location: { pathname: '/products/12' } });
await simple.load();
assert.equal(simple.hasVariants.value, false);
assert.equal(simple.attributeAxes.value.length, 0);
assert.equal(simple.needsVariantSelection.value, false);
assert.equal(simple.displayInStock.value, true);
assert.equal(simple.brand.value, 'گالری');
cart.clearCart();
assert.equal(simple.add(), true);
items = cart.getCartItems();
assert.equal(items[0].key, 'product_12');
assert.equal(items[0].variant_id, null);
assert.equal(items[0].quantity, 1);
assert.equal(items[0].stock, 999, 'unknown stock keeps the cart default ceiling');
console.log('PASS variant-free product, attribute brand fallback and reuse of the existing cart module');

/* errors, 404, retry, missing id */
behavior = 'error';
const failing = detail.createProductDetailState({ location: { pathname: '/products/7' } });
await failing.load();
assert.equal(failing.result.loading, false);
assert.equal(failing.result.notFound, false);
assert.ok(failing.result.error.includes('خطا در دریافت اطلاعات محصول'));
assert.ok(!failing.result.error.includes('private'));
behavior = 'fixture';
await failing.load();
assert.equal(failing.product.value.id, 7);
assert.equal(failing.result.error, '');

behavior = '404';
const missing = detail.createProductDetailState({ location: { pathname: '/products/999' } });
await missing.load();
assert.equal(missing.result.notFound, true);
assert.equal(missing.product.value, null);
behavior = 'fixture';
const noId = detail.createProductDetailState({ location: { pathname: '/store' } });
await noId.load();
assert.equal(noId.result.notFound, true);
assert.equal(noId.result.loading, false);
console.log('PASS error copy without internals, retry, 404 and missing id states');

/* cancellation, stale responses and disposal */
queue.length = 0;
behavior = 'manual';
const racing = detail.createProductDetailState({ location: { pathname: '/products/7' } });
const older = racing.load();
const newer = racing.load();
assert.equal(queue.length, 2);
assert.equal(queue[0].options.signal.aborted, true);
assert.equal(queue[1].options.signal.aborted, false);
queue[1].resolve({ data: { data: coat } });
await sleep(0);
assert.equal(queue.length, 3, 'related products request follows the fresh response');
assert.equal(queue[2].options.params.category, 'coats');
queue[2].resolve({ data: { data: [coat, scarf] } });
await newer;
assert.equal(racing.product.value.id, 7);
assert.deepEqual(Array.from(racing.related.value).map((item) => item.id), [8]);
queue[0].resolve({ data: { data: { id: 999, name: 'پاسخ قدیمی' } } });
await older;
assert.equal(racing.product.value.id, 7, 'a stale response cannot replace the fresh product');
assert.equal(racing.result.loading, false);

queue.length = 0;
const owned = detail.createProductDetailState({ location: { pathname: '/products/7' } });
const pendingLoad = owned.load();
const pendingRequest = queue[0];
owned.dispose();
assert.equal(pendingRequest.options.signal.aborted, true);
pendingRequest.resolve({ data: { data: coat } });
await pendingLoad;
assert.equal(owned.product.value, null, 'a disposed page never consumes its response');
assert.equal(owned.result.loading, true);
queue.length = 0;
behavior = 'fixture';
console.log('PASS request cancellation, stale response isolation and disposal');

/* no new backend surface */
const stateSource = await readFile(resolve(root, 'resources/js/product-detail-state.js'), 'utf8');
const endpoints = [...stateSource.matchAll(/axios\.get\((['`])([^'"`]+)\1/g)].map((match) => match[2]);
assert.deepEqual(endpoints.sort(), ['/api/products', '/api/products/${id}']);
console.log('PASS state module only calls the existing product endpoints');

/* --------------------------- 2. component mount -------------------------- */
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
const warnings = [];
const mountModel = detail.createProductDetailState({ location: { pathname: '/products/7' } });
mountModel.product.value = coat;
mountModel.result.loading = false;
const { default: detailComponent } = await namespace('resources/js/ProductDetail.vue', true);
detailComponent.render = () => null;
const props = Vue.shallowReactive({ model: mountModel });
const exposed = Vue.ref();
const app = renderer.createApp({ render: () => Vue.h(detailComponent, { ...props, ref: exposed }) });
app.config.warnHandler = (message) => warnings.push(message);
app.mount({});
await flush();

assert.equal(queue.length, 0, 'a provided model never triggers the page loader');
exposed.value.toggleWishlist();
assert.equal(location.href, '/login?redirect=%2Fproducts%2F7');
assert.equal(wishlistMock.isInWishlist(), false);

exposed.value.add();
assert.match(mountModel.notice.message, /سایز/);
assert.equal(cart.getCartItems().length, 1, 'the previous cart entry is untouched by a rejected add');

auth.user = { id: 3 };
await flush();
await exposed.value.toggleWishlist();
assert.ok(mountModel.notice.message.includes('ذخیره شد'));
await exposed.value.toggleWishlist();
assert.ok(mountModel.notice.message.includes('حذف شد'));
exposed.value.clearNotice();
assert.equal(mountModel.notice.message, '');
assert.equal(warnings.length, 0, warnings.join('\n'));
app.unmount();
console.log('PASS guest wishlist redirect, signed-in wishlist reuse, add validation and warning-free setup');

/* ------------------------------ 3. server render ------------------------- */
auth.user = null;
const { default: serverComponent } = await namespace('resources/js/ProductDetail.vue');
const ssrModel = detail.createProductDetailState({ location: { pathname: '/products/7' } });
ssrModel.product.value = coat;
ssrModel.result.loading = false;
ssrModel.related.value = [scarf];
let html = await renderToString(Vue.createSSRApp({ render: () => Vue.h(serverComponent, { model: ssrModel }) }));
assert.ok(html.includes('مانتو کتی پشمی'));
assert.ok(html.includes(helpers.formatPrice(900000)));
assert.ok(html.includes('افزودن به سبد خرید'));
assert.ok(html.includes('افزودن به سبد'));
assert.ok(html.includes('کد کالا'));
assert.ok(html.includes('MNT-1'));
assert.ok(html.includes('انتخاب سایز'));
assert.ok(html.includes('aria-pressed="false"'));
assert.ok(html.includes('/c/coats'));
assert.ok(html.includes('موجود در گالری'));
assert.ok(html.includes('/products/8'));
assert.ok(html.includes('تجربه‌های واقعی'));
assert.ok(html.includes('اولین تجربه را شما ثبت کنید'));
assert.ok(html.includes('این محصول ثبت نکرده'));
assert.ok(html.includes('ورود برای ذخیره در علاقه‌مندی‌ها'));
assert.ok(html.includes('pd-sticky'));
assert.ok(!html.includes('قطعه'));
assert.ok(!html.includes('خودرو'));
assert.ok(!html.includes('EXP-01'));

const notFoundModel = detail.createProductDetailState({ location: { pathname: '/products/7' } });
notFoundModel.result.loading = false;
notFoundModel.result.notFound = true;
html = await renderToString(Vue.createSSRApp({ render: () => Vue.h(serverComponent, { model: notFoundModel }) }));
assert.ok(html.includes('role="alert"'));
assert.ok(html.includes('این محصول دیگر در دسترس نیست.'));
assert.ok(html.includes('NOT IN THE EDIT'));

const errorModel = detail.createProductDetailState({ location: { pathname: '/products/7' } });
errorModel.result.loading = false;
errorModel.result.error = 'خطا در دریافت اطلاعات محصول. لطفاً دوباره تلاش کنید.';
html = await renderToString(Vue.createSSRApp({ render: () => Vue.h(serverComponent, { model: errorModel }) }));
assert.ok(html.includes('کمی بعد دوباره ببینیم.'));
assert.ok(html.includes('تلاش مجدد'));

html = await renderToString(Vue.createSSRApp({ render: () => Vue.h(serverComponent, { model: detail.createProductDetailState({ location: { pathname: '/products/7' } }) }) }));
assert.ok(html.includes('role="status"'));
assert.ok(html.includes('در حال بارگذاری محصول'));
console.log('PASS product, not-found, error and loading markup with no automotive copy');

/* --------------------------------- 4. css -------------------------------- */
const componentSource = await readFile(resolve(root, 'resources/js/ProductDetail.vue'), 'utf8');
assert.ok(componentSource.includes("import '../css/product.css'"));
assert.ok(componentSource.includes("import '../css/homepage.css'"), 'ProductCard markup needs the homepage classes');
const css = postcss.parse(await readFile(resolve(root, 'resources/css/product.css'), 'utf8'));
css.walkRules((rule) => {
    if (rule.parent.type === 'atrule' && rule.parent.name.endsWith('keyframes')) return;
    for (const selector of postcss.list.comma(rule.selector)) assert.ok(selector.startsWith('.storefront-theme '), selector);
});
const cssText = css.toString();
assert.ok(cssText.includes('prefers-reduced-motion'));
assert.ok(cssText.includes('.pd-sticky'));
assert.ok(cssText.includes('.pd-cta'));
console.log('PASS CSS isolation, reduced motion and required stylesheet imports');
console.log('4 verification groups passed; no browser layout claim.');
