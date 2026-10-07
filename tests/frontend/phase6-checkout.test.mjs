// Run: node --experimental-vm-modules tests/frontend/phase6-checkout.test.mjs
// Behaviour checks against the real checkout modules, components and existing payment flow.
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
const timers = new Map();
let timerId = 0;
const calls = [];
const redirects = [];
const location = { pathname: '/checkout', href: '', search: '', replace: (url) => redirects.push(url) };
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
    confirm: () => true,
    addEventListener() {},
    removeEventListener() {},
    dispatchEvent() {},
};
let responder = () => new Promise(() => {});
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
const payUrl = 'https://gateway.test/pay/tx-42';

/* -------------------------------- loader --------------------------------- */
const cache = new Map();
let freshId = 0;
function stub(name, exports) {
    return new SyntheticModule(Object.keys(exports), function () {
        for (const [key, value] of Object.entries(exports)) this.setExport(key, value);
    }, { context, identifier: name });
}
cache.set('vue', stub('vue', Vue));
cache.set('axios', stub('axios', {
    default: {
        post(url, body) { calls.push({ url, body }); return responder(url, body); },
        get(url, config) { calls.push({ url, body: config }); return responder(url, config); },
        defaults: { headers: { common: {} } },
    },
}));
const auth = Vue.reactive({ user: null, loading: false });
cache.set(resolve(root, 'resources/js/auth-state.js'), stub('auth', {
    state: auth,
    isLoggedIn: Vue.computed(() => Boolean(auth.user)),
    loadUser: async () => {},
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
        if (expose) source = source.replace('</script>', '\ndefineExpose({ submitOrder, retryPayment, phase, createdOrder, payError, form, validationErrors, lineErrors });\n</script>');
        const { descriptor } = parse(source, { filename: id });
        if (expose) { descriptor.template = null; source = compileScript(descriptor, { id: 'phase6-expose' }).content; }
        else source = compileScript(descriptor, { id: 'phase6-test', inlineTemplate: true }).content;
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
async function flush() {
    await Vue.nextTick();
    await sleep(0);
    await Vue.nextTick();
    for (const [id, fn] of [...timers]) { timers.delete(id); fn(); }
    await Vue.nextTick();
}

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

/* A fresh instance per scenario so no flow inherits the previous order or phase. */
async function mountPage() {
    const module = await load(resolve(root, 'resources/js/CheckoutPage.vue'), true, true);
    if (module.status !== 'evaluated') await module.evaluate();
    const ref = Vue.ref();
    const exposed = module.namespace.default;
    exposed.render = () => null;
    const app = renderer.createApp({ render: () => Vue.h(exposed, { ref }) });
    app.mount({});
    await flush();
    return { page: ref.value, dispose: () => app.unmount() };
}

const checkoutCalls = () => calls.filter((call) => call.url === '/api/customer/checkout');
const payCalls = () => calls.filter((call) => /\/pay$/.test(call.url));

/* -------------------- 1. presentation helpers mirror the API -------------- */
const checkout = await namespace('resources/js/checkout-presentation.js');
const cart = await namespace('resources/js/cart-state.js');
const presentation = await namespace('resources/js/product-presentation.js');
const { formatPrice } = presentation;

assert.deepEqual(
    Array.from(checkout.CHECKOUT_CONTACT_FIELDS, (field) => field.key),
    ['customer_name', 'customer_phone', 'customer_email'],
    'the contact section matches the fields the checkout endpoint validates',
);
assert.deepEqual(
    Array.from(checkout.CHECKOUT_SHIPPING_FIELDS, (field) => field.key),
    ['shipping_province', 'shipping_city', 'shipping_postal_code', 'shipping_address', 'notes'],
    'the shipping section matches the address fields the checkout endpoint validates',
);
const allFields = checkout.CHECKOUT_CONTACT_FIELDS.concat(checkout.CHECKOUT_SHIPPING_FIELDS);
const allKeys = allFields.map((field) => field.key);
assert.equal(new Set(allKeys).size, allKeys.length, 'no field is rendered twice');
assert.ok(allFields.every((field) => field.label), 'every field is labelled');
const phone = allFields.find((field) => field.key === 'customer_phone');
assert.equal(phone.maxlength, 32, 'the phone field carries the server max length');
assert.equal(phone.inputmode, 'tel');
assert.equal(phone.dir, 'ltr');
const email = allFields.find((field) => field.key === 'customer_email');
assert.equal(email.type, 'email');
const postal = allFields.find((field) => field.key === 'shipping_postal_code');
assert.equal(postal.inputmode, 'numeric');
assert.equal(allFields.find((field) => field.key === 'notes').optional, true, 'notes stays optional');
assert.equal(allFields.find((field) => field.key === 'shipping_address').control, 'textarea');

assert.equal(checkout.checkoutCount([coatItem, scarfItem, { quantity: 'x' }, { quantity: -4 }]), 5, 'broken quantities fall back to one unit');
assert.equal(checkout.checkoutCount(null), 0);
const totals = checkout.checkoutTotals([coatItem, scarfItem], 2020000);
assert.equal(totals.count, 3);
assert.equal(totals.subtotal, 2020000);
assert.equal(totals.total, 2020000);
assert.equal(totals.discount, 0, 'no discount is invented; the backend stores zero');
assert.equal(totals.shipping, 0, 'no shipping cost is invented; the backend stores zero');
assert.equal(checkout.checkoutTotals([coatItem], Number('bad')).total, 0, 'a broken cart total never leaks NaN');

assert.equal(checkout.fieldError({ customer_name: ['فیلد الزامی است.'] }, 'customer_name'), 'فیلد الزامی است.');
assert.equal(checkout.fieldError({ customer_name: ['a', 'b'] }, 'customer_name'), 'a', 'the first message is the field error');
assert.equal(checkout.fieldError({ customer_name: ['a'] }, 'customer_email'), '');
const lineErrors = (errors, index) => Array.from(checkout.lineErrorMessages(errors, index), (entry) => ({ field: entry.field, message: entry.message }));
assert.deepEqual(
    lineErrors({ 'items.0.product_id': ['محصول موجود یا فعال نیست.'] }, 0),
    [{ field: 'product_id', message: 'محصول موجود یا فعال نیست.' }],
    'a line error keeps the message the backend returned',
);
assert.deepEqual(lineErrors({ 'items.0.quantity': ['موجودی کافی نیست.'] }, 1), [], 'line errors never leak to another line');
assert.equal(checkout.errorSummary({ customer_name: ['خطا'], 'items.2.quantity': ['خطای دوم'] }).length, 2);

assert.equal(checkout.submitLabel('idle'), 'پرداخت و تکمیل سفارش', 'the primary action names what happens');
assert.equal(checkout.submitLabel('ordering'), 'در حال ثبت سفارش…');
assert.equal(checkout.submitLabel('paying'), 'در حال اتصال به درگاه پرداخت…');
assert.equal(checkout.submitLabel('pay-failed'), 'پرداخت انجام نشد');
console.log('PASS field definitions mirror the checkout API, totals never invent values and labels are phase aware');

/* --------------------- 2. checkout field accessibility -------------------- */
const { default: fieldComponent } = await namespace('resources/js/components/checkout/CheckoutField.vue');
const renderField = (field, props = {}) => renderToString(Vue.createSSRApp({
    render: () => Vue.h(fieldComponent, { field, modelValue: '', ...props }),
}));

let html = await renderField(phone, { modelValue: '09120000000', error: 'شماره تلفن معتبر نیست.' });
assert.ok(html.includes('for="checkout-customer_phone"'), 'the label points at the control');
assert.ok(html.includes('id="checkout-customer_phone"'));
assert.ok(html.includes('type="tel"') && html.includes('inputmode="tel"'));
assert.ok(html.includes('dir="ltr"'));
assert.ok(html.includes('maxlength="32"'));
assert.ok(html.includes('value="09120000000"'));
assert.ok(html.includes('aria-invalid="true"'), 'an invalid field is announced');
assert.ok(html.includes('id="checkout-customer_phone-error"'));
assert.ok(html.includes('aria-describedby="checkout-customer_phone-error"'), 'the error is wired to the control');
assert.ok(html.includes('role="alert"'));
assert.ok(html.includes('شماره تلفن معتبر نیست.'));

html = await renderField(allFields.find((field) => field.key === 'notes'));
assert.ok(html.includes('اختیاری'), 'the optional field says so');
assert.ok(!html.includes('aria-invalid'), 'a clean field is not marked invalid');
assert.ok(!html.includes('role="alert"'));
assert.ok(!html.includes('required'), 'an optional field is not required');

html = await renderField(allFields.find((field) => field.key === 'shipping_address'), { modelValue: 'تهران، خیابان ولیعصر' });
assert.ok(html.includes('<textarea'), 'the address renders as a textarea');
assert.ok(html.includes('maxlength="2000"'));
assert.ok(html.includes('required'));
assert.ok(html.includes('تهران، خیابان ولیعصر'));
console.log('PASS fields expose label wiring, required state, ltr inputs, error announcements and textareas');

/* -------------------- 3. summary line and summary card --------------------- */
const { default: lineComponent } = await namespace('resources/js/components/checkout/CheckoutLineRow.vue');
const { default: summaryComponent } = await namespace('resources/js/components/checkout/OrderSummaryCard.vue');
const renderLine = (item, errors = []) => renderToString(Vue.createSSRApp({
    render: () => Vue.h(lineComponent, { item, errors }),
}));
const renderSummary = (props) => renderToString(Vue.createSSRApp({ render: () => Vue.h(summaryComponent, props) }));

html = await renderLine(coatItem);
assert.ok(html.includes('مانتو کتی پشمی'));
assert.ok(html.includes('href="/products/7"'));
assert.ok(html.includes('مشکی') && html.includes('رنگ') && html.includes('M') && html.includes('سایز'), 'variant axes read as Persian labels');
assert.ok(html.includes('MNT-1-S-BLK'));
assert.ok(html.includes('تعداد:'));
assert.ok(html.includes(formatPrice(1700000)), 'the line shows the line total, not the unit price');
assert.ok(html.includes(`قیمت واحد: ${formatPrice(850000)}`), 'the unit price is labelled as the unit price');
assert.ok(!html.includes('افزایش تعداد'), 'checkout never edits the cart quantity');
assert.ok(!html.includes('fa-trash-can'), 'checkout never removes a line');
assert.ok(!html.includes('<button'), 'the summary line is read only');
assert.ok(html.includes('co-line'));

const messy = { ...coatItem, key: 'product_9', price: 'bad', quantity: 0, attributes: 'broken' };
const messyHtml = await renderLine(messy);
assert.ok(!messyHtml.includes('NaN'), 'a broken line price never reaches the markup as NaN');
assert.ok(!messyHtml.includes('undefined'));
html = await renderLine(scarfItem, [{ field: 'quantity', message: 'موجودی کافی نیست.' }]);
assert.ok(html.includes('/images/placeholder.svg'), 'a line without an image falls back to the shared placeholder');
assert.ok(html.includes('موجودی کافی نیست.'), 'a backend line error is shown on its own line');
assert.ok(html.includes('co-line-error'));

html = await renderSummary({ items: [coatItem, scarfItem], count: 3, subtotal: 2020000, total: 2020000, lineErrors: [[], []] });
assert.ok(html.includes('خلاصه سفارش'));
assert.ok(html.includes('تعداد کالاها'));
assert.ok(html.includes('مبلغ نهایی'));
assert.ok(html.includes(formatPrice(2020000)), 'the summary total is the cart total');
assert.ok(html.includes('تخفیف') && html.includes('هزینه ارسال'));
assert.ok(html.includes(checkout.CHECKOUT_AUTHORITY_NOTE), 'the page says the server is authoritative');
assert.ok(!html.includes('رایگان'), 'no free shipping is promised');
console.log('PASS order lines render read only with line totals, Persian variants, image fallback and backend line errors');
console.log('PASS the summary card shows the cart total, honest zero rows and the server authority note');

/* --------------------------- 4. checkout page ----------------------------- */
const { default: pageComponent } = await namespace('resources/js/CheckoutPage.vue');
const renderPage = () => renderToString(Vue.createSSRApp({ render: () => Vue.h(pageComponent) }));

auth.user = { id: 3, name: 'زهرا محمدی', email: 'zahra@example.com' };
auth.loading = false;
cart.clearCart();
html = await renderPage();
assert.ok(html.includes('سبد خرید شما خالی است'), 'an empty cart explains itself');
assert.ok(html.includes('مشاهده محصولات'));
assert.ok(!html.includes('id="checkout-form"'), 'there is no form without items');

cart.addToCart(coatItem);
cart.addToCart(scarfItem);
await flush();
html = await renderPage();
assert.ok(html.includes('تکمیل خرید'), 'the page keeps its heading');
assert.ok(html.includes('aria-label="مسیر صفحه"'));
assert.ok(html.includes('aria-current="page">تکمیل خرید'));
assert.ok(html.includes('aria-current="step"'), 'the checkout step is marked current');
assert.ok(html.includes('اطلاعات گیرنده') && html.includes('آدرس ارسال') && html.includes('روش پرداخت'));
for (const key of allKeys) assert.ok(html.includes(`id="checkout-${key}"`), `${key} renders with its own control`);
assert.ok(html.includes('پرداخت اینترنتی از طریق درگاه بانکی'), 'the only real payment method is named');
assert.ok(!html.includes('پرداخت در محل') && !html.includes('کیف پول'), 'no payment method is invented');
assert.ok(html.includes('پرداخت و تکمیل سفارش'), 'the primary action names the payment');
assert.ok(html.includes('form="checkout-form"'), 'the mobile bar submits the same form');
const submitTag = (html.match(/<button[^>]*co-submit[^>]*>/) || [])[0] || '';
assert.ok(submitTag.includes('type="submit"'), 'the primary action submits the form');
assert.ok(!submitTag.includes('disabled'), 'the primary action is enabled while the form is idle');
assert.ok(html.includes('href="/cart"'), 'the cart stays reachable');
assert.ok(html.includes('value="زهرا محمدی"'), 'the account profile pre-fills the name');
for (const legacy of ['bg-cream', 'rounded-xl', 'text-ink', 'brand-accent', 'text-slate-500']) {
    assert.ok(!html.includes(legacy), `the legacy theme class ${legacy} is gone`);
}
console.log('PASS checkout page: auth and empty states, step marker, every field, the real payment method and no legacy classes');

/* the auth gate is preserved: a signed out visitor is sent to the existing login */
auth.user = null;
const signedOut = await mountPage();
assert.ok(redirects.includes('/login?redirect=/checkout'), 'an unauthenticated visitor is redirected to the existing login');
assert.equal(signedOut.page.form.customer_name, '', 'no account data is pre-filled without a session');
signedOut.dispose();
auth.user = { id: 3, name: 'زهرا محمدی', email: 'zahra@example.com' };

/* -------------------- 5. submit, pay and retry behaviour ------------------ */
/* a rejected order keeps the cart and surfaces the backend messages */
cart.clearCart();
cart.addToCart(coatItem);
cart.addToCart(scarfItem);
await flush();
calls.length = 0;
let rejected = await mountPage();
assert.equal(rejected.page.form.customer_name, 'زهرا محمدی', 'the account profile fills the form once');
responder = async () => { throw { response: { status: 422, data: { message: 'اطلاعات نامعتبر است.', errors: { customer_phone: ['شماره تلفن معتبر نیست.'], 'items.0.quantity': ['موجودی کافی نیست.'] } } } }; };
await rejected.page.submitOrder();
assert.equal(checkoutCalls().length, 1);
assert.equal(payCalls().length, 0, 'no gateway call happens when the order is rejected');
assert.equal(rejected.page.phase, 'idle', 'the action unlocks so the customer can fix the form');
assert.equal(rejected.page.createdOrder, null);
assert.equal(cart.getCartItems().length, 2, 'the cart survives a rejected order');
assert.equal(rejected.page.validationErrors.customer_phone[0], 'شماره تلفن معتبر نیست.');
assert.equal(rejected.page.lineErrors[0].length, 1, 'the failing cart line carries its own message');
assert.deepEqual(Array.from(rejected.page.lineErrors[1]), [], 'the healthy line stays clean');
rejected.dispose();

const { default: alertComponent } = await namespace('resources/js/components/checkout/CheckoutAlert.vue');
const renderAlert = (props) => renderToString(Vue.createSSRApp({ render: () => Vue.h(alertComponent, props) }));
html = await renderAlert({ message: 'اطلاعات نامعتبر است.', errors: checkout.errorSummary({ customer_phone: ['شماره تلفن معتبر نیست.'], 'items.0.quantity': ['موجودی کافی نیست.'] }) });
assert.ok(html.includes('role="alert"'), 'the failure is announced assertively');
assert.ok(html.includes('اطلاعات نامعتبر است.'), 'the alert shows the backend message');
assert.ok(html.includes('شماره تلفن معتبر نیست.'), 'the alert lists the field messages');
assert.ok(html.includes('موجودی کافی نیست.'));
assert.ok(!(await renderAlert({ message: '', errors: [] })).includes('co-alert'), 'no alert without a message');

/* the happy path creates one order, starts the existing payment and leaves for the gateway */
calls.length = 0;
session.clear();
location.href = '';
let happy = await mountPage();
responder = async (url) => (url === '/api/customer/checkout'
    ? { data: { order: { id: 42, user_id: 3, total: '2020000.00' } } }
    : { data: { payment_url: payUrl, authority: 'tx-42' } });
await happy.page.submitOrder();
assert.equal(checkoutCalls().length, 1, 'exactly one order is created');
assert.equal(payCalls().length, 1, 'the existing pay endpoint is used once');
assert.equal(payCalls()[0].url, '/api/customer/orders/42/pay');
assert.equal(location.href, payUrl, 'the browser is sent to the gateway url the backend returned');
assert.equal(happy.page.createdOrder.id, 42);
assert.equal(cart.getCartItems().length, 2, 'the cart is not cleared after order creation');
assert.equal(JSON.parse(session.get('turbopart-order-receipt')).id, 42, 'the existing receipt contract is kept');
const payload = checkoutCalls()[0].body;
assert.deepEqual(Object.keys(payload).sort(), ['customer_email', 'customer_name', 'customer_phone', 'items', 'notes', 'shipping_address', 'shipping_city', 'shipping_postal_code', 'shipping_province'].sort());
assert.deepEqual(Array.from(payload.items, (item) => ({ product_id: item.product_id, variant_id: item.variant_id, quantity: item.quantity })), [
    { product_id: 7, variant_id: 91, quantity: 2 },
    { product_id: 8, variant_id: null, quantity: 1 },
], 'the browser sends identifiers and quantities only');
assert.ok(!JSON.stringify(payload).includes('price'), 'no client price is sent to the checkout endpoint');
assert.ok(!JSON.stringify(payload).includes('total'), 'no client total is sent to the checkout endpoint');
happy.dispose();

/* a second click while the request is in flight must not create a second order */
cart.clearCart();
cart.addToCart(coatItem);
await flush();
calls.length = 0;
let release;
responder = (url) => (url === '/api/customer/checkout'
    ? new Promise((resolve) => { release = () => resolve({ data: { order: { id: 77, user_id: 3, total: '850000.00' } } }); })
    : Promise.resolve({ data: { payment_url: payUrl } }));
let lockedFlow = await mountPage();
const first = lockedFlow.page.submitOrder();
const second = lockedFlow.page.submitOrder();
assert.equal(checkoutCalls().length, 1, 'a double submit is locked into one order request');
assert.equal(lockedFlow.page.phase, 'ordering');
assert.equal(lockedFlow.page.createdOrder, null, 'no order is treated as created before the response');
release();
await first; await second;
assert.equal(checkoutCalls().length, 1, 'the lock holds until the request settles');
assert.equal(payCalls().length, 1);
lockedFlow.dispose();

/* a failed gateway call keeps the order and retries that same order */
cart.clearCart();
cart.addToCart(coatItem);
cart.addToCart(scarfItem);
await flush();
calls.length = 0;
let failed = await mountPage();
responder = async (url) => {
    if (url === '/api/customer/checkout') return { data: { order: { id: 99, user_id: 3, total: '850000.00' } } };
    throw { response: { status: 502, data: { message: 'اتصال به درگاه پرداخت انجام نشد.' } } };
};
await failed.page.submitOrder();
assert.equal(failed.page.phase, 'pay-failed');
assert.equal(failed.page.createdOrder.id, 99, 'the created order is kept for the retry');
assert.equal(failed.page.payError, 'اتصال به درگاه پرداخت انجام نشد.');
assert.equal(cart.getCartItems().length, 2, 'the cart remains after a failed payment');
failed.dispose();

const { default: pendingComponent } = await namespace('resources/js/components/checkout/PendingPaymentPanel.vue');
const renderPending = (props) => renderToString(Vue.createSSRApp({ render: () => Vue.h(pendingComponent, props) }));
html = await renderPending({ orderId: 99, total: '850000.00', error: 'اتصال به درگاه پرداخت انجام نشد.' });
assert.ok(html.includes('سفارش شما ثبت شد، پرداخت انجام نشد'), 'the failure panel states what happened');
assert.ok(html.includes('#99'), 'the order number is shown so the customer can follow up');
assert.ok(html.includes(formatPrice(850000)));
assert.ok(html.includes('تلاش دوباره برای پرداخت'), 'a retry is offered');
assert.ok(html.includes('href="/orders/99"'));
assert.ok(html.includes('role="alert"'));
const retryTag = (html.match(/<button[^>]*>/) || [])[0] || '';
assert.ok(retryTag.includes('type="button"'), 'the retry never submits the order form again');
assert.ok(!retryTag.includes('disabled'), 'the retry is available when nothing is in flight');
const lockedHtml = await renderPending({ orderId: 99, retrying: true });
assert.ok(lockedHtml.includes('disabled'), 'the retry is locked while in flight');
assert.ok(lockedHtml.includes('در حال اتصال به درگاه…'), 'the locked retry reports progress');
assert.ok(!lockedHtml.includes('تلاش دوباره برای پرداخت'), 'the locked retry does not offer a second click');

calls.length = 0;
location.href = '';
responder = async () => ({ data: { payment_url: payUrl } });
await failed.page.retryPayment();
assert.equal(checkoutCalls().length, 0, 'the retry never creates another order');
assert.equal(payCalls().length, 1);
assert.equal(payCalls()[0].url, '/api/customer/orders/99/pay', 'the retry reuses the same order');
assert.equal(location.href, payUrl);
failed.dispose();
console.log('PASS one order per submission, server identifiers only, gateway redirect, failed payment keeps the order and retries it');

/* -------------------- 6. payment return and the banner --------------------- */
const paid = { id: 42, status: 'confirmed', paid_at: '2026-09-20 10:00:00', payment_ref: 'ref-tx-42' };
const pending = { id: 42, status: 'pending', paid_at: null, payment_ref: null };

let result = checkout.paymentReturnState('?payment=success', paid);
assert.equal(result.tone, 'success');
assert.equal(result.retry, false, 'a paid order is never offered a second payment');
assert.equal(result.reference, 'ref-tx-42');

result = checkout.paymentReturnState('?payment=success', pending);
assert.equal(result.tone, 'failure', 'the query string alone never proves a payment');
assert.equal(result.retry, true, 'an unpaid order can be paid again');
assert.ok(result.message.includes('تا پایان روز'), 'an unverified payment explains the money position');

result = checkout.paymentReturnState('?payment=failed&reason=verification_failed', pending);
assert.equal(result.tone, 'failure');
assert.ok(result.message.includes('تأیید نشد'));
assert.ok(checkout.paymentReturnState('?payment=failed&reason=no_authority', pending).message.includes('شناسه تراکنش'));
assert.ok(checkout.paymentReturnState('?payment=failed&reason=order_not_found', pending).message.includes('یافت نشد'));
assert.ok(checkout.paymentReturnState('?payment=failed', pending).message.includes('تراکنش'));

result = checkout.paymentReturnState('?payment=failed', paid);
assert.equal(result.tone, 'success', 'a verified payment wins over a failed attempt hint');
assert.equal(result.retry, false);

assert.equal(checkout.paymentReturnState('', pending).tone, 'none', 'a normal order visit shows no banner');
assert.equal(checkout.paymentReturnState('?payment=unknown', pending).tone, 'none');
assert.equal(checkout.canRetryPayment(pending), true);
assert.equal(checkout.canRetryPayment(paid), false);
assert.equal(checkout.canRetryPayment({ status: 'cancelled' }), false);

const { default: bannerComponent } = await namespace('resources/js/components/checkout/PaymentResultBanner.vue');
const renderBanner = (state, props = {}) => renderToString(Vue.createSSRApp({
    render: () => Vue.h(bannerComponent, { state, orderId: 42, ...props }),
}));

html = await renderBanner(checkout.paymentReturnState('?payment=success', paid));
assert.ok(html.includes('پرداخت با موفقیت تأیید شد'));
assert.ok(html.includes('role="status"'), 'a success is announced politely');
assert.ok(html.includes('ref-tx-42'), 'the server reference is shown');
assert.ok(!html.includes('تلاش دوباره'), 'a paid order gets no retry button');

html = await renderBanner(checkout.paymentReturnState('?payment=failed&reason=verification_failed', pending));
assert.ok(html.includes('پرداخت انجام نشد'));
assert.ok(html.includes('role="alert"'), 'a failure is announced assertively');
assert.ok(html.includes('تلاش دوباره برای پرداخت'), 'a pending order offers the existing retry');
assert.ok(html.includes('href="/orders/42"'));
const lockedBanner = await renderBanner(checkout.paymentReturnState('?payment=failed&reason=verification_failed', pending), { retrying: true });
assert.ok(lockedBanner.includes('disabled'), 'the banner retry is locked while in flight');
assert.ok(lockedBanner.includes('در حال اتصال به درگاه'), 'the locked retry reports progress');
assert.ok(!(await renderBanner(checkout.paymentReturnState('', pending))).includes('co-result'), 'no banner without a gateway return');
console.log('PASS the browser return is only a hint: paid_at decides success, reasons map to honest copy and pending orders can retry');

/* --------------------- 7. css isolation and wiring ------------------------- */
const files = [
    'resources/js/CheckoutPage.vue',
    'resources/js/OrderSuccessPage.vue',
    'resources/js/OrderDetailPage.vue',
    'resources/js/checkout-presentation.js',
    'resources/js/components/checkout/CheckoutAlert.vue',
    'resources/js/components/checkout/CheckoutField.vue',
    'resources/js/components/checkout/CheckoutLineRow.vue',
    'resources/js/components/checkout/OrderSummaryCard.vue',
    'resources/js/components/checkout/PaymentResultBanner.vue',
    'resources/js/components/checkout/PendingPaymentPanel.vue',
];
const sources = {};
for (const file of files) sources[file] = await readFile(resolve(root, file), 'utf8');

assert.ok(sources['resources/js/CheckoutPage.vue'].includes("import '../css/checkout.css'"));
assert.ok(sources['resources/js/OrderSuccessPage.vue'].includes("import '../css/checkout.css'"));
assert.ok(sources['resources/js/components/checkout/PaymentResultBanner.vue'].includes("import '../../../css/checkout.css'"), 'the banner ships with its styles wherever the order page renders it');

for (const file of files) {
    assert.ok(!sources[file].includes('localStorage'), `${file} must not add storage of its own`);
    assert.ok(!sources[file].includes('vue-router'), `${file} must not introduce a router`);
}
assert.ok(!sources['resources/js/checkout-presentation.js'].includes('axios'), 'presentation helpers stay pure');
for (const file of files.filter((name) => name.startsWith('resources/js/components/'))) {
    assert.ok(!sources[file].includes('axios'), `${file} must not call the API`);
}

/* The order page keeps every payment behaviour Phase 6 added. Its visual design is
   deliberately not asserted here: Phase 7 owns the redesign of that page. */
const orderPage = sources['resources/js/OrderDetailPage.vue'];
assert.ok(orderPage.includes('PaymentResultBanner') && orderPage.includes('paymentReturnState'));
for (const marker of ['وضعیت سفارش', 'لغو سفارش', 'cancelOrder', 'خلاصه مالی', '/api/customer/orders/${id}/pay', 'payment_url']) {
    assert.ok(orderPage.includes(marker), `the order page keeps ${marker}`);
}

/* the existing receipt page still pays through the existing endpoint */
const successPage = sources['resources/js/OrderSuccessPage.vue'];
assert.ok(successPage.includes('turbopart-order-receipt'), 'the receipt contract is unchanged');
assert.ok(successPage.includes('/api/customer/orders/${receipt.value.id}/pay'), 'payment still goes through the existing endpoint');
assert.ok(successPage.includes('data.payment_url'), 'the gateway url still comes from the backend');
assert.ok(successPage.includes('در انتظار پرداخت'), 'the page never claims the payment is done');
assert.ok(!successPage.includes('پرداخت موفق'), 'no fabricated payment success');

const packageJson = JSON.parse(await readFile(resolve(root, 'package.json'), 'utf8'));
assert.deepEqual(Object.keys(packageJson.dependencies), ['vue'], 'no runtime dependency was added');
assert.equal(packageJson.devDependencies['@tailwindcss/vite'], '^4.3.3', 'the toolchain is untouched');

const css = postcss.parse(await readFile(resolve(root, 'resources/css/checkout.css'), 'utf8'));
css.walkRules((rule) => {
    if (rule.parent.type === 'atrule' && rule.parent.name.endsWith('keyframes')) return;
    for (const selector of postcss.list.comma(rule.selector)) assert.ok(selector.startsWith('.storefront-theme '), selector);
});
const cssText = css.toString();
assert.ok(cssText.includes('prefers-reduced-motion'));
assert.ok(cssText.includes('.co-summary'), 'the summary column is part of the checkout stylesheet');
assert.ok(cssText.includes('.co-result'), 'the payment return presentation is part of the checkout stylesheet');
assert.ok(cssText.includes('position: sticky'), 'the summary sticks beside the form on desktop');
assert.ok(cssText.includes('.co-sticky'), 'mobile keeps a sticky total and action');
assert.ok(cssText.includes('.co-pending'), 'the failed payment panel is styled here');
console.log('PASS CSS isolation, existing contracts preserved, no dependency added and the order page keeps the Phase 6 payment behaviour');
console.log('7 verification groups passed; no browser layout claim.');
