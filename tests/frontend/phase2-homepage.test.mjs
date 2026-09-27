// Behavioral checks against actual frontend modules; no server mutations.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SourceTextModule, SyntheticModule, createContext } from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import postcss from 'postcss';
import * as Vue from 'vue';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const requests = [];
const storage = new Map();
const location = { href: '' };
const axios = { isCancel: (error) => error?.code === 'ERR_CANCELED', get: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })) };
const loggedIn = Vue.ref(false), saved = Vue.ref(false), wishlist = Vue.reactive({ loading: false });
let wishlistResult = true;
let mutations = 0;
const wishlistMock = { state: wishlist, isInWishlist: () => saved.value, addToWishlist: async () => { mutations++; if (wishlistResult) saved.value = true; return wishlistResult; }, removeFromWishlist: async () => { mutations++; if (wishlistResult) saved.value = false; return wishlistResult; } };
const context = createContext({ console, AbortController, location, setTimeout, clearTimeout, localStorage: { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) } });
const cache = new Map();
function stub(id, exports) { return new SyntheticModule(Object.keys(exports), function () { for (const [key, value] of Object.entries(exports)) this.setExport(key, value); }, { context, identifier: id }); }
cache.set('vue', stub('vue', Vue)); cache.set('axios', stub('axios', { default: axios }));
cache.set(resolve(root, 'resources/js/auth-state.js'), stub('auth', { isLoggedIn: loggedIn }));
cache.set(resolve(root, 'resources/js/wishlist-state.js'), stub('wishlist', wishlistMock));
async function load(id, expose = false) {
    const key = expose ? `${id}:expose` : id;
    if (cache.has(key)) return cache.get(key);
    let source = await readFile(id, 'utf8');
    if (id.endsWith('.vue')) {
        if (expose) source = source.replace('</script>', '\ndefineExpose({ quickAdd, toggleWishlist, feedback, feedbackError, markImageFailed, images });\n</script>');
        const { descriptor } = parse(source, { filename: id });
        if (expose) descriptor.template = null;
        source = compileScript(descriptor, { id: 'homepage-test', inlineTemplate: !expose }).content;
    }
    const module = new SourceTextModule(source, { context, identifier: id }); cache.set(key, module);
    await module.link((name, parent) => load(cache.has(name) ? name : resolve(dirname(parent.identifier), name)));
    return module;
}
async function namespace(path, expose = false) { const module = await load(resolve(root, path), expose); if (module.status !== 'evaluated') await module.evaluate(); return module.namespace; }
const helpers = await namespace('resources/js/product-presentation.js');
const product = { id: 14, name: 'پیراهن', price: 800, compare_at_price: 1000, in_stock: true, is_featured: true, categories: [{ slug: 'shirts' }], images: [{ path: 'second.jpg' }, { path: 'first.jpg', is_primary: true }], attributes: { color: [{ hex_color: '#FFF', label: 'سفید' }] }, variants: [] };
assert.equal(helpers.productImages(product)[0].path, 'first.jpg');
assert.equal(helpers.discountPercent(product), 20);
assert.equal(helpers.productColors({ ...product, variants: [{ attributes: { color: [{ hex_color: '#fff', label: 'سفید' }] } }] }).length, 1);
assert.equal(helpers.cartItemFromProduct({ ...product, has_variants: true }), null);
assert.equal(helpers.cartItemFromProduct({ ...product, in_stock: false }), null);
assert.equal(helpers.cartItemFromProduct(product).variant_id, null);
console.log('PASS actual price, image ordering, color deduplication, variant and stock guards');

