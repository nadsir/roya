// Run: node --experimental-vm-modules tests/frontend/phase1-shell.test.mjs
// Behavioral checks against the actual Vue setup code; no database writes.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SourceTextModule, SyntheticModule, createContext } from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import postcss from 'postcss';
import * as Vue from 'vue';

const project = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const storage = new Map();
const listeners = new Map();
const timers = new Map();
let timerId = 0;
const classes = new Set();
const root = { dataset: {}, classList: { add: (name) => classes.add(name), toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name) } };
const document = { title: 'CAR', activeElement: null, body: { style: { overflow: 'auto' } }, getElementById: () => root, addEventListener() {}, removeEventListener() {} };
const location = { pathname: '/store', href: '' };
const requests = [];
let categoryCalls = 0;
let rejectCategories = false;
const axios = {
    isCancel: (error) => error?.code === 'ERR_CANCELED',
    get: async (url, options) => {
        if (url === '/api/categories/tree') {
            categoryCalls += 1;
            if (rejectCategories) throw new Error('offline');
            return { data: { data: [
                { name: 'لباس', slug: 'clothing', children: [{ name: 'پیراهن', slug: 'dresses', children: [] }] },
                { name: 'قطعات خودرو', slug: 'car-parts', children: [{ name: 'ترمز', slug: 'brakes' }] },
            ] } };
        }
        // Intentionally ignore abort to verify the stale-response guard as well.
        return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
    },
};
const context = createContext({
    console, document, location, AbortController,
    localStorage: { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key) },
    window: { addEventListener: (name, fn) => listeners.set(name, fn), removeEventListener() {} },
    matchMedia: () => ({ matches: true }),
    setTimeout: (fn) => { const id = ++timerId; timers.set(id, fn); return id; },
    clearTimeout: (id) => timers.delete(id),
});
const modules = new Map();
function synthetic(name, exports) {
    return new SyntheticModule(Object.keys(exports), function () { for (const [key, value] of Object.entries(exports)) this.setExport(key, value); }, { context, identifier: name });
}
modules.set('vue', synthetic('vue', Vue));
modules.set('axios', synthetic('axios', { default: axios }));
const exposure = {
    'SearchOverlay.vue': 'query, products, loading, error, recent, schedule, viewResults, remember, clearRecent',
    'ShellDialog.vue': 'dialog',
    'MegaMenu.vue': 'open, activeIndex, hover, toggle, close, enter',
};
async function loadModule(id) {
    if (modules.has(id)) return modules.get(id);
    let source = await readFile(id, 'utf8');
    if (id.endsWith('.vue')) {
        source = source.replace('</script>', `\ndefineExpose({ ${exposure[id.split(/[\\/]/).at(-1)] || ''} });\n</script>`);
        const { descriptor } = parse(source, { filename: id });
        descriptor.template = null;
        source = compileScript(descriptor, { id: 'shell-test' }).content;
    }
    const module = new SourceTextModule(source, { context, identifier: id });
    modules.set(id, module);
    await module.link((specifier, parent) => loadModule(modules.has(specifier) ? specifier : resolve(dirname(parent.identifier), specifier)));
    return module;
}
async function namespace(relative) {
    const module = await loadModule(resolve(project, relative));
    if (module.status !== 'evaluated') await module.evaluate();
    return module.namespace;
}
const renderer = Vue.createRenderer({
    createComment: () => ({}), createText: (text) => ({ text }), createElement: (tag) => ({ tag, children: [] }),
    insert: (node, parent) => { node.parent = parent; }, remove: () => {}, parentNode: (node) => node.parent,
    nextSibling: () => null, setText: () => {}, setElementText: () => {}, patchProp: () => {},
});
const warnings = [];
async function mount(relative, initialProps) {
    const { default: component } = await namespace(relative);
    component.render = () => null;
    const props = Vue.reactive(initialProps);
    const exposed = Vue.ref();
    const app = renderer.createApp({ render: () => Vue.h(component, { ...props, ref: exposed }) });
    app.config.warnHandler = (message) => warnings.push(message);
    app.mount({});
    await Vue.nextTick();
    return { proxy: exposed.value, props, unmount: () => app.unmount() };
}
async function flush() { await Vue.nextTick(); await Promise.resolve(); await Vue.nextTick(); }
function runTimers() { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach((fn) => fn()); }
const passed = [];

