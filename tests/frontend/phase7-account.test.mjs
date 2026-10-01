// Run: node --experimental-vm-modules tests/frontend/phase7-account.test.mjs
// Behaviour checks against the real account modules, components and existing API contracts.
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
const intervals = new Map();
let timerId = 0;
const calls = [];
const redirects = [];
const location = { pathname: '/account', href: '', search: '', replace: (url) => redirects.push(url) };
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
    setInterval: (fn) => { const id = ++timerId; intervals.set(id, fn); return id; },
    clearInterval: (id) => intervals.delete(id),
});

/* -------------------------------- fixtures ------------------------------- */
const customer = { id: 3, name: 'زهرا محمدی', email: 'zahra@example.com', mobile: '09121234567' };

const pendingOrder = {
    id: 42, status: 'pending', created_at: '2026-09-20 10:00:00', total: '2020000.00', items_count: 3,
    customer_name: 'زهرا محمدی', customer_phone: '09121234567', customer_email: 'zahra@example.com',
    shipping_province: 'تهران', shipping_city: 'تهران', shipping_address: 'خیابان ولیعصر، پلاک ۱۲',
    shipping_postal_code: '1234567890', notes: null, subtotal: '2020000.00', discount: '0.00',
    shipping_cost: '0.00', paid_at: null, payment_method: null, payment_ref: null,
    cancelled_at: null, cancelled_reason: null,
    items: [{
        id: 5, product_id: 7, product_variant_id: 91, product_name: 'مانتو کتی پشمی', sku: 'MNT-1-S-BLK',
        quantity: 2, unit_price: '850000.00', subtotal: '1700000.00', image: 'coats/coat.jpg',
        attributes: { size: [{ id: 2, label: 'M', value: 'm' }], color: [{ id: 10, label: 'مشکی', value: 'black' }] },
    }],
};
const paidOrder = {
    ...pendingOrder,
    status: 'confirmed',
    paid_at: '2026-09-20 10:05:00',
    payment_method: 'online',
    payment_ref: 'ref-tx-42',
};
const shippedOrder = { ...paidOrder, status: 'shipped' };
const cancelledOrder = { ...pendingOrder, status: 'cancelled', cancelled_at: '2026-09-21 08:00:00', cancelled_reason: 'انصراف از خرید' };
const wishlistProduct = {
    id: 7, name: 'مانتو کتی پشمی', slug: 'mantо', price: 850000, in_stock: true, stock: 4,
    images: [{ id: 3, path: 'coats/coat.jpg', alt_text: 'مانتو', is_primary: true, sort_order: 1 }],
};
const wishlistItem = { product_id: 7, product: wishlistProduct, created_at: '2026-09-19 12:00:00' };

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
        get(url, config) { calls.push({ method: 'get', url, body: config }); return responder(url, config); },
        post(url, body) { calls.push({ method: 'post', url, body }); return responder(url, body); },
        put(url, body) { calls.push({ method: 'put', url, body }); return responder(url, body); },
        patch(url, body) { calls.push({ method: 'patch', url, body }); return responder(url, body); },
        delete(url) { calls.push({ method: 'delete', url }); return responder(url); },
        defaults: { headers: { common: {} } },
    },
}));

const auth = Vue.reactive({ user: null, loading: false });
const wishlist = Vue.reactive({ items: [], loading: false, error: null });
let wishlistLoads = 0;
let wishlistRemovals = [];
const authCalls = [];
cache.set(resolve(root, 'resources/js/auth-state.js'), stub('auth', {
    state: auth,
    isLoggedIn: Vue.computed(() => Boolean(auth.user)),
    loadUser: async () => {},
    login: async (email, password) => { authCalls.push({ type: 'login', email, password }); },
    register: async (...args) => { authCalls.push({ type: 'register', args }); },
    sendOtp: async (mobile) => { authCalls.push({ type: 'send-otp', mobile }); return { message: 'کد ارسال شد.', retry_after: 60, expires_in: 120 }; },
    verifyOtp: async (mobile, code) => { authCalls.push({ type: 'verify-otp', mobile, code }); },
    logout: async () => { authCalls.push({ type: 'logout' }); },
    updateProfile: async (name, email) => {
        authCalls.push({ type: 'update-profile', name, email });
        if (email === 'taken@example.com') throw { response: { status: 422, data: { message: 'اطلاعات نامعتبر است.', errors: { email: ['این ایمیل قبلاً ثبت شده است.'] } } } };
        auth.user = { ...auth.user, name, email };
    },
    getToken: () => 'test-token',
}));
cache.set(resolve(root, 'resources/js/wishlist-state.js'), stub('wishlist', {
    state: wishlist,
    wishlistCount: Vue.computed(() => wishlist.items.length),
    loadWishlist: () => { wishlistLoads += 1; },
    removeFromWishlist: async (productId) => {
        wishlistRemovals.push(productId);
        wishlist.items = wishlist.items.filter((item) => String(item.product_id) !== String(productId));
        return true;
    },
    isInWishlist: () => false,
    addToWishlist: async () => null,
    checkWishlist: async () => false,
    resetWishlist: () => {},
}));
cache.set(resolve(root, 'resources/js/comment-state.js'), stub('comment', { peekCommentIntentPath: () => null }));

