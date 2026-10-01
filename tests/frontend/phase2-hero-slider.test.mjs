// Run: node --experimental-vm-modules tests/frontend/phase2-hero-slider.test.mjs
// Scoped checks for the homepage hero slider: autoplay start, next, previous and timer cleanup.
import assert from 'node:assert/strict';
import { readFile, access } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SourceTextModule, SyntheticModule, createContext } from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import postcss from 'postcss';
import * as Vue from 'vue';

const project = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const timers = new Map();
let timerId = 0;
let mediaMatches = false;
let mediaListener = null;
const documentListeners = new Map();
const storage = new Map();
const context = createContext({
    console, AbortController,
    location: { pathname: '/', href: '' },
    localStorage: { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key) },
    window: {
        matchMedia: () => ({ get matches() { return mediaMatches; }, addEventListener: (name, fn) => { if (name === 'change') mediaListener = fn; }, removeEventListener() {} }),
        addEventListener() {}, removeEventListener() {},
    },
    document: { hidden: false, addEventListener: (name, fn) => documentListeners.set(name, fn), removeEventListener: (name) => documentListeners.delete(name) },
    setTimeout: (fn, ms) => { const id = ++timerId; timers.set(id, { fn, ms }); return id; },
    clearTimeout: (id) => timers.delete(id),
});
const modules = new Map();
function synthetic(name, exports) { return new SyntheticModule(Object.keys(exports), function () { for (const [key, value] of Object.entries(exports)) this.setExport(key, value); }, { context, identifier: name }); }
modules.set('vue', synthetic('vue', Vue));
async function loadModule(id, ssr = false) {
    const key = ssr ? `${id}:ssr` : id;
    if (modules.has(key)) return modules.get(key);
    let source = await readFile(id, 'utf8');
    if (id.endsWith('.vue')) {
        if (ssr) {
            const { descriptor } = parse(source, { filename: id });
            source = compileScript(descriptor, { id: 'hero-slider-ssr', inlineTemplate: true }).content;
        } else {
            source = source.replace('</script>', '\ndefineExpose({ active, playing, paused, engaged, hovered, focused, reducedMotion, previous, next, select, startTouch, endTouch, imageErrorFor: (image, index) => imageError({ target: image }, index) });\n</script>');
            const { descriptor } = parse(source, { filename: id });
            descriptor.template = null;
            source = compileScript(descriptor, { id: 'hero-slider-test' }).content;
        }
    }
    const module = new SourceTextModule(source, { context, identifier: id });
    modules.set(key, module);
    await module.link((specifier, parent) => modules.has(specifier) ? modules.get(specifier) : loadModule(resolve(dirname(parent.identifier), specifier), ssr));
    return module;
}
async function namespace(relative, ssr = false) {
    const module = await loadModule(resolve(project, relative), ssr);
    if (module.status !== 'evaluated') await module.evaluate();
    return module.namespace;
}
const renderer = Vue.createRenderer({
    createComment: () => ({}), createText: (text) => ({ text }), createElement: () => ({}),
    insert: (node, parent) => { node.parent = parent; }, remove() {}, parentNode: (node) => node.parent,
    nextSibling: () => null, setText() {}, setElementText() {}, patchProp() {},
});
async function mountHero() {
    const { default: hero } = await namespace('resources/js/components/homepage/EditorialHero.vue');
    hero.render = () => null;
    const props = Vue.reactive({ product: null, status: 'ready' });
    const exposed = Vue.ref();
    const warnings = [];
    const app = renderer.createApp({ render: () => Vue.h(hero, { ...props, ref: exposed }) });
    app.config.warnHandler = (message) => warnings.push(message);
    app.mount({});
    await flush();
    return { proxy: exposed.value, props, unmount: () => app.unmount(), warnings };
}
async function flush() { await Vue.nextTick(); await Promise.resolve(); await Vue.nextTick(); }
async function exists(file) { try { await access(file); return true; } catch { return false; } }
function autoplayTimers() { return [...timers.values()].filter((timer) => timer.ms === 6500); }
async function tick(times = 1) {
    for (let n = 0; n < times; n++) {
        const jobs = [...timers.entries()];
        timers.clear();
        jobs.forEach(([, timer]) => timer.fn());
        await flush();
    }
}
const passed = [];

