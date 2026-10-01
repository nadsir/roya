// Run with node --experimental-vm-modules. No backend/database mutations.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SourceTextModule, SyntheticModule, createContext } from 'node:vm';
import { parse, compileScript } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import postcss from 'postcss';
import * as Vue from 'vue';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const queued = [];
const categories = [{ slug: 'clothing', name: 'لباس', children: [{ slug: 'shirts', name: 'پیراهن', children: [] }] }, { slug: 'bags', name: 'کیف', children: [] }];
const facets = [{ id: 1, name: 'سایز', slug: 'size', type: 'select', values: [{ value: 'm', label: 'M', count: 2 }] }, { id: 2, name: 'رنگ', slug: 'color', type: 'color', values: [{ value: 'black', label: 'مشکی', hex_color: '#000000', count: 1 }] }];
const product = { id: 1, name: 'پیراهن', in_stock: true, categories: [{ slug: 'shirts' }] };
const axios = { isCancel: (error) => error?.code === 'ERR_CANCELED', get: (url, options) => url === '/api/categories/tree' ? Promise.resolve({ data: { data: categories } }) : new Promise((resolve, reject) => queued.push({ url, options, resolve, reject })) };
const context = createContext({ console, AbortController, URLSearchParams });
const cache = new Map();
function stub(name, exports) { return new SyntheticModule(Object.keys(exports), function () { for (const [key, value] of Object.entries(exports)) this.setExport(key, value); }, { context, identifier: name }); }
cache.set('vue', stub('vue', Vue)); cache.set('axios', stub('axios', { default: axios }));
async function load(id) {
    if (cache.has(id)) return cache.get(id);
    let source = await readFile(id, 'utf8');
    if (id.endsWith('.vue')) { const { descriptor } = parse(source, { filename: id }); source = compileScript(descriptor, { id: 'discovery-test', inlineTemplate: true }).content; }
    const module = new SourceTextModule(source, { context, identifier: id }); cache.set(id, module);
    await module.link((specifier, parent) => load(cache.has(specifier) ? specifier : resolve(dirname(parent.identifier), specifier)));
    return module;
}
async function namespace(path) { const module = await load(resolve(root, path)); if (module.status !== 'evaluated') await module.evaluate(); return module.namespace; }
const events = new Map();
const browser = { location: { pathname: '/c/clothing', search: '?size=m&search=مانتو&sort=price_asc&page=2' }, history: { pushState(_, __, url) { const parsed = new URL(url, 'http://local'); browser.location.pathname = parsed.pathname; browser.location.search = parsed.search; } }, addEventListener: (event, fn) => events.set(event, fn), removeEventListener: (event) => events.delete(event) };
const { createDiscoveryState, discoverySorts } = await namespace('resources/js/discovery-state.js');
const model = createDiscoveryState(browser);
function settle() { while (queued.length) { const request = queued.shift(); request.resolve({ data: request.url.endsWith('/filters') ? { data: facets } : request.url === '/api/products' ? { data: [product], meta: { current_page: Number(request.options.params.get('page')), last_page: 3, total: 25 } } : { data: { slug: model.state.category, name: model.state.category === 'bags' ? 'کیف' : 'لباس', description: 'انتخاب‌های روزمره' } } }); } }
const init = model.init(); settle(); await init;
assert.equal(model.state.category, 'clothing'); assert.equal(model.state.sort, 'price_asc'); assert.equal(model.state.page, 2);
assert.equal(model.state.values.size[0], 'm'); assert.equal(model.subcategories.value[0].slug, 'shirts'); assert.equal(model.pagination.total, 25);
assert.equal(model.result.filters.length, 2); assert.equal(model.title.value, 'لباس');
assert.equal(model.params({ filter: true }).has('sort'), false); assert.equal(model.params({ filter: true }).has('page'), false);
assert.equal(model.params({ url: true }).has('category'), false);
console.log('PASS category detail/tree, active-category facets, sorting and initial URL restoration');