// Shared categories preserve real records, remove an entire automotive branch,
// deduplicate concurrent requests, and recover from an API error.
const catalog = await namespace('resources/js/catalog-state.js');
await Promise.all([catalog.loadCatalog(), catalog.loadCatalog()]);
assert.equal(categoryCalls, 1);
assert.deepEqual(JSON.parse(JSON.stringify(catalog.catalog.categories)).map((item) => item.slug), ['clothing']);
assert.equal(catalog.catalog.categories[0].children[0].slug, 'dresses');
assert.equal(catalog.isStorefrontProduct({ name: 'لنت', categories: [{ slug: 'brakes' }] }), false);
assert.equal(catalog.isStorefrontProduct({ name: 'پیراهن', categories: [{ slug: 'dresses' }] }), true);
rejectCategories = true;
await catalog.loadCatalog({ retry: true });
assert.equal(catalog.catalog.status, 'error');
rejectCategories = false;
await catalog.loadCatalog({ retry: true });
assert.equal(catalog.catalog.status, 'ready');
passed.push('category cache, real hierarchy, automotive exclusion, error and retry');

// Search race: a server that still responds after abort cannot replace newer results.
const search = await mount('resources/js/components/SearchOverlay.vue', { open: false });
search.props.open = true; await flush();
search.proxy.query = 'پیراهن'; await flush();
assert.equal(search.proxy.loading, true);
assert.equal(requests.length, 0);
runTimers(); await flush();
assert.equal(requests.length, 1);
search.proxy.query = 'کیف'; await flush();
assert.equal(requests[0].options.signal.aborted, true);
runTimers(); await flush();
requests[1].resolve({ data: { data: [{ id: 2, name: 'کیف جدید', categories: [] }] } }); await flush();
requests[0].resolve({ data: { data: [{ id: 1, name: 'پاسخ قدیمی', categories: [] }] } }); await flush();
assert.equal(search.proxy.products[0].id, 2);
assert.equal(search.proxy.loading, false);
search.proxy.query = 'بوت'; await flush(); runTimers(); await flush();
requests[2].reject(new Error('offline')); await flush();
assert.ok(search.proxy.error.includes('جستجو انجام نشد'));
search.proxy.schedule(); runTimers(); await flush();
requests[3].resolve({ data: { data: [] } }); await flush();
assert.equal(search.proxy.error, '');
assert.equal(search.proxy.products.length, 0);
search.proxy.query = 'مانتو'; await flush(); runTimers(); await flush();
search.props.open = false; await flush();
requests[4].resolve({ data: { data: [{ id: 9, name: 'پاسخ بعد از بسته شدن' }] } }); await flush();
assert.equal(search.proxy.products.length, 0);
passed.push('search debounce, cancellation, stale responses, close, error and retry');

search.props.open = true; await flush();
for (let n = 0; n < 8; n++) { search.proxy.query = `جستجو ${n}`; search.proxy.remember(); }
assert.equal(search.proxy.recent.length, 6);
search.proxy.remember(); assert.equal(search.proxy.recent.length, 6);
search.proxy.query = 'کیف چرمی'; search.proxy.viewResults();
assert.equal(location.href, '/store?search=' + encodeURIComponent('کیف چرمی'));
search.unmount(); await flush();
const reopened = await mount('resources/js/components/SearchOverlay.vue', { open: false });
reopened.props.open = true; await flush();
assert.equal(reopened.proxy.recent[0], 'کیف چرمی');
reopened.proxy.clearRecent(); assert.equal(storage.has('gallery-recent-searches'), false);
reopened.unmount();
passed.push('recent searches persistence, limit, deduplication and encoded result route');