// Autoplay initializes on mount with exactly one timer and advances the loop.
const hero = await mountHero();
assert.equal(hero.warnings.length, 0, hero.warnings.join('\n'));
assert.equal(hero.proxy.active, 0);
assert.equal(hero.proxy.reducedMotion, false);
assert.equal(hero.proxy.playing, true);
assert.equal(autoplayTimers().length, 1);
await tick();
assert.equal(hero.proxy.active, 1);
assert.equal(autoplayTimers().length, 1);
await tick(2);
assert.equal(hero.proxy.active, 0);
assert.equal(autoplayTimers().length, 1);
passed.push('autoplay initializes on mount, advances one slide per cycle and keeps a single timer');

// The next control moves forward and loops back to the first slide from the last one.
hero.proxy.next(); await flush();
assert.equal(hero.proxy.active, 1);
hero.proxy.next(); await flush();
assert.equal(hero.proxy.active, 2);
hero.proxy.next(); await flush();
assert.equal(hero.proxy.active, 0);
assert.equal(autoplayTimers().length, 0);
passed.push('next button advances and loops after the last slide');

// The previous control moves backward and wraps from the first slide to the last one.
hero.proxy.previous(); await flush();
assert.equal(hero.proxy.active, 2);
hero.proxy.previous(); await flush();
assert.equal(hero.proxy.active, 1);
hero.proxy.previous(); await flush();
assert.equal(hero.proxy.active, 0);
passed.push('previous button goes back and loops to the last slide');

// A manual selection pauses playback only temporarily, so autoplay comes back on its own.
hero.proxy.next(); await flush();
assert.equal(hero.proxy.engaged, true);
assert.equal(autoplayTimers().length, 0);
await tick(12);
assert.equal(hero.proxy.engaged, false);
assert.equal(hero.proxy.playing, true);
assert.equal(autoplayTimers().length, 1);
passed.push('interaction pauses playback temporarily and autoplay resumes afterwards');

// Hovering the control cluster pauses playback and leaving it restores it.
hero.proxy.hovered = true; await flush();
assert.equal(hero.proxy.playing, false);
assert.equal(autoplayTimers().length, 0);
hero.proxy.hovered = false; await flush();
assert.equal(hero.proxy.playing, true);
assert.equal(autoplayTimers().length, 1);
passed.push('hovering the controls pauses playback and leaving restores it');

// A plain tap or a vertical gesture records the touch without permanently stopping autoplay.
hero.proxy.startTouch({ touches: [{ clientX: 100, clientY: 100 }] }); await flush();
assert.equal(hero.proxy.paused, false);
assert.equal(hero.proxy.engaged, false);
assert.equal(autoplayTimers().length, 1);
hero.proxy.endTouch({ changedTouches: [{ clientX: 104, clientY: 260 }] }); await flush();
assert.equal(autoplayTimers().length, 1);
// A real horizontal swipe changes the slide and takes the temporary hold.
hero.proxy.startTouch({ touches: [{ clientX: 300, clientY: 100 }] });
hero.proxy.endTouch({ changedTouches: [{ clientX: 120, clientY: 108 }] }); await flush();
assert.equal(hero.proxy.engaged, true);
assert.equal(autoplayTimers().length, 0);
passed.push('touch tracking ignores taps and vertical gestures but still swipes');

// The explicit play/pause control stays the only permanent user choice.
hero.proxy.select(0);
await flush();
hero.unmount(); await flush();
assert.equal(timers.size, 0);
assert.equal(documentListeners.size, 0);
passed.push('unmount clears the autoplay timer and the visibility listener');

