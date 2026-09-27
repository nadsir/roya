```vue
<script setup>
import {
    ref,
    computed,
    onMounted,
    onUnmounted,
} from 'vue';

import MultiSelect from './components/MultiSelect.vue';

import {
    products,
    categories,
    adminCategories,
    attributes,
    categoryAttributes,
    loading,
    saving,
    editingProductId,
    form,
    load,
    loadProduct,
    resetForm,
    addVariant,
    removeVariant,
    save,
    updateProduct,
    removeProduct,
    uploadProductImage,
    removeProductImage,
    setPrimaryProductImage,
    reorderProductImages,
    adminUser,
    isAuthenticated,
    login,
    logout,
    restoreAdminSession,
    loadCategoryAttributeConfig,
    saveCategoryAttributeConfig,
    createCategory,
    updateCategory,
    removeCategory,
    createAttribute,
    updateAttribute,
    removeAttribute,
    createAttributeValue,
    updateAttributeValue,
    removeAttributeValue,
    loadAdminBrands,
    createBrand,
    updateBrand,
    deleteBrand,
    loadAdminModels,
    createModel,
    updateModel,
    deleteModel,
    loadAdminGenerations,
    createGeneration,
    updateGeneration,
    deleteGeneration,
    loadAdminTrims,
    createTrim,
    updateTrim,
    deleteTrim,
    loadAdminEngines,
    createEngine,
    updateEngine,
    deleteEngine,
    loadBrands,
    loadBrandModels,
    loadModelGenerations,
    loadGenerationTrims,
    loadTrimEngines,
    loadProductCompatibility,
    attachProductCompatibility,
    detachProductCompatibility,
    productSearch,
    productPage,
    productTotalPages,
    productTotal,
    loadAdminProducts,
    loadMeta,
    adminOrders,
    adminOrder,
    adminOrderLoading,
    adminOrderError,
    adminOrderSuccess,
    adminOrderSearch,
    adminOrderStatusFilter,
    adminOrderPage,
    adminOrderTotalPages,
    adminOrderTotal,
    loadAdminOrders,
    loadAdminOrder,
    getStatusLabel,
    adminNotification,
    showAdminNotification,
    hideAdminNotification,
    adminUsers,
    adminUserDetail,
    adminUserLoading,
    adminUserError,
    adminUserSuccess,
    adminUserSearch,
    adminUserStatusFilter,
    adminUserPage,
    adminUserTotalPages,
    adminUserTotal,
    loadAdminUsers,
    loadAdminUser,
    updateAdminUserStatus,
    loadAdminComments,
    categorySearch,
    selectedCategoryId,
    expandedCategoryIds,
    categorySearchResults,
    categorySearchLoading,
    searchCategories,
    toggleCategoryExpand,
    expandAllCategories,
    collapseAllCategories,
    selectCategory,
    clearCategorySelection,
    adminArticles,
    adminArticleLoading,
    adminArticleError,
    adminArticleSuccess,
    articleFormErrors,
    adminArticleSearch,
    adminArticleStatusFilter,
    adminArticlePage,
    adminArticleTotalPages,
    adminArticleTotal,
    articleEditingId,
    showArticleEditor,
    articleSaving,
    articleForm,
    createEmptyArticleForm,
    openCreateArticle,
    openEditArticle,
    loadAdminArticles,
    loadAdminArticle,
    saveArticle,
    deleteArticle,
    goToArticlePage,
    closeArticleForm,
    loadArticleFormData,
    loadAllVehicleEngines,
    vehicleEngines,
    vehicleBrands,
} from './admin-state';

import AdminOrdersPage from './AdminOrdersPage.vue';
import AdminOrderDetailPage from './AdminOrderDetailPage.vue';
import AdminNotification from './AdminNotification.vue';
import AdminUsersPage from './AdminUsersPage.vue';
import AdminCommentsPage from './AdminCommentsPage.vue';




async function openEditProduct(id) {
    try {
        await loadProduct(id);
        await loadCompat(id);
        await initCompatSelectors();
        showProductModal.value = true;
    } catch (error) {
        console.error('Failed to load product:', error);

        alert('اطلاعات محصول دریافت نشد.');
    }
}
/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const section = ref('dashboard');

const showProductModal = ref(false);

const loginEmail = ref('');
const loginPassword = ref('');
const loginLoading = ref(false);
const loginError = ref('');
const errorMessage = ref('');
const successMessage = ref('');
const configuredCategoryId = ref(null);
const categoryAttributeConfig = ref([]);
const categoryAttributeLoading = ref(false);
const categoryAttributeSaving = ref(false);
const categoryEditingId = ref(null);
const categorySaving = ref(false);
const categoryForm = ref(createEmptyCategoryForm());

// Category Manager state
const showCategoryEditor = ref(false);
const categoryFormParentOptions = computed(() => {
    const result = [];
    const visit = (category, depth = 0) => {
        if (category.id !== categoryEditingId.value) {
            result.push({ ...category, depth });
        }
        for (const child of category.children || []) {
            visit(child, depth + 1);
        }
    };
    for (const category of adminCategories.value) {
        visit(category);
    }
    return result;
});

const selectedCategory = computed(() => {
    const findCategory = (categories, id) => {
        for (const cat of categories) {
            if (cat.id === id) return cat;
            if (cat.children?.length) {
                const found = findCategory(cat.children, id);
                if (found) return found;
            }
        }
        return null;
    };
    return selectedCategoryId.value ? findCategory(adminCategories.value, selectedCategoryId.value) : null;
});

const categoryBreadcrumb = computed(() => {
    if (!selectedCategory.value) return [];
    const path = [];
    let current = selectedCategory.value;
    while (current) {
        path.unshift(current);
        current = current.parent_id ? findCategory(adminCategories.value, current.parent_id) : null;
    }
    return path;
});

function findCategory(categories, id) {
    for (const cat of categories) {
        if (cat.id === id) return cat;
        if (cat.children?.length) {
            const found = findCategory(cat.children, id);
            if (found) return found;
        }
    }
    return null;
}

const attributeEditingId = ref(null);
const attributeSaving = ref(false);
const attributeForm = ref(createEmptyAttributeForm());
const attributeValueEditingId = ref(null);
const attributeValueSaving = ref(false);
const attributeValueForm = ref(createEmptyAttributeValueForm());

// ── Vehicle Management State ──────────────────────────────────
const vehicleModels = ref([]);
const vehicleGenerations = ref([]);
const vehicleTrims = ref([]);

const selectedBrandId = ref(null);
const selectedModelId = ref(null);
const selectedGenerationId = ref(null);
const selectedTrimId = ref(null);

const vehicleLoading = ref(false);
const vehicleSaving = ref(false);
const vehicleError = ref('');

const vehicleForm = ref(createEmptyVehicleForm());
const vehicleEditingId = ref(null);
const vehicleLevel = ref('brands');

function createEmptyVehicleForm() {
    return { name: '', slug: '', is_active: true, year_start: null, year_end: null, displacement: null, fuel_type: '', horsepower: null };
}

// ── Vehicle Product Compat State ──────────────────────────────
const productCompat = ref([]);
const compatLoading = ref(false);
const compatBrands = ref([]);
const compatModels = ref([]);
const compatGenerations = ref([]);
const compatTrims = ref([]);
const compatEngines = ref([]);
const compatSelectedBrand = ref(null);
const compatSelectedModel = ref(null);
const compatSelectedGeneration = ref(null);
const compatSelectedTrim = ref(null);
const compatSelectedEngine = ref(null);

// ── Orders State ───────────────────────────────────────────────
const selectedOrderId = ref(null);
const showOrderDetail = ref(false);

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

const nav = [
    {
        key: 'dashboard',
        label: 'داشبورد',
        icon: '▦',
    },
    {
        key: 'products',
        label: 'محصولات',
        icon: '◈',
    },
    {
        key: 'orders',
        label: 'سفارش‌ها',
        icon: '📦',
    },
    {
        key: 'categories',
        label: 'دسته‌بندی‌ها',
        icon: '◫',
    },
    {
        key: 'articles',
        label: 'مقالات',
        icon: '📝',
    },
    {
        key: 'attributes',
        label: 'ویژگی‌ها',
        icon: '◇',
    },
    {
        key: 'vehicles',
        label: 'خودروها',
        icon: '◀',
    },
    {
        key: 'users',
        label: 'کاربران',
        icon: '◎',
    },
    {
        key: 'comments',
        label: 'نظرات کاربران',
        icon: '❝',
    },
    {
        key: 'settings',
        label: 'تنظیمات',
        icon: '⚙',
    },
];

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const currentSection = computed(() => {
    return nav.find(
        item => item.key === section.value
    );
});

const selectedAttributes = computed(() => {
    const categoryIds = form.value.category_ids;

    if (!categoryIds.length) {
        return [];
    }

    const sets = categoryIds.map(categoryId =>
        categoryAttributes.value[categoryId] || []
    );

    const first = sets[0];
    const rest = sets.slice(1);

    const intersected = first.filter(attribute =>
        rest.every(list =>
            list.some(item => item.id === attribute.id)
        )
    );

    return intersected.sort(
        (a, b) =>
            (a.sort_order ?? 0) -
            (b.sort_order ?? 0)
    );
});

const variantAttributes = computed(() => {
    return selectedAttributes.value.filter(
        attribute =>
            attribute.values?.length &&
            attribute.pivot?.is_variant_axis
    );
});

const hasIncompleteVariants = computed(() => {
    if (!variantAttributes.value.length) return false;
    return form.value.variants.some(
        v => !v.attribute_value_ids.length
    );
});

const stockProducts = computed(() => {
    return products.value.filter(
        product => product.in_stock
    ).length;
});

const outOfStockProducts = computed(() => {
    return products.value.length -
        stockProducts.value;
});

function debounce(fn, ms = 300) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), ms);
    };
}

const debouncedProductSearch = debounce(() => {
    productPage.value = 1;
    loadAdminProducts();
});

function onProductSearchInput(value) {
    productSearch.value = value;
    debouncedProductSearch();
}

function goToProductPage(page) {
    if (page < 1 || page > productTotalPages.value) return;
    productPage.value = page;
    loadAdminProducts();
}

const categoryConfigurationOptions = computed(() => {
    const result = [];

    const visit = (category, depth = 0) => {
        result.push({ ...category, depth });

        for (const child of category.children || []) {
            visit(child, depth + 1);
        }
    };

    for (const category of adminCategories.value) {
        visit(category);
    }

    return result;
});

const productCategoryOptions = computed(() => {
    const result = [];

    const visit = (category, depth = 0) => {
        result.push({ ...category, depth });

        for (const child of category.children || []) {
            visit(child, depth + 1);
        }
    };

    for (const category of categories.value) {
        visit(category);
    }

    return result;
});

const configuredCategory = computed(() => {
    return categoryConfigurationOptions.value.find(
        category => category.id === configuredCategoryId.value
    );
});

// Article form selectors
const availableCategories = computed(() => {
    const result = [];
    const visit = (category, depth = 0) => {
        result.push({ ...category, depth });
        for (const child of category.children || []) {
            visit(child, depth + 1);
        }
    };
    for (const category of adminCategories.value) {
        visit(category);
    }
    return result;
});

const availableProducts = computed(() => {
    return products.value.map(p => ({ id: p.id, name: p.name, slug: p.slug }));
});

const availableBrands = computed(() => {
    return vehicleBrands.value.map(b => ({ id: b.id, name: b.name, slug: b.slug }));
});

const availableVehicles = computed(() => {
    // Return vehicle engines for selection
    return vehicleEngines.value.map(e => ({ 
        id: e.id, 
        name: `${e.name} (${e.trim?.name || ''} - ${e.trim?.generation?.model?.brand?.name || ''})`,
        slug: e.slug 
    }));
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function formatPrice(value) {
    if (
        value === null ||
        value === undefined ||
        value === ''
    ) {
        return '۰';
    }

    return Number(value).toLocaleString(
        'fa-IR'
    );
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('fa-IR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function categoryName(categoryId) {
    return productCategoryOptions.value.find(
        category => category.id === categoryId
    )?.name || '';
}

function selectedCategoryNames() {
    return form.value.category_ids
        .map(categoryName)
        .filter(Boolean);
}

function categoryAttributeConfigIndex(attributeId) {
    return categoryAttributeConfig.value.findIndex(
        item => item.attribute_id === attributeId
    );
}

function createEmptyCategoryForm() {
    return {
        name: '',
        slug: '',
        parent_id: null,
        description: '',
        image: '',
        is_active: true,
        sort_order: 0,
    };
}

function createEmptyAttributeForm() {
    return {
        name: '',
        slug: '',
        type: 'select',
        sort_order: 0,
    };
}

function createEmptyAttributeValueForm() {
    return {
        label: '',
        value: '',
        hex_color: '',
        sort_order: 0,
    };
}

function isOptionAttribute(attribute) {
    return ['select', 'multiselect', 'color'].includes(attribute.type);
}

function openCreateAttribute() {
    attributeEditingId.value = null;
    attributeForm.value = createEmptyAttributeForm();
    attributeValueEditingId.value = null;
    attributeValueForm.value = createEmptyAttributeValueForm();
    errorMessage.value = '';
}

function openEditAttribute(attribute) {
    attributeEditingId.value = attribute.id;
    attributeForm.value = {
        name: attribute.name || '',
        slug: attribute.slug || '',
        type: attribute.type || 'select',
        sort_order: attribute.sort_order ?? 0,
    };
    attributeValueEditingId.value = null;
    attributeValueForm.value = createEmptyAttributeValueForm();
    errorMessage.value = '';
}

function currentEditingAttribute() {
    return attributes.value.find(
        attribute => attribute.id === attributeEditingId.value
    );
}

async function submitAttribute() {
    attributeSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const attribute = attributeEditingId.value
            ? await updateAttribute(attributeEditingId.value, attributeForm.value)
            : await createAttribute(attributeForm.value);

        await load();
        openEditAttribute(
            attributes.value.find(item => item.id === attribute.id) || attribute
        );
        successMessage.value = 'ویژگی ذخیره شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'ذخیره ویژگی انجام نشد.';
    } finally {
        attributeSaving.value = false;
    }
}

async function deleteAttribute(attribute) {
    if (!confirm(`ویژگی «${attribute.name}» حذف شود؟`)) {
        return;
    }

    errorMessage.value = '';
    successMessage.value = '';

    try {
        await removeAttribute(attribute.id);
        await load();

        if (attributeEditingId.value === attribute.id) {
            openCreateAttribute();
        }

        successMessage.value = 'ویژگی حذف شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'حذف ویژگی انجام نشد.';
    }
}

function openEditAttributeValue(value) {
    attributeValueEditingId.value = value.id;
    attributeValueForm.value = {
        label: value.label || '',
        value: value.value || '',
        hex_color: value.hex_color || '',
        sort_order: value.sort_order ?? 0,
    };
}

function resetAttributeValueForm() {
    attributeValueEditingId.value = null;
    attributeValueForm.value = createEmptyAttributeValueForm();
}

async function submitAttributeValue() {
    const attribute = currentEditingAttribute();

    if (!attribute) {
        return;
    }

    attributeValueSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        if (attributeValueEditingId.value) {
            await updateAttributeValue(
                attribute.id,
                attributeValueEditingId.value,
                attributeValueForm.value
            );
        } else {
            await createAttributeValue(attribute.id, attributeValueForm.value);
        }

        await load();
        openEditAttribute(attributes.value.find(item => item.id === attribute.id) || attribute);
        successMessage.value = 'مقدار ویژگی ذخیره شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'ذخیره مقدار ویژگی انجام نشد.';
    } finally {
        attributeValueSaving.value = false;
    }
}

async function deleteAttributeValue(value) {
    const attribute = currentEditingAttribute();

    if (!attribute || !confirm(`مقدار «${value.label}» حذف شود؟`)) {
        return;
    }

    errorMessage.value = '';
    successMessage.value = '';

    try {
        await removeAttributeValue(attribute.id, value.id);
        await load();
        openEditAttribute(attributes.value.find(item => item.id === attribute.id) || attribute);
        successMessage.value = 'مقدار ویژگی حذف شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'حذف مقدار ویژگی انجام نشد.';
    }
}

function openCreateCategory() {
    categoryEditingId.value = null;
    categoryForm.value = createEmptyCategoryForm();
    showCategoryEditor.value = true;
    clearCategorySelection();
    errorMessage.value = '';
}

function openCreateChildCategory(parentCategory) {
    categoryEditingId.value = null;
    categoryForm.value = createEmptyCategoryForm();
    categoryForm.value.parent_id = parentCategory.id;
    showCategoryEditor.value = true;
    selectCategory(parentCategory.id);
    errorMessage.value = '';
}

function openEditCategory(category) {
    categoryEditingId.value = category.id;
    categoryForm.value = {
        name: category.name || '',
        slug: category.slug || '',
        parent_id: category.parent_id || null,
        description: category.description || '',
        image: category.image || '',
        is_active: Boolean(category.is_active),
        sort_order: category.sort_order ?? 0,
    };
    showCategoryEditor.value = true;
    selectCategory(category.id);
    errorMessage.value = '';
}

function closeCategoryEditor() {
    showCategoryEditor.value = false;
    categoryEditingId.value = null;
    categoryForm.value = createEmptyCategoryForm();
    clearCategorySelection();
}

async function submitCategory() {
    categorySaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const data = {
            ...categoryForm.value,
            parent_id: categoryForm.value.parent_id || null,
        };

        if (categoryEditingId.value) {
            await updateCategory(categoryEditingId.value, data);
        } else {
            await createCategory(data);
        }

        await load();
        closeCategoryEditor();
        showAdminNotification('success', 'موفقیت', 'دسته‌بندی ذخیره شد.');
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'ذخیره دسته‌بندی انجام نشد.';
        showAdminNotification('error', 'خطا', errorMessage.value);
    } finally {
        categorySaving.value = false;
    }
}

async function deleteCategory(category) {
    if (!confirm(`دسته‌بندی «${category.name}» حذف شود؟`)) {
        return;
    }

    errorMessage.value = '';
    successMessage.value = '';

    try {
        await removeCategory(category.id);
        await load();

        if (categoryEditingId.value === category.id) {
            closeCategoryEditor();
        }
        if (selectedCategoryId.value === category.id) {
            clearCategorySelection();
        }

        showAdminNotification('success', 'موفقیت', 'دسته‌بندی حذف شد.');
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'حذف دسته‌بندی انجام نشد.';
        showAdminNotification('error', 'خطا', errorMessage.value);
    }
}

function isCategoryAttributeConfigured(attributeId) {
    const config = categoryAttributeConfiguration(attributeId);
    return config && config.state === 'enabled';
}

function isCategoryAttributeInherited(attributeId) {
    const config = categoryAttributeConfiguration(attributeId);
    return config && config.state === 'inherit';
}

function getAttributeState(attributeId) {
    const config = categoryAttributeConfiguration(attributeId);
    return config ? config.state : 'inherit';
}

function categoryAttributeConfiguration(attributeId) {
    return categoryAttributeConfig.value[
        categoryAttributeConfigIndex(attributeId)
    ];
}

function setAttributeState(attribute, newState) {
    const index = categoryAttributeConfigIndex(attribute.id);

    if (index !== -1) {
        if (newState === 'inherit') {
            categoryAttributeConfig.value.splice(index, 1);
        } else {
            categoryAttributeConfig.value[index].state = newState;
            categoryAttributeConfig.value[index].is_enabled = newState === 'enabled';
        }
        return;
    }

    if (newState !== 'inherit') {
        categoryAttributeConfig.value.push({
            attribute_id: attribute.id,
            state: newState,
            is_enabled: newState === 'enabled',
            is_required: false,
            is_filterable: false,
            is_variant_axis: false,
            sort_order: categoryAttributeConfig.value.length,
        });
    }
}

function updateCategoryAttributeConfig(attributeId, field, value) {
    const index = categoryAttributeConfigIndex(attributeId);

    if (index === -1) {
        return;
    }

    categoryAttributeConfig.value[index][field] = value;
}

function supportsVariantAxis(attribute) {
    return ['select', 'multiselect', 'color'].includes(attribute.type);
}

async function selectCategoryForAttributes(categoryId) {
    configuredCategoryId.value = Number(categoryId) || null;
    categoryAttributeConfig.value = [];

    if (!configuredCategoryId.value) {
        return;
    }

    categoryAttributeLoading.value = true;
    errorMessage.value = '';

    try {
        const configurations = await loadCategoryAttributeConfig(
            configuredCategoryId.value
        );

        categoryAttributeConfig.value = configurations.map(item => ({
            attribute_id: item.id,
            state: item.state,
            is_enabled: item.config?.is_enabled ?? true,
            is_required: item.config?.is_required ?? false,
            is_filterable: item.config?.is_filterable ?? false,
            is_variant_axis: item.config?.is_variant_axis ?? false,
            sort_order: item.config?.sort_order ?? 0,
        }));
    } catch (error) {
        errorMessage.value = 'تنظیمات ویژگی‌های دسته دریافت نشد.';
    } finally {
        categoryAttributeLoading.value = false;
    }
}

async function saveCategoryAttributes() {
    if (!configuredCategoryId.value) {
        return;
    }

    categoryAttributeSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const toSave = categoryAttributeConfig.value
            .filter(item => item.state !== 'inherit')
            .map(item => ({
                attribute_id: item.attribute_id,
                is_enabled: item.is_enabled,
                is_required: item.is_required,
                is_filterable: item.is_filterable,
                is_variant_axis: item.is_variant_axis,
                sort_order: item.sort_order,
            }));

        const configurations = await saveCategoryAttributeConfig(
            configuredCategoryId.value,
            toSave
        );

        categoryAttributeConfig.value = configurations.map(attribute => ({
            attribute_id: attribute.id,
            state: attribute.pivot?.is_enabled ? 'enabled' : 'disabled',
            is_enabled: Boolean(attribute.pivot?.is_enabled),
            is_required: Boolean(attribute.pivot?.is_required),
            is_filterable: Boolean(attribute.pivot?.is_filterable),
            is_variant_axis: Boolean(attribute.pivot?.is_variant_axis),
            sort_order: attribute.pivot?.sort_order ?? 0,
        }));

        await loadMeta();

        successMessage.value = 'تنظیمات ویژگی‌های دسته ذخیره شد.';
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'ذخیره تنظیمات انجام نشد.';
    } finally {
        categoryAttributeSaving.value = false;
    }
}

function attributeSelected(attributeId) {
    return form.value.attribute_value_ids
        .some(id => {
            const attribute =
                attributes.value.find(
                    item => item.id === attributeId
                );

            return attribute?.values?.some(
                value => value.id === id
            );
        });
}

function isValueSelected(valueId) {
    return form.value.attribute_value_ids.includes(
        valueId
    );
}

function toggleAttributeValue(valueId) {
    const index =
        form.value.attribute_value_ids.indexOf(
            valueId
        );

    if (index === -1) {
        form.value.attribute_value_ids.push(
            valueId
        );
    } else {
        form.value.attribute_value_ids.splice(
            index,
            1
        );
    }
}

function handleSelectAttribute(attribute, selectedId) {
    const ids = form.value.attribute_value_ids;
    for (let i = ids.length - 1; i >= 0; i--) {
        if (attribute.values.some(v => v.id === ids[i])) {
            ids.splice(i, 1);
        }
    }
    if (selectedId) {
        ids.push(Number(selectedId));
    }
}

function isCustomAttribute(attribute) {
    return ['number', 'boolean', 'text'].includes(
        attribute.type
    );
}

function customAttributeIndex(attributeId) {
    return form.value.custom_attribute_values.findIndex(
        item => item.attribute_id === attributeId
    );
}

function customAttributeValue(attribute) {
    const index = customAttributeIndex(attribute.id);

    if (index === -1) {
        return attribute.type === 'boolean' ? false : '';
    }

    return form.value.custom_attribute_values[index].value;
}

function setCustomAttributeValue(attribute, value) {
    const index = customAttributeIndex(attribute.id);

    if (
        value === '' ||
        value === null ||
        value === undefined
    ) {
        if (index !== -1) {
            form.value.custom_attribute_values.splice(index, 1);
        }

        return;
    }

    if (index === -1) {
        form.value.custom_attribute_values.push({
            attribute_id: attribute.id,
            value,
        });

        return;
    }

    form.value.custom_attribute_values[index].value = value;
}

function isVariantValueSelected(
    variant,
    valueId
) {
    return variant.attribute_value_ids.includes(
        valueId
    );
}

function toggleVariantValue(
    variant,
    valueId
) {
    const index =
        variant.attribute_value_ids.indexOf(
            valueId
        );

    if (index === -1) {
        variant.attribute_value_ids.push(
            valueId
        );
    } else {
        variant.attribute_value_ids.splice(
            index,
            1
        );
    }
}
const selectedImageFiles = ref([]);
const imagePreviews = ref([]);
const imageAltText = ref('');
const imageUploading = ref(false);
const draggingImageId = ref(null);

function startImageDrag(imageId) {
    draggingImageId.value = imageId;
}

async function dropImage(targetImageId) {
    if (
        !draggingImageId.value ||
        draggingImageId.value === targetImageId
    ) {
        draggingImageId.value = null;
        return;
    }

    const images = [...(form.value.images || [])];

    const fromIndex = images.findIndex(
        image => image.id === draggingImageId.value
    );

    const toIndex = images.findIndex(
        image => image.id === targetImageId
    );

    if (fromIndex === -1 || toIndex === -1) {
        draggingImageId.value = null;
        return;
    }

    const [movedImage] = images.splice(
        fromIndex,
        1
    );

    images.splice(
        toIndex,
        0,
        movedImage
    );

    form.value.images = images.map(
        (image, index) => ({
            ...image,
            sort_order: index + 1,
        })
    );

    const imageIds = form.value.images.map(
        image => image.id
    );

    draggingImageId.value = null;

    try {
        await reorderProductImages(
            editingProductId.value,
            imageIds
        );
    } catch (error) {
        console.error(
            'Failed to reorder images:',
            error
        );

        alert('مرتب‌سازی تصاویر انجام نشد.');

        await loadProduct(
            editingProductId.value
        );
    }
}

function onImagesSelected(event) {
    const files = Array.from(
        event.target.files || []
    );

    if (!files.length) {
        return;
    }

    const validFiles = files.filter(file => {
        const validType = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ].includes(file.type);

        const validSize =
            file.size <= 5 * 1024 * 1024;

        return validType && validSize;
    });

    selectedImageFiles.value = [
        ...selectedImageFiles.value,
        ...validFiles,
    ];

    imagePreviews.value = [
        ...imagePreviews.value,
        ...validFiles.map(file => ({
            file,
            url: URL.createObjectURL(file),
        })),
    ];

    event.target.value = '';
}
function clearSelectedImages() {
    imagePreviews.value.forEach(
        preview => {
            if (preview.url) {
                URL.revokeObjectURL(
                    preview.url
                );
            }
        }
    );

    selectedImageFiles.value = [];
    imagePreviews.value = [];
    imageAltText.value = '';
}
function removeSelectedImage(index) {
    const preview =
        imagePreviews.value[index];

    if (preview?.url) {
        URL.revokeObjectURL(preview.url);
    }

    selectedImageFiles.value.splice(
        index,
        1
    );

    imagePreviews.value.splice(
        index,
        1
    );
}


async function deleteImage(imageId) {
    if (!editingProductId.value || !imageId) {
        return;
    }

    if (!confirm('این تصویر حذف شود؟')) {
        return;
    }

    try {
        await removeProductImage(
            editingProductId.value,
            imageId
        );
    } catch (error) {
        console.error(
            'Failed to delete image:',
            error
        );

        alert('حذف تصویر انجام نشد.');
    }
}

function imageUrl(image) {
    if (!image?.path) {
        return '';
    }

    return `/storage/${image.path}`;
}

async function setPrimaryImage(imageId) {
    if (!editingProductId.value || !imageId) {
        return;
    }

    try {
        await setPrimaryProductImage(
            editingProductId.value,
            imageId
        );
    } catch (error) {
        console.error(
            'Failed to set primary image:',
            error
        );

        alert('تعیین تصویر اصلی انجام نشد.');
    }
}
function openProductModal() {
    resetForm();

    errorMessage.value = '';
    successMessage.value = '';

    showProductModal.value = true;
}

function closeProductModal() {
    if (saving.value) {
        return;
    }

    showProductModal.value = false;
}

async function submitProduct() {
    errorMessage.value = '';
    successMessage.value = '';

    console.log('[PRODUCT SAVE] submitProduct called, editingProductId:', editingProductId.value);

    const incompleteCount = form.value.variants.filter(
        v => variantAttributes.value.length && !v.attribute_value_ids.length
    ).length;

    if (incompleteCount > 0) {
        errorMessage.value =
            `${incompleteCount} Variant بدون ویژگی محوری ذخیره نمی‌شود. لطفاً مقدار ویژگی‌ها را انتخاب کنید یا Variant را حذف کنید.`;
        console.log('[PRODUCT SAVE] blocked by incomplete variants');
        return;
    }

    try {
        let product;

        if (editingProductId.value) {
            console.log('[PRODUCT SAVE] calling updateProduct');
            product = await updateProduct();
        } else {
            console.log('[PRODUCT SAVE] calling save (create)');
            product = await save();
        }

        const productId =
            editingProductId.value ||
            product?.id;

        if (
            productId &&
            selectedImageFiles.value.length
        ) {
            imageUploading.value = true;

            for (
                const file of selectedImageFiles.value
            ) {
                await uploadProductImage(
                    productId,
                    file,
                    imageAltText.value
                );
            }

            imageUploading.value = false;
        }

        showAdminNotification('success', 'موفقیت', 'محصول با موفقیت ذخیره شد.');

        clearSelectedImages();

        resetForm();

        showProductModal.value = false;

    } catch (error) {
        imageUploading.value = false;

        console.error(
            'Failed to save product:',
            error
        );

        const message = error.message || error.response?.data?.message || 'ذخیره محصول انجام نشد.';
        showAdminNotification('error', 'خطا', message);
        errorMessage.value = message;
    }
}

async function deleteProduct(id) {
    try {
        await removeProduct(id);
    } catch (error) {
        alert(
            error.response?.data?.message ||
            'حذف محصول انجام نشد.'
        );
    }
}

async function submitLogin() {
    loginLoading.value = true;
    loginError.value = '';

    try {
        await login({
            email: loginEmail.value,
            password: loginPassword.value,
        });
        await load();
    } catch (error) {
        loginError.value =
            error.response?.data?.errors?.email?.[0] ||
            'ورود به پنل مدیریت ناموفق بود.';
    } finally {
        loginLoading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Vehicle Management
|--------------------------------------------------------------------------
*/

async function loadVehicleBrands() {
    vehicleLoading.value = true;
    vehicleError.value = '';
    try {
        vehicleBrands.value = await loadAdminBrands();
    } catch (e) {
        vehicleError.value = 'دریافت برندها انجام نشد.';
    } finally {
        vehicleLoading.value = false;
    }
}

async function selectBrand(brandId) {
    selectedBrandId.value = brandId;
    selectedModelId.value = null;
    selectedGenerationId.value = null;
    selectedTrimId.value = null;
    vehicleModels.value = [];
    vehicleGenerations.value = [];
    vehicleTrims.value = [];
    vehicleEngines.value = [];
    vehicleLevel.value = 'models';
    if (!brandId) return;
    vehicleLoading.value = true;
    try {
        vehicleModels.value = await loadAdminModels(brandId);
    } catch (e) {
        vehicleError.value = 'دریافت مدل‌ها انجام نشد.';
    } finally {
        vehicleLoading.value = false;
    }
}

async function selectModel(modelId) {
    selectedModelId.value = modelId;
    selectedGenerationId.value = null;
    selectedTrimId.value = null;
    vehicleGenerations.value = [];
    vehicleTrims.value = [];
    vehicleEngines.value = [];
    vehicleLevel.value = 'generations';
    if (!modelId) return;
    vehicleLoading.value = true;
    try {
        vehicleGenerations.value = await loadAdminGenerations(selectedBrandId.value, modelId);
    } catch (e) {
        vehicleError.value = 'دریافت نسل‌ها انجام نشد.';
    } finally {
        vehicleLoading.value = false;
    }
}

async function selectGeneration(genId) {
    selectedGenerationId.value = genId;
    selectedTrimId.value = null;
    vehicleTrims.value = [];
    vehicleEngines.value = [];
    vehicleLevel.value = 'trims';
    if (!genId) return;
    vehicleLoading.value = true;
    try {
        vehicleTrims.value = await loadAdminTrims(selectedBrandId.value, selectedModelId.value, genId);
    } catch (e) {
        vehicleError.value = 'دریافت تیپ‌ها انجام نشد.';
    } finally {
        vehicleLoading.value = false;
    }
}

async function selectTrim(trimId) {
    selectedTrimId.value = trimId;
    vehicleEngines.value = [];
    vehicleLevel.value = 'engines';
    if (!trimId) return;
    vehicleLoading.value = true;
    try {
        vehicleEngines.value = await loadAdminEngines(selectedBrandId.value, selectedModelId.value, selectedGenerationId.value, trimId);
    } catch (e) {
        vehicleError.value = 'دریافت موتورها انجام نشد.';
    } finally {
        vehicleLoading.value = false;
    }
}

function openVehicleForm(level, item = null) {
    vehicleLevel.value = level;
    vehicleEditingId.value = item ? item.id : null;
    if (item) {
        vehicleForm.value = {
            name: item.name || '',
            slug: item.slug || '',
            is_active: Boolean(item.is_active),
            year_start: item.year_start ?? null,
            year_end: item.year_end ?? null,
            displacement: item.displacement ?? null,
            fuel_type: item.fuel_type || '',
            horsepower: item.horsepower ?? null,
        };
    } else {
        vehicleForm.value = createEmptyVehicleForm();
    }
    vehicleError.value = '';
}

async function submitVehicle() {
    vehicleSaving.value = true;
    vehicleError.value = '';
    try {
        const level = vehicleLevel.value;
        const data = { ...vehicleForm.value };
        if (level === 'brands') {
            if (vehicleEditingId.value) {
                await updateBrand(vehicleEditingId.value, data);
            } else {
                await createBrand(data);
            }
            await loadVehicleBrands();
        } else if (level === 'models') {
            if (vehicleEditingId.value) {
                await updateModel(selectedBrandId.value, vehicleEditingId.value, data);
            } else {
                await createModel(selectedBrandId.value, data);
            }
            await selectBrand(selectedBrandId.value);
        } else if (level === 'generations') {
            if (vehicleEditingId.value) {
                await updateGeneration(selectedBrandId.value, selectedModelId.value, vehicleEditingId.value, data);
            } else {
                await createGeneration(selectedBrandId.value, selectedModelId.value, data);
            }
            await selectModel(selectedModelId.value);
        } else if (level === 'trims') {
            if (vehicleEditingId.value) {
                await updateTrim(selectedBrandId.value, selectedModelId.value, selectedGenerationId.value, vehicleEditingId.value, data);
            } else {
                await createTrim(selectedBrandId.value, selectedModelId.value, selectedGenerationId.value, data);
            }
            await selectGeneration(selectedGenerationId.value);
        } else if (level === 'engines') {
            if (vehicleEditingId.value) {
                await updateEngine(selectedBrandId.value, selectedModelId.value, selectedGenerationId.value, selectedTrimId.value, vehicleEditingId.value, data);
            } else {
                await createEngine(selectedBrandId.value, selectedModelId.value, selectedGenerationId.value, selectedTrimId.value, data);
            }
            await selectTrim(selectedTrimId.value);
        }
        vehicleEditingId.value = null;
        vehicleForm.value = createEmptyVehicleForm();
    } catch (e) {
        const errors = e.response?.data?.errors;
        vehicleError.value = errors ? Object.values(errors).flat().join(' ') : 'ذخیره انجام نشد.';
    } finally {
        vehicleSaving.value = false;
    }
}

async function deleteVehicleItem(level, id) {
    if (!confirm('آیا مطمئن هستید؟')) return;
    vehicleError.value = '';
    try {
        if (level === 'brands') {
            await deleteBrand(id);
            await loadVehicleBrands();
        } else if (level === 'models') {
            await deleteModel(selectedBrandId.value, id);
            await selectBrand(selectedBrandId.value);
        } else if (level === 'generations') {
            await deleteGeneration(selectedBrandId.value, selectedModelId.value, id);
            await selectModel(selectedModelId.value);
        } else if (level === 'trims') {
            await deleteTrim(selectedBrandId.value, selectedModelId.value, selectedGenerationId.value, id);
            await selectGeneration(selectedGenerationId.value);
        } else if (level === 'engines') {
            await deleteEngine(selectedBrandId.value, selectedModelId.value, selectedGenerationId.value, selectedTrimId.value, id);
            await selectTrim(selectedTrimId.value);
        }
    } catch (e) {
        const errors = e.response?.data?.errors;
        vehicleError.value = errors ? Object.values(errors).flat().join(' ') : 'حذف انجام نشد.';
    }
}

function vehicleFormLabel() {
    const labels = { brands: 'برند', models: 'مدل', generations: 'نسل', trims: 'تیپ', engines: 'موتور' };
    return labels[vehicleLevel.value] || '';
}

/*
|--------------------------------------------------------------------------
| Product Vehicle Compatibility
|--------------------------------------------------------------------------
*/

async function loadCompat(productId) {
    compatLoading.value = true;
    try {
        productCompat.value = await loadProductCompatibility(productId);
    } catch (e) {
        console.error(e);
    } finally {
        compatLoading.value = false;
    }
}

async function initCompatSelectors() {
    try {
        compatBrands.value = await loadBrands();
    } catch (e) {
        console.error(e);
    }
}

async function compatSelectBrand(brandId) {
    compatSelectedBrand.value = brandId;
    compatSelectedModel.value = null;
    compatSelectedGeneration.value = null;
    compatSelectedTrim.value = null;
    compatSelectedEngine.value = null;
    compatModels.value = [];
    compatGenerations.value = [];
    compatTrims.value = [];
    compatEngines.value = [];
    if (!brandId) return;
    compatModels.value = await loadBrandModels(brandId);
}

async function compatSelectModel(modelId) {
    compatSelectedModel.value = modelId;
    compatSelectedGeneration.value = null;
    compatSelectedTrim.value = null;
    compatSelectedEngine.value = null;
    compatGenerations.value = [];
    compatTrims.value = [];
    compatEngines.value = [];
    if (!modelId) return;
    compatGenerations.value = await loadModelGenerations(modelId);
}

async function compatSelectGeneration(genId) {
    compatSelectedGeneration.value = genId;
    compatSelectedTrim.value = null;
    compatSelectedEngine.value = null;
    compatTrims.value = [];
    compatEngines.value = [];
    if (!genId) return;
    compatTrims.value = await loadGenerationTrims(genId);
}

async function compatSelectTrim(trimId) {
    compatSelectedTrim.value = trimId;
    compatSelectedEngine.value = null;
    compatEngines.value = [];
    if (!trimId) return;
    compatEngines.value = await loadTrimEngines(trimId);
}

function isEngineAlreadyCompat(engineId) {
    return productCompat.value.some(c => c.engine.id === engineId);
}

async function addCompat(productId) {
    if (!compatSelectedEngine.value) return;
    if (isEngineAlreadyCompat(compatSelectedEngine.value)) return;
    try {
        await attachProductCompatibility(productId, [compatSelectedEngine.value]);
        await loadCompat(productId);
        compatSelectedBrand.value = null;
        compatSelectedModel.value = null;
        compatSelectedGeneration.value = null;
        compatSelectedTrim.value = null;
        compatSelectedEngine.value = null;
        compatModels.value = [];
        compatGenerations.value = [];
        compatTrims.value = [];
        compatEngines.value = [];
    } catch (e) {
        console.error(e);
    }
}

async function removeCompat(productId, engineId) {
    try {
        await detachProductCompatibility(productId, engineId);
        await loadCompat(productId);
    } catch (e) {
        console.error(e);
    }
}

function compatSummary(item) {
    return `${item.brand.name} › ${item.model.name} › ${item.generation.name} ${item.generation.year_start || ''}${item.generation.year_end ? '-' + item.generation.year_end : ''} › ${item.trim.name} › ${item.engine.name}`;
}

async function signOut() {
    await logout();
    resetForm();
    errorMessage.value = '';
    successMessage.value = '';
}

async function changeSection(value) {
    section.value = value;
    if (value === 'articles') {
        loadAdminArticles();
    }
    if (value === 'vehicles') {
        loadVehicleBrands();
    }
    if (value === 'orders') {
        loadAdminOrders();
    }
    if (value === 'users') {
        loadAdminUsers();
    }
    if (value === 'comments') {
        loadAdminComments();
    }
}

function openOrderDetail(orderId) {
    selectedOrderId.value = orderId;
    showOrderDetail.value = true;
    loadAdminOrder(orderId);
}

function closeOrderDetail() {
    showOrderDetail.value = false;
    selectedOrderId.value = null;
}

onMounted(async () => {
    const restored = await restoreAdminSession();

    if (!restored) {
        return;
    }

    try {
        await load();
    } catch (error) {
        console.error(error);

        errorMessage.value =
            'دریافت اطلاعات پنل مدیریت انجام نشد.';
    }
});

const CategoryTreeNode = {
    props: ['category', 'selectedId', 'expandedIds'],
    emits: ['toggle-expand', 'select', 'edit', 'add-child', 'delete'],
    setup(props, { emit }) {
        const hasChildren = computed(() => props.category.children && props.category.children.length > 0);
        const isExpanded = computed(() => props.expandedIds.has(props.category.id));
        const isSelected = computed(() => props.selectedId === props.category.id);

        function handleClick(event) {
            if (event.target.closest('.node-toggle') || event.target.closest('.node-actions')) {
                return;
            }
            emit('select', props.category.id);
            emit('edit', props.category);
        }

        function handleToggleExpand(event) {
            event.stopPropagation();
            emit('toggle-expand', props.category.id);
        }

        return { hasChildren, isExpanded, isSelected, handleClick, handleToggleExpand };
    },
    template: `
        <div class="tree-node" :class="{ selected: isSelected, has-children: hasChildren }">
            <div class="node-main" @click="handleClick">
                <button
                    v-if="hasChildren"
                    type="button"
                    class="node-toggle"
                    @click="handleToggleExpand"
                    :aria-expanded="isExpanded"
                    :aria-label="isExpanded ? 'بستن' : 'باز کردن'"
                >
                    <span class="toggle-icon">{{ isExpanded ? '▼' : '▶' }}</span>
                </button>
                <div class="node-content">
                    <strong>{{ category.name }}</strong>
                    <small dir="ltr">/{{ category.slug }}</small>
                    <span :class="category.is_active ? 'status ok' : 'status bad'">
                        {{ category.is_active ? 'فعال' : 'غیرفعال' }}
                    </span>
                </div>
                <div class="node-actions">
                    <button type="button" class="icon-button" @click.stop="$emit('add-child', category)" title="افزودن زیر‌دسته">+</button>
                    <button type="button" class="icon-button text-button" @click.stop="$emit('edit', category)" title="ویرایش">✎</button>
                    <button type="button" class="icon-button danger" @click.stop="$emit('delete', category)" title="حذف">🗑</button>
                </div>
            </div>
            <div v-show="isExpanded && hasChildren" class="node-children">
                <CategoryTreeNode
                    v-for="child in category.children"
                    :key="child.id"
                    :category="child"
                    :selected-id="selectedId"
                    :expanded-ids="expandedIds"
                    @toggle-expand="$emit('toggle-expand', $event)"
                    @select="$emit('select', $event)"
                    @edit="$emit('edit', $event)"
                    @add-child="$emit('add-child', $event)"
                    @delete="$emit('delete', $event)"
                />
            </div>
        </div>
    `
};
</script>

<template>
    <div
        v-if="!isAuthenticated"
        dir="rtl"
        class="admin-login"
    >
        <form
            class="login-card"
            @submit.prevent="submitLogin"
        >
            <p class="section-label">CAR ADMIN</p>
            <h1>ورود مدیر</h1>
            <p>برای مدیریت محصولات و دسته‌بندی‌ها وارد شوید.</p>

            <label>
                ایمیل
                <input
                    v-model="loginEmail"
                    type="email"
                    autocomplete="email"
                    required
                />
            </label>

            <label>
                رمز عبور
                <input
                    v-model="loginPassword"
                    type="password"
                    autocomplete="current-password"
                    required
                />
            </label>

            <p
                v-if="loginError"
                class="login-error"
            >
                {{ loginError }}
            </p>

            <button
                type="submit"
                :disabled="loginLoading"
            >
                {{ loginLoading ? 'در حال ورود…' : 'ورود به پنل' }}
            </button>
        </form>
    </div>

    <div
        v-else
        dir="rtl"
        class="admin-shell"
    >
        <!-- ================================================= -->
        <!-- SIDEBAR -->
        <!-- ================================================= -->

        <aside class="sidebar">
            <div class="brand">
                CAR
                <span>ADMIN</span>
            </div>

            <nav class="sidebar-nav">
                <button
                    v-for="item in nav"
                    :key="item.key"
                    type="button"
                    class="nav-item"
                    :class="{
                        active:
                            section === item.key
                    }"
                    @click="
                        changeSection(item.key)
                    "
                >
                    <b>{{ item.icon }}</b>

                    <span>
                        {{ item.label }}
                    </span>
                </button>
            </nav>

            <div class="side-foot">
                پنل مدیریت فروشگاه

                <small>
                    CAR ADMIN · v1.0
                </small>
            </div>
        </aside>

        <!-- ================================================= -->
        <!-- MAIN -->
        <!-- ================================================= -->

        <main class="main">
            <!-- HEADER -->
            <header class="topbar">
                <div>
                    <p class="eyebrow">
                        مدیریت فروشگاه
                    </p>

                    <h1>
                        {{
                            currentSection?.label
                        }}
                    </h1>
                </div>

                <div class="admin-user">
                    <span>{{ adminUser?.name || 'مدیر' }}</span>

                    <button
                        type="button"
                        class="logout-button"
                        @click="signOut"
                    >
                        خروج
                    </button>
                </div>
            </header>

            <!-- Admin Notification -->
            <AdminNotification
                :notification="adminNotification"
                @close="hideAdminNotification"
            />

            <!-- ================================================= -->
            <!-- LOADING -->
            <!-- ================================================= -->

            <div
                v-if="loading"
                class="loading"
            >
                <div class="spinner"></div>

                <span>
                    در حال بارگذاری اطلاعات...
                </span>
            </div>

            <template v-else>
                <!-- ================================================= -->
                <!-- DASHBOARD -->
                <!-- ================================================= -->

                <section
                    v-if="section === 'dashboard'"
                >
                    <div class="cards">
                        <div class="stat">
                            <span>
                                کل محصولات
                            </span>

                            <strong>
                                {{
                                    products.length
                                }}
                            </strong>

                            <i>◈</i>
                        </div>

                        <div class="stat">
                            <span>
                                دسته‌بندی‌ها
                            </span>

                            <strong>
                                {{
                                    categories.length
                                }}
                            </strong>

                            <i>◫</i>
                        </div>

                        <div class="stat">
                            <span>
                                موجود در انبار
                            </span>

                            <strong>
                                {{
                                    stockProducts
                                }}
                            </strong>

                            <i>✓</i>
                        </div>

                        <div class="stat">
                            <span>
                                ناموجود
                            </span>

                            <strong>
                                {{
                                    outOfStockProducts
                                }}
                            </strong>

                            <i>!</i>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    آخرین محصولات
                                </p>

                                <h2>
                                    محصولات اخیر
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="text-button"
                                @click="
                                    section =
                                        'products'
                                "
                            >
                                مشاهده همه
                            </button>
                        </div>

                        <div
                            v-if="
                                products.length
                            "
                            class="rows"
                        >
                            <div
                                v-for="product in products.slice(
                                    0,
                                    6
                                )"
                                :key="product.id"
                                class="product-row"
                            >
                                <div class="product-thumb">
                                    {{
                                        product.name?.charAt(
                                            0
                                        )
                                    }}
                                </div>

                                <div class="grow">
                                    <b>
                                        {{
                                            product.name
                                        }}
                                    </b>

                                    <small>
                                        {{
                                            product.slug
                                        }}
                                    </small>
                                </div>

                                <strong>
                                    {{
                                        formatPrice(
                                            product.price
                                        )
                                    }}
                                    تومان
                                </strong>

                                <span
                                    class="status"
                                    :class="
                                        product.in_stock
                                            ? 'ok'
                                            : 'bad'
                                    "
                                >
                                    {{
                                        product.in_stock
                                            ? 'موجود'
                                            : 'ناموجود'
                                    }}
                                </span>
<div class="product-actions">

    <button
        class="btn-edit"
        @click="openEditProduct(product.id)"
    >
        ویرایش
    </button>

    <button
        class="btn-delete"
        @click="removeProduct(product.id)"
    >
        حذف
    </button>

</div>

                            </div>
                        </div>

                        <div
                            v-else
                            class="empty"
                        >
                            هنوز محصولی ثبت نشده است.
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- PRODUCTS -->
                <!-- ================================================= -->

                <section
                    v-else-if="
                        section === 'products'
                    "
                >
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    کاتالوگ فروشگاه
                                </p>

                                <h2>
                                    مدیریت محصولات
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="primary"
                                @click="
                                    openProductModal()
                                "
                            >
                                + افزودن محصول
                            </button>
                        </div>

                        <div class="products-search-bar">
                            <input
                                type="text"
                                class="search-input"
                                placeholder="جستجو بر اساس نام، slug یا SKU..."
                                :value="productSearch"
                                @input="onProductSearchInput($event.target.value)"
                            >
                            <span
                                v-if="productSearch"
                                class="search-clear"
                                @click="productSearch = ''; productPage = 1; loadAdminProducts()"
                            >
                                ✕
                            </span>
                        </div>

                        <div
                            v-if="
                                products.length
                            "
                            class="products-table"
                        >
                            <div
                                v-for="product in products"
                                :key="product.id"
                                class="product-row"
                            >
                                <div class="product-thumb">
                                    {{
                                        product.name?.charAt(
                                            0
                                        )
                                    }}
                                </div>

                                <div class="grow">
                                    <b>
                                        {{
                                            product.name
                                        }}
                                    </b>

                                    <small>
                                        {{
                                            product.slug
                                        }}
                                    </small>
                                </div>

                                <strong class="price">
                                    {{
                                        formatPrice(
                                            product.price
                                        )
                                    }}
                                    تومان
                                </strong>

                                <span
                                    class="status"
                                    :class="
                                        product.in_stock
                                            ? 'ok'
                                            : 'bad'
                                    "
                                >
                                    {{
                                        product.in_stock
                                            ? 'فعال'
                                            : 'ناموجود'
                                    }}
                                </span>

                                <div class="product-actions">
                                    <button
                                        type="button"
                                        class="btn-edit"
                                        @click="openEditProduct(product.id)"
                                    >
                                        ویرایش
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-delete"
                                        @click="deleteProduct(product.id)"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="empty"
                        >
                            <div class="empty-icon">
                                ◈
                            </div>

                            <template v-if="productSearch">
                                <h3>
                                    نتیجه‌ای یافت نشد
                                </h3>

                                <p>
                                    هیچ محصولی با عبارت «{{ productSearch }}» مطابقت نداشت.
                                </p>
                            </template>

                            <template v-else>
                                <h3>
                                    هنوز محصولی ندارید
                                </h3>

                                <p>
                                    اولین محصول فروشگاه قطعات خودرو
                                    را اضافه کنید.
                                </p>

                                <button
                                    type="button"
                                    class="primary"
                                    @click="
                                        openProductModal()
                                    "
                                >
                                    افزودن اولین محصول
                                </button>
                            </template>
                        </div>

                        <div
                            v-if="productTotalPages > 1 || productSearch"
                            class="products-pagination"
                        >
                            <span class="pagination-info">
                                صفحه {{ productPage }} از {{ productTotalPages }} — {{ productTotal }} محصول
                            </span>

                            <div class="pagination-buttons">
                                <button
                                    type="button"
                                    class="pagination-btn"
                                    :disabled="productPage <= 1"
                                    @click="goToProductPage(productPage - 1)"
                                >
                                    ◀ قبلی
                                </button>
                                <button
                                    type="button"
                                    class="pagination-btn"
                                    :disabled="productPage >= productTotalPages"
                                    @click="goToProductPage(productPage + 1)"
                                >
                                    بعدی ▶
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- CATEGORIES -->
                <!-- ================================================= -->

                <section
                    v-else-if="
                        section === 'categories'
                    "
                >
                    <div class="category-manager">
                        <!-- Tree Toolbar -->
                        <div class="category-manager-toolbar">
                            <div class="toolbar-left">
                                <label class="search-wrapper">
                                    <input
                                        type="text"
                                        class="search-input"
                                        placeholder="جستجوی دسته‌بندی‌ها..."
                                        v-model="categorySearch"
                                        @input="searchCategories($event.target.value)"
                                        @focus="searchCategories(categorySearch)"
                                    >
                                    <span v-if="categorySearchLoading" class="search-spinner"></span>
                                    <button
                                        v-if="categorySearch"
                                        type="button"
                                        class="search-clear"
                                        @click="categorySearch = ''; searchCategories('')"
                                    >
                                        ✕
                                    </button>
                                </label>
                                <span v-if="categorySearch && categorySearchResults.length" class="search-results-count">
                                    {{ categorySearchResults.length }} نتیجه
                                </span>
                            </div>
                            <div class="toolbar-right">
                                <button
                                    type="button"
                                    class="icon-button"
                                    @click="expandAllCategories"
                                    title="باز کردن همه"
                                >
                                    ▼
                                </button>
                                <button
                                    type="button"
                                    class="icon-button"
                                    @click="collapseAllCategories"
                                    title="بستن همه"
                                >
                                    ▲
                                </button>
                                <button
                                    type="button"
                                    class="primary"
                                    @click="openCreateCategory"
                                >
                                    + دسته اصلی
                                </button>
                            </div>
                        </div>

                        <!-- Main Content: Tree + Editor -->
                        <div class="category-manager-content">
                            <!-- Left: Category Tree -->
                            <aside class="category-tree-panel">
                                <div class="panel-head">
                                    <h3>درخت دسته‌بندی‌ها</h3>
                                </div>

                                <div
                                    v-if="categorySearch && categorySearchResults.length"
                                    class="search-results-tree"
                                >
                                    <div
                                        v-for="result in categorySearchResults"
                                        :key="result.id"
                                        class="tree-node search-result-node"
                                        :class="{ selected: selectedCategoryId === result.id }"
                                        @click="selectCategory(result.id); openEditCategory(result)"
                                    >
                                        <div class="node-content">
                                            <span class="node-indent" :style="{ paddingRight: (result.ancestors?.length || 0) * 16 + 'px' }"></span>
                                            <strong>{{ result.name }}</strong>
                                            <small>/{{ result.slug }}</small>
                                            <span :class="result.is_active ? 'status ok' : 'status bad'">
                                                {{ result.is_active ? 'فعال' : 'غیرفعال' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div v-if="!categorySearchResults.length" class="empty">هیچ دسته‌بندی یافت نشد</div>
                                </div>

                                <div
                                    v-else
                                    class="category-tree"
                                >
                                    <CategoryTreeNode
                                        v-for="category in adminCategories"
                                        :key="category.id"
                                        :category="category"
                                        :selected-id="selectedCategoryId"
                                        :expanded-ids="expandedCategoryIds"
                                        @toggle-expand="toggleCategoryExpand"
                                        @select="selectCategory"
                                        @edit="openEditCategory"
                                        @add-child="openCreateChildCategory"
                                        @delete="deleteCategory"
                                    />
                                    <div v-if="!adminCategories.length" class="empty">
                                        <p>هنوز دسته‌بندی‌ای وجود ندارد</p>
                                        <button class="primary" @click="openCreateCategory">افزودن اولین دسته</button>
                                    </div>
                                </div>
                            </aside>

                            <!-- Right: Category Editor -->
                            <aside class="category-editor-panel" v-if="showCategoryEditor || selectedCategory">
                                <div class="panel-head">
                                    <h3>{{ categoryEditingId ? 'ویرایش دسته‌بندی' : (selectedCategory ? 'جزئیات دسته' : 'دسته‌بندی جدید') }}</h3>
                                </div>

                                <div v-if="selectedCategory && !categoryEditingId" class="category-detail">
                                    <!-- Breadcrumb -->
                                    <nav class="breadcrumb" aria-label="مسیر دسته‌بندی">
                                        <ol>
                                            <li><span>دسته‌بندی‌ها</span></li>
                                            <li v-for="(crumb, index) in categoryBreadcrumb" :key="crumb.id">
                                                <span class="sep">/</span>
                                                <span v-if="index < categoryBreadcrumb.length - 1">{{ crumb.name }}</span>
                                                <strong v-else>{{ crumb.name }}</strong>
                                            </li>
                                        </ol>
                                    </nav>

                                    <div class="detail-fields">
                                        <div class="detail-row">
                                            <label>نام</label>
                                            <span>{{ selectedCategory.name }}</span>
                                        </div>
                                        <div class="detail-row">
                                            <label>Slug</label>
                                            <span dir="ltr">{{ selectedCategory.slug }}</span>
                                        </div>
                                        <div class="detail-row">
                                            <label>دسته والد</label>
                                            <span>{{ selectedCategory.parent_id ? 'دارد' : 'ندارد (ریشه)' }}</span>
                                        </div>
                                        <div class="detail-row">
                                            <label>وضعیت</label>
                                            <span :class="selectedCategory.is_active ? 'status ok' : 'status bad'">
                                                {{ selectedCategory.is_active ? 'فعال' : 'غیرفعال' }}
                                            </span>
                                        </div>
                                        <div class="detail-row" v-if="selectedCategory.description">
                                            <label>توضیحات</label>
                                            <span>{{ selectedCategory.description }}</span>
                                        </div>
                                    </div>

                                    <div class="detail-actions">
                                        <button type="button" class="primary" @click="openEditCategory(selectedCategory)">
                                            ویرایش
                                        </button>
                                        <button type="button" class="text-button" @click="openCreateChildCategory(selectedCategory)">
                                            + افزودن زیر‌دسته
                                        </button>
                                        <button type="button" class="text-button danger" @click="deleteCategory(selectedCategory)">
                                            حذف
                                        </button>
                                    </div>
                                </div>

                                <form
                                    v-else
                                    class="category-form"
                                    @submit.prevent="submitCategory"
                                >
                                    <div class="category-form-head">
                                        <h3>{{ categoryEditingId ? 'ویرایش دسته‌بندی' : 'دسته‌بندی جدید' }}</h3>
                                        <button
                                            type="button"
                                            class="text-button"
                                            @click="closeCategoryEditor"
                                        >
                                            انصراف
                                        </button>
                                    </div>

                                    <div class="category-form-grid">
                                        <label class="form-field">
                                            <span>نام</span>
                                            <input v-model.trim="categoryForm.name" required autocomplete="off">
                                        </label>

                                        <label class="form-field">
                                            <span>Slug</span>
                                            <input v-model.trim="categoryForm.slug" required dir="ltr" autocomplete="off">
                                        </label>

                                        <label class="form-field">
                                            <span>دسته والد</span>
                                            <select v-model="categoryForm.parent_id">
                                                <option :value="null">دسته اصلی (ریشه)</option>
                                                <option
                                                    v-for="category in categoryFormParentOptions"
                                                    :key="category.id"
                                                    :value="category.id"
                                                    :disabled="category.id === categoryEditingId"
                                                >
                                                    {{ '— '.repeat(category.depth) }}{{ category.name }}
                                                </option>
                                            </select>
                                        </label>

                                        <label class="form-field">
                                            <span>ترتیب</span>
                                            <input
                                                v-model.number="categoryForm.sort_order"
                                                type="number"
                                                min="0"
                                            >
                                        </label>

                                        <label class="form-field full-width">
                                            <span>توضیحات</span>
                                            <textarea v-model.trim="categoryForm.description" rows="2"></textarea>
                                        </label>

                                        <label class="form-field full-width">
                                            <span>آدرس تصویر</span>
                                            <input v-model.trim="categoryForm.image" type="url" dir="ltr">
                                        </label>
                                    </div>

                                    <label class="category-active-toggle">
                                        <input v-model="categoryForm.is_active" type="checkbox">
                                        دسته فعال باشد
                                    </label>

                                    <div class="category-form-actions">
                                        <button
                                            type="submit"
                                            class="primary"
                                            :disabled="categorySaving"
                                        >
                                            {{ categorySaving ? 'در حال ذخیره…' : (categoryEditingId ? 'به‌روزرسانی' : 'ایجاد دسته‌بندی') }}
                                        </button>
                                    </div>
                                </form>
                            </aside>

                            <!-- Empty state when no selection -->
                            <div v-if="!showCategoryEditor && !selectedCategory" class="category-editor-empty">
                                <div class="empty-icon">◫</div>
                                <h3>دسته‌ای انتخاب نشده</h3>
                                <p>یک دسته را از سمت چپ انتخاب کنید یا دسته جدید ایجاد کنید</p>
                                <button class="primary" @click="openCreateCategory">+ دسته اصلی جدید</button>
                            </div>
                        </div>
                    </div>

                    <!-- Category Attribute Editor (kept from original) -->
                    <div class="category-attribute-editor">
                        <div>
                            <p class="section-label">پیکربندی ویژگی‌ها</p>
                            <h3>ویژگی‌های دسته‌بندی</h3>
                            <p class="editor-help">
                                برای هر ویژگی می‌توانید استفاده از تنظیم والد، فعال‌سازی، یا غیرفعال‌سازی را انتخاب کنید.
                            </p>
                        </div>

                        <label class="form-field">
                            <span>دسته‌بندی</span>
                            <select
                                :value="configuredCategoryId || ''"
                                @change="selectCategoryForAttributes($event.target.value)"
                            >
                                <option value="">یک دسته را انتخاب کنید</option>
                                <option
                                    v-for="category in categoryConfigurationOptions"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ '— '.repeat(category.depth) }}{{ category.name }}
                                </option>
                            </select>
                        </label>

                        <p v-if="categoryAttributeLoading" class="editor-help">
                            در حال دریافت تنظیمات…
                        </p>

                        <template v-else-if="configuredCategory">
                            <div class="configuration-heading">
                                تنظیم ویژگی‌ها برای «{{ configuredCategory.name }}»
                            </div>

                            <div class="category-attribute-config-list">
                                <div
                                    v-for="attribute in attributes"
                                    :key="attribute.id"
                                    class="category-attribute-config"
                                    :class="{
                                        enabled: isCategoryAttributeConfigured(attribute.id),
                                        inherited: isCategoryAttributeInherited(attribute.id),
                                    }"
                                >
                                    <div class="config-toggle-row">
                                        <span class="config-label">
                                            <strong>{{ attribute.name }}</strong>
                                            <small>{{ attribute.type }}</small>
                                        </span>

                                        <select
                                            class="attribute-state-select"
                                            :value="getAttributeState(attribute.id)"
                                            @change="setAttributeState(attribute, $event.target.value)"
                                        >
                                            <option
                                                v-if="configuredCategory?.parent_id"
                                                value="inherit"
                                            >
                                                استفاده از تنظیم والد
                                            </option>
                                            <option value="enabled">فعال</option>
                                            <option value="disabled">غیرفعال</option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="isCategoryAttributeConfigured(attribute.id)"
                                        class="config-fields"
                                    >
                                        <label>
                                            <input
                                                type="checkbox"
                                                :checked="categoryAttributeConfiguration(attribute.id).is_required"
                                                @change="updateCategoryAttributeConfig(attribute.id, 'is_required', $event.target.checked)"
                                            >
                                            اجباری
                                        </label>
                                        <label>
                                            <input
                                                type="checkbox"
                                                :checked="categoryAttributeConfiguration(attribute.id).is_filterable"
                                                @change="updateCategoryAttributeConfig(attribute.id, 'is_filterable', $event.target.checked)"
                                            >
                                            فیلتر
                                        </label>
                                        <label :class="{ disabled: !supportsVariantAxis(attribute) }">
                                            <input
                                                type="checkbox"
                                                :disabled="!supportsVariantAxis(attribute)"
                                                :checked="categoryAttributeConfiguration(attribute.id).is_variant_axis"
                                                @change="updateCategoryAttributeConfig(attribute.id, 'is_variant_axis', $event.target.checked)"
                                            >
                                            محور تنوع
                                        </label>
                                        <label class="sort-input">
                                            ترتیب
                                            <input
                                                type="number"
                                                min="0"
                                                :value="categoryAttributeConfiguration(attribute.id).sort_order"
                                                @input="updateCategoryAttributeConfig(attribute.id, 'sort_order', Number($event.target.value) || 0)"
                                            >
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="config-actions">
                                <button
                                    type="button"
                                    class="primary"
                                    :disabled="categoryAttributeSaving"
                                    @click="saveCategoryAttributes"
                                >
                                    {{ categoryAttributeSaving ? 'در حال ذخیره…' : 'ذخیره تنظیمات' }}
                                </button>
                            </div>
                        </template>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- ARTICLES -->
                <!-- ================================================= -->

                <section
                    v-else-if="
                        section === 'articles'
                    "
                >
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    مدیریت محتوا
                                </p>

                                <h2>
                                    مقالات
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="primary"
                                @click="openCreateArticle"
                            >
                                + مقاله جدید
                            </button>
                        </div>

                        <!-- Search & Filter -->
                        <div class="articles-toolbar">
                            <div class="toolbar-left">
                                <label class="search-wrapper">
                                    <input
                                        type="text"
                                        class="search-input"
                                        placeholder="جستجوی مقالات..."
                                        v-model="adminArticleSearch"
                                        @input="loadAdminArticles"
                                    >
                                    <button
                                        v-if="adminArticleSearch"
                                        type="button"
                                        class="search-clear"
                                        @click="adminArticleSearch = ''; loadAdminArticles()"
                                    >
                                        ✕
                                    </button>
                                </label>

                                <label class="form-field filter-select">
                                    <span>وضعیت</span>
                                    <select v-model="adminArticleStatusFilter" @change="loadAdminArticles">
                                        <option value="">همه</option>
                                        <option value="draft">پیش‌نویس</option>
                                        <option value="published">منتشرشده</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <!-- Articles List -->
                        <div
                            v-if="adminArticleLoading"
                            class="loading"
                        >
                            <div class="spinner"></div>
                            <span>در حال بارگذاری مقالات...</span>
                        </div>

                        <template v-else>
                            <div
                                v-if="adminArticles.length"
                                class="articles-table"
                            >
                                <div class="table-header">
                                    <div class="col-title">عنوان</div>
                                    <div class="col-status">وضعیت</div>
                                    <div class="col-author">نویسنده</div>
                                    <div class="col-date">تاریخ انتشار</div>
                                    <div class="col-featured">ویژه</div>
                                    <div class="col-actions">عملیات</div>
                                </div>

                                <div
                                    v-for="article in adminArticles"
                                    :key="article.id"
                                    class="table-row"
                                >
                                    <div class="col-title">
                                        <strong>{{ article.title }}</strong>
                                        <small dir="ltr">/{{ article.slug }}</small>
                                    </div>

                                    <div class="col-status">
                                        <span :class="article.status === 'published' ? 'status ok' : 'status warning'">
                                            {{ article.status === 'published' ? 'منتشرشده' : 'پیش‌نویس' }}
                                        </span>
                                    </div>

                                    <div class="col-author">
                                        {{ article.author?.name || '—' }}
                                    </div>

                                    <div class="col-date">
                                        {{ article.published_at ? formatDate(article.published_at) : '—' }}
                                    </div>

                                    <div class="col-featured">
                                        <span v-if="article.is_featured" class="badge featured">⭐ ویژه</span>
                                        <span v-else class="badge normal">—</span>
                                    </div>

                                    <div class="col-actions">
                                        <button type="button" class="text-button" @click="openEditArticle(article)">
                                            ویرایش
                                        </button>
                                        <button type="button" class="text-button danger" @click="deleteArticle(article.id)">
                                            حذف
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="empty"
                            >
                                <div class="empty-icon">📝</div>
                                <template v-if="adminArticleSearch || adminArticleStatusFilter">
                                    <h3>نتیجه‌ای یافت نشد</h3>
                                    <p>هیچ مقاله‌ای با فیلترهای انتخاب شده یافت نشد.</p>
                                </template>
                                <template v-else>
                                    <h3>هنوز مقاله‌ای وجود ندارد</h3>
                                    <p>اولین مقاله را اضافه کنید.</p>
                                    <button
                                        type="button"
                                        class="primary"
                                        @click="openCreateArticle"
                                    >
                                        + مقاله جدید
                                    </button>
                                </template>
                            </div>

                            <!-- Pagination -->
                            <div
                                v-if="adminArticleTotalPages > 1"
                                class="articles-pagination"
                            >
                                <span class="pagination-info">
                                    صفحه {{ adminArticlePage }} از {{ adminArticleTotalPages }} — {{ adminArticleTotal }} مقاله
                                </span>

                                <div class="pagination-buttons">
                                    <button
                                        type="button"
                                        class="pagination-btn"
                                        :disabled="adminArticlePage <= 1"
                                        @click="goToArticlePage(adminArticlePage - 1)"
                                    >
                                        ◀ قبلی
                                    </button>
                                    <button
                                        type="button"
                                        class="pagination-btn"
                                        :disabled="adminArticlePage >= adminArticleTotalPages"
                                        @click="goToArticlePage(adminArticlePage + 1)"
                                    >
                                        بعدی ▶
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Article Form Modal -->
                        <div
                            v-if="showArticleEditor"
                            class="modal-overlay"
                            @click.self="closeArticleForm"
                        >
                            <div class="modal article-form-modal">
                                <div class="modal-header">
                                    <h3>{{ articleEditingId ? 'ویرایش مقاله' : 'مقاله جدید' }}</h3>
                                    <button type="button" class="modal-close" @click="closeArticleForm">✕</button>
                                </div>

                                <form
                                    class="modal-body"
                                    @submit.prevent="saveArticle"
                                >
                                    <div class="form-section">
                                        <h4>اطلاعات اصلی</h4>

                                        <div class="form-grid">
                                            <label class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.title }">
                                                <span>عنوان <span class="required">*</span></span>
                                                <input v-model.trim="articleForm.title" autocomplete="off">
                                                <span v-if="articleFormErrors && articleFormErrors.title" class="field-error-text">{{ articleFormErrors.title }}</span>
                                            </label>

                                            <label class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.slug }">
                                                <span>Slug <span class="required">*</span></span>
                                                <input v-model.trim="articleForm.slug" dir="ltr" autocomplete="off">
                                                <span v-if="articleFormErrors && articleFormErrors.slug" class="field-error-text">{{ articleFormErrors.slug }}</span>
                                            </label>

                                            <label class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.status }">
                                                <span>وضعیت</span>
                                                <select v-model="articleForm.status">
                                                    <option value="draft">پیش‌نویس</option>
                                                    <option value="published">منتشرشده</option>
                                                </select>
                                                <span v-if="articleFormErrors && articleFormErrors.status" class="field-error-text">{{ articleFormErrors.status }}</span>
                                            </label>

                                            <label class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.published_at }">
                                                <span>تاریخ انتشار</span>
                                                <input v-model="articleForm.published_at" type="datetime-local">
                                                <span v-if="articleFormErrors && articleFormErrors.published_at" class="field-error-text">{{ articleFormErrors.published_at }}</span>
                                            </label>

                                            <label class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.author_id }">
                                                <span>نویسنده</span>
                                                <select v-model="articleForm.author_id">
                                                    <option value="">خودکار (کاربر جاری)</option>
                                                </select>
                                                <span v-if="articleFormErrors && articleFormErrors.author_id" class="field-error-text">{{ articleFormErrors.author_id }}</span>
                                            </label>

                                            <label class="form-field full-width" :class="{ 'field-error': articleFormErrors && articleFormErrors.excerpt }">
                                                <span>خلاصه</span>
                                                <textarea v-model.trim="articleForm.excerpt" rows="3" placeholder="خلاصه کوتاه مقاله برای نمایش در لیست‌ها"></textarea>
                                                <span v-if="articleFormErrors && articleFormErrors.excerpt" class="field-error-text">{{ articleFormErrors.excerpt }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h4>محتوای مقاله</h4>

                                        <label class="form-field full-width" :class="{ 'field-error': articleFormErrors && articleFormErrors.content }">
                                            <span>متن کامل</span>
                                            <textarea v-model.trim="articleForm.content" rows="15" class="content-editor" placeholder="محتوای مقاله (برای ویرایشگر غنی در آینده آماده است)"></textarea>
                                            <span v-if="articleFormErrors && articleFormErrors.content" class="field-error-text">{{ articleFormErrors.content }}</span>
                                        </label>
                                    </div>

                                    <div class="form-section">
                                        <h4>تصویر شاخص</h4>

                                        <label class="form-field full-width" :class="{ 'field-error': articleFormErrors && articleFormErrors.featured_image }">
                                            <span>آدرس تصویر</span>
                                            <input v-model.trim="articleForm.featured_image" type="url" dir="ltr" placeholder="https://example.com/image.jpg">
                                            <span v-if="articleFormErrors && articleFormErrors.featured_image" class="field-error-text">{{ articleFormErrors.featured_image }}</span>
                                        </label>
                                    </div>

                                    <div class="form-section">
                                        <h4>روابط</h4>

                                        <div class="form-grid">
                                            <div class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.categories }">
                                                <span>دسته‌بندی‌ها</span>
                                                <div class="multi-select-wrapper">
                                                    <MultiSelect
                                                        v-model="articleForm.categories"
                                                        :options="availableCategories"
                                                        option-label="name"
                                                        option-value="id"
                                                        placeholder="جستجو و انتخاب دسته‌بندی‌ها..."
                                                        :searchable="true"
                                                    />
                                                </div>
                                                <span v-if="articleFormErrors && articleFormErrors.categories" class="field-error-text">{{ articleFormErrors.categories }}</span>
                                            </div>

                                            <div class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.products }">
                                                <span>محصولات</span>
                                                <div class="multi-select-wrapper">
                                                    <MultiSelect
                                                        v-model="articleForm.products"
                                                        :options="availableProducts"
                                                        option-label="name"
                                                        option-value="id"
                                                        placeholder="جستجو و انتخاب محصولات..."
                                                        :searchable="true"
                                                    />
                                                </div>
                                                <span v-if="articleFormErrors && articleFormErrors.products" class="field-error-text">{{ articleFormErrors.products }}</span>
                                            </div>

                                            <div class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.vehicles }">
                                                <span>خودروها (موتورها)</span>
                                                <div class="multi-select-wrapper">
                                                    <MultiSelect
                                                        v-model="articleForm.vehicles"
                                                        :options="availableVehicles"
                                                        option-label="name"
                                                        option-value="id"
                                                        placeholder="جستجو و انتخاب موتورهای خودرو..."
                                                        :searchable="true"
                                                    />
                                                </div>
                                                <span v-if="articleFormErrors && articleFormErrors.vehicles" class="field-error-text">{{ articleFormErrors.vehicles }}</span>
                                            </div>

                                            <div class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.brands }">
                                                <span>برندها</span>
                                                <div class="multi-select-wrapper">
                                                    <MultiSelect
                                                        v-model="articleForm.brands"
                                                        :options="availableBrands"
                                                        option-label="name"
                                                        option-value="id"
                                                        placeholder="جستجو و انتخاب برندها..."
                                                        :searchable="true"
                                                    />
                                                </div>
                                                <span v-if="articleFormErrors && articleFormErrors.brands" class="field-error-text">{{ articleFormErrors.brands }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h4>SEO</h4>

                                        <div class="form-grid">
                                            <label class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.meta_title }">
                                                <span>Meta Title</span>
                                                <input v-model.trim="articleForm.meta_title" maxlength="60" placeholder="حداکثر 60 کاراکتر">
                                                <span v-if="articleFormErrors && articleFormErrors.meta_title" class="field-error-text">{{ articleFormErrors.meta_title }}</span>
                                            </label>

                                            <label class="form-field" :class="{ 'field-error': articleFormErrors && articleFormErrors.canonical_url }">
                                                <span>Canonical URL</span>
                                                <input v-model.trim="articleForm.canonical_url" type="url" dir="ltr" placeholder="https://example.com/article-slug">
                                                <span v-if="articleFormErrors && articleFormErrors.canonical_url" class="field-error-text">{{ articleFormErrors.canonical_url }}</span>
                                            </label>

                                            <label class="form-field full-width" :class="{ 'field-error': articleFormErrors && articleFormErrors.meta_description }">
                                                <span>Meta Description</span>
                                                <textarea v-model.trim="articleForm.meta_description" rows="2" maxlength="160" placeholder="حداکثر 160 کاراکتر"></textarea>
                                                <span v-if="articleFormErrors && articleFormErrors.meta_description" class="field-error-text">{{ articleFormErrors.meta_description }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-section" :class="{ 'field-error': articleFormErrors && articleFormErrors.is_featured }">
                                        <label class="checkbox-field">
                                            <input type="checkbox" v-model="articleForm.is_featured">
                                            <span>مقاله ویژه (نمایش در صفحه اصلی)</span>
                                        </label>
                                        <span v-if="articleFormErrors && articleFormErrors.is_featured" class="field-error-text">{{ articleFormErrors.is_featured }}</span>
                                    </div>

                                    <div class="alert"
                                        v-if="adminArticleError || adminArticleSuccess"
                                        :class="adminArticleError ? 'error' : 'success'"
                                    >
                                        {{ adminArticleError || adminArticleSuccess }}
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="text-button" @click="closeArticleForm">
                                            انصراف
                                        </button>
                                        <button
                                            type="submit"
                                            class="primary"
                                            :disabled="articleSaving"
                                        >
                                            {{ articleSaving ? 'در حال ذخیره…' : (articleEditingId ? 'به‌روزرسانی' : 'ایجاد مقاله') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- ATTRIBUTES -->
                <!-- ================================================= -->

                <section
                    v-else-if="
                        section === 'attributes'
                    "
                >
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    ویژگی‌های قابل استفاده
                                </p>

                                <h2>
                                    ویژگی‌ها
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="primary"
                                @click="openCreateAttribute"
                            >
                                + ویژگی جدید
                            </button>
                        </div>

                        <form
                            class="attribute-form"
                            @submit.prevent="submitAttribute"
                        >
                            <div class="category-form-head">
                                <h3>
                                    {{ attributeEditingId ? 'ویرایش ویژگی' : 'ویژگی جدید' }}
                                </h3>

                                <button
                                    v-if="attributeEditingId"
                                    type="button"
                                    class="text-button"
                                    @click="openCreateAttribute"
                                >
                                    انصراف
                                </button>
                            </div>

                            <div class="category-form-grid">
                                <label class="form-field">
                                    <span>نام</span>
                                    <input v-model.trim="attributeForm.name" required>
                                </label>

                                <label class="form-field">
                                    <span>Slug</span>
                                    <input v-model.trim="attributeForm.slug" required dir="ltr">
                                </label>

                                <label class="form-field">
                                    <span>نوع</span>
                                    <select v-model="attributeForm.type">
                                        <option value="select">select</option>
                                        <option value="multiselect">multiselect</option>
                                        <option value="color">color</option>
                                        <option value="number">number</option>
                                        <option value="boolean">boolean</option>
                                        <option value="text">text</option>
                                    </select>
                                </label>

                                <label class="form-field">
                                    <span>ترتیب</span>
                                    <input v-model.number="attributeForm.sort_order" type="number" min="0">
                                </label>
                            </div>

                            <div class="category-form-actions">
                                <button type="submit" class="primary" :disabled="attributeSaving">
                                    {{ attributeSaving ? 'در حال ذخیره…' : 'ذخیره ویژگی' }}
                                </button>
                            </div>
                        </form>

                        <div class="attribute-list">
                            <div
                                v-for="attribute in attributes"
                                :key="attribute.id"
                                class="attribute-item"
                            >
                                <div>
                                    <strong>
                                        {{
                                            attribute.name
                                        }}
                                    </strong>

                                    <small>
                                        {{
                                            attribute.type
                                        }}
                                    </small>
                                </div>

                                <div class="category-row-actions">
                                    <span>
                                        {{ attribute.values?.length || 0 }} مقدار
                                    </span>
                                    <button type="button" class="text-button" @click="openEditAttribute(attribute)">
                                        ویرایش
                                    </button>
                                    <button type="button" class="text-button danger" @click="deleteAttribute(attribute)">
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="attributeEditingId && currentEditingAttribute()"
                            class="attribute-values-editor"
                        >
                            <div>
                                <p class="section-label">مقدارهای ازپیش‌تعریف‌شده</p>
                                <h3>مقدارهای «{{ currentEditingAttribute().name }}»</h3>
                            </div>

                            <p
                                v-if="!isOptionAttribute(currentEditingAttribute())"
                                class="editor-help"
                            >
                                نوع {{ currentEditingAttribute().type }} مقدار ثابت ندارد؛ مقدار آن هنگام ثبت محصول وارد می‌شود.
                            </p>

                            <template v-else>
                                <form
                                    class="attribute-value-form"
                                    @submit.prevent="submitAttributeValue"
                                >
                                    <div class="attribute-value-grid">
                                        <label class="form-field">
                                            <span>برچسب</span>
                                            <input v-model.trim="attributeValueForm.label" required>
                                        </label>
                                        <label class="form-field">
                                            <span>مقدار</span>
                                            <input v-model.trim="attributeValueForm.value" required dir="ltr">
                                        </label>
                                        <label
                                            v-if="currentEditingAttribute().type === 'color'"
                                            class="form-field"
                                        >
                                            <span>کد رنگ</span>
                                            <input v-model.trim="attributeValueForm.hex_color" placeholder="#000000" dir="ltr">
                                        </label>
                                        <label class="form-field">
                                            <span>ترتیب</span>
                                            <input v-model.number="attributeValueForm.sort_order" type="number" min="0">
                                        </label>
                                    </div>

                                    <div class="category-form-actions">
                                        <button
                                            v-if="attributeValueEditingId"
                                            type="button"
                                            class="text-button"
                                            @click="resetAttributeValueForm"
                                        >
                                            انصراف
                                        </button>
                                        <button type="submit" class="primary" :disabled="attributeValueSaving">
                                            {{ attributeValueSaving ? 'در حال ذخیره…' : attributeValueEditingId ? 'به‌روزرسانی مقدار' : 'افزودن مقدار' }}
                                        </button>
                                    </div>
                                </form>

                                <div class="attribute-value-list">
                                    <div
                                        v-for="value in currentEditingAttribute().values || []"
                                        :key="value.id"
                                        class="attribute-value-item"
                                    >
                                        <span
                                            v-if="value.hex_color"
                                            class="color-swatch"
                                            :style="{ background: value.hex_color }"
                                        ></span>
                                        <div class="grow">
                                            <strong>{{ value.label }}</strong>
                                            <small>{{ value.value }}</small>
                                        </div>
                                        <span>{{ value.sort_order }}</span>
                                        <button type="button" class="text-button" @click="openEditAttributeValue(value)">ویرایش</button>
                                        <button type="button" class="text-button danger" @click="deleteAttributeValue(value)">حذف</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- VEHICLES -->
                <!-- ================================================= -->

                <section
                    v-else-if="section === 'vehicles'"
                >
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    مدیریت خودرو
                                </p>

                                <h2>
                                    خودروها
                                </h2>
                            </div>

                            <button
                                v-if="vehicleLevel === 'brands'"
                                type="button"
                                class="primary"
                                @click="openVehicleForm('brands')"
                            >
                                + برند جدید
                            </button>
                            <button
                                v-else-if="vehicleLevel === 'models'"
                                type="button"
                                class="primary"
                                @click="openVehicleForm('models')"
                            >
                                + مدل جدید
                            </button>
                            <button
                                v-else-if="vehicleLevel === 'generations'"
                                type="button"
                                class="primary"
                                @click="openVehicleForm('generations')"
                            >
                                + نسل جدید
                            </button>
                            <button
                                v-else-if="vehicleLevel === 'trims'"
                                type="button"
                                class="primary"
                                @click="openVehicleForm('trims')"
                            >
                                + تیپ جدید
                            </button>
                            <button
                                v-else-if="vehicleLevel === 'engines'"
                                type="button"
                                class="primary"
                                @click="openVehicleForm('engines')"
                            >
                                + موتور جدید
                            </button>
                        </div>

                        <!-- ERROR -->
                        <div v-if="vehicleError" class="alert error">
                            {{ vehicleError }}
                        </div>

                        <!-- BREADCRUMB -->
                        <div class="vehicle-breadcrumb">
                            <button
                                type="button"
                                class="text-button"
                                :class="{ active: vehicleLevel === 'brands' }"
                                @click="vehicleLevel = 'brands'; selectedBrandId = null; selectedModelId = null; selectedGenerationId = null; selectedTrimId = null; loadVehicleBrands()"
                            >
                                برندها
                            </button>
                            <template v-if="selectedBrandId">
                                <span class="bc-sep">‹</span>
                                <button
                                    type="button"
                                    class="text-button"
                                    :class="{ active: vehicleLevel === 'models' }"
                                    @click="selectBrand(selectedBrandId)"
                                >
                                    {{ vehicleBrands.find(b => b.id === selectedBrandId)?.name || 'مدل‌ها' }}
                                </button>
                            </template>
                            <template v-if="selectedModelId">
                                <span class="bc-sep">‹</span>
                                <button
                                    type="button"
                                    class="text-button"
                                    :class="{ active: vehicleLevel === 'generations' }"
                                    @click="selectModel(selectedModelId)"
                                >
                                    {{ vehicleModels.find(m => m.id === selectedModelId)?.name || 'نسل‌ها' }}
                                </button>
                            </template>
                            <template v-if="selectedGenerationId">
                                <span class="bc-sep">‹</span>
                                <button
                                    type="button"
                                    class="text-button"
                                    :class="{ active: vehicleLevel === 'trims' }"
                                    @click="selectGeneration(selectedGenerationId)"
                                >
                                    {{ vehicleGenerations.find(g => g.id === selectedGenerationId)?.name || 'تیپ‌ها' }}
                                </button>
                            </template>
                            <template v-if="selectedTrimId">
                                <span class="bc-sep">‹</span>
                                <button
                                    type="button"
                                    class="text-button active"
                                    @click="selectTrim(selectedTrimId)"
                                >
                                    {{ vehicleTrims.find(t => t.id === selectedTrimId)?.name || 'موتورها' }}
                                </button>
                            </template>
                        </div>

                        <!-- LOADING -->
                        <div v-if="vehicleLoading" class="loading" style="min-height:100px">
                            <div class="spinner"></div>
                            <span>در حال دریافت اطلاعات...</span>
                        </div>

                        <!-- VEHICLE FORM -->
                        <form
                            v-if="!vehicleLoading"
                            class="vehicle-form"
                            @submit.prevent="submitVehicle"
                        >
                            <div class="vehicle-form-grid">
                                <label class="form-field">
                                    <span>نام</span>
                                    <input v-model.trim="vehicleForm.name" required>
                                </label>

                                <label class="form-field">
                                    <span>Slug</span>
                                    <input v-model.trim="vehicleForm.slug" required dir="ltr">
                                </label>

                                <template v-if="vehicleLevel === 'generations'">
                                    <label class="form-field">
                                        <span>سال شروع</span>
                                        <input v-model.number="vehicleForm.year_start" type="number" min="1900" max="2100">
                                    </label>
                                    <label class="form-field">
                                        <span>سال پایان</span>
                                        <input v-model.number="vehicleForm.year_end" type="number" min="1900" max="2100">
                                    </label>
                                </template>

                                <template v-if="vehicleLevel === 'engines'">
                                    <label class="form-field">
                                        <span>حجم موتور</span>
                                        <input v-model.number="vehicleForm.displacement" type="number" step="0.1" min="0">
                                    </label>
                                    <label class="form-field">
                                        <span>نوع سوخت</span>
                                        <input v-model.trim="vehicleForm.fuel_type" placeholder="بنزین، دیزل، هیبرید...">
                                    </label>
                                    <label class="form-field">
                                        <span>قدرت (اسب‌بخار)</span>
                                        <input v-model.number="vehicleForm.horsepower" type="number" min="0">
                                    </label>
                                </template>
                            </div>

                            <label class="category-active-toggle">
                                <input v-model="vehicleForm.is_active" type="checkbox">
                                فعال باشد
                            </label>

                            <div class="category-form-actions">
                                <button
                                    type="submit"
                                    class="primary"
                                    :disabled="vehicleSaving"
                                >
                                    {{ vehicleSaving ? 'در حال ذخیره…' : (vehicleEditingId ? 'به‌روزرسانی' : 'ذخیره') }}
                                </button>
                                <button
                                    v-if="vehicleEditingId"
                                    type="button"
                                    class="text-button"
                                    @click="vehicleEditingId = null; vehicleForm = createEmptyVehicleForm()"
                                >
                                    انصراف
                                </button>
                            </div>
                        </form>

                        <!-- LIST -->
                        <div v-if="!vehicleLoading && vehicleLevel === 'brands'" class="vehicle-list">
                            <div v-if="vehicleBrands.length === 0" class="empty">
                                هنوز برندی ثبت نشده است.
                            </div>
                            <div
                                v-for="brand in vehicleBrands"
                                :key="brand.id"
                                class="vehicle-item"
                            >
                                <div class="vehicle-item-info" @click="selectBrand(brand.id)">
                                    <strong>{{ brand.name }}</strong>
                                    <small>/{{ brand.slug }}</small>
                                    <span :class="brand.is_active ? 'status ok' : 'status bad'">
                                        {{ brand.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                    <span class="hint">{{ brand.models_count }} مدل</span>
                                </div>
                                <div class="vehicle-item-actions">
                                    <button type="button" class="text-button" @click="openVehicleForm('brands', brand)">ویرایش</button>
                                    <button type="button" class="text-button danger" @click="deleteVehicleItem('brands', brand.id)">حذف</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="!vehicleLoading && vehicleLevel === 'models'" class="vehicle-list">
                            <div v-if="vehicleModels.length === 0" class="empty">
                                برای این برند مدلی ثبت نشده است.
                            </div>
                            <div
                                v-for="model in vehicleModels"
                                :key="model.id"
                                class="vehicle-item"
                            >
                                <div class="vehicle-item-info" @click="selectModel(model.id)">
                                    <strong>{{ model.name }}</strong>
                                    <small>/{{ model.slug }}</small>
                                    <span :class="model.is_active ? 'status ok' : 'status bad'">
                                        {{ model.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                    <span class="hint">{{ model.generations_count }} نسل</span>
                                </div>
                                <div class="vehicle-item-actions">
                                    <button type="button" class="text-button" @click="openVehicleForm('models', model)">ویرایش</button>
                                    <button type="button" class="text-button danger" @click="deleteVehicleItem('models', model.id)">حذف</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="!vehicleLoading && vehicleLevel === 'generations'" class="vehicle-list">
                            <div v-if="vehicleGenerations.length === 0" class="empty">
                                برای این مدل نسلی ثبت نشده است.
                            </div>
                            <div
                                v-for="gen in vehicleGenerations"
                                :key="gen.id"
                                class="vehicle-item"
                            >
                                <div class="vehicle-item-info" @click="selectGeneration(gen.id)">
                                    <strong>{{ gen.name }}</strong>
                                    <small>/{{ gen.slug }}</small>
                                    <span :class="gen.is_active ? 'status ok' : 'status bad'">
                                        {{ gen.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                    <span class="hint" v-if="gen.year_start">{{ gen.year_start }}{{ gen.year_end ? ' - ' + gen.year_end : '' }}</span>
                                    <span class="hint">{{ gen.trims_count }} تیپ</span>
                                </div>
                                <div class="vehicle-item-actions">
                                    <button type="button" class="text-button" @click="openVehicleForm('generations', gen)">ویرایش</button>
                                    <button type="button" class="text-button danger" @click="deleteVehicleItem('generations', gen.id)">حذف</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="!vehicleLoading && vehicleLevel === 'trims'" class="vehicle-list">
                            <div v-if="vehicleTrims.length === 0" class="empty">
                                برای این نسل تیپی ثبت نشده است.
                            </div>
                            <div
                                v-for="trim in vehicleTrims"
                                :key="trim.id"
                                class="vehicle-item"
                            >
                                <div class="vehicle-item-info" @click="selectTrim(trim.id)">
                                    <strong>{{ trim.name }}</strong>
                                    <small>/{{ trim.slug }}</small>
                                    <span :class="trim.is_active ? 'status ok' : 'status bad'">
                                        {{ trim.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                    <span class="hint">{{ trim.engines_count }} موتور</span>
                                </div>
                                <div class="vehicle-item-actions">
                                    <button type="button" class="text-button" @click="openVehicleForm('trims', trim)">ویرایش</button>
                                    <button type="button" class="text-button danger" @click="deleteVehicleItem('trims', trim.id)">حذف</button>
                                </div>
                            </div>
                        </div>

                        <div v-if="!vehicleLoading && vehicleLevel === 'engines'" class="vehicle-list">
                            <div v-if="vehicleEngines.length === 0" class="empty">
                                برای این تیپ موتوری ثبت نشده است.
                            </div>
                            <div
                                v-for="engine in vehicleEngines"
                                :key="engine.id"
                                class="vehicle-item"
                            >
                                <div class="vehicle-item-info">
                                    <strong>{{ engine.name }}</strong>
                                    <small>/{{ engine.slug }}</small>
                                    <span :class="engine.is_active ? 'status ok' : 'status bad'">
                                        {{ engine.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                    <span class="hint" v-if="engine.displacement">{{ engine.displacement }}L</span>
                                    <span class="hint" v-if="engine.fuel_type">{{ engine.fuel_type }}</span>
                                    <span class="hint" v-if="engine.horsepower">{{ engine.horsepower }}hp</span>
                                </div>
                                <div class="vehicle-item-actions">
                                    <button type="button" class="text-button" @click="openVehicleForm('engines', engine)">ویرایش</button>
                                    <button type="button" class="text-button danger" @click="deleteVehicleItem('engines', engine.id)">حذف</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- ORDER DETAIL (shown when showOrderDetail is true) -->
                <!-- ================================================= -->

                <section
                    v-else-if="showOrderDetail"
                >
                    <AdminOrderDetailPage :order-id="selectedOrderId" @back="closeOrderDetail" />
                </section>

                <!-- ================================================= -->
                <!-- ORDERS LIST -->
                <!-- ================================================= -->

                <section
v-else-if="section === 'orders'"
            >
                <AdminOrdersPage @open-detail="openOrderDetail" />
            </section>

            <!-- ================================================= -->
            <!-- USERS -->
            <!-- ================================================= -->

            <section
                v-else-if="section === 'users'"
            >
                <AdminUsersPage />
            </section>

            <!-- ================================================= -->
            <!-- COMMENTS -->
            <!-- ================================================= -->

            <section
                v-else-if="section === 'comments'"
            >
                <AdminCommentsPage />
            </section>

            <!-- ================================================= -->
            <!-- SETTINGS -->
            <!-- ================================================= -->

            <section
                v-else-if="section === 'settings'"
                class="panel"
            >
                <div class="empty">
                    تنظیمات در مرحله بعدی
                    تکمیل می‌شود.
                </div>
            </section>

            <!-- ================================================= -->
            <!-- OTHER -->
            <!-- ================================================= -->

            <section
                v-else
                class="panel"
            >
                <div class="empty">
                    این بخش در مرحله بعدی
                    تکمیل می‌شود.
                </div>
            </section>
        </template>
        </main>

        <!-- ================================================= -->
        <!-- PRODUCT MODAL -->
        <!-- ================================================= -->

        <Teleport to="body">
            <div
                v-if="showProductModal"
                class="modal-backdrop"
                @click.self="
                    closeProductModal()
                "
            >
                <div class="modal">
                    <!-- MODAL HEADER -->

                    <div class="modal-header">
                        <div>
                            <p class="section-label">
                                کاتالوگ فروشگاه
                            </p>

                            <h2>
                                   {{ editingProductId ? 'ویرایش محصول' : 'افزودن محصول جدید' }}

                            </h2>
                        </div>

                        <button
                            type="button"
                            class="close-button"
                            @click="
                                closeProductModal()
                            "
                        >
                            ×
                        </button>
                    </div>

                    <!-- ERROR -->

                    <div
                        v-if="errorMessage"
                        class="alert error"
                    >
                        {{ errorMessage }}
                    </div>

                    <!-- FORM -->

                    <form
                        class="product-form"
                        @submit.prevent="
                            submitProduct()
                        "
                    >
                        <!-- BASIC -->

                        <div class="form-section">
                            <div class="form-section-title">
                                اطلاعات اصلی
                            </div>

                            <div class="form-grid">
                                <label class="field full">
                                    <span>
                                        نام محصول
                                    </span>

                                    <input
                                        v-model="
                                            form.name
                                        "
                                        type="text"
                                        placeholder="مثلاً کت زنانه کلاسیک"
                                        required
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        Slug
                                    </span>

                                    <input
                                        v-model="
                                            form.slug
                                        "
                                        type="text"
                                        dir="ltr"
                                        placeholder="classic-women-coat"
                                        required
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        SKU
                                    </span>

                                    <input
                                        v-model="
                                            form.sku
                                        "
                                        type="text"
                                        dir="ltr"
                                        placeholder="ROY-001"
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        قیمت
                                    </span>

                                    <input
                                        v-model.number="
                                            form.price
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        required
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        قیمت قبل
                                    </span>

                                    <input
                                        v-model.number="
                                            form.compare_at_price
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="اختیاری"
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        موجودی
                                    </span>

                                    <input
                                        v-model.number="
                                            form.stock
                                        "
                                        type="number"
                                        min="0"
                                    />
                                </label>
                            </div>
                        </div>
<!-- ================================================= -->
<!-- PRODUCT IMAGES -->
<!-- ================================================= -->

<div class="form-section">

    <div class="form-section-title">
        تصاویر محصول
    </div>

    <p
        v-if="!editingProductId"
        class="hint"
    >
        تصاویر را همین‌جا انتخاب کنید؛ پس از ذخیره محصول،
        تصاویر به‌صورت خودکار آپلود می‌شوند.
    </p>

    <p
        v-else
        class="hint"
    >
        تصاویر جدید را انتخاب کنید یا تصاویر موجود را مدیریت کنید.
    </p>

    <!-- IMAGE UPLOAD -->

    <div class="image-upload-box">

        <label
            for="product-image-input"
            class="image-file-label"
        >
            <span class="image-upload-icon">
                📷
            </span>

            <span>
                انتخاب تصویر
            </span>

            <small>
                JPG، PNG یا WebP — هر تصویر حداکثر 5MB
            </small>
        </label>

        <input
            id="product-image-input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="image-file-input"
            multiple
            @change="onImagesSelected"
        />

    </div>

    <!-- NEW IMAGE PREVIEWS -->

    <div
        v-if="imagePreviews.length"
        class="selected-images-grid"
    >

        <div
            v-for="(preview, index) in imagePreviews"
            :key="preview.url"
            class="selected-image-card"
        >

            <div class="selected-image-card-preview">

                <img
                    :src="preview.url"
                    alt="پیش‌نمایش تصویر"
                />

                <span
                    v-if="index === 0"
                    class="new-primary-badge"
                >
                    ⭐ تصویر اصلی
                </span>

                <button
                    type="button"
                    class="remove-selected-image"
                    @click="removeSelectedImage(index)"
                >
                    ×
                </button>

            </div>

            <div class="selected-image-card-name">
                {{ preview.file.name }}
            </div>

        </div>

    </div>

    <!-- ALT TEXT -->

    <div
        v-if="imagePreviews.length"
        class="selected-images-info"
    >

        <label class="field">

            <span>
                متن جایگزین تصاویر
            </span>

            <input
                v-model="imageAltText"
                type="text"
                                    placeholder="مثلاً لنت ترمز جلو پژو 405"
            />

        </label>

        <p class="hint">
            اولین تصویر به‌عنوان تصویر اصلی محصول ثبت می‌شود.
        </p>

    </div>

    <!-- EXISTING IMAGES -->

    <div
        v-if="editingProductId && form.images?.length"
        class="product-images-grid"
    >

        <div
            v-for="image in form.images"
            :key="image.id"
            class="product-image-card"
            :class="{
                'is-dragging':
                    draggingImageId === image.id
            }"
            draggable="true"
            @dragstart="startImageDrag(image.id)"
            @dragover.prevent
            @drop="dropImage(image.id)"
        >

            <div class="product-image-preview">

                <img
                    :src="imageUrl(image)"
                    :alt="
                        image.alt_text ||
                        form.name
                    "
                />

                <span
                    v-if="image.is_primary"
                    class="primary-image-badge"
                >
                    تصویر اصلی
                </span>

            </div>

            <div class="product-image-footer">

                <div class="product-image-meta">

                    <span>
                        {{
                            image.alt_text ||
                            'بدون متن جایگزین'
                        }}
                    </span>

                    <button
                        v-if="!image.is_primary"
                        type="button"
                        class="btn-primary-image"
                        @click="setPrimaryImage(image.id)"
                    >
                        ⭐ تصویر اصلی
                    </button>

                    <span
                        v-else
                        class="current-primary-label"
                    >
                        ⭐ تصویر اصلی
                    </span>

                </div>

                <button
                    type="button"
                    class="btn-delete-image"
                    @click="deleteImage(image.id)"
                >
                    حذف
                </button>

            </div>

        </div>

    </div>

    <!-- NO EXISTING IMAGES -->

    <div
        v-else-if="editingProductId"
        class="empty-images"
    >
        هنوز تصویری برای این محصول ثبت نشده است.
    </div>

</div>

                        <!-- DESCRIPTION -->

                        <div class="form-section">
                            <div class="form-section-title">
                                توضیحات
                            </div>

                            <div class="form-grid">
                                <label class="field full">
                                    <span>
                                        توضیح کوتاه
                                    </span>

                                    <textarea
                                        v-model="
                                            form.short_description
                                        "
                                        rows="3"
                                        placeholder="توضیح کوتاه محصول..."
                                    ></textarea>
                                </label>

                                <label class="field full">
                                    <span>
                                        توضیحات کامل
                                    </span>

                                    <textarea
                                        v-model="
                                            form.description
                                        "
                                        rows="5"
                                        placeholder="توضیحات کامل محصول..."
                                    ></textarea>
                                </label>
                            </div>
                        </div>

                        <!-- CATEGORIES -->

                        <div class="form-section">
                            <div class="form-section-title">
                                دسته‌بندی محصول
                            </div>

                            <p class="hint">
                                با انتخاب دسته‌بندی،
                                ویژگی‌های اختصاصی همان
                                دسته نمایش داده می‌شوند.
                            </p>

                            <div
                                v-if="
                                    categories.length
                                "
                                class="category-select"
                            >
                                <label
                                    v-for="category in productCategoryOptions"
                                    :key="category.id"
                                    class="check-card"
                                    :class="{ child: category.depth > 0 }"
                                    :style="{ marginRight: `${category.depth * 16}px` }"
                                >
                                    <input
                                        v-model="form.category_ids"
                                        type="checkbox"
                                        :value="category.id"
                                    />

                                    <span>{{ category.name }}</span>
                                </label>
                            </div>

                            <div
                                v-if="
                                    form.category_ids
                                        .length
                                "
                                class="selected-categories"
                            >
                                <span
                                    v-for="name in selectedCategoryNames()"
                                    :key="name"
                                >
                                    {{ name }}
                                </span>
                            </div>
                        </div>

                        <!-- ATTRIBUTES -->

                        <div class="form-section">
                            <div class="form-section-title">
                                ویژگی‌های محصول
                            </div>

                            <p
                                v-if="
                                    !form.category_ids
                                        .length
                                "
                                class="hint warning-text"
                            >
                                ابتدا یک دسته‌بندی انتخاب
                                کنید.
                            </p>

                            <p
                                v-else-if="
                                    !selectedAttributes.length
                                "
                                class="hint"
                            >
                                برای دسته‌بندی انتخاب‌شده
                                هنوز ویژگی‌ای تعریف نشده
                                است.
                            </p>

                            <div
                                v-else
                                class="attributes-form"
                            >
                                <div
                                    v-for="attribute in selectedAttributes"
                                    :key="
                                        attribute.id
                                    "
                                    class="attribute-box"
                                >
                                    <div class="attribute-heading">
                                        <strong>
                                            {{
                                                attribute.name
                                            }}
                                        </strong>

                                        <small>
                                            {{
                                                attribute.type
                                            }}
                                        </small>
                                    </div>

                                    <div
                                        v-if="isCustomAttribute(attribute)"
                                        class="custom-attribute-input"
                                    >
                                        <label
                                            v-if="attribute.type === 'boolean'"
                                            class="boolean-value"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="customAttributeValue(attribute)"
                                                @change="setCustomAttributeValue(attribute, $event.target.checked)"
                                            />

                                            مقدار فعال است
                                        </label>

                                        <input
                                            v-else
                                            :type="attribute.type === 'number' ? 'number' : 'text'"
                                            :step="attribute.type === 'number' ? 'any' : undefined"
                                            :value="customAttributeValue(attribute)"
                                            :placeholder="attribute.type === 'number' ? 'مقدار عددی' : 'مقدار متنی'"
                                            @input="setCustomAttributeValue(attribute, $event.target.value)"
                                        />
                                    </div>

                                    <div
                                        v-else-if="attribute.values?.length && attribute.type === 'select'"
                                        class="values-grid"
                                    >
                                        <select
                                            class="attribute-select"
                                            :value="form.attribute_value_ids.find(id => attribute.values.some(v => v.id === id)) || ''"
                                            @change="handleSelectAttribute(attribute, $event.target.value)"
                                        >
                                            <option value="">— انتخاب نشده —</option>
                                            <option
                                                v-for="value in attribute.values"
                                                :key="value.id"
                                                :value="value.id"
                                            >
                                                {{ value.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-else-if="attribute.values?.length"
                                        class="values-grid"
                                    >
                                        <button
                                            v-for="value in attribute.values"
                                            :key="
                                                value.id
                                            "
                                            type="button"
                                            class="value-chip"
                                            :class="{
                                                selected:
                                                    isValueSelected(
                                                        value.id
                                                    ),
                                            }"
                                            @click="
                                                toggleAttributeValue(
                                                    value.id
                                                )
                                            "
                                        >
                                            <span
                                                v-if="
                                                    value.hex_color
                                                "
                                                class="color-dot"
                                                :style="{
                                                    backgroundColor:
                                                        value.hex_color,
                                                }"
                                            ></span>

                                            {{
                                                value.label
                                            }}
                                        </button>
                                    </div>

                                    <div
                                        v-else
                                        class="hint"
                                    >
                                        برای این ویژگی هنوز
                                        مقداری تعریف نشده.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- VARIANTS -->

                        <div class="form-section">
                            <div class="variant-header">
                                <div>
                                    <div
                                        class="form-section-title"
                                    >
                                        Variantها
                                    </div>

                                    <p class="hint">
                                        برای محصولاتی مثل لباس
                                        که سایز یا رنگ متفاوت
                                        دارند.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="secondary"
                                    :disabled="!variantAttributes.length"
                                    :title="!variantAttributes.length ? 'برای افزودن Variant ابتدا یک ویژگی محوری (Variant Axis) برای این دسته‌بندی تعریف کنید.' : ''"
                                    @click="
                                        addVariant()
                                    "
                                >
                                    + افزودن Variant
                                </button>
                            </div>

                            <div
                                v-if="
                                    form.variants.length
                                "
                                class="variants"
                            >
                                <div
                                    v-for="(
                                        variant,
                                        index
                                    ) in form.variants"
                                    :key="index"
                                    class="variant-box"
                                >
                                    <div class="variant-top">
                                        <strong>
                                            Variant
                                            {{
                                                index + 1
                                            }}
                                        </strong>

                                        <button
                                            type="button"
                                            class="remove-variant"
                                            @click="
                                                removeVariant(
                                                    index
                                                )
                                            "
                                        >
                                            حذف
                                        </button>
                                    </div>

                                    <div class="form-grid">
                                        <label class="field">
                                            <span>
                                                SKU
                                            </span>

                                            <input
                                                v-model="
                                                    variant.sku
                                                "
                                                type="text"
                                                dir="ltr"
                                                placeholder="ROY-001-BLK-M"
                                            />
                                        </label>

                                        <label class="field">
                                            <span>
                                                قیمت
                                            </span>

                                            <input
                                                v-model.number="
                                                    variant.price
                                                "
                                                type="number"
                                                min="0"
                                                placeholder="قیمت اصلی"
                                            />
                                        </label>

                                        <label class="field">
                                            <span>
                                                قیمت مقایسه‌ای
                                            </span>

                                            <input
                                                v-model.number="
                                                    variant.compare_at_price
                                                "
                                                type="number"
                                                min="0"
                                                placeholder="قیمت قبل (اختیاری)"
                                            />
                                        </label>

                                        <label class="field">
                                            <span>
                                                موجودی
                                            </span>

                                            <input
                                                v-model.number="
                                                    variant.stock
                                                "
                                                type="number"
                                                min="0"
                                            />
                                        </label>

                                        <label class="field checkbox-field">
                                            <input
                                                type="checkbox"
                                                v-model="
                                                    variant.is_active
                                                "
                                            />

                                            <span>
                                                فعال
                                            </span>
                                        </label>
                                    </div>

                                    <div
                                        v-if="variantAttributes.length"
                                        class="variant-attributes"
                                    >
                                        <div
                                            v-for="attribute in variantAttributes"
                                            :key="
                                                attribute.id
                                            "
                                            class="variant-attribute"
                                        >
                                            <span>
                                                {{
                                                    attribute.name
                                                }}
                                            </span>

                                            <div
                                                class="values-grid"
                                            >
                                                <button
                                                    v-for="value in attribute.values"
                                                    :key="
                                                        value.id
                                                    "
                                                    type="button"
                                                    class="value-chip"
                                                    :class="{
                                                        selected:
                                                            isVariantValueSelected(
                                                                variant,
                                                                value.id
                                                            ),
                                                    }"
                                                    @click="
                                                        toggleVariantValue(
                                                            variant,
                                                            value.id
                                                        )
                                                    "
                                                >
                                                    {{
                                                        value.label
                                                    }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <p
                                        v-if="variantAttributes.length && !variant.attribute_value_ids.length"
                                        class="hint warning-text"
                                        style="margin-top: 0.5rem;"
                                    >
                                        برای ذخیره Variant باید مقدار هر ویژگی محوری را انتخاب کنید.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else
                                class="variant-empty"
                            >
                                <span>＋</span>

                                <div>
                                    <strong>
                                        Variant ندارد
                                    </strong>

                                    <small v-if="variantAttributes.length">
                                        اگر محصول سایز، رنگ یا
                                        ترکیب متفاوت دارد، Variant
                                        اضافه کنید.
                                    </small>

                                    <small v-else>
                                        برای افزودن Variant ابتدا یک ویژگی محوری (Variant Axis) برای این دسته‌بندی تعریف کنید.
                                    </small>
                                </div>
                            </div>

                        </div>

                        <!-- VEHICLE COMPATIBILITY -->

                        <div v-if="editingProductId" class="form-section">
                            <div class="form-section-title">
                                خودروهای سازگار
                            </div>

                            <p class="hint">
                                خودروهایی که این محصول با آن‌ها سازگار است را انتخاب کنید.
                            </p>

                            <!-- LOADING COMPAT -->
                            <div v-if="compatLoading" class="hint">
                                در حال دریافت اطلاعات سازگاری...
                            </div>

                            <template v-else>
                                <!-- EXISTING COMPAT LIST -->
                                <div v-if="productCompat.length" class="compat-list">
                                    <div
                                        v-for="item in productCompat"
                                        :key="item.engine.id"
                                        class="compat-item"
                                    >
                                        <div class="compat-info">
                                            <strong>{{ item.brand.name }}</strong>
                                            <small>› {{ item.model.name }}</small>
                                            <small>› {{ item.generation.name }}</small>
                                            <small v-if="item.generation.year_start">
                                                ({{ item.generation.year_start }}{{ item.generation.year_end ? '-' + item.generation.year_end : '' }})
                                            </small>
                                            <small>› {{ item.trim.name }}</small>
                                            <small>› {{ item.engine.name }}</small>
                                        </div>
                                        <button
                                            type="button"
                                            class="text-button danger"
                                            @click="removeCompat(editingProductId, item.engine.id)"
                                        >
                                            حذف
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="hint">
                                    هنوز خودروی سازگاری ثبت نشده است.
                                </div>

                                <!-- ADD NEW COMPAT -->
                                <div class="compat-add-section">
                                    <p class="hint" style="margin-top:12px"><strong>+ افزودن خودروی سازگار</strong></p>

                                    <div class="compat-cascading-selects">
                                        <label class="form-field">
                                            <span>برند</span>
                                            <select v-model="compatSelectedBrand" @change="compatSelectBrand($event.target.value)">
                                                <option :value="null">انتخاب برند</option>
                                                <option v-for="b in compatBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                            </select>
                                        </label>

                                        <label class="form-field">
                                            <span>مدل</span>
                                            <select v-model="compatSelectedModel" :disabled="!compatSelectedBrand" @change="compatSelectModel($event.target.value)">
                                                <option :value="null">انتخاب مدل</option>
                                                <option v-for="m in compatModels" :key="m.id" :value="m.id">{{ m.name }}</option>
                                            </select>
                                        </label>

                                        <label class="form-field">
                                            <span>نسل</span>
                                            <select v-model="compatSelectedGeneration" :disabled="!compatSelectedModel" @change="compatSelectGeneration($event.target.value)">
                                                <option :value="null">انتخاب نسل</option>
                                                <option v-for="g in compatGenerations" :key="g.id" :value="g.id">
                                                    {{ g.name }}{{ g.year_start ? ' (' + g.year_start + (g.year_end ? '-' + g.year_end : '') + ')' : '' }}
                                                </option>
                                            </select>
                                        </label>

                                        <label class="form-field">
                                            <span>تیپ</span>
                                            <select v-model="compatSelectedTrim" :disabled="!compatSelectedGeneration" @change="compatSelectTrim($event.target.value)">
                                                <option :value="null">انتخاب تیپ</option>
                                                <option v-for="t in compatTrims" :key="t.id" :value="t.id">{{ t.name }}</option>
                                            </select>
                                        </label>

                                        <label class="form-field">
                                            <span>موتور</span>
                                            <select v-model="compatSelectedEngine" :disabled="!compatSelectedTrim">
                                                <option :value="null">انتخاب موتور</option>
                                                <option v-for="e in compatEngines" :key="e.id" :value="e.id" :disabled="isEngineAlreadyCompat(e.id)">
                                                    {{ e.name }}{{ e.displacement ? ' (' + e.displacement + 'L)' : '' }}{{ isEngineAlreadyCompat(e.id) ? ' — اضافه شده' : '' }}
                                                </option>
                                            </select>
                                        </label>
                                    </div>

                                    <div class="category-form-actions">
                                        <button
                                            type="button"
                                            class="primary"
                                            :disabled="!compatSelectedEngine || isEngineAlreadyCompat(compatSelectedEngine)"
                                            @click="addCompat(editingProductId)"
                                        >
                                            افزودن سازگاری
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- SETTINGS -->

                        <div class="form-section">
                            <div class="form-section-title">
                                وضعیت محصول
                            </div>

                            <div class="toggle-row">
                                <label class="toggle-card">
                                    <input
                                        v-model="
                                            form.is_active
                                        "
                                        type="checkbox"
                                    />

                                    <span class="toggle">
                                    </span>

                                    <div>
                                        <strong>
                                            محصول فعال
                                        </strong>

                                        <small>
                                            در فروشگاه نمایش داده
                                            شود.
                                        </small>
                                    </div>
                                </label>

                                <label class="toggle-card">
                                    <input
                                        v-model="
                                            form.is_featured
                                        "
                                        type="checkbox"
                                    />

                                    <span class="toggle">
                                    </span>

                                    <div>
                                        <strong>
                                            محصول ویژه
                                        </strong>

                                        <small>
                                            در بخش محصولات ویژه
                                            نمایش داده شود.
                                        </small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- FOOTER -->

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="cancel"
                                :disabled="saving"
                                @click="
                                    closeProductModal()
                                "
                            >
                                انصراف
                            </button>

<button
    type="submit"
    class="primary save-button"
    :disabled="saving"
>
    <span
        v-if="saving"
        class="button-spinner"
    ></span>

    {{
        saving
            ? 'در حال ذخیره...'
            : (
                editingProductId
                    ? 'ذخیره تغییرات'
                    : 'ذخیره محصول'
            )
    }}
</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
/* =========================================================
   PRODUCT IMAGES
========================================================= */

.image-upload-box {
    border: 1.5px dashed #d9d4c9;
    border-radius: 14px;
    background: #fafbfc;
    padding: 24px;
    text-align: center;
    transition: .2s ease;
}

.image-upload-box:hover {
    border-color: #6563d9;
    background: #fafaff;
}

.image-file-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 7px;
    cursor: pointer;
}

.image-upload-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: #efefff;
    color: #6563d9;
    display: grid;
    place-items: center;
    font-size: 22px;
}

.image-file-label span:not(.image-upload-icon) {
    font-size: 13px;
    font-weight: 700;
    color: #36384b;
}

.image-file-label small {
    color: #999aae;
    font-size: 11px;
}

.image-file-input {
    display: none;
}


/* Selected image */

.selected-image-wrapper {
    margin-top: 16px;
    padding: 15px;
    border: 1px solid #eeeeF4;
    border-radius: 14px;
    display: flex;
    gap: 16px;
    background: #fff;
}

.selected-image-preview {
    width: 130px;
    height: 160px;
    flex-shrink: 0;
    border-radius: 11px;
    overflow: hidden;
    background: #f1f1f5;
}

.selected-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.selected-image-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.selected-image-info > strong {
    font-size: 13px;
    word-break: break-word;
}

.selected-image-info > span {
    color: #999aae;
    font-size: 11px;
}

.selected-image-actions {
    display: flex;
    gap: 8px;
    margin-top: auto;
}


/* Existing images */

.product-images-grid {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
}

.product-image-card {
    overflow: hidden;
    border: 1px solid #eeeeF4;
    border-radius: 13px;
    background: #fff;
}

.product-image-preview {
    position: relative;
    aspect-ratio: 3 / 4;
    overflow: hidden;
    background: #f1f1f5;
}

.product-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.primary-image-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 5px 8px;
    border-radius: 7px;
    background: #6563d9;
    color: #fff;
    font-size: 10px;
}

.product-image-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px;
}

.product-image-footer span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #888a9b;
    font-size: 10px;
}

.btn-delete-image {
    flex-shrink: 0;
    border: 0;
    background: #fff0f1;
    color: #d25f68;
    border-radius: 7px;
    padding: 6px 9px;
    font-family: inherit;
    font-size: 10px;
    cursor: pointer;
}

.btn-delete-image:hover {
    background: #ffe0e3;
}

.empty-images {
    margin-top: 16px;
    padding: 25px;
    border: 1px dashed #dddde7;
    border-radius: 12px;
    text-align: center;
    color: #999aae;
    font-size: 12px;
}


/* Mobile */

@media (max-width: 700px) {

    .product-images-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .selected-image-wrapper {
        flex-direction: column;
    }

    .selected-image-preview {
        width: 100%;
        height: 240px;
    }

}
/* =========================================================
   BASE
========================================================= */

.admin-shell {
    min-height: 100vh;
    background: #f5f6fa;
    color: #202235;
    display: flex;
    font-family:
        Vazirmatn,
        Tahoma,
        Arial,
        sans-serif;
}

.admin-login {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px;
    background: #f6f6fb;
}

.login-card {
    width: min(100%, 380px);
    display: grid;
    gap: 16px;
    padding: 30px;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 18px 50px rgba(42, 43, 72, .12);
}

.login-card h1,
.login-card p {
    margin: 0;
}

.login-card > p:not(.section-label):not(.login-error) {
    color: #7c7d91;
    font-size: 13px;
    line-height: 1.8;
}

.login-card label {
    display: grid;
    gap: 7px;
    color: #505266;
    font-size: 12px;
    font-weight: 600;
}

.login-card input {
    border: 1px solid #dedfea;
    border-radius: 9px;
    padding: 10px 12px;
    font: inherit;
}

.login-card button,
.logout-button {
    border: 0;
    border-radius: 9px;
    padding: 10px 14px;
    background: #6563d9;
    color: #fff;
    font: inherit;
    cursor: pointer;
}

.login-card button:disabled {
    opacity: .65;
    cursor: wait;
}

.login-error {
    color: #c5394f;
    font-size: 12px;
}

.logout-button {
    padding: 6px 10px;
    background: transparent;
    color: #6563d9;
    border: 1px solid #d8d8ea;
    font-size: 11px;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    width: 260px;
    min-height: 100vh;
    background: #171827;
    color: #fff;
    padding: 28px 18px;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.brand {
    margin: 0 12px 42px;
    font-family: Georgia, serif;
    font-size: 28px;
    letter-spacing: 1px;
}

.brand span {
    display: block;
    margin-top: 4px;
    color: #aaaac0;
    font-family: Vazirmatn, Tahoma, sans-serif;
    font-size: 10px;
    letter-spacing: 3px;
}

.sidebar-nav {
    display: grid;
    gap: 7px;
}

.nav-item {
    width: 100%;
    border: 0;
    background: transparent;
    color: #a8a9bd;
    text-align: right;
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 13px;
    cursor: pointer;
    transition: .2s ease;
}

.nav-item:hover,
.nav-item.active {
    background: #6563d9;
    color: #fff;
}

.nav-item b {
    width: 22px;
    font-size: 18px;
    text-align: center;
}

.side-foot {
    margin-top: auto;
    color: #85869c;
    font-size: 12px;
    line-height: 2;
}

.side-foot small {
    display: block;
    color: #66677c;
}

/* =========================================================
   MAIN
========================================================= */

.main {
    flex: 1;
    min-width: 0;
    padding: 34px 42px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.eyebrow,
.section-label {
    margin: 0 0 6px;
    color: #777895;
    font-size: 12px;
}

.topbar h1 {
    margin: 0;
    font-size: 28px;
}

.admin-user {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #e9e8ff;
    color: #5b59c7;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
}

/* =========================================================
   CARDS
========================================================= */

.cards {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.stat,
.panel {
    background: #fff;
    border-radius: 18px;
    box-shadow:
        0 8px 30px rgba(35, 35, 77, .05);
}

.stat {
    position: relative;
    padding: 23px;
}

.stat span {
    color: #7b7d94;
    font-size: 13px;
}

.stat strong {
    display: block;
    margin-top: 13px;
    font-size: 30px;
}

.stat i {
    position: absolute;
    left: 22px;
    top: 22px;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #efefff;
    color: #6563d9;
    display: grid;
    place-items: center;
    font-style: normal;
}

/* =========================================================
   PANEL
========================================================= */

.panel {
    padding: 23px;
}

.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 18px;
}

.panel-head h2 {
    margin: 0;
    font-size: 19px;
}

.text-button {
    border: 0;
    background: transparent;
    color: #6563d9;
    cursor: pointer;
    font-family: inherit;
}

.primary,
.secondary,
.cancel {
    border: 0;
    border-radius: 10px;
    padding: 10px 16px;
    font-family: inherit;
    cursor: pointer;
    transition: .2s ease;
}

.primary {
    background: #6563d9;
    color: #fff;
}

.primary:hover {
    background: #5553c8;
}

.primary:disabled {
    opacity: .65;
    cursor: not-allowed;
}

.secondary {
    background: #efefff;
    color: #5b59c7;
}

.secondary:hover {
    background: #e4e3ff;
}

.cancel {
    background: #f1f2f6;
    color: #5e6073;
}

/* =========================================================
   PRODUCTS
========================================================= */

.rows,
.products-table {
    display: grid;
}

.products-search-bar {
    position: relative;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f0f5;
}

.search-input {
    width: 100%;
    padding: 10px 36px 10px 14px;
    border: 1px solid #e0e0ea;
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
}

.search-input:focus {
    border-color: #6563d9;
}

.search-clear {
    position: absolute;
    right: 30px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #999aae;
    font-size: 14px;
    padding: 4px;
    line-height: 1;
}

.search-clear:hover {
    color: #333;
}

.products-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-top: 1px solid #f0f0f5;
    font-size: 13px;
    color: #666;
}

.pagination-buttons {
    display: flex;
    gap: 8px;
}

.pagination-btn {
    padding: 6px 14px;
    border: 1px solid #e0e0ea;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    font-size: 13px;
    font-family: inherit;
    transition: all 0.15s;
}

.pagination-btn:hover:not(:disabled) {
    border-color: #6563d9;
    color: #6563d9;
}

.pagination-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.product-row {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 14px 4px;
    border-bottom: 1px solid #f0f0f5;
}

.product-row:last-child {
    border-bottom: 0;
}

.product-thumb {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 12px;
    background: #eeeefe;
    color: #6563d9;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 18px;
}

.grow {
    flex: 1;
    min-width: 0;
}

.grow b,
.grow small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.grow small {
    margin-top: 4px;
    color: #999aae;
    font-size: 11px;
}

.price {
    white-space: nowrap;
}

.status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    white-space: nowrap;
}

.status.ok {
    background: #e7faf3;
    color: #27a37a;
}

.status.bad {
    background: #fff0f1;
    color: #dd6870;
}

.delete-button {
    border: 0;
    background: #fff0f1;
    color: #d85f68;
    border-radius: 8px;
    padding: 7px 10px;
    font-family: inherit;
    cursor: pointer;
}

.delete-button:hover {
    background: #ffe2e5;
}

/* =========================================================
   EMPTY / LOADING
========================================================= */

.empty {
    padding: 70px 20px;
    text-align: center;
    color: #999aae;
}

.empty-icon {
    font-size: 40px;
    color: #6563d9;
    margin-bottom: 10px;
}

.empty h3 {
    margin: 0 0 7px;
    color: #303247;
}

.empty p {
    margin: 0 0 20px;
}

.loading {
    min-height: 400px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 14px;
    color: #85869c;
}

.spinner,
.button-spinner {
    border: 3px solid #e5e5f8;
    border-top-color: #6563d9;
    border-radius: 50%;
    animation: spin .7s linear infinite;
}

.spinner {
    width: 35px;
    height: 35px;
}

.button-spinner {
    width: 15px;
    height: 15px;
    display: inline-block;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* =========================================================
   ALERT
========================================================= */

.alert {
    margin-bottom: 18px;
    border-radius: 12px;
    padding: 12px 15px;
    font-size: 13px;
}
.btn-edit,
.btn-delete {
    border: none;
    border-radius: 8px;
    padding: 7px 12px;
    font-family: inherit;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-edit {
    background: #f5efe2;
    color: #8a6b2f;
}

.btn-edit:hover {
    background: #e9ddc3;
}

.btn-delete {
    background: #fff0f1;
    color: #c9545e;
}

.btn-delete:hover {
    background: #ffe0e3;
}
.product-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
}
.alert.error {
    background: #fff0f1;
    color: #c9545e;
    border: 1px solid #ffd9dc;
}
.alert.success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

/* =========================================================
   CATEGORIES / ATTRIBUTES
========================================================= */

.category-list,
.attribute-list {
    display: grid;
    gap: 10px;
}

.category-form {
    margin-bottom: 22px;
    padding: 18px;
    border: 1px solid #e6e6ef;
    border-radius: 14px;
    background: #fafaff;
}

.attribute-form,
.attribute-values-editor {
    margin-bottom: 22px;
    padding: 18px;
    border: 1px solid #e6e6ef;
    border-radius: 14px;
    background: #fafaff;
}

.attribute-values-editor {
    margin-top: 26px;
    margin-bottom: 0;
}

.attribute-values-editor h3 {
    margin: 4px 0 0;
    font-size: 16px;
}

.attribute-value-form {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #eeeeF4;
}

.attribute-value-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
}

.attribute-value-list {
    display: grid;
    gap: 8px;
    margin-top: 18px;
}

.attribute-value-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px;
    border: 1px solid #eeeeF4;
    border-radius: 10px;
    font-size: 12px;
}

.attribute-value-item strong,
.attribute-value-item small {
    display: block;
}

.attribute-value-item small {
    margin-top: 3px;
    color: #85869c;
}

.color-swatch {
    width: 18px;
    height: 18px;
    border: 1px solid #d4d4dd;
    border-radius: 50%;
    flex-shrink: 0;
}

.category-form-head,
.category-form-actions,
.category-row-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.category-form-head {
    justify-content: space-between;
    margin-bottom: 16px;
}

.category-form-head h3 {
    margin: 0;
    font-size: 15px;
}

.category-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
}

.form-field {
    display: grid;
    gap: 6px;
}

.form-field > span {
    color: #5c5e71;
    font-size: 12px;
    font-weight: 600;
}

.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 11px;
    border: 1px solid #e3e4eb;
    border-radius: 9px;
    background: #fff;
    color: #25273a;
    font-family: inherit;
}

.form-field textarea {
    resize: vertical;
}

.form-field.full-width {
    grid-column: 1 / -1;
}

.category-active-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    font-size: 12px;
}

.category-form-actions {
    justify-content: flex-end;
    margin-top: 16px;
}

.category-row-actions {
    justify-content: flex-end;
}

.text-button.danger {
    color: #c9545e;
}

.category-item,
.attribute-item {
    padding: 16px;
    border: 1px solid #eeeeF4;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.category-item strong,
.attribute-item strong {
    display: block;
}

.category-item small,
.attribute-item small {
    display: block;
    margin-top: 5px;
    color: #999aae;
    font-size: 11px;
}

.category-item > span,
.attribute-item > span {
    color: #85869c;
    font-size: 12px;
}

.category-attribute-editor {
    margin-top: 26px;
    padding-top: 24px;
    border-top: 1px solid #eeeeF4;
}

.category-attribute-editor h3 {
    margin: 4px 0;
    font-size: 17px;
}

.editor-help {
    margin: 6px 0 18px;
    color: #85869c;
    font-size: 12px;
    line-height: 1.8;
}

.category-attribute-editor .form-field {
    max-width: 420px;
    margin: 18px 0;
}

.configuration-heading {
    margin: 20px 0 12px;
    font-weight: 700;
    font-size: 13px;
}

.category-attribute-config-list {
    display: grid;
    gap: 10px;
}

.category-attribute-config {
    padding: 14px;
    border: 1px solid #eeeeF4;
    border-radius: 12px;
}

.category-attribute-config.enabled {
    border-color: #b9b8f1;
    background: #fafaff;
}

.category-attribute-config.inherited {
    border-style: dashed;
    border-color: #d1d5db;
    background: #fafafa;
}

.config-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.config-label strong,
.config-label small {
    display: block;
}

.config-label small {
    margin-top: 3px;
    color: #85869c;
    font-size: 11px;
}

.attribute-state-select {
    flex-shrink: 0;
    padding: 4px 8px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 12px;
    background: white;
    cursor: pointer;
}

.config-fields {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #eeeeF4;
    font-size: 12px;
}

.config-fields label {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.config-fields .disabled {
    color: #b3b4c1;
}

.sort-input input {
    width: 58px;
    padding: 5px 7px;
    border: 1px solid #dedee8;
    border-radius: 7px;
}

.config-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

/* =========================================================
   MODAL
========================================================= */

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1000;
    padding: 30px;
    background: rgba(17, 18, 32, .62);
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal {
    width: min(950px, 100%);
    max-height: calc(100vh - 60px);
    overflow-y: auto;
    background: #fff;
    border-radius: 22px;
    box-shadow:
        0 30px 80px rgba(0, 0, 0, .2);
}

.modal-header {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    padding: 24px;
    border-bottom: 1px solid #eeeeF4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 22px;
}

.close-button {
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 10px;
    background: #f2f3f7;
    color: #55576a;
    font-size: 25px;
    cursor: pointer;
}

/* =========================================================
   FORM
========================================================= */

.product-form {
    padding: 24px;
}

.form-section {
    padding: 0 0 25px;
    margin-bottom: 25px;
    border-bottom: 1px solid #eeeeF4;
}

.form-section:last-of-type {
    border-bottom: 0;
}

.form-section-title {
    margin-bottom: 5px;
    font-size: 16px;
    font-weight: 700;
}

.hint {
    margin: 0 0 15px;
    color: #8b8d9f;
    font-size: 12px;
    line-height: 1.8;
}

.warning-text {
    color: #c88a32;
}

.form-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.field {
    display: grid;
    gap: 7px;
}

.field.full {
    grid-column: 1 / -1;
}

.field > span {
    color: #5c5e71;
    font-size: 12px;
    font-weight: 600;
}

.field input,
.field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e3e4eb;
    background: #fafbfc;
    border-radius: 10px;
    padding: 11px 12px;
    outline: 0;
    color: #25273a;
    font-family: inherit;
    font-size: 13px;
    transition: .2s ease;
}

.field textarea {
    resize: vertical;
}

.field input:focus,
.field textarea:focus {
    border-color: #7775df;
    background: #fff;
    box-shadow:
        0 0 0 3px rgba(101, 99, 217, .08);
}

.checkbox-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}

.checkbox-field > span {
    order: 0;
}

.checkbox-field input[type="checkbox"] {
    width: auto;
    accent-color: #7775df;
}

/* =========================================================
   CATEGORY SELECT
========================================================= */

.category-select {
    display: grid;
    gap: 10px;
}

.category-group {
    padding: 13px;
    border: 1px solid #eeeeF4;
    border-radius: 12px;
}

.check-card {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #36384b;
    font-size: 13px;
    cursor: pointer;
}

.check-card input {
    accent-color: #6563d9;
}

.children {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
    padding-right: 25px;
}

.check-card.child {
    padding: 7px 10px;
    border-radius: 8px;
    background: #f7f7fb;
}

.selected-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 13px;
}

.selected-categories span {
    padding: 6px 10px;
    border-radius: 20px;
    background: #efefff;
    color: #5c5ac9;
    font-size: 11px;
}

/* =========================================================
   ATTRIBUTES
========================================================= */

.attributes-form {
    display: grid;
    gap: 14px;
}

.attribute-box {
    padding: 16px;
    border: 1px solid #eeeeF4;
    border-radius: 14px;
}

.attribute-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.attribute-heading small {
    color: #999aae;
    font-size: 10px;
}

.values-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.custom-attribute-input input[type="number"],
.custom-attribute-input input[type="text"] {
    width: 100%;
    border: 1px solid #e2e3eb;
    border-radius: 9px;
    padding: 9px 11px;
    font: inherit;
}

.boolean-value {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #505266;
    font-size: 12px;
}

.value-chip {
    border: 1px solid #e2e3eb;
    background: #fff;
    color: #505266;
    border-radius: 9px;
    padding: 7px 11px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: inherit;
    font-size: 12px;
    cursor: pointer;
    transition: .15s ease;
}

.value-chip:hover {
    border-color: #a3a1ed;
}

.value-chip.selected {
    background: #6563d9;
    border-color: #6563d9;
    color: #fff;
}

.color-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 1px solid rgba(0,0,0,.12);
}

/* =========================================================
   VARIANTS
========================================================= */

.variant-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 15px;
}

.variants {
    display: grid;
    gap: 13px;
}

.variant-box {
    padding: 16px;
    background: #fafbfc;
    border: 1px solid #e8e9ef;
    border-radius: 14px;
}

.variant-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.remove-variant {
    border: 0;
    background: #fff0f1;
    color: #d25f68;
    padding: 6px 9px;
    border-radius: 7px;
    font-family: inherit;
    cursor: pointer;
}

.variant-attributes {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e6e7ed;
    display: grid;
    gap: 13px;
}

.variant-attribute > span {
    display: block;
    margin-bottom: 7px;
    color: #656779;
    font-size: 12px;
    font-weight: 600;
}

.variant-empty {
    padding: 20px;
    background: #fafbfc;
    border: 1px dashed #dcdde6;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 13px;
}

.variant-empty > span {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #efefff;
    color: #6563d9;
    display: grid;
    place-items: center;
    font-size: 20px;
}

.variant-empty strong,
.variant-empty small {
    display: block;
}

.variant-empty small {
    margin-top: 4px;
    color: #999aae;
    font-size: 11px;
}

/* =========================================================
   TOGGLES
========================================================= */

.toggle-row {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.toggle-card {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 14px;
    border: 1px solid #eeeeF4;
    border-radius: 12px;
    cursor: pointer;
}

.toggle-card input {
    display: none;
}

.toggle {
    position: relative;
    width: 40px;
    height: 22px;
    flex-shrink: 0;
    border-radius: 30px;
    background: #d9dae3;
    transition: .2s ease;
}

.toggle::after {
    content: '';
    position: absolute;
    top: 3px;
    right: 3px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #fff;
    transition: .2s ease;
}

.toggle-card input:checked + .toggle {
    background: #6563d9;
}

.toggle-card input:checked + .toggle::after {
    right: 21px;
}

.toggle-card strong,
.toggle-card small {
    display: block;
}

.toggle-card strong {
    font-size: 12px;
}

.toggle-card small {
    margin-top: 3px;
    color: #999aae;
    font-size: 10px;
}

/* =========================================================
   MODAL FOOTER
========================================================= */

.modal-footer {
    position: sticky;
    bottom: 0;
    z-index: 2;
    margin: 0 -24px -24px;
    padding: 17px 24px;
    background: #fff;
    border-top: 1px solid #eeeeF4;
    display: flex;
    justify-content: flex-end;
    gap: 9px;
}

.save-button {
    min-width: 135px;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 1000px) {
    .cards {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 800px) {
    .sidebar {
        width: 70px;
        padding: 20px 7px;
    }

    .brand {
        margin: 0 0 30px;
        text-align: center;
        font-size: 0;
    }

    .brand::first-letter {
        font-size: 27px;
    }

    .brand span {
        display: none;
    }

    .nav-item {
        justify-content: center;
        padding: 13px 5px;
    }

    .nav-item span {
        display: none;
    }

    .side-foot {
        display: none;
    }

    .main {
        padding: 22px 15px;
    }

    .topbar {
        margin-bottom: 22px;
    }

    .topbar h1 {
        font-size: 23px;
    }

    .product-row {
        flex-wrap: wrap;
    }

    .price {
        margin-right: auto;
    }

    .modal-backdrop {
        padding: 10px;
        align-items: flex-end;
    }

    .modal {
        max-height: calc(100vh - 20px);
        border-radius: 20px 20px 0 0;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

    .toggle-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .cards {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat {
        padding: 16px;
    }

    .stat strong {
        font-size: 24px;
    }

    .stat i {
        left: 12px;
        top: 12px;
    }

    .panel {
        padding: 16px;
    }

    .panel-head {
        align-items: flex-start;
    }

    .panel-head .primary {
        padding: 8px 11px;
        font-size: 11px;
    }

    .product-row > strong,
    .product-row > .status {
        font-size: 10px;
    }

    .modal-header,
    .product-form {
        padding: 18px;
    }

    .modal-footer {
        margin: 0 -18px -18px;
        padding: 14px 18px;
    }

    .variant-header {
        flex-direction: column;
    }
    .product-image-card {
    overflow: hidden;
    border: 1px solid #eeeeF4;
    border-radius: 13px;
    background: #fff;
    cursor: grab;
    transition: .2s ease;
}

.product-image-card:active {
    cursor: grabbing;
}

.product-image-card.is-dragging {
    opacity: .45;
    transform: scale(.98);
}

.product-image-card:hover {
    border-color: #6563d9;
    box-shadow: 0 8px 24px rgba(30, 30, 60, .08);
}

.product-image-meta {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 5px;
}

.btn-primary-image {
    border: 0;
    background: #f1f0ff;
    color: #6563d9;
    border-radius: 7px;
    padding: 5px 8px;
    font-family: inherit;
    font-size: 10px;
    cursor: pointer;
}

.btn-primary-image:hover {
    background: #e7e5ff;
}

.current-primary-label {
    color: #6563d9;
    font-size: 10px;
    font-weight: 700;
}
.selected-images-grid {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
}

.selected-image-card {
    overflow: hidden;
    border: 1px solid #eeeeF4;
    border-radius: 13px;
    background: #fff;
}

.selected-image-card-preview {
    position: relative;
    aspect-ratio: 3 / 4;
    overflow: hidden;
    background: #f1f1f5;
}

.selected-image-card-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.new-primary-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 5px 8px;
    border-radius: 7px;
    background: #6563d9;
    color: #fff;
    font-size: 10px;
}

.remove-selected-image {
    position: absolute;
    top: 8px;
    left: 8px;

    width: 27px;
    height: 27px;

    border: 0;
    border-radius: 50%;

    background: rgba(0, 0, 0, .65);
    color: #fff;

    font-size: 18px;
    line-height: 1;

    cursor: pointer;
}

.remove-selected-image:hover {
    background: #d25f68;
}

.selected-image-card-name {
    padding: 8px 9px;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    color: #888a9b;
    font-size: 10px;
}

}

@media (max-width: 700px) {
    .selected-images-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

/* =========================================================
   VEHICLE MANAGEMENT
========================================================= */

.vehicle-breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 0;
    flex-wrap: wrap;
}

.vehicle-breadcrumb .text-button {
    font-size: 13px;
    padding: 4px 8px;
    border-radius: 6px;
}

.vehicle-breadcrumb .text-button.active {
    font-weight: 700;
    color: #6563d9;
    background: #efefff;
}

.vehicle-breadcrumb .bc-sep {
    color: #999aae;
    font-size: 12px;
}

.vehicle-form {
    margin-bottom: 20px;
    padding: 18px;
    background: #fafbfc;
    border: 1px solid #eef0f5;
    border-radius: 12px;
}

.vehicle-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 14px;
}

.vehicle-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.vehicle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #f0f1f5;
    transition: .15s ease;
}

.vehicle-item:hover {
    border-color: #d5d6e6;
}

.vehicle-item-info {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    flex: 1;
    min-width: 0;
}

.vehicle-item-info strong {
    font-size: 14px;
}

.vehicle-item-info small {
    color: #999aae;
    font-size: 12px;
}

.vehicle-item-info .hint {
    color: #999aae;
    font-size: 11px;
}

.vehicle-item-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

/* =========================================================
   VEHICLE COMPATIBILITY IN PRODUCT FORM
========================================================= */

.compat-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
}

.compat-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: #fafbfc;
    border: 1px solid #eef0f5;
    border-radius: 10px;
    gap: 10px;
}

.compat-info {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
    flex: 1;
    min-width: 0;
}

.compat-info strong {
    font-size: 13px;
    color: #36384b;
}

.compat-info small {
    color: #85869c;
    font-size: 12px;
}

.compat-add-section {
    border-top: 1px solid #eef0f5;
    padding-top: 12px;
}

.compat-cascading-selects {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.compat-cascading-selects select:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}


/* =========================================================
   ORDERS (ADMIN) — Complete Redesign
   ========================================================= */

/* ── Container ─────────────────────────────────── */

.orders-section {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 20px;
}

/* ── Header ────────────────────────────────────── */

.orders-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.orders-header-content h1 {
    font-size: 26px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0 0 4px;
}

.orders-subtitle {
    font-size: 14px;
    color: #888;
    margin: 0;
}

.orders-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.orders-count {
    font-size: 13px;
    color: #888;
    background: #f0f0ec;
    padding: 6px 14px;
    border-radius: 20px;
    white-space: nowrap;
}

/* ── Refresh Button ───────────────────────────── */

.btn-refresh {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1.5px solid #e0e0dc;
    border-radius: 10px;
    background: #fff;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
    white-space: nowrap;
}

.btn-refresh:hover:not(:disabled) {
    background: #f5f5f2;
    border-color: #1a1a1a;
}

.btn-refresh:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.btn-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(0,0,0,.15);
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin .6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ── Summary Cards ────────────────────────────── */

.orders-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.orders-summary .summary-card {
    background: #fff;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    padding: 20px;
    text-align: center;
    transition: all .2s ease;
}

.orders-summary .summary-card:hover {
    border-color: #1a1a1a;
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,.06);
}

.orders-summary .summary-card .summary-value {
    font-size: 32px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 8px 0 4px;
}

.orders-summary .summary-card .summary-label {
    font-size: 13px;
    color: #888;
    font-weight: 500;
}

/* ── Toolbar ──────────────────────────────────── */

.orders-toolbar {
    display: flex;
    gap: 12px;
    align-items: center;
    background: #fff;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.toolbar-search-wrap {
    position: relative;
    flex: 1;
    min-width: 240px;
}

.toolbar-search-wrap .search-icon {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    color: #aaa;
    pointer-events: none;
}

.toolbar-search-wrap .search-input {
    width: 100%;
    padding: 10px 14px 10px 40px;
    border: 1.5px solid #e8e8e4;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    background: #fafaf8;
    transition: border-color .15s ease;
    box-sizing: border-box;
}

.toolbar-search-wrap .search-input:focus {
    outline: none;
    border-color: #1a1a1a;
    background: #fff;
}

.toolbar-search-wrap .search-clear-btn {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #aaa;
    cursor: pointer;
    font-size: 14px;
    padding: 4px;
}

.toolbar-filters {
    display: flex;
    gap: 10px;
}

.filter-select {
    padding: 10px 14px;
    border: 1.5px solid #e8e8e4;
    border-radius: 10px;
    background: #fff;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 13px;
    cursor: pointer;
    min-width: 160px;
}

.filter-select:focus {
    outline: none;
    border-color: #1a1a1a;
}

.btn-clear-filters {
    padding: 10px 18px;
    border: 1.5px solid #e8e8e4;
    border-radius: 10px;
    background: #fff;
    color: #c9545e;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
    white-space: nowrap;
}

.btn-clear-filters:hover {
    background: #fef2f2;
    border-color: #c9545e;
}

/* ── Table Container ──────────────────────────── */

.orders-content {
    background: #fff;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    overflow: hidden;
}

.orders-table-wrapper {
    overflow-x: auto;
}

/* ── Orders Table ─────────────────────────────── */

.orders-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.orders-table thead {
    background: #f8f8f6;
    border-bottom: 2px solid #e8e8e4;
}

.orders-table th {
    padding: 14px 16px;
    text-align: right;
    font-weight: 700;
    font-size: 12px;
    color: #555;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
    border-bottom: 2px solid #e8e8e4;
}

.orders-table td {
    padding: 14px 16px;
    text-align: right;
    border-bottom: 1px solid #f0f0ec;
    vertical-align: middle;
}

.orders-table tbody tr {
    transition: background .15s ease;
}

.orders-table tbody tr:hover {
    background: #fafaf8;
}

.orders-table tbody tr:last-child td {
    border-bottom: none;
}

.th-id { width: 80px; }
.th-items { width: 60px; }
.th-amount { width: 120px; }
.th-payment { width: 100px; }
.th-status { width: 120px; }
.th-date { width: 120px; }
.th-action { width: 90px; }

.order-id {
    font-family: 'SF Mono', SFMono-Regular, Consolas, monospace;
    font-size: 13px;
    color: #6563d9;
    font-weight: 700;
}

.customer-cell {
    min-width: 160px;
}

.customer-name {
    font-weight: 600;
    color: #1a1a1a;
    font-size: 13px;
}

.customer-phone {
    font-size: 11px;
    color: #888;
    margin-top: 2px;
}

.items-count {
    color: #888;
    font-size: 12px;
}

.amount {
    font-weight: 700;
    color: #1a1a1a;
    white-space: nowrap;
    font-size: 14px;
}

.amount small {
    font-weight: 400;
    color: #888;
    font-size: 11px;
    display: block;
}

.date-cell {
    white-space: nowrap;
    color: #888;
    font-size: 12px;
}

/* ── Status & Payment Badges ──────────────────── */

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    letter-spacing: .2px;
}

.status-badge.status-pending { background: #fef3cd; color: #856404; }
.status-badge.status-confirmed { background: #d1e7ff; color: #084298; }
.status-badge.status-processing { background: #e8dafe; color: #692fc2; }
.status-badge.status-shipped { background: #cffafe; color: #0e7490; }
.status-badge.status-delivered { background: #d1fae5; color: #065f46; }
.status-badge.status-cancelled { background: #fee2e2; color: #991b1b; }

.status-badge.status-lg {
    padding: 6px 14px;
    font-size: 13px;
}

.status-badge.status-sm {
    padding: 2px 8px;
    font-size: 11px;
}

.payment-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.payment-badge.paid { background: #d1fae5; color: #065f46; }
.payment-badge.unpaid { background: #fee2e2; color: #991b1b; }

.payment-badge.payment-lg {
    padding: 6px 14px;
    font-size: 13px;
}

/* ── Action Buttons ───────────────────────────── */

.btn-view {
    padding: 7px 16px;
    border: 1.5px solid #6563d9;
    border-radius: 8px;
    background: transparent;
    color: #6563d9;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
    font-family: inherit;
    white-space: nowrap;
}

.btn-view:hover {
    background: #6563d9;
    color: #fff;
}

.btn-view-full {
    width: 100%;
    padding: 10px;
    font-size: 13px;
}

/* ── Empty & Loading States ───────────────────── */

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 60px 20px;
    color: #888;
    font-size: 14px;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 60px 20px;
    text-align: center;
    background: #fff;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
}

.empty-state .empty-icon {
    font-size: 48px;
    opacity: .5;
}

.empty-state h3 {
    margin: 0;
    font-size: 16px;
    color: #1a1a1a;
}

.empty-hint {
    margin: 0;
    font-size: 13px;
    color: #aaa;
}

/* ── Pagination ────────────────────────────────── */

.orders-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-top: 1px solid #e8e8e4;
    background: #fafaf8;
}

.pagination-info {
    font-size: 13px;
    color: #888;
}

.pagination-buttons {
    display: flex;
    gap: 6px;
}

.pagination-btn {
    padding: 6px 14px;
    border: 1px solid #e8e8e4;
    border-radius: 8px;
    background: #fff;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 13px;
    cursor: pointer;
    transition: all .15s ease;
}

.pagination-btn:hover:not(:disabled) {
    background: #f5f5f2;
    border-color: #1a1a1a;
}

.pagination-btn:disabled {
    opacity: .4;
    cursor: not-allowed;
}

/* =========================================================
   ORDER DETAIL — Complete Redesign
   ========================================================= */

.order-detail-section {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 20px;
}

/* ── Detail Header ────────────────────────────── */

.detail-header {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.detail-title-row {
    flex: 1;
}

.detail-title {
    font-size: 24px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0 0 4px;
}

.order-id-large {
    font-family: 'SF Mono', SFMono-Regular, Consolas, monospace;
    color: #6563d9;
}

.detail-date {
    font-size: 13px;
    color: #888;
}

.detail-badges {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}

.btn-back {
    padding: 8px 16px;
    border: 1.5px solid #e8e8e4;
    border-radius: 10px;
    background: #fff;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
    white-space: nowrap;
}

.btn-back:hover {
    background: #f5f5f2;
    border-color: #1a1a1a;
}

/* ── Status Management Card ──────────────────── */

.status-management-card {
    background: #fff;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 20px;
}

.status-management-card .card-title {
    margin: 0 0 16px;
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    padding: 0;
    background: none;
    border: none;
}

.current-status-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.current-status-label {
    font-size: 13px;
    color: #888;
    font-weight: 500;
}

/* ── Timeline ──────────────────────────────────── */

.status-timeline {
    margin-bottom: 24px;
}

.timeline-track {
    height: 4px;
    background: #e8e8e4;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 14px;
}

.timeline-progress {
    height: 100%;
    background: #6563d9;
    border-radius: 4px;
    transition: width .4s ease;
}

.timeline-steps {
    display: flex;
    justify-content: space-between;
}

.timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    position: relative;
}

.step-marker {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #d9dae3;
    border: 2px solid #fff;
    transition: all .3s ease;
    z-index: 1;
}

.timeline-step.completed .step-marker {
    background: #6563d9;
}

.timeline-step.current .step-marker {
    background: #6563d9;
    box-shadow: 0 0 0 4px rgba(101, 99, 217, .2);
}

.step-label {
    font-size: 11px;
    color: #bbb;
    font-weight: 500;
    text-align: center;
}

.timeline-step.completed .step-label,
.timeline-step.current .step-label {
    color: #1a1a1a;
    font-weight: 600;
}

/* ── Actions Section ──────────────────────────── */

.actions-section {
    margin-bottom: 8px;
}

.actions-title {
    margin: 0 0 12px;
    font-size: 13px;
    font-weight: 600;
    color: #888;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border: 0;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
}

.action-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.action-btn-primary {
    background: #6563d9;
    color: #fff;
}

.action-btn-primary:hover:not(:disabled) {
    background: #5552c9;
}

.action-btn-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1.5px solid #fecaca;
}

.action-btn-danger:hover:not(:disabled) {
    background: #fecaca;
}

.no-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px;
    background: #f8f8f6;
    border-radius: 10px;
    color: #aaa;
    font-size: 13px;
}

.no-actions-icon {
    font-size: 16px;
}

/* ── Cancelled Info Bar ──────────────────────── */

.cancelled-info-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.cancelled-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #fee2e2;
    color: #991b1b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    flex-shrink: 0;
}

.cancelled-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.cancelled-details strong {
    font-size: 13px;
    color: #991b1b;
}

.cancelled-details span {
    font-size: 12px;
    color: #b91c1c;
}

.cancelled-reason {
    padding: 6px 12px;
    background: #fff;
    border: 1px solid #fecaca;
    border-radius: 8px;
    font-size: 12px;
    color: #7f1d1d;
}

/* ── Detail Cards (single level) ─────────────── */

.detail-card {
    background: #fff;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 20px;
}

.detail-card .card-title {
    margin: 0;
    padding: 16px 20px;
    font-size: 14px;
    font-weight: 700;
    color: #1a1a1a;
    background: #f8f8f6;
    border-bottom: 1px solid #e8e8e4;
}

.card-body {
    padding: 16px 20px;
}

.info-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 8px 0;
    gap: 12px;
}

.info-row + .info-row {
    border-top: 1px solid #f5f5f8;
}

.info-row-full {
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 12px;
    color: #888;
    white-space: nowrap;
    flex-shrink: 0;
}

.info-value {
    font-size: 13px;
    color: #1a1a1a;
    text-align: left;
}

.info-value-lg {
    font-size: 15px;
}

.discount-value {
    color: #c9545e;
}

.font-mono {
    font-family: 'SF Mono', SFMono-Regular, Consolas, monospace;
}

.whitespace-pre-line {
    white-space: pre-line;
}

/* ── Order Items Table ────────────────────────── */

.items-card .card-body {
    padding: 0;
}

.items-table-wrapper {
    overflow-x: auto;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.items-table th {
    padding: 12px 16px;
    text-align: right;
    font-weight: 700;
    font-size: 12px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: .5px;
    background: #fafaf8;
    border-bottom: 1px solid #e8e8e4;
}

.items-table td {
    padding: 12px 16px;
    text-align: right;
    border-bottom: 1px solid #f5f5f8;
    vertical-align: middle;
}

.item-row:last-child td {
    border-bottom: none;
}

.item-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.item-image {
    flex-shrink: 0;
}

.item-thumb {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e8e8e4;
}

.item-thumb-placeholder {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f8;
    border-radius: 8px;
    font-size: 18px;
    border: 1px solid #e8e8e4;
}

.item-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.item-name {
    font-weight: 600;
    font-size: 13px;
    color: #1a1a1a;
}

.item-sku {
    font-size: 11px;
    color: #aaa;
    font-family: 'SF Mono', SFMono-Regular, Consolas, monospace;
}

.item-attrs {
    font-size: 11px;
    color: #aaa;
}

.td-qty, .td-price, .td-subtotal {
    font-size: 13px;
    color: #1a1a1a;
}

.td-subtotal strong {
    font-weight: 700;
}

/* ── Financial Summary ────────────────────────── */

.detail-card.summary-card {
    background: #fff;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 20px;
}

.summary-card .card-title {
    margin: 0;
    padding: 16px 20px;
    font-size: 14px;
    font-weight: 700;
    color: #1a1a1a;
    background: #f8f8f6;
    border-bottom: 1px solid #e8e8e4;
}

.summary-body {
    padding: 16px 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
}

.summary-row.total {
    border-top: 2px solid #e8e8e4;
    margin-top: 8px;
    padding-top: 12px;
}

.summary-label {
    font-size: 13px;
    color: #888;
}

.summary-value {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
}

.summary-row.total .summary-value {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
}

.summary-row.discount .summary-value {
    color: #c9545e;
}

/* ── Payment Card ──────────────────────────────── */

.payment-card .card-title {
    margin: 0;
    padding: 16px 20px;
    font-size: 14px;
    font-weight: 700;
    color: #1a1a1a;
    background: #f8f8f6;
    border-bottom: 1px solid #e8e8e4;
}

/* ── Payment Attempts ──────────────────────────── */

.attempts-card .card-title {
    margin: 0;
    padding: 16px 20px;
    font-size: 14px;
    font-weight: 700;
    color: #1a1a1a;
    background: #f8f8f6;
    border-bottom: 1px solid #e8e8e4;
}

.attempts-list {
    display: flex;
    flex-direction: column;
}

.attempt-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 20px;
    flex-wrap: wrap;
}

.attempt-row + .attempt-row {
    border-top: 1px solid #f5f5f8;
}

.attempt-main {
    display: flex;
    align-items: center;
    gap: 10px;
}

.attempt-gateway {
    font-weight: 600;
    font-size: 13px;
    color: #1a1a1a;
}

.attempt-amount {
    font-size: 13px;
    color: #888;
}

.attempt-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.attempt-code {
    font-size: 11px;
    color: #aaa;
    font-family: 'SF Mono', SFMono-Regular, Consolas, monospace;
}

.attempt-date {
    font-size: 11px;
    color: #aaa;
}

/* ── Toast Messages ────────────────────────────── */

.order-toast {
    margin-bottom: 16px;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
}

.order-toast.alert.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.order-toast.alert.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* ── Modal ─────────────────────────────────────── */

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.modal {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 20px 60px rgba(0,0,0,.15);
}

.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #e8e8e4;
}

.modal-head h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
}

.modal-close {
    background: none;
    border: none;
    font-size: 16px;
    color: #aaa;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all .15s ease;
}

.modal-close:hover {
    background: #f5f5f2;
    color: #1a1a1a;
}

.modal-body {
    padding: 24px;
}

.cancel-warning, .confirm-warning {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px;
    background: #fef3cd;
    border: 1px solid #fde68a;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #856404;
}

.cancel-warning-icon, .confirm-warning-icon {
    font-size: 18px;
    flex-shrink: 0;
    margin-top: 1px;
}

.form-field {
    margin-bottom: 16px;
}

.form-field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 6px;
}

.form-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e8e8e4;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    resize: vertical;
    box-sizing: border-box;
    transition: border-color .15s ease;
}

.form-textarea:focus {
    outline: none;
    border-color: #6563d9;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-cancel-modal {
    padding: 10px 20px;
    border: 1.5px solid #e8e8e4;
    border-radius: 10px;
    background: #fff;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
}

.btn-cancel-modal:hover {
    background: #f5f5f2;
    border-color: #1a1a1a;
}

.btn-confirm-cancel {
    padding: 10px 20px;
    border: 0;
    border-radius: 10px;
    background: #c9545e;
    color: #fff;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
}

.btn-confirm-cancel:hover:not(:disabled) {
    background: #b5434d;
}

.btn-confirm-cancel:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.btn-confirm-action {
    padding: 10px 20px;
    border: 0;
    border-radius: 10px;
    background: #6563d9;
    color: #fff;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
}

.btn-confirm-action:hover:not(:disabled) {
    background: #5552c9;
}

.btn-confirm-action:disabled {
    opacity: .5;
    cursor: not-allowed;
}

/* ── Alert ──────────────────────────────────────── */

.alert {
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 16px;
}

.alert.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert .btn-retry {
    margin-right: 8px;
    padding: 4px 12px;
    border: 1px solid currentColor;
    border-radius: 6px;
    background: transparent;
    color: inherit;
    font-family: inherit;
    font-size: 12px;
    cursor: pointer;
}

/* ── Mobile Cards ─────────────────────────────── */

.orders-mobile-list {
    display: none;
}

.order-card {
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    background: #fff;
    margin-bottom: 12px;
    overflow: hidden;
}

.order-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0ec;
    background: #f8f8f6;
}

.order-card-body {
    padding: 14px 16px;
}

.order-card-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0;
}

.order-card-row + .order-card-row {
    border-top: 1px solid #f5f5f8;
}

.card-label {
    font-size: 12px;
    color: #888;
}

.card-value {
    font-size: 13px;
    color: #1a1a1a;
    font-weight: 500;
}

.order-card-footer {
    padding: 0 14px 14px;
}

/* ── Keyframes ────────────────────────────────── */

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1024px) {
    .orders-summary {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .orders-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .toolbar-filters {
        flex-direction: column;
    }

    .filter-select {
        min-width: unset;
        width: 100%;
    }

    .detail-header {
        flex-direction: column;
    }

    .detail-badges {
        align-self: flex-start;
    }

    .action-buttons {
        flex-direction: column;
    }

    .action-btn {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .orders-section {
        padding: 16px 12px;
    }

    .orders-header {
        flex-direction: column;
        gap: 12px;
    }

    .orders-header-content h1 {
        font-size: 20px;
    }

    .orders-summary {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }

    .orders-summary .summary-card .summary-value {
        font-size: 24px;
    }

    .orders-table-wrapper {
        display: none;
    }

    .orders-mobile-list {
        display: block;
    }

    .orders-pagination {
        flex-direction: column;
        gap: 10px;
    }
}

@media (max-width: 480px) {
    .orders-summary {
        grid-template-columns: 1fr;
    }

    .modal {
        max-width: 100%;
    }

    .modal-actions {
        flex-direction: column;
    }

    .btn-cancel-modal, .btn-confirm-cancel, .btn-confirm-action {
        width: 100%;
        text-align: center;
    }
}

/* =========================================================
   DARK MODE SUPPORT
   ========================================================= */

.dark .orders-header-content h1,
.dark .detail-title,
.dark .status-management-card .card-title,
.dark .detail-card .card-title,
.dark .summary-card .card-title,
.dark .attempts-card .card-title {
    color: #e5e5e5;
}

.dark .orders-section,
.dark .order-detail-section {
    color: #e5e5e5;
}

.dark .orders-header,
.dark .orders-toolbar,
.dark .orders-content,
.dark .status-management-card,
.dark .orders-summary .summary-card,
.dark .detail-card,
.dark .detail-card.summary-card,
.dark .order-card {
    background: #161616;
    border-color: #2a2a2a;
}

.dark .orders-table thead {
    background: #1e1e1e;
    border-bottom-color: #2a2a2a;
}

.dark .orders-table th {
    color: #999;
    border-bottom-color: #2a2a2a;
}

.dark .orders-table td {
    border-bottom-color: #222;
    color: #e5e5e5;
}

.dark .orders-table tbody tr:hover {
    background: #1e1e1e;
}

.dark .orders-table tbody tr:last-child td {
    border-bottom-color: #222;
}

.dark .customer-name,
.dark .order-id,
.dark .amount,
.dark .item-name,
.dark .summary-value {
    color: #e5e5e5;
}

.dark .customer-phone,
.dark .info-label,
.dark .date-cell,
.dark .step-label,
.dark .summary-label,
.dark .card-label,
.dark .attempt-gateway,
.dark .attempt-amount,
.dark .attempt-code,
.dark .attempt-date,
.dark .info-value,
.dark .order-id-large {
    color: #999;
}

.dark .filter-select,
.dark .filter-select option,
.dark .form-textarea,
.dark .btn-back,
.dark .btn-refresh,
.dark .btn-view,
.dark .btn-cancel-modal,
.dark .pagination-btn {
    background: #1e1e1e;
    color: #e5e5e5;
    border-color: #2a2a2a;
}

.dark .timeline-track {
    background: #2a2a2a;
}

.dark .timeline-step .step-marker {
    background: #3a3a3a;
}

.dark .timeline-step.completed .step-marker {
    background: #6563d9;
}

.dark .no-actions {
    background: #1e1e1e;
    color: #666;
}


/* =========================================================
   CATEGORY MANAGER
========================================================= */

.category-manager {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 200px);
    min-height: 600px;
}

.category-manager-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 16px;
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 12px;
    margin-bottom: 16px;
}

.toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-wrapper {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.search-input {
    width: 100%;
    padding: 10px 40px 10px 14px;
    border: 1px solid #e0e0ea;
    border-radius: 10px;
    font-size: 13px;
    font-family: inherit;
    background: #fafbfc;
    transition: all 0.15s;
    direction: rtl;
}

.search-input:focus {
    outline: none;
    border-color: #6563d9;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(101, 99, 217, 0.15);
}

.search-spinner {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border: 2px solid #e0e0ea;
    border-top-color: #6563d9;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: translateY(-50%) rotate(360deg); }
}

.search-clear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 22px;
    height: 22px;
    border: none;
    background: #f0f0f5;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    transition: all 0.15s;
}

.search-clear:hover {
    background: #e0e0ea;
    color: #333;
}

.search-results-count {
    font-size: 12px;
    color: #999aae;
    padding: 4px 8px;
    background: #f5f5fa;
    border-radius: 6px;
}

.icon-button {
    width: 36px;
    height: 36px;
    border: 1px solid #e0e0ea;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    transition: all 0.15s;
}

.icon-button:hover {
    border-color: #6563d9;
    color: #6563d9;
    background: #fafaff;
}

.category-manager-content {
    display: flex;
    flex: 1;
    gap: 16px;
    overflow: hidden;
}

.category-tree-panel {
    width: 380px;
    flex-shrink: 0;
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 320px;
}

.category-tree-panel .panel-head {
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0f5;
}

.category-tree-panel .panel-head h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #36384b;
}

.category-tree {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.search-results-tree {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.tree-node {
    border-radius: 8px;
    transition: background 0.1s;
}

.tree-node:hover {
    background: #fafbfc;
}

.tree-node.selected {
    background: #efefff;
}

.node-main {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.1s;
}

.node-main:hover {
    background: #f5f5fa;
}

.node-toggle {
    width: 24px;
    height: 24px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 10px;
    border-radius: 4px;
    flex-shrink: 0;
    transition: all 0.15s;
}

.node-toggle:hover {
    background: #f0f0f5;
    color: #333;
}

.toggle-icon {
    display: block;
    transition: transform 0.15s;
}

.node-content {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.node-content strong {
    font-size: 13px;
    color: #36384b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.node-content small {
    font-size: 11px;
    color: #999aae;
    white-space: nowrap;
}

.node-content .status {
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 12px;
    white-space: nowrap;
}

.node-actions {
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.15s;
}

.tree-node:hover .node-actions {
    opacity: 1;
}

.node-actions .icon-button {
    width: 28px;
    height: 28px;
    font-size: 12px;
    border: none;
    background: transparent;
    color: #999;
}

.node-actions .icon-button:hover {
    background: #f0f0f5;
    color: #333;
}

.node-actions .icon-button.danger:hover {
    background: #fff0f1;
    color: #dd6870;
}

.node-children {
    margin-right: 28px;
    border-right: 1px dashed #eef0f5;
    padding-right: 8px;
}

.category-editor-panel {
    flex: 1;
    min-width: 350px;
    max-width: 500px;
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.category-editor-panel .panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0f5;
}

.category-editor-panel .panel-head h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #36384b;
}

.category-detail {
    padding: 16px;
}

.breadcrumb {
    margin-bottom: 16px;
}

.breadcrumb ol {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: 12px;
    color: #999;
}

.breadcrumb li {
    display: flex;
    align-items: center;
    gap: 4px;
}

.breadcrumb .sep {
    color: #ccc;
}

.breadcrumb strong {
    color: #36384b;
}

.detail-fields {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f0f0f5;
}

.detail-row {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.detail-row label {
    width: 100px;
    flex-shrink: 0;
    font-size: 12px;
    color: #999;
    font-weight: 500;
}

.detail-row span {
    flex: 1;
    font-size: 13px;
    color: #36384b;
    word-break: break-word;
}

.detail-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.category-editor-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    text-align: center;
    color: #999;
    background: #fafbfc;
    border: 1px dashed #eef0f5;
    border-radius: 12px;
    margin: 16px;
}

.category-editor-empty .empty-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.category-editor-empty h3 {
    margin: 0 0 8px;
    font-size: 16px;
    color: #666;
}

.category-editor-empty p {
    margin: 0 0 20px;
    font-size: 13px;
}

.category-form {
    padding: 16px;
    flex: 1;
    overflow-y: auto;
}

.category-form-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f5;
}

.category-form-head h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #36384b;
}

.category-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-field.full-width {
    grid-column: 1 / -1;
}

.form-field span {
    font-size: 12px;
    font-weight: 500;
    color: #666;
}

.form-field input,
.form-field select,
.form-field textarea {
    padding: 10px 12px;
    border: 1px solid #e0e0ea;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    background: #fff;
    transition: all 0.15s;
}

.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    outline: none;
    border-color: #6563d9;
    box-shadow: 0 0 0 3px rgba(101, 99, 217, 0.15);
}

.form-field input[dir="ltr"] {
    direction: ltr;
    text-align: left;
}

.category-active-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #fafbfc;
    border-radius: 8px;
    margin-bottom: 16px;
    cursor: pointer;
}

.category-active-toggle input {
    width: 18px;
    height: 18px;
    accent-color: #6563d9;
}

.category-active-toggle span {
    font-size: 13px;
    color: #36384b;
}

.category-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f5;
}

.search-result-node .node-content {
    padding-right: 8px;
}

/* Mobile responsive */
@media (max-width: 1024px) {
    .category-manager-content {
        flex-direction: column;
    }

    .category-tree-panel {
        width: 100%;
        max-height: 400px;
    }

    .category-editor-panel {
        max-width: 100%;
    }
}

@media (max-width: 640px) {
    .category-form-grid {
        grid-template-columns: 1fr;
    }

    .category-manager-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .toolbar-left,
    .toolbar-right {
        justify-content: center;
    }

    .search-wrapper {
        max-width: none;
    }

    .detail-row {
        flex-direction: column;
        gap: 4px;
    }

    .detail-row label {
        width: auto;
    }
}

/* =========================================================
   ARTICLES
========================================================= */

.articles-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 16px;
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 12px;
    margin-bottom: 16px;
}

.articles-toolbar .toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.articles-toolbar .search-wrapper {
    max-width: 400px;
    flex: 1;
}

.articles-toolbar .filter-select {
    min-width: 180px;
}

.articles-table {
    overflow-x: auto;
}

.articles-table .table-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1.5fr 0.8fr 1.5fr;
    gap: 12px;
    padding: 12px 16px;
    background: #fafbfc;
    border: 1px solid #eef0f5;
    border-radius: 8px 8px 0 0;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-align: right;
}

.articles-table .table-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1.5fr 0.8fr 1.5fr;
    gap: 12px;
    padding: 12px 16px;
    border: 1px solid #eef0f5;
    border-top: none;
    align-items: center;
    font-size: 13px;
}

.articles-table .table-row:last-child {
    border-radius: 0 0 8px 8px;
}

.articles-table .table-row:nth-child(even) {
    background: #fafbfc;
}

.articles-table .col-title strong {
    display: block;
    color: #36384b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.articles-table .col-title small {
    display: block;
    color: #999aae;
    font-size: 11px;
}

.articles-table .status {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    white-space: nowrap;
}

.articles-table .status.ok {
    background: #e7faf3;
    color: #27a37a;
}

.articles-table .status.warning {
    background: #fff8e1;
    color: #f5a623;
}

.articles-table .badge.featured {
    background: #fff8e1;
    color: #f5a623;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.articles-table .badge.normal {
    color: #999;
    font-size: 11px;
}

.articles-table .col-actions {
    display: flex;
    gap: 8px;
}

.articles-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.articles-pagination .pagination-info {
    font-size: 13px;
    color: #666;
}

.articles-pagination .pagination-buttons {
    display: flex;
    gap: 8px;
}

.articles-pagination .pagination-btn {
    padding: 6px 14px;
    border: 1px solid #e0e0ea;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
    font-family: inherit;
    transition: all 0.15s;
}

.articles-pagination .pagination-btn:hover:not(:disabled) {
    border-color: #6563d9;
    color: #6563d9;
}

.articles-pagination .pagination-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Article Form Modal */
.article-form-modal {
    max-width: 900px !important;
    max-height: 90vh;
    overflow: visible;
}

.article-form-modal .modal-body {
    max-height: 90vh;
    overflow-y: auto;
    padding: 0;
}

.modal-body {
    padding: 0;
}

.form-section {
    padding: 24px;
    border-bottom: 1px solid #f0f0f5;
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h4 {
    margin: 0 0 20px;
    font-size: 14px;
    font-weight: 600;
    color: #36384b;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f5;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}

.content-editor {
    font-family: 'SF Mono', SFMono-Regular, Consolas, monospace;
    font-size: 13px;
    line-height: 1.6;
    min-height: 300px;
    direction: rtl;
}

.multi-select-wrapper {
    min-width: 280px;
}

.form-field.field-error input,
.form-field.field-error select,
.form-field.field-error textarea {
    border-color: #dd6870;
}

.form-field.field-error .multi-select {
    border-color: #dd6870;
}

.field-error-text {
    display: block;
    color: #c9545e;
    font-size: 12px;
    font-weight: 600;
    margin-top: 2px;
}

.required {
    color: #dd6870;
    margin-right: 2px;
}

.checkbox-field {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #fafbfc;
    border-radius: 8px;
}

.checkbox-field input {
    width: 18px;
    height: 18px;
    accent-color: #6563d9;
}

.checkbox-field span {
    font-size: 13px;
    color: #36384b;
}

/* Responsive for Articles */
@media (max-width: 1024px) {
    .articles-table .table-header,
    .articles-table .table-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .articles-table .table-header > div:not(:first-child),
    .articles-table .table-row > div:not(:first-child) {
        display: none;
    }

    .article-form-modal {
        max-width: 100% !important;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .articles-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .articles-toolbar .search-wrapper,
    .articles-toolbar .filter-select {
        width: 100%;
        max-width: none;
    }

    .form-section {
        padding: 16px;
    }
}

/* Dark mode for Articles */
.dark .articles-toolbar,
.dark .articles-table .table-header,
.dark .articles-table .table-row {
    background: #1e1e1e;
    border-color: #2a2a2a;
}

.dark .articles-table .table-row:nth-child(even) {
    background: #1a1a1a;
}

.dark .articles-table .col-title strong {
    color: #e5e5e5;
}

.dark .articles-table .col-title small {
    color: #888;
}

.dark .articles-table .status.ok {
    background: #1a3a2e;
    color: #4ade80;
}

.dark .articles-table .status.warning {
    background: #3a2f1a;
    color: #fbbf24;
}

.dark .articles-table .badge.featured {
    background: #3a2f1a;
    color: #fbbf24;
}

.dark .articles-table .badge.normal {
    color: #888;
}

.dark .multi-select {
    background: #1e1e1e;
    border-color: #2a2a2a;
    color: #e5e5e5;
}

.dark .multi-select:hover {
    border-color: #3a3a4a;
}

.dark .multi-select.open {
    border-color: #6563d9;
}

.dark .multi-select-tag {
    background: #2a2a3a;
    color: #a5a3ff;
}

.dark .multi-select-tag .tag-remove {
    color: #a5a3ff;
}

.dark .multi-select-tag .tag-remove:hover {
    background: rgba(101, 99, 217, 0.2);
}

.dark .multi-select-search {
    color: #e5e5e5;
}

.dark .multi-select-dropdown {
    background: #1e1e1e;
    border-color: #2a2a2a;
}

.dark .multi-select-option {
    color: #e5e5e5;
}

.dark .multi-select-option:hover,
.dark .multi-select-option.selected {
    background: #2a2a3a;
    color: #a5a3ff;
}

.dark .multi-select-empty {
    color: #888;
}

.dark .form-section {
    border-color: #2a2a2a;
}

.dark .form-section h4 {
    border-color: #2a2a2a;
    color: #e5e5e5;
}

.dark .content-editor {
    background: #1e1e1e;
    border-color: #2a2a2a;
    color: #e5e5e5;
}

.dark .checkbox-field {
    background: #1e1e1e;
}

.dark .checkbox-field span {
    color: #e5e5e5;
}
</style>

اگر این نسخه **بدون خطا بالا آمد**، مرحله بعدی را روی همین کد می‌سازیم: **ویرایش محصول + API `show/update`**؛ بعد می‌رویم سراغ مدیریت کامل دسته‌بندی و Attributeها.