// Theme state belongs to #app; admin initialization leaves its root untouched.
const theme = await namespace('resources/js/storefront-theme.js');
location.pathname = '/admin'; theme.initStorefrontTheme(); assert.equal(classes.size, 0);
assert.equal(document.title, 'CAR');
location.pathname = '/store'; theme.initStorefrontTheme(); assert.equal(root.dataset.theme, 'light');
assert.equal(document.title, 'گالری | پوشاک و اکسسوری زنانه');
theme.toggleStorefrontTheme(); assert.equal(root.dataset.theme, 'dark'); assert.equal(classes.has('dark'), true);
listeners.get('storage')({ key: 'turbopart-theme', newValue: 'light' }); assert.equal(root.dataset.theme, 'light');
passed.push('admin isolation, dark/light preference and cross-tab theme sync');

// Modal lifecycle prioritizes autofocus and restores both scroll and trigger focus.
const modal = await mount('resources/js/components/ShellDialog.vue', { open: false, label: 'test' });
let autofocusCount = 0;
let restoreCount = 0;
const previous = { isConnected: true, focus: () => { restoreCount++; } };
const fakeDialog = { open: false, showModal() { this.open = true; }, close() { this.open = false; }, querySelector: (selector) => selector === '[autofocus]' ? { focus: () => { autofocusCount++; } } : null };
modal.proxy.dialog = fakeDialog;
document.activeElement = previous;
modal.props.open = true; await flush();
assert.equal(fakeDialog.open, true); assert.equal(autofocusCount, 1); assert.equal(document.body.style.overflow, 'hidden');
modal.props.open = false; await flush();
assert.equal(fakeDialog.open, false); assert.equal(restoreCount, 1); assert.equal(document.body.style.overflow, 'auto');
modal.props.open = true; await flush(); modal.unmount();
assert.equal(document.body.style.overflow, 'auto');
passed.push('dialog autofocus, scroll lock, close and unmount cleanup');

const menu = await mount('resources/js/components/MegaMenu.vue', { variant: 'desktop' });
menu.proxy.hover(); assert.equal(menu.proxy.open, true);
menu.proxy.toggle(); assert.equal(menu.proxy.open, true);
menu.proxy.toggle(); assert.equal(menu.proxy.open, false);
let prevented = false;
await menu.proxy.enter({ key: 'ArrowDown', preventDefault: () => { prevented = true; } });
assert.equal(prevented, true); assert.equal(menu.proxy.open, true);
menu.unmount();
passed.push('mega menu hover/click interaction and keyboard open');

// CSS scope and contrast are checked independently of a connected browser.
const css = postcss.parse(await readFile(resolve(project, 'resources/css/storefront.css'), 'utf8'));
css.walkRules((rule) => {
    if (rule.parent.type === 'atrule' && rule.parent.name.endsWith('keyframes')) return;
    assert.ok(postcss.list.comma(rule.selector).every((selector) => selector.trim().startsWith('.storefront-theme')), `Unscoped selector: ${rule.selector}`);
});
function luminance(hex) {
    const rgb = hex.slice(1).match(/../g).map((part) => parseInt(part, 16) / 255).map((value) => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
    return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
}
function contrast(a, b) { const values = [luminance(a), luminance(b)].sort((a, b) => b - a); return (values[0] + .05) / (values[1] + .05); }
for (const selector of ['.storefront-theme', '.storefront-theme[data-theme="dark"]']) {
    const rule = css.nodes.find((node) => node.type === 'rule' && node.selector === selector);
    const tokens = Object.fromEntries(rule.nodes.filter((node) => node.type === 'decl').map((node) => [node.prop, node.value]));
    for (const text of ['--sf-text', '--sf-text-secondary', '--sf-text-muted']) assert.ok(contrast(tokens[text], tokens['--sf-surface']) >= 4.5, `${selector} ${text} contrast`);
    assert.ok(contrast(tokens['--sf-accent'], tokens['--sf-on-accent']) >= 4.5);
}
assert.equal(warnings.length, 0, warnings.join('\n'));
passed.push('CSS scope, light/dark text and button contrast, no Vue setup warnings');
passed.forEach((name) => console.log('PASS ' + name));
console.log(`${passed.length} verification groups passed.`);