let request = model.toggleValue('color', 'black');
assert.equal(model.state.page, 1); assert.equal(model.params().get('color'), 'black'); assert.ok(browser.location.search.includes('color=black')); settle(); await request;
request = model.goToPage(3); assert.equal(model.params().get('page'), '3'); settle(); await request;
assert.equal(model.pagination.currentPage, 3);
request = model.clearFilters(); assert.equal(model.state.category, 'clothing'); assert.equal(model.state.sort, 'price_asc'); assert.equal(model.state.search, ''); assert.equal(Object.keys(model.state.values).length, 0); assert.equal(model.state.page, 1); settle(); await request;
assert.equal(browser.location.pathname, '/c/clothing'); assert.ok(!browser.location.search.includes('size='));
assert.equal(discoverySorts.length, 6);
console.log('PASS filter toggle, page reset, pagination and clear-all preserves category and sort');

const old = model.selectCategory('shirts'); const oldRequests = queued.splice(0);
const newer = model.selectCategory('bags'); const newRequests = queued.splice(0);
for (const pending of oldRequests) assert.equal(pending.options.signal.aborted, true);
for (const pending of newRequests) pending.resolve({ data: pending.url.endsWith('/filters') ? { data: [{ slug: 'bag-type', name: 'نوع کیف', type: 'select', values: [] }] } : pending.url === '/api/products' ? { data: [{ ...product, id: 2, name: 'کیف' }], meta: { total: 1, current_page: 1, last_page: 1 } } : { data: { name: 'کیف', slug: 'bags' } } });
await newer;
for (const pending of oldRequests) pending.resolve({ data: pending.url.endsWith('/filters') ? { data: facets } : pending.url === '/api/products' ? { data: [product] } : { data: { name: 'پیراهن', slug: 'shirts' } } });
await old;
assert.equal(model.title.value, 'کیف'); assert.equal(model.result.filters[0].slug, 'bag-type'); assert.equal(model.products.value[0].id, 2);
assert.equal(browser.location.pathname, '/c/bags');
console.log('PASS category-switch cancellation and late detail/filter/product response isolation');

request = model.refresh(); const failureRequests = queued.splice(0);
failureRequests.find((pending) => pending.url.endsWith('/filters')).reject(new Error('private technical failure'));
failureRequests.find((pending) => pending.url === '/api/products').reject(new Error('private technical failure'));
await request; assert.ok(model.result.error); assert.ok(model.result.filtersError); assert.ok(!model.result.error.includes('private'));
request = model.refresh(); settle(); await request; assert.equal(model.result.error, ''); assert.equal(model.result.filtersError, '');
request = model.loadProducts(); queued.shift().resolve({ data: { data: [], meta: { total: 0, current_page: 1, last_page: 1 } } }); await request;
assert.equal(model.products.value.length, 0); assert.equal(model.pagination.total, 0);
browser.location.pathname = '/c/shirts'; browser.location.search = '?size=m&page=2&sort=name_desc';
request = events.get('popstate')(); settle(); await request;
assert.equal(model.state.category, 'shirts'); assert.equal(model.state.page, 2); assert.equal(model.state.sort, 'name_desc'); assert.equal(model.state.values.size[0], 'm');
console.log('PASS errors, retry, empty result and Back/Forward URL/query restoration');

const { default: filterComponent } = await namespace('resources/js/components/discovery/DiscoveryFilters.vue');
let html = await renderToString(Vue.createSSRApp({ render: () => Vue.h(filterComponent, { model, prefix: 'test' }) }));
assert.ok(html.includes('سایز')); assert.ok(html.includes('aria-pressed="true"')); assert.ok(html.includes('test-category-search')); assert.ok(html.includes('aria-current="page"'));
model.result.filtersError = 'فیلترها دریافت نشدند';
html = await renderToString(Vue.createSSRApp({ render: () => Vue.h(filterComponent, { model }) })); assert.ok(html.includes('role="alert"')); assert.ok(html.includes('تلاش مجدد'));
const css = postcss.parse(await readFile(resolve(root, 'resources/css/discovery.css'), 'utf8'));
css.walkRules((rule) => { for (const selector of postcss.list.comma(rule.selector)) assert.ok(selector.startsWith('.storefront-theme '), selector); });
assert.ok(css.toString().includes('prefers-reduced-motion')); assert.ok(css.toString().includes('safe-area-inset-bottom'));
request = model.loadProducts(); const disposedRequest = queued.shift(); model.dispose(); assert.equal(disposedRequest.options.signal.aborted, true); disposedRequest.resolve({ data: { data: [product] } }); await request;
assert.equal(events.has('popstate'), false);
console.log('PASS actual filter/tree templates, selected state, accessibility, CSS scope, safe area, disposal');
console.log('5 verification groups passed; no browser layout claim.');