const reopened = await mountHero();
assert.equal(autoplayTimers().length, 1);
reopened.proxy.previous(); await flush();
assert.equal(reopened.proxy.active, 2);
await tick(12);
assert.equal(reopened.proxy.playing, true);
assert.equal(autoplayTimers().length, 1);
const before = reopened.proxy.active;
await tick(3);
assert.equal(reopened.proxy.active, (before + 3) % 3);
reopened.unmount(); await flush();
assert.equal(timers.size, 0);
passed.push('reopened slider restarts clean and unmount leaves no pending timer');

// prefers-reduced-motion users never get forced animation.
mediaMatches = true;
const reduced = await mountHero();
assert.equal(reduced.proxy.reducedMotion, true);
assert.equal(reduced.proxy.playing, false);
assert.equal(autoplayTimers().length, 0);
reduced.unmount(); await flush();
assert.equal(timers.size, 0);
passed.push('prefers-reduced-motion keeps autoplay off');

const css = postcss.parse(await readFile(resolve(project, 'resources/css/homepage.css'), 'utf8'));
assert.ok(css.toString().includes('prefers-reduced-motion'));

// The real template keeps every slide, both arrows, the indicators and the untouched store link.
const { default: renderedHero } = await namespace('resources/js/components/homepage/EditorialHero.vue', true);
const markup = await renderToString(Vue.createSSRApp({ render: () => Vue.h(renderedHero, { product: null, status: 'ready' }) }));
assert.equal((markup.match(/class="hp-slide[ "]/g) || []).length, 3);
assert.ok(markup.includes('aria-label="اسلاید قبلی"'));
assert.ok(markup.includes('aria-label="اسلاید بعدی"'));
assert.ok(markup.includes('aria-label="انتخاب اسلاید"'));
assert.ok(markup.includes('href="/store?sort=newest"'));
assert.ok(markup.includes('aria-roledescription="اسلایدر"'));

// The three slides must never share one image again.
const { heroSlideImages } = await namespace('resources/js/hero-slides.js');
assert.equal(heroSlideImages.length, 3);
assert.equal(new Set(heroSlideImages.map((image) => image.src)).size, 3);
for (const image of heroSlideImages) {
    assert.ok(image.src.startsWith('/images/'), image.src);
    assert.ok(image.width > 0 && image.height > 0, image.src);
    assert.ok(image.alt, image.src);
}
const sources = [...markup.matchAll(/<img[^>]+src="([^"]+)"/g)].map((match) => match[1]);
assert.equal(sources.length, 3);
assert.equal(new Set(sources).size, 3);
for (const source of sources) assert.ok(await exists(resolve(project, 'public' + source)), source);
passed.push('three slides render three distinct images from one config and every file exists on disk');
// A product no longer hijacks the third slide, and a broken product photo never
// replaces a configured campaign image with a remote placeholder.
const { default: productHero } = await namespace('resources/js/components/homepage/EditorialHero.vue', true);
const product = { id: 2, name: 'پیراهن', price: 800, in_stock: true, images: [{ path: 'first.jpg', is_primary: true }] };
const productMarkup = await renderToString(Vue.createSSRApp({ render: () => Vue.h(productHero, { product, status: 'ready' }) }));
assert.ok(!productMarkup.includes('/storage/first.jpg'));
assert.ok(productMarkup.includes(`src="${heroSlideImages[2].src}"`));
assert.ok(productMarkup.includes('>پیراهن<'));
assert.equal(new Set([...productMarkup.matchAll(/<img[^>]+src="([^"]+)"/g)].map((match) => match[1])).size, 3);
const errorHero = await mountHero();
const broken = { currentSrc: 'http://localhost/storage/first.jpg', src: 'http://localhost/storage/first.jpg' };
errorHero.props.product = product; await flush();
errorHero.proxy.imageErrorFor(broken, 2);
assert.equal(broken.src, heroSlideImages[2].src);
passed.push('the configured third photo wins, and a failed product photo steps back to it instead of a remote placeholder');
passed.forEach((name) => console.log('PASS ' + name));
console.log(`${passed.length} verification groups passed (browser layout remains unverified).`);