const { createHomepageData } = await namespace('resources/js/homepage-data.js');
const home = createHomepageData();
const initial = home.load();
assert.equal(requests.length, 2);
requests.find((request) => request.url === '/api/categories/tree').reject(new Error('offline'));
requests.find((request) => request.url === '/api/products').resolve({ data: { data: [product, { ...product, id: 19, name: 'Vehicle repair kit' }] } });
await initial;
assert.equal(home.catalog.status, 'error'); assert.equal(home.state.status, 'ready');
assert.equal(home.arrivals.value.length, 1); assert.equal(home.featured.value.length, 1);
assert.equal(home.categoryProductImage({ slug: 'clothing', children: [{ slug: 'shirts' }] }), '/storage/first.jpg');
assert.equal(home.heroProduct.value.id, 14);
const old = home.loadProducts(), oldRequest = requests.at(-1);
const newer = home.loadProducts(), newRequest = requests.at(-1);
assert.equal(oldRequest.options.signal.aborted, true);
newRequest.resolve({ data: { data: [] } }); await newer;
oldRequest.resolve({ data: { data: [product] } }); await old;
assert.equal(home.arrivals.value.length, 0);
const failure = home.loadProducts(); requests.at(-1).reject(new Error('offline')); await failure;
assert.equal(home.state.status, 'error'); assert.ok(home.state.error);
const retry = home.loadProducts(); requests.at(-1).resolve({ data: { data: [product] } }); await retry;
assert.equal(home.state.status, 'ready');
const disposed = home.loadProducts(); const disposedRequest = requests.at(-1); home.dispose();
assert.equal(disposedRequest.options.signal.aborted, true); disposedRequest.resolve({ data: { data: [] } }); await disposed;
assert.equal(home.arrivals.value.length, 1);
console.log('PASS independent category failure, automotive exclusion, retry, empty, stale and unmount responses');

const renderer = Vue.createRenderer({ createComment: () => ({}), createText: (text) => ({ text }), createElement: () => ({}), insert: (node, parent) => { node.parent = parent; }, remove() {}, parentNode: (node) => node.parent, nextSibling: () => null, setText() {}, setElementText() {}, patchProp() {} });
const { default: card } = await namespace('resources/js/components/ProductCard.vue', true);
card.render = () => null;
const props = Vue.reactive({ product }); const exposed = Vue.ref();
const app = renderer.createApp({ render: () => Vue.h(card, { ...props, ref: exposed }) }); app.mount({});
const cart = await namespace('resources/js/cart-state.js');
exposed.value.quickAdd(); assert.equal(cart.getCartItems().length, 1); assert.equal(cart.getCartItems()[0].product_id, 14);
props.product = { ...product, id: 15, variants: [{ id: 9 }] }; await Vue.nextTick();
exposed.value.quickAdd(); assert.equal(location.href, '/products/15'); assert.equal(cart.getCartItems().length, 1);
props.product = { ...product, id: 16, in_stock: false }; await Vue.nextTick(); exposed.value.quickAdd(); assert.equal(cart.getCartItems().length, 1);
await exposed.value.toggleWishlist(); assert.equal(location.href, '/login?redirect=%2F'); assert.equal(mutations, 0);
loggedIn.value = true; wishlistResult = null; await exposed.value.toggleWishlist(); assert.equal(exposed.value.feedbackError, true); assert.equal(saved.value, false);
wishlistResult = true; await exposed.value.toggleWishlist(); assert.equal(saved.value, true); assert.equal(exposed.value.feedbackError, false);
await exposed.value.toggleWishlist(); assert.equal(saved.value, false);
exposed.value.markImageFailed({ target: { dataset: { imagePath: 'first.jpg' } } });
exposed.value.markImageFailed({ target: { dataset: { imagePath: 'second.jpg' } } }); assert.equal(exposed.value.images.length, 0);
app.unmount();
console.log('PASS real cart module, variant navigation, unavailable stock, guest wishlist, failed and successful wishlist, image error race');

const { default: renderedCard } = await namespace('resources/js/components/ProductCard.vue');
const html = await renderToString(Vue.createSSRApp({ render: () => Vue.h(renderedCard, { product }) }));
assert.ok(html.includes('/products/14')); assert.ok(html.includes('پیراهن')); assert.ok(html.includes('aria-pressed="false"'));
const { default: section } = await namespace('resources/js/components/homepage/ProductSection.vue');
for (const status of ['loading', 'error', 'ready']) {
    const output = await renderToString(Vue.createSSRApp({ render: () => Vue.h(section, { id: 'arrivals', title: 'تازه‌ها', products: [], status, error: 'offline' }) }));
    assert.ok(output.includes(status === 'loading' ? 'role="status"' : status === 'error' ? 'role="alert"' : 'انتخاب‌های تازه'));
}
const css = postcss.parse(await readFile(resolve(root, 'resources/css/homepage.css'), 'utf8'));
css.walkRules((rule) => { if (rule.parent.type === 'atrule' && rule.parent.name.endsWith('keyframes')) return; for (const selector of postcss.list.comma(rule.selector)) assert.ok(selector.startsWith('.storefront-theme '), selector); });
assert.ok(css.toString().includes('prefers-reduced-motion'));
console.log('PASS Vue template rendering, real links, loading/error/empty rendering, Admin CSS isolation and reduced motion');
console.log('4 verification groups passed (browser layout remains unverified).');
