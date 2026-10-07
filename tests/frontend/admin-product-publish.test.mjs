// Run: node tests/frontend/admin-product-publish.test.mjs
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createContext, runInContext } from 'node:vm';
import { ref } from 'vue';

const source = await readFile(new URL('../../resources/js/Admin.vue', import.meta.url), 'utf8');
const start = source.indexOf('async function submitProduct()');
const submit = source.slice(start, source.indexOf('\nasync function deleteProduct', start));

async function scenario({ count, editing = false, failAt = -1 }) {
    const events = [];
    const state = {
        productSubmitting: ref(false), errorMessage: ref(''), successMessage: ref(''),
        editingProductId: ref(editing ? 9 : null), form: ref({ variants: [] }),
        variantAttributes: ref([]), selectedImageFiles: ref(Array.from({ length: count }, (_, i) => `image-${i}`)),
        imageUploading: ref(false), imageAltText: ref('تصویر فارسی'), showProductModal: ref(true),
    };
    const context = createContext({
        ...state, console: { log() {}, error() {} },
        save: async imageCount => { events.push(['create', imageCount]); return { id: 9, publish_token: 'creation-only' }; },
        updateProduct: async () => { events.push(['edit']); return { id: 9 }; },
        uploadProductImage: async (id, file, alt, token) => {
            events.push(['upload', id, file, alt, token]);
            if (file === `image-${failAt}`) throw new Error('upload failed');
        },
        showAdminNotification: (...args) => events.push(['notification', ...args]),
        clearSelectedImages() {}, resetForm() {},
    });
    runInContext(submit, context);
    const pending = context.submitProduct();
    await context.submitProduct(); // Double click while the first create is pending.
    await pending;
    assert.equal(events.filter(e => ['create', 'edit'].includes(e[0])).length, 1);
    assert.equal(state.productSubmitting.value, false);
    const uploads = events.filter(e => e[0] === 'upload');
    if (failAt >= 0) {
        assert.equal(uploads.length, failAt + 1);
        assert.ok(uploads.every(e => e[4] === null));
        assert.equal(events.at(-1)[1], 'error');
    } else {
        assert.equal(uploads.length, count);
        assert.deepEqual(uploads.map(e => e[2]), Array.from({ length: count }, (_, i) => `image-${i}`));
        assert.equal(uploads.filter(e => e[4] === 'creation-only').length, !editing && count > 0 ? 1 : 0);
        if (!editing && count > 0) assert.equal(uploads.at(-1)[4], 'creation-only');
        assert.equal(events.at(-1)[1], 'success');
    }
    if (!editing) assert.deepEqual(events[0], ['create', count]);
}

await scenario({ count: 1 });
await scenario({ count: 5 });
await scenario({ count: 0 });
await scenario({ count: 3, editing: true });
await scenario({ count: 5, failAt: 1 });
console.log('Product publishing: create, final-upload token, all images, no images, edit exclusion, upload failure and double-click guard passed.');