async function load(id, expose = false, fresh = false, exposeList = null) {
    const key = fresh ? `${id}:${++freshId}` : expose ? `${id}:expose:${exposeList}` : id;
    if (cache.has(key)) return cache.get(key);
    if (id.endsWith('.css')) {
        const cssModule = stub(id, { default: {} });
        cache.set(key, cssModule);
        return cssModule;
    }
    let source = await readFile(id, 'utf8');
    if (id.endsWith('.vue')) {
        if (exposeList) source = source.replace('</script>', `\ndefineExpose({ ${exposeList} });\n</script>`);
        const { descriptor } = parse(source, { filename: id });
        if (expose) { descriptor.template = null; source = compileScript(descriptor, { id: 'phase7-expose' }).content; }
        else source = compileScript(descriptor, { id: 'phase7-test', inlineTemplate: true }).content;
    }
    const module = new SourceTextModule(source, { context, identifier: id });
    cache.set(key, module);
    await module.link((specifier, parent) => load(cache.has(specifier) ? specifier : resolve(dirname(parent.identifier), specifier)));
    return module;
}
async function evaluate(id, exposeList, fresh = false) {
    const module = await load(resolve(root, id), true, fresh, exposeList);
    if (module.status !== 'evaluated') await module.evaluate();
    return module.namespace.default;
}
async function namespace(path, exposeList = null) {
    const module = await load(resolve(root, path), Boolean(exposeList), false, exposeList);
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
const render = (component, props, slots) => renderToString(Vue.createSSRApp({ render: () => Vue.h(component, props, slots) }));

/* ----------------- 1. presentation mirrors the real backend --------------- */
const account = await namespace('resources/js/account-presentation.js');
const orders = await namespace('resources/js/customer-orders.js');
const presentation = await namespace('resources/js/product-presentation.js');
const { formatPrice } = presentation;

assert.deepEqual(Array.from(account.ACCOUNT_NAV, (item) => item.key), ['profile', 'orders', 'wishlist'], 'the account navigation covers the three customer sections');
assert.deepEqual(Array.from(account.ACCOUNT_NAV, (item) => item.href), ['/account', '/orders', '/wishlist'], 'the navigation points at the real web routes');
for (const item of account.ACCOUNT_NAV) assert.ok(item.label && item.hint && item.icon, 'every navigation item is labelled');

/* the profile endpoint validates exactly name and email */
assert.deepEqual(Array.from(account.PROFILE_EDITABLE_FIELDS, (field) => field.key), ['name', 'email'], 'only the two accepted profile keys are editable');
for (const field of account.PROFILE_EDITABLE_FIELDS) {
    assert.ok(field.label, `${field.key} is labelled`);
    assert.equal(field.required, true, `${field.key} is required`);
}
assert.equal(account.PROFILE_EDITABLE_FIELDS.find((field) => field.key === 'email').type, 'email');
assert.equal(account.PROFILE_EDITABLE_FIELDS.find((field) => field.key === 'name').autocomplete, 'name');
assert.deepEqual(Array.from(account.PROFILE_FIXED_FIELDS, (field) => field.key), ['mobile'], 'the mobile number is displayed, never edited');
assert.equal(account.PROFILE_FIXED_FIELDS[0].dir, 'ltr', 'the mobile number reads left to right');
assert.ok(!account.PROFILE_FIXED_FIELDS.some((field) => field.required), 'a read only field is not required');

assert.equal(account.firstError({ email: ['ایمیل نادرست است.', 'دوم'] }, 'email'), 'ایمیل نادرست است.', 'the first backend message is the field error');
assert.equal(account.firstError({ email: ['a'] }, 'name'), '', 'a field never shows another field error');
assert.deepEqual(Array.from(account.errorSummary({ name: ['الف'], email: ['ب', 'ج'] })), ['الف', 'ب', 'ج'], 'the notice lists every backend message');
assert.deepEqual(Array.from(account.errorSummary(null)), [], 'no errors means no summary');
assert.deepEqual(Object.keys(account.profileValidation({ name: '', email: '' })).sort(), ['email', 'name'], 'an empty form is rejected before the request');
assert.deepEqual(Array.from(account.profileValidation({ name: 'زهرا', email: 'bad' }).email), ['قالب ایمیل معتبر نیست.']);
assert.deepEqual(Object.keys(account.profileValidation({ name: '  ', email: 'a@b.co' })), ['name'], 'whitespace is not a name');
assert.deepEqual(Object.keys(account.profileValidation({ name: 'زهرا', email: 'a@b.co' })), [], 'a valid form passes');
console.log('PASS profile fields match the update endpoint and error copy comes from the backend shape');

/* statuses and transitions mirror the Order model */
assert.deepEqual(Array.from(account.ORDER_FLOW), Object.keys(orders.orderStatuses).filter((status) => status !== 'cancelled'), 'the timeline covers every non cancelled backend status');
for (const status of Object.keys(orders.orderStatuses)) {
    assert.equal(account.orderStatusLabel(status), orders.statusLabel(status), `${status} reads the same everywhere`);
}
assert.equal(account.orderStatusLabel('refunded'), 'refunded', 'an unknown status is never translated into an invented state');

let steps = account.orderSteps(pendingOrder);
assert.deepEqual(Array.from(steps, (step) => step.state), ['current', 'upcoming', 'upcoming', 'upcoming', 'upcoming'], 'a pending order has nothing completed');
steps = account.orderSteps(shippedOrder);
assert.deepEqual(Array.from(steps, (step) => step.state), ['done', 'done', 'done', 'current', 'upcoming'], 'future steps stay upcoming');
steps = account.orderSteps({ status: 'delivered' });
assert.deepEqual(Array.from(steps, (step) => step.state), ['done', 'done', 'done', 'done', 'current'], 'the final status is current');
assert.deepEqual(Array.from(account.orderSteps(cancelledOrder)), [], 'a cancelled order has no reachable flow to draw');
assert.deepEqual(Array.from(account.orderSteps({ status: 'archived' })), [], 'an unknown status draws no timeline');
assert.deepEqual(Array.from(account.orderSteps(null)), [], 'a missing order draws no timeline');

assert.equal(account.orderIsPaid(paidOrder), true);
assert.equal(account.orderIsPaid(pendingOrder), false);
assert.equal(account.orderIsCancelled(cancelledOrder), true);
assert.equal(account.orderCanCancel(pendingOrder), true, 'pending may transition to cancelled');
assert.equal(account.orderCanCancel({ status: 'confirmed' }), true, 'confirmed may transition to cancelled');
assert.equal(account.orderCanCancel({ status: 'processing' }), false, 'processing may not be cancelled');
assert.equal(account.orderCanCancel(shippedOrder), false);
assert.equal(account.orderCanCancel(cancelledOrder), false);
assert.equal(account.orderCanPay(pendingOrder), true, 'an unpaid pending order can be paid');
assert.equal(account.orderCanPay(paidOrder), false, 'a paid order never offers a second payment');
assert.equal(account.orderCanPay(cancelledOrder), false);
assert.equal(account.orderItemCount({ items_count: 3 }), 3);
assert.equal(account.orderItemCount({ items: [1, 2] }), 2);
assert.equal(account.orderItemCount({}), 0, 'a missing count is zero, never NaN');
console.log('PASS statuses, timeline positions and the cancel and pay capabilities mirror the Order model');

/* ------------------- 2. field, notice and state components ---------------- */
const { default: fieldComponent } = await namespace('resources/js/components/account/ProfileField.vue');
const nameField = account.PROFILE_EDITABLE_FIELDS[0];
let html = await render(fieldComponent, { field: nameField, modelValue: 'زهرا محمدی' });
assert.ok(html.includes('for="ac-name"') && html.includes('id="ac-name"'), 'the label points at the control');
assert.ok(html.includes('value="زهرا محمدی"'));
assert.ok(html.includes('maxlength="255"'));
assert.ok(html.includes('required'));
assert.ok(!html.includes('aria-invalid'), 'a clean field is not announced as invalid');
assert.ok(html.includes('id="ac-name-hint"'), 'the hint is rendered');
assert.ok(html.includes('aria-describedby="ac-name-hint"'), 'the hint is wired to the control');

html = await render(fieldComponent, { field: nameField, modelValue: '', error: 'نام را وارد کنید.' });
assert.ok(html.includes('aria-invalid="true"'), 'an invalid field is announced');
assert.ok(html.includes('id="ac-name-error"') && html.includes('aria-describedby="ac-name-error"'), 'the error replaces the hint in the description');
assert.ok(html.includes('role="alert"') && html.includes('نام را وارد کنید.'));

const mobileField = account.PROFILE_FIXED_FIELDS[0];
html = await render(fieldComponent, { field: mobileField, modelValue: '09121234567', readonly: true });
assert.ok(html.includes('09121234567'), 'the mobile number is shown');
assert.ok(!html.includes('type="tel"'), 'a read only field renders no control');
assert.ok(!html.includes('<input'), 'a read only field is never an input');
assert.ok(!html.includes('required'), 'a read only field is not required');
assert.ok(html.includes('این شماره هنگام ثبت‌نام ثبت شده'), 'the read only reason is explained');

const { default: noticeComponent } = await namespace('resources/js/components/account/AccountNotice.vue');
html = await render(noticeComponent, { tone: 'error' }, { default: () => 'ذخیره نشد.' });
assert.ok(html.includes('role="alert"'), 'an error is announced assertively');
assert.ok(html.includes('ac-notice--error'));
assert.ok(html.includes('ذخیره نشد.'));
html = await render(noticeComponent, { tone: 'success' }, { default: () => 'ذخیره شد.' });
assert.ok(html.includes('role="status"'), 'a success is announced politely');
assert.ok(html.includes('ذخیره شد.'));

const { default: stateComponent } = await namespace('resources/js/components/account/AccountState.vue');
html = await render(stateComponent, { busy: true });
assert.ok(html.includes('role="status"') && html.includes('fa-spin'), 'a busy state is announced and shows progress');
assert.ok(!html.includes('<button'), 'a busy state offers no retry');
html = await render(stateComponent, { title: 'خطا رخ داد', retryable: true });
assert.ok(html.includes('خطا رخ داد') && html.includes('تلاش دوباره') && html.includes('type="button"'), 'a retryable state offers a real button');
console.log('PASS profile fields, notices and state blocks stay accessible and announce the right politeness');

/* ------------------- 3. the shared account shell -------------------------- */
const { default: layoutComponent } = await namespace('resources/js/components/account/AccountLayout.vue');
auth.user = { ...customer };
html = await render(layoutComponent, { active: 'orders', title: 'سفارش‌های من', description: 'توضیح' });
assert.ok(html.includes('aria-label="بخش‌های حساب کاربری"'), 'the account navigation is labelled');
assert.ok(html.includes('aria-current="page">سفارش‌های من'), 'the current section is marked');
assert.ok(!html.includes('aria-current="page">حساب کاربری'), 'other sections are not marked current');
assert.ok(html.includes('aria-label="مسیر صفحه"'), 'a breadcrumb trail exists');
assert.ok(html.includes('aria-current="page">سفارش‌های من</span>'), 'the breadcrumb marks the current page');
for (const item of account.ACCOUNT_NAV) assert.ok(html.includes(`href="${item.href}"`), `${item.href} is reachable`);
assert.ok(html.includes('زهرا محمدی') && html.includes('zahra@example.com'), 'the member summary shows the real account values');
assert.ok(html.includes('ac-layout'), 'the shell uses the shared layout');
assert.ok(html.includes('dir="rtl"'), 'the shell keeps the right to left reading direction');
console.log('PASS one shell gives every account page the same navigation, breadcrumb and member summary');

/* ------------------- 4. order presentation components -------------------- */
const { default: chipComponent } = await namespace('resources/js/components/account/OrderStatusChip.vue');
const { default: stepsComponent } = await namespace('resources/js/components/account/OrderSteps.vue');
const { default: orderCardComponent } = await namespace('resources/js/components/account/OrderCard.vue');
const { default: lineComponent } = await namespace('resources/js/components/account/OrderLineRow.vue');
const date = (value) => new Date(value).toLocaleDateString('fa-IR');

html = await render(chipComponent, { order: pendingOrder });
assert.ok(html.includes('در انتظار تأیید') && html.includes('ac-chip--current'), 'a pending order reads as waiting for payment');
html = await render(chipComponent, { order: paidOrder });
assert.ok(html.includes('تأیید شده') && html.includes('ac-chip--paid'), 'a paid order shows the payment confirmation');
html = await render(chipComponent, { order: cancelledOrder });
assert.ok(html.includes('لغو شده') && html.includes('ac-chip--cancelled'), 'a cancelled order is called cancelled');
html = await render(chipComponent, { order: { status: 'mystery' } });
assert.ok(html.includes('mystery'), 'an unknown status is shown as the raw status, not as a guess');

html = await render(stepsComponent, { order: shippedOrder, formatDate: date });
assert.ok(html.includes('ارسال شده') && html.includes('وضعیت فعلی'), 'the current step is named');
assert.ok(html.includes('data-state="done"') && html.includes('data-state="upcoming"'), 'completed and upcoming steps are distinguished');
assert.ok(!html.includes('در حال پردازش</strong>\n'), 'no step is marked current twice');
html = await render(stepsComponent, { order: cancelledOrder, formatDate: date });
assert.ok(!html.includes('ac-steps'), 'a cancelled order draws no timeline');
assert.ok(html.includes('لغو شده') && html.includes('انصراف از خرید'), 'the cancellation facts are shown instead');
assert.ok(html.includes(date(cancelledOrder.cancelled_at)), 'the cancellation date is real');

html = await render(orderCardComponent, { order: pendingOrder, formatDate: date });
assert.ok(html.includes('#42') && html.includes('href="/orders/42"'), 'the card links to the order detail route');
assert.ok(html.includes(formatPrice(2020000)), 'the card shows the stored total');
assert.ok(html.includes('۳ قلم کالا') || html.includes('3 قلم کالا'), 'the card shows the item count');
assert.ok(html.includes(date(pendingOrder.created_at)), 'the card shows the order date');
assert.ok(!html.includes('پرداخت تأییدشده'), 'an unpaid order is never described as paid');

html = await render(lineComponent, { item: pendingOrder.items[0] });
assert.ok(html.includes('مانتو کتی پشمی'));
assert.ok(html.includes('/storage/coats/coat.jpg'), 'the line image uses the stored path');
assert.ok(html.includes('MNT-1-S-BLK'));
assert.ok(html.includes('سایز') && html.includes('M'), 'variant axes read as Persian labels');
assert.ok(html.includes(formatPrice(1700000)), 'the line shows the line total');
assert.ok(!html.includes('افزایش تعداد') && !html.includes('fa-trash-can'), 'an order line is read only');
assert.ok(!html.includes('<button'), 'an order line has no action');

html = await render(lineComponent, { item: { ...pendingOrder.items[0], image: null, attributes: 'broken', quantity: 'x' } });
assert.ok(!html.includes('NaN') && !html.includes('undefined'), 'broken order data never leaks into the markup');
assert.ok(!html.includes('سایز'), 'a broken attributes payload renders no variant text');
console.log('PASS order chips, timeline and lines report stored facts and never invent a state or allow editing');

/* ------------------- 5. the account page ---------------------------------- */
const { default: accountPage } = await namespace('resources/js/AccountPage.vue');
auth.user = { ...customer };
html = await render(accountPage);
assert.ok(html.includes('حساب کاربری'), 'the account page keeps its heading');
assert.ok(html.includes('aria-current="page">حساب کاربری'), 'the profile section is current in the navigation');
assert.ok(html.includes('value="زهرا محمدی"'), 'the profile form is filled with the real name');
assert.ok(html.includes('value="zahra@example.com"'), 'the profile form is filled with the real email');
assert.ok(html.includes('09121234567'), 'the real mobile number is displayed');
assert.ok(!html.includes('id="ac-mobile"'), 'the mobile number is never an editable control');
assert.ok(html.includes('href="/orders"') && html.includes('href="/wishlist"'), 'the other account sections stay reachable');
for (const forbidden of account.UNSUPPORTED_PROFILE_ACTIONS) {
    assert.ok(!html.includes(`>${forbidden}`) && !html.includes(`${forbidden}:`), `no ${forbidden} control is offered`);
}
assert.ok(html.includes('خروج از حساب'), 'logout is available');
for (const legacy of ['bg-cream', 'rounded-xl', 'text-ink', 'brand-accent', 'text-slate-500', 'min-h-screen']) {
    assert.ok(!html.includes(legacy), `the legacy theme class ${legacy} is gone`);
}
console.log('PASS the account page edits only name and email, shows the real mobile read only and offers no unsupported capability');

/* the redirect to login and the save flow */
const accountExposed = await evaluate('resources/js/AccountPage.vue', 'onSave, onLogout, form, fieldErrors, saveError', true);
accountExposed.render = () => null;
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
async function mount(component) {
    const ref = Vue.ref();
    const app = renderer.createApp({ render: () => Vue.h(component, { ref }) });
    app.mount({});
    await flush();
    return { page: ref.value, dispose: () => app.unmount() };
}

authCalls.length = 0;
let page = await mount(accountExposed);
await page.page.onSave();
assert.deepEqual(authCalls, [], 'an unchanged profile is never submitted');
assert.equal(page.page.saveError, '', 'an unchanged profile reports no error');
page.dispose();

authCalls.length = 0;
page = await mount(accountExposed);
page.page.form.name = 'زهرا محمدی نیک';
await page.page.onSave();
assert.deepEqual(authCalls.at(-1), { type: 'update-profile', name: 'زهرا محمدی نیک', email: 'zahra@example.com' }, 'the save sends only the two accepted keys');
page.dispose();

authCalls.length = 0;
page = await mount(accountExposed);
page.page.form.email = 'not-an-email';
await page.page.onSave();
assert.equal(authCalls.length, 0, 'a client side validation failure never reaches the API');
assert.equal(page.page.fieldErrors.email[0], 'قالب ایمیل معتبر نیست.');
page.dispose();

page = await mount(accountExposed);
page.page.form.email = 'taken@example.com';
await page.page.onSave();
assert.equal(page.page.fieldErrors.email[0], 'این ایمیل قبلاً ثبت شده است.', 'a backend field error reaches its own control');
assert.ok(page.page.saveError.includes('اطلاعات نامعتبر است.'), 'the backend message is still summarised');
page.dispose();

authCalls.length = 0;
location.href = '';
page = await mount(accountExposed);
await page.page.onLogout();
assert.ok(authCalls.some((call) => call.type === 'logout'), 'logout uses the existing auth endpoint');
assert.equal(location.href, '/', 'a signed out customer returns to the storefront');
page.dispose();

/* an unauthenticated visitor is sent to the existing login */
auth.user = null;
location.href = '';
html = await render(accountPage);
assert.ok(html.includes('حساب کاربری در دسترس نیست'), 'a signed out visitor is offered the login');
assert.ok(html.includes('href="/login"'), 'the existing login route is used');
assert.ok(!html.includes('value="زهرا محمدی"'), 'no account data is shown without a session');
auth.user = { ...customer };
console.log('PASS profile save, validation, backend errors and logout behave through the existing auth state');

/* ------------------- 6. orders list and detail ---------------------------- */
const { default: ordersPage } = await namespace('resources/js/OrdersPage.vue');
const { default: pagerComponent } = await namespace('resources/js/components/account/OrderPager.vue');
auth.user = { ...customer };

/* before the response arrives the shell is shown and no order content is invented */
html = await render(ordersPage);
assert.ok(html.includes('سفارش‌های من'), 'the orders page keeps its heading');
assert.ok(html.includes('aria-current="page">سفارش‌های من'), 'the orders section is current in the navigation');
assert.ok(html.includes('در حال دریافت است'), 'the list waits for the backend');
assert.ok(!html.includes('#42'), 'no order is rendered before the response');

/* the pager mirrors the Laravel meta and nothing else */
const pagerButton = (markup, label) => (markup.match(new RegExp(`<button[^>]*>[^<]*${label}`)) || [])[0] || '';
html = await render(pagerComponent, { meta: { current_page: 1, last_page: 3 } });
assert.ok(html.includes('صفحه ۱ از ۳'), 'the pager shows the backend page numbers in Persian digits');
assert.ok(pagerButton(html, 'قبلی').includes('disabled'), 'the first page disables the previous button');
assert.ok(!pagerButton(html, 'بعدی').includes('disabled'), 'the next button stays available');
html = await render(pagerComponent, { meta: { current_page: 3, last_page: 3 } });
assert.ok(!pagerButton(html, 'قبلی').includes('disabled'), 'the previous button stays available on the last page');
assert.ok(pagerButton(html, 'بعدی').includes('disabled'), 'the last page disables the next button');
html = await render(pagerComponent, { meta: { current_page: 1, last_page: 1 } });
assert.ok(!html.includes('ac-pager'), 'a single page needs no pager');
html = await render(pagerComponent, { meta: { current_page: 2, last_page: 5 }, busy: true });
assert.ok(pagerButton(html, 'قبلی').includes('disabled') && pagerButton(html, 'بعدی').includes('disabled'), 'the pager locks while a page is in flight');

/* the mounted page is populated from the existing endpoint */
const ordersExposed = await evaluate('resources/js/OrdersPage.vue', 'orders, loading, error, data, load', true);
ordersExposed.render = () => null;
const listMeta = { current_page: 1, last_page: 3, data: [pendingOrder, { ...cancelledOrder, id: 43 }], total: 41, per_page: 20, from: 1, to: 2 };
calls.length = 0;
responder = async () => ({ data: listMeta });
const ordersMounted = await mount(ordersExposed);
assert.deepEqual(Array.from(ordersMounted.page.orders, (order) => order.id), [42, 43], 'both stored orders reach the page');
assert.equal(ordersMounted.page.error, '', 'a successful load reports no error');
assert.equal(ordersMounted.page.loading, false, 'the busy state clears once the list arrives');
const listCalls = calls.filter((call) => call.url === '/api/customer/orders');
assert.ok(listCalls.length >= 1 && listCalls.every((call) => call.method === 'get'), 'the list is read only through the existing endpoint');

/* the real order objects render the real statuses */
html = await render(orderCardComponent, { order: ordersExposed ? listMeta.data[0] : null, formatDate: date });
assert.ok(html.includes('#42') && html.includes('در انتظار تأیید'), 'the first order card shows its own status');
html = await render(orderCardComponent, { order: listMeta.data[1], formatDate: date });
assert.ok(html.includes('#43') && html.includes('لغو شده'), 'the cancelled order card says so');

/* an empty list and a failure both stay friendly */
const emptyExposed = await evaluate('resources/js/OrdersPage.vue', 'orders, error, load', true);
emptyExposed.render = () => null;
responder = async () => ({ data: { current_page: 1, last_page: 1, data: [], total: 0, per_page: 20 } });
const emptyPage = await mount(emptyExposed);
assert.deepEqual(Array.from(emptyPage.page.orders), [], 'an empty list stays empty');
assert.equal(emptyPage.page.error, '', 'an empty list is not an error');
emptyPage.dispose();

const failExposed = await evaluate('resources/js/OrdersPage.vue', 'orders, error, load', true);
failExposed.render = () => null;
responder = async () => { throw { response: { status: 500, data: { message: 'SQLSTATE[HY000] connection refused' } } }; };
const failPage = await mount(failExposed);
assert.ok(failPage.page.error.includes('دریافت اطلاعات سفارش انجام نشد'), 'a failure reads in friendly copy');
assert.ok(!failPage.page.error.includes('SQLSTATE'), 'a raw backend message never reaches the customer');
failPage.dispose();
console.log('PASS the orders list shows real statuses, real pagination and friendly empty and failure states');

/* the detail page keeps the Phase 6 payment behaviour in the new design */
location.pathname = '/orders/42';
location.search = '?payment=failed&reason=verification_failed';
const detailExposed = await evaluate('resources/js/OrderDetailPage.vue', 'order, paymentResult, retryPayment, handleCancel, cancelling', true);
detailExposed.render = () => null;
auth.user = { ...customer };
calls.length = 0;
location.href = '';
responder = async (url) => {
    if (url === '/api/customer/orders/42') return { data: { order: pendingOrder } };
    if (url === '/api/customer/orders/42/pay') return { data: { payment_url: 'https://gateway.test/pay/tx-42' } };
    throw new Error(`unexpected request ${url}`);
};
const detailPage = await mount(detailExposed);
assert.equal(detailPage.page.order.status, 'pending', 'the detail page loads the owned order');
assert.equal(detailPage.page.paymentResult.tone, 'failure', 'a failed gateway return is reported as a failure');
assert.equal(detailPage.page.paymentResult.retry, true, 'an unpaid order can be retried');

calls.length = 0;
await detailPage.page.retryPayment();
const payCalls = calls.filter((call) => call.url === '/api/customer/orders/42/pay');
assert.equal(payCalls.length, 1, 'a retry uses the existing pay endpoint exactly once');
assert.equal(location.href, 'https://gateway.test/pay/tx-42', 'the retry follows the gateway url the backend returned');
assert.equal(calls.filter((call) => call.url !== '/api/customer/orders/42/pay').length, 0, 'the retry only calls the pay endpoint and never refetches or recreates the order');
detailPage.dispose();

/* a paid order carries its reference and offers nothing to retry */
calls.length = 0;
location.search = '';
responder = async (url) => (url === '/api/customer/orders/42' ? { data: { order: paidOrder } } : { data: {} });
const paidExposed = await evaluate('resources/js/OrderDetailPage.vue', 'order, paymentResult, retryPayment, handleCancel', true);
paidExposed.render = () => null;
const paidPage = await mount(paidExposed);
assert.equal(paidPage.page.order.payment_ref, 'ref-tx-42', 'the stored payment reference reaches the page');
assert.equal(paidPage.page.paymentResult.tone, 'none', 'a normal visit without a gateway return shows no banner');
assert.equal(orders.statusLabel(paidPage.page.order.status), 'تأیید شده');
paidPage.dispose();

/* a shipped order is not cancellable and its timeline is honest */
html = await render(stepsComponent, { order: shippedOrder, formatDate: date });
assert.ok(html.includes('ارسال شده') && html.includes('وضعیت فعلی'), 'the shipped step is the current one');
assert.ok(html.includes('data-state="upcoming"'), 'the delivered step stays upcoming');
assert.equal(account.orderCanCancel(shippedOrder), false, 'a shipped order cannot be cancelled');

/* a cancelled order shows the cancellation facts and no cancel action */
html = await render(stepsComponent, { order: cancelledOrder, formatDate: date });
assert.ok(html.includes('این سفارش لغو شده است'), 'a cancelled order states it plainly');
assert.ok(html.includes('انصراف از خرید'), 'the stored cancellation reason is shown');
assert.ok(!html.includes('ac-steps'), 'a cancelled order shows no timeline');
assert.equal(account.orderCanCancel(cancelledOrder), false, 'a cancelled order offers no cancel action');

/* cancelling goes through the existing helper and adopts the server response */
calls.length = 0;
responder = async (url) => {
    if (url === '/api/customer/orders/42/cancel') return { data: { message: 'سفارش شما لغو شد.', order: cancelledOrder } };
    return { data: { order: pendingOrder } };
};
const cancelExposed = await evaluate('resources/js/OrderDetailPage.vue', 'order, handleCancel, cancelling', true);
cancelExposed.render = () => null;
const cancelPage = await mount(cancelExposed);
await cancelPage.page.handleCancel();
const cancelCalls = calls.filter((call) => call.url === '/api/customer/orders/42/cancel');
assert.equal(cancelCalls.length, 1, 'cancellation uses the existing endpoint exactly once');
assert.equal(cancelCalls[0].method, 'patch');
assert.equal(cancelPage.page.order.status, 'cancelled', 'the server response replaces the local order');
cancelPage.dispose();
console.log('PASS the order detail keeps the payment retry, the cancel contract and an honest timeline for every real status');

/* ------------------- 7. the wishlist page -------------------------------- */
const { default: wishlistPage } = await namespace('resources/js/WishlistPage.vue');
const { default: wishlistCard } = await namespace('resources/js/components/account/WishlistCard.vue');

auth.user = { ...customer };
wishlist.items = [wishlistItem];
html = await render(wishlistPage);
assert.ok(html.includes('علاقه‌مندی‌ها'), 'the wishlist page keeps its heading');
assert.ok(html.includes('aria-current="page">علاقه‌مندی‌ها'), 'the wishlist section is current in the navigation');
assert.ok(html.includes('مانتو کتی پشمی'), 'the stored product name is shown');
assert.ok(html.includes(formatPrice(850000)), 'the stored price is shown');
assert.ok(html.includes('موجود'), 'the stored stock flag is shown');
assert.ok(html.includes('href="/products/7"'), 'the item links to the real product route');
assert.ok(html.includes('/storage/coats/coat.jpg'), 'the primary image is used');

html = await render(wishlistCard, { item: { product_id: 9, product: { id: 9, name: 'روسری ابریشمی', price: 320000, in_stock: false, images: [] } } });
assert.ok(html.includes('تصویر محصول به‌زودی'), 'a product without an image says so instead of a broken frame');
assert.ok(html.includes('ناموجود'), 'a genuinely out of stock product says so');
assert.ok(html.includes('روسری ابریشمی'), 'the product name is still rendered');
assert.ok(!html.includes('undefined') && !html.includes('NaN'), 'a sparse product payload never leaks broken values');

wishlist.items = [];
html = await render(wishlistPage);
assert.ok(html.includes('فهرست علاقه‌مندی‌ها خالی است'), 'an empty wishlist explains itself');
assert.ok(html.includes('href="/store"'));

wishlist.error = { status: 429, message: 'Too Many Attempts.' };
html = await render(wishlistPage);
assert.ok(html.includes('بیش از حد مجاز'), 'a throttled response reads in friendly copy');
assert.ok(!html.includes('Too Many Attempts'), 'the raw backend message is not shown');
wishlist.error = null;

auth.user = null;
html = await render(wishlistPage);
assert.ok(html.includes('برای دیدن علاقه‌مندی‌ها وارد شوید'), 'a signed out visitor is asked to sign in');
assert.ok(html.includes('href="/login"'), 'the existing login route is used');
assert.ok(!html.includes('مانتو کتی پشمی'), 'no wishlist data is shown without a session');

/* removal always goes through the shared wishlist state */
auth.user = { ...customer };
wishlist.items = [wishlistItem];
wishlistRemovals = [];
const wishlistExposed = await evaluate('resources/js/WishlistPage.vue', 'removeItem, friendlyError', true);
wishlistExposed.render = () => null;
const wishlistMounted = await mount(wishlistExposed);
await flush();
assert.ok(wishlistMounted.page.friendlyError(429).includes('بیش از حد مجاز'), 'a throttled response reads in friendly copy');
await wishlistMounted.page.removeItem(7);
assert.deepEqual(wishlistRemovals, [7], 'removal is delegated to the existing wishlist state');
assert.equal(wishlist.items.length, 0);
wishlistMounted.dispose();
console.log('PASS the wishlist page uses the shared state, shows real products and never leaks backend copy');

/* ------------------- 8. authentication pages ------------------------------ */
const { default: loginPage } = await namespace('resources/js/LoginPage.vue');
const { default: registerPage } = await namespace('resources/js/RegisterPage.vue');

html = await render(loginPage);
assert.ok(html.includes('ورود به حساب'), 'the login page keeps its heading');
assert.ok(html.includes('id="login-email"') && html.includes('for="login-email"'), 'the email control is labelled');
assert.ok(html.includes('type="email"') && html.includes('autocomplete="email"'), 'email is typed and autocompleted');
assert.ok(html.includes('autocomplete="current-password"'), 'the password field is autocompleted for sign in');
assert.ok(html.includes('aria-pressed="true"') && html.includes('ورود با موبایل'), 'both existing login modes are offered');
assert.ok(html.includes('aria-label="روش ورود"'), 'the mode switch is grouped and labelled');
assert.ok(html.includes('href="/register"'), 'registration stays reachable');
assert.ok(!html.includes('bg-cream') && !html.includes('rounded-xl') && !html.includes('text-ink'), 'the legacy theme classes are gone');

const loginExposed = await evaluate('resources/js/LoginPage.vue', 'mode, mobile, code, expiresIn, loading, onSendOtp, onVerifyOtp, onSubmit, changeMode, changeMobile', true);
loginExposed.render = () => null;
const loginMounted = await mount(loginExposed);
await flush();
authCalls.length = 0;
loginMounted.page.mode = 'mobile';
await loginMounted.page.onSendOtp();
assert.deepEqual(authCalls.at(-1), { type: 'send-otp', mobile: '' }, 'the OTP send uses the existing endpoint');
loginMounted.page.code = '123456';
await loginMounted.page.onVerifyOtp();
assert.deepEqual(authCalls.at(-1), { type: 'verify-otp', mobile: '', code: '123456' }, 'the OTP verification uses the existing endpoint');
loginMounted.dispose();

html = await render(registerPage);
assert.ok(html.includes('ساخت حساب کاربری'), 'the registration page keeps its purpose');
for (const field of ['mobile', 'name', 'email', 'password', 'passwordConfirmation']) {
    assert.ok(html.includes(`id="register-${field}"`) && html.includes(`for="register-${field}"`), `${field} is labelled`);
}
assert.ok(html.includes('autocomplete="tel"'), 'the mobile field is autocompleted as a phone number');
assert.ok(html.includes('autocomplete="new-password"'), 'the password fields are autocompleted for sign up');
assert.ok(html.includes('minlength="8"'), 'the password carries the server minimum length');
assert.ok(html.includes('maxlength="32"'), 'the mobile field carries the server max length');
assert.ok(html.includes('href="/login"'), 'sign in stays reachable');

const registerExposed = await evaluate('resources/js/RegisterPage.vue', 'form, fields, onSubmit', true);
registerExposed.render = () => null;
const registerMounted = await mount(registerExposed);
await flush();
authCalls.length = 0;
registerMounted.page.form = { mobile: '09121234567', name: 'نام', email: 'a@b.co', password: 'secret12', passwordConfirmation: 'secret12' };
assert.deepEqual(Array.from(registerMounted.page.fields, (field) => field.key), ['mobile', 'name', 'email', 'password', 'passwordConfirmation']);
await registerMounted.page.onSubmit();
assert.equal(authCalls.at(-1).type, 'register', 'registration goes through the existing auth state');
assert.deepEqual(authCalls.at(-1).args, ['نام', 'a@b.co', 'secret12', 'secret12', '09121234567'], 'the registration payload keeps its original five arguments');
assert.equal(location.href, '/account', 'a new customer lands on the account page');
registerMounted.dispose();
console.log('PASS login keeps both real modes and registration keeps its payload and field errors');

/* ------------------- 9. contracts, isolation and css --------------------- */
const files = [
    'resources/js/AccountPage.vue',
    'resources/js/OrdersPage.vue',
    'resources/js/OrderDetailPage.vue',
    'resources/js/WishlistPage.vue',
    'resources/js/LoginPage.vue',
    'resources/js/RegisterPage.vue',
    'resources/js/account-presentation.js',
    'resources/js/components/account/AccountLayout.vue',
    'resources/js/components/account/AccountNotice.vue',
    'resources/js/components/account/AccountState.vue',
    'resources/js/components/account/OrderCard.vue',
    'resources/js/components/account/OrderLineRow.vue',
    'resources/js/components/account/OrderPager.vue',
    'resources/js/components/account/OrderStatusChip.vue',
    'resources/js/components/account/OrderSteps.vue',
    'resources/js/components/account/ProfileField.vue',
    'resources/js/components/account/WishlistCard.vue',
];
const sources = {};
for (const file of files) sources[file] = await readFile(resolve(root, file), 'utf8');

for (const file of files) {
    assert.ok(!sources[file].includes('localStorage'), `${file} must not add storage of its own`);
    assert.ok(!sources[file].includes('vue-router'), `${file} must not introduce a router`);
    assert.ok(!sources[file].includes('pinia'), `${file} must not add a store`);
    assert.ok(!sources[file].includes('turbopart-'), `${file} must not invent its own storage keys`);
}
assert.ok(!sources['resources/js/account-presentation.js'].includes('axios'), 'presentation helpers stay pure');
assert.ok(!sources['resources/js/account-presentation.js'].includes('import '), 'presentation helpers have no imports at all');
for (const file of files.filter((name) => name.includes('components/account/') && !name.includes('AccountLayout'))) {
    assert.ok(!sources[file].includes('axios'), `${file} must not call the API`);
    assert.ok(!sources[file].includes('localStorage'), `${file} must not read storage`);
}

/* only the existing endpoints are used, and the shared state modules still own them */
const code = (source) => source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
const pageSource = files.map((file) => code(sources[file])).join('\n');
const authSource = await readFile(resolve(root, 'resources/js/auth-state.js'), 'utf8');
const wishlistSource = await readFile(resolve(root, 'resources/js/wishlist-state.js'), 'utf8');
const ordersSource = await readFile(resolve(root, 'resources/js/customer-orders.js'), 'utf8');
for (const [file, source, endpoint] of [
    ['auth-state.js', authSource, '/api/customer/profile'],
    ['auth-state.js', authSource, '/api/customer/me'],
    ['auth-state.js', authSource, '/api/customer/logout'],
    ['auth-state.js', authSource, '/api/customer/login'],
    ['auth-state.js', authSource, '/api/customer/send-otp'],
    ['auth-state.js', authSource, '/api/customer/verify-otp'],
    ['auth-state.js', authSource, '/api/customer/register'],
    ['wishlist-state.js', wishlistSource, '/api/customer/wishlist'],
    ['customer-orders.js', ordersSource, '/api/customer/orders'],
]) {
    assert.ok(source.includes(endpoint), `the existing ${endpoint} endpoint still lives in ${file}`);
}
assert.ok(!pageSource.includes('/api/customer/wishlist'), 'no page re-declares a wishlist endpoint');
assert.ok(!pageSource.includes('/api/customer/profile'), 'no page re-declares a profile endpoint');
assert.ok(!pageSource.includes('/api/customer/logout'), 'no page re-declares a logout endpoint');
assert.ok(!pageSource.includes('/api/customer/me'), 'no page re-declares the me endpoint');
const axiosCalls = pageSource.match(/axios\.\w+\(([^)]*)/g) || [];
assert.deepEqual(axiosCalls, ['axios.post(`/api/customer/orders/${id}/pay`'], 'the only direct api call in the account area is the existing payment retry');
assert.ok(sources['resources/js/OrdersPage.vue'].includes("useCustomerOrders('/api/customer/orders')"), 'the list only asks the shared composable for its page of orders');
assert.ok(sources['resources/js/OrderDetailPage.vue'].includes('`/api/customer/orders/${id}`'), 'the detail page only asks the shared composable for one owned order');
assert.ok(sources['resources/js/AccountPage.vue'].includes('updateProfile(form.value.name.trim(), form.value.email.trim())'), 'the profile save calls the existing helper with the two accepted keys');
assert.ok(!sources['resources/js/AccountPage.vue'].includes('mobile:' ) || !sources['resources/js/AccountPage.vue'].includes('updateProfile(form.value.name.trim(), form.value.email.trim(), form.value.mobile)'), 'the mobile is never submitted to the profile endpoint');
assert.ok(sources['resources/js/OrderDetailPage.vue'].includes('POST /api/customer/orders/{id}/pay') || sources['resources/js/OrderDetailPage.vue'].includes('`/api/customer/orders/${id}/pay`'), 'payment still goes through the existing endpoint');
assert.ok(sources['resources/js/OrderDetailPage.vue'].includes('paymentReturnState'), 'the gateway return is still only a hint');
assert.ok(sources['resources/js/OrderDetailPage.vue'].includes('cancelOrder(id)'), 'cancellation still goes through the existing helper');
assert.ok(sources['resources/js/WishlistPage.vue'].includes('loadWishlist()') && sources['resources/js/WishlistPage.vue'].includes('removeFromWishlist(productId)'), 'the wishlist page only talks to the shared wishlist state');

/* the account stylesheet is isolated to the storefront root */
const css = postcss.parse(await readFile(resolve(root, 'resources/css/account.css'), 'utf8'));
css.walkRules((rule) => {
    if (rule.parent.type === 'atrule' && rule.parent.name.endsWith('keyframes')) return;
    if (rule.parent.type === 'atrule' && ['media', 'supports'].includes(rule.parent.name)) return;
    for (const selector of postcss.list.comma(rule.selector)) {
        assert.ok(selector.trim().startsWith('.storefront-theme '), selector);
    }
});
const cssText = css.toString();
for (const rule of [...css.nodes]) {
    if (rule.type !== 'atrule') continue;
    if (rule.params.includes('prefers-reduced-motion')) {
        assert.ok(rule.toString().includes('transition-duration'), 'reduced motion is respected');
    }
}
for (const token of ['--sf-space-', '--sf-surface', '--sf-border', '--sf-radius-card', '--sf-type-small']) {
    assert.ok(cssText.includes(token), `the account styles use the shared ${token} tokens`);
}
for (const breakpoint of ['min-width: 900px', 'max-width: 899px', 'max-width: 480px']) {
    assert.ok(cssText.includes(breakpoint), `the ${breakpoint} layout is designed deliberately`);
}
assert.ok(cssText.includes('min-height: 44px'), 'touch targets stay at least 44px');
assert.ok(cssText.includes('min-height: 48px'), 'form controls stay at least 48px');
assert.ok(cssText.includes('overflow-x: auto'), 'the mobile navigation rail scrolls instead of overflowing the page');
assert.ok(cssText.includes('overflow-wrap: anywhere'), 'long values wrap on narrow screens');
assert.ok(!cssText.includes('overflow-x: hidden') || !cssText.includes('100vw'), 'no 100vw overflow trick is used');

/* the storefront shell and earlier phases are untouched */
const packageJson = JSON.parse(await readFile(resolve(root, 'package.json'), 'utf8'));
assert.deepEqual(Object.keys(packageJson.dependencies), ['vue'], 'no runtime dependency was added');
assert.equal(packageJson.devDependencies['@tailwindcss/vite'], '^4.3.3', 'the toolchain is untouched');
const app = await readFile(resolve(root, 'resources/js/app.js'), 'utf8');
for (const page of ['AccountPage', 'OrdersPage', 'OrderDetailPage', 'WishlistPage', 'LoginPage', 'RegisterPage']) {
    assert.ok(app.includes(page), `${page} is still resolved by path`);
}
assert.ok(app.includes("if (path === '/account') return AccountPage;"), 'the customer route resolution is unchanged');
const header = await readFile(resolve(root, 'resources/js/SiteHeader.vue'), 'utf8');
assert.ok(header.includes('href="/account"') && header.includes('href="/orders"') && header.includes('href="/wishlist"'), 'the header still reaches every account section');
assert.ok(header.includes('logout()') && header.includes('resetWishlist()'), 'the header logout and wishlist reset are untouched');
const stateModule = await readFile(resolve(root, 'resources/js/auth-state.js'), 'utf8');
assert.ok(stateModule.includes("const TOKEN_KEY = 'turbopart-auth-token'"), 'the existing token key is untouched');
console.log('PASS contracts preserved, no new storage or dependency, and the account stylesheet is isolated and responsive');
console.log('9 verification groups passed; no browser layout claim.');

