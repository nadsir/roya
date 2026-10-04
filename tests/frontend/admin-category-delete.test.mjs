// Run: node tests/frontend/admin-category-delete.test.mjs
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createContext, runInContext } from 'node:vm';
import { ref } from 'vue';

const source = await readFile(new URL('../../resources/js/Admin.vue', import.meta.url), 'utf8');
const api = await readFile(new URL('../../resources/js/admin-state.js', import.meta.url), 'utf8');
const leaf = { id: 2, name: 'لباس', children: [] };
const sibling = { id: 3, name: 'شلوار', children: [] };
const state = {
    categoryDeletingId: ref(null), categorySaving: ref(false),
    errorMessage: ref(''), successMessage: ref(''),
    adminCategories: ref([{ id: 1, children: [leaf, sibling] }]),
    categories: ref([leaf, sibling]), categorySearchResults: ref([leaf, sibling]),
    expandedCategoryIds: ref(new Set([1, 2])), categoryAttributes: ref({ 2: [], 3: [] }),
    configuredCategoryId: ref(2), categoryAttributeConfig: ref([{ attribute_id: 7 }]),
    categoryEditingId: ref(2), selectedCategoryId: ref(2),
};
let accepted = false;
let confirmation = '';
let requests = [];
let notifications = [];
let refreshes = 0;
let release;
let failure;
let refreshFailure = false;
const context = createContext({
    ...state,
    confirm: message => { confirmation = message; return accepted; },
    axios: { delete: async url => {
        requests.push(url);
        if (failure) throw failure;
        await new Promise(resolve => { release = resolve; });
    } },
    loadAdminCategories: async () => { refreshes++; if (refreshFailure) throw new Error('offline'); },
    loadMeta: async () => { refreshes++; },
    closeCategoryEditor: () => { state.categoryEditingId.value = null; state.selectedCategoryId.value = null; },
    clearCategorySelection: () => { state.selectedCategoryId.value = null; },
    showAdminNotification: (...args) => notifications.push(args),
});
const apiStart = api.indexOf('export async function removeCategory(');
runInContext(api.slice(apiStart, api.indexOf('\n}', apiStart) + 2).replace('export ', ''), context);
const start = source.indexOf('async function deleteCategory(');
runInContext(source.slice(start, source.indexOf('\n}', start) + 2), context);

// The edit form exposes a non-submit delete button, including loading state.
const actions = source.slice(source.indexOf('<div class="category-form-actions">'), source.indexOf('<!-- Empty state when no selection -->'));
assert.match(actions, /type="button"[\s\S]*@click="deleteCategory\(selectedCategory\)"/);
assert.match(actions, /حذف دسته‌بندی/);
assert.match(actions, /:disabled="categoryDeletingId !== null \|\| categorySaving"/);
await context.deleteCategory(leaf);
assert.equal(requests.length, 0);
assert.ok(confirmation.includes(leaf.name));
assert.ok(confirmation.includes('قابل بازگشت نیست'));

accepted = true;
const pending = context.deleteCategory(leaf);
assert.equal(state.categoryDeletingId.value, 2);
await context.deleteCategory(leaf);
assert.equal(requests.length, 1);
assert.equal(requests[0], '/api/admin/categories/2');
release();
await pending;
assert.equal(refreshes, 2);
assert.deepEqual(state.adminCategories.value[0].children.map(x => x.id), [3]);
assert.deepEqual(state.categorySearchResults.value.map(x => x.id), [3]);
assert.equal(state.configuredCategoryId.value, null);
assert.equal(state.categoryAttributeConfig.value.length, 0);
assert.equal(state.selectedCategoryId.value, null);
assert.equal(state.categoryDeletingId.value, null);
assert.equal(notifications.at(-1)[0], 'success');

failure = { response: { data: { errors: { category: ['این دسته‌بندی دارای محصول است.'] } } } };
await context.deleteCategory(sibling);
assert.equal(state.errorMessage.value, 'این دسته‌بندی دارای محصول است.');
assert.equal(notifications.at(-1)[0], 'error');
assert.equal(refreshes, 2);
assert.equal(state.adminCategories.value[0].children.length, 1);
assert.equal(state.categoryDeletingId.value, null);

failure = null;
refreshFailure = true;
const deleted = context.deleteCategory(sibling);
release();
await deleted;
assert.equal(state.adminCategories.value[0].children.length, 0);
assert.ok(state.errorMessage.value.includes('دسته‌بندی حذف شد'));
assert.equal(state.categoryDeletingId.value, null);
console.log('Category deletion: action, confirmation, cancellation, DELETE request, duplicate guard, refresh, cleanup and errors passed.');
