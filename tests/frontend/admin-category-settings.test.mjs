// Run: node tests/frontend/admin-category-settings.test.mjs
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createContext, runInContext } from 'node:vm';
import { ref } from 'vue';

const source = await readFile(new URL('../../resources/js/Admin.vue', import.meta.url), 'utf8');
const state = {
    categoryAttributeConfig: ref([]),
    configuredCategoryId: ref(2),
    configuredCategory: ref({ id: 2, parent_id: 1 }),
    categoryAttributeLoading: ref(false),
    categoryAttributeSaving: ref(false),
    errorMessage: ref(''),
    successMessage: ref(''),
};
let saved;
let response = [];
let failRead = false;
const context = createContext({
    ...state,
    loadMeta: async () => {},
    loadCategoryAttributeConfig: async () => {
        if (failRead) throw new Error('offline');
        return response;
    },
    saveCategoryAttributeConfig: async (id, payload) => { saved = { id, payload }; },
});
const indexStart = source.indexOf('function categoryAttributeConfigIndex(');
runInContext(source.slice(indexStart, source.indexOf('\n}', indexStart) + 2), context);
runInContext(source.slice(
    source.indexOf('function isCategoryAttributeConfigured('),
    source.indexOf('function attributeSelected('),
), context);

for (const enabled of [true, false]) {
    context.setAttributeState({ id: 7 }, enabled ? 'enabled' : 'disabled');
    response = [{ id: 7, state: enabled ? 'enabled' : 'disabled', config: { is_enabled: enabled } }];
    await context.saveCategoryAttributes();
    assert.equal(saved.id, 2);
    assert.equal(saved.payload[0].is_enabled, enabled);
    assert.equal(context.getAttributeState(7), enabled ? 'enabled' : 'disabled');
    await context.selectCategoryForAttributes(2);
    assert.equal(context.getAttributeState(7), enabled ? 'enabled' : 'disabled');
}

context.setAttributeState({ id: 7 }, 'inherit');
response = [{ id: 7, state: 'inherit', config: { is_enabled: true, is_required: true } }];
await context.saveCategoryAttributes();
assert.equal(saved.payload.length, 0);
assert.equal(context.getAttributeState(7), 'inherit');
assert.equal(context.isCategoryAttributeInherited(7), true);
assert.equal(context.categoryAttributeConfiguration(7).is_required, true);

response[0].config.is_required = false;
await context.selectCategoryForAttributes(2);
assert.equal(context.categoryAttributeConfiguration(7).is_required, false);

state.configuredCategory.value = { id: 1, parent_id: null };
response = [];
await context.selectCategoryForAttributes(1);
assert.equal(context.getAttributeState(7), 'disabled');

failRead = true;
await context.saveCategoryAttributes();
assert.equal(state.successMessage.value, '');
assert.notEqual(state.errorMessage.value, '');
assert.equal(state.categoryAttributeSaving.value, false);
console.log('Admin category settings: enabled, disabled, inherit, refresh, root fallback and reload failure passed.');
