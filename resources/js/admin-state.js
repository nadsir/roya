import { ref, watch } from 'vue';
import axios from 'axios';

export const products = ref([]);
export const categories = ref([]);
export const adminCategories = ref([]);
export const attributes = ref([]);
export const categoryAttributes = ref({});
export const vehicleEngines = ref([]);
export const vehicleBrands = ref([]);

export const loading = ref(false);
export const saving = ref(false);
export const editingProductId = ref(null);
export const adminUser = ref(null);
export const isAuthenticated = ref(false);

export const productSearch = ref('');
export const productPage = ref(1);
export const productTotalPages = ref(1);
export const productTotal = ref(0);

const adminTokenStorageKey = 'car.admin_token';

function applyAdminToken(token) {
    axios.defaults.headers.common.Authorization =
        `Bearer ${token}`;
}

function clearAdminToken() {
    delete axios.defaults.headers.common.Authorization;
    localStorage.removeItem(adminTokenStorageKey);
    adminUser.value = null;
    isAuthenticated.value = false;
}

export async function restoreAdminSession() {
    const token = localStorage.getItem(adminTokenStorageKey);

    if (!token) {
        return false;
    }

    applyAdminToken(token);

    try {
        const response = await axios.get('/api/admin/me');

        adminUser.value = response.data.user;
        isAuthenticated.value = true;

        return true;
    } catch {
        clearAdminToken();

        return false;
    }
}

export async function login(credentials) {
    const response = await axios.post('/api/admin/login', credentials);

    localStorage.setItem(adminTokenStorageKey, response.data.token);
    applyAdminToken(response.data.token);
    adminUser.value = response.data.user;
    isAuthenticated.value = true;
}

export async function logout() {
    try {
        await axios.post('/api/admin/logout');
    } finally {
        clearAdminToken();
    }
}

export function createEmptyForm() {
    return {
        name: '',
        slug: '',
        sku: '',
        short_description: '',
        description: '',
        price: 0,
        compare_at_price: null,
        stock: 0,
        is_active: true,
        is_featured: false,
        category_ids: [],
        attribute_value_ids: [],
        custom_attribute_values: [],
        variants: [],
        images: [],
    };
}

export const form = ref(createEmptyForm());

export function resetForm() {
    form.value = createEmptyForm();
    editingProductId.value = null;
}

export async function loadAdminProducts() {
    const params = {
        page: productPage.value,
    };

    if (productSearch.value) {
        params.search = productSearch.value;
    }

    const response = await axios.get('/api/admin/products', { params });

    products.value = response.data.data || [];
    productTotalPages.value = response.data.last_page || 1;
    productTotal.value = response.data.total || 0;

    if (productPage.value > productTotalPages.value) {
        productPage.value = productTotalPages.value;
    }
}

export async function loadProducts() {
    const response = await axios.get('/api/products');

    products.value = response.data.data || [];
}

export async function loadMeta() {
    const response = await axios.get('/api/admin/products/meta');

    categories.value =
        response.data.categories || [];

    attributes.value =
        response.data.attributes || [];

    categoryAttributes.value =
        response.data.category_attributes || {};
}

export async function loadAdminCategories() {
    const response = await axios.get('/api/admin/categories');

    adminCategories.value = response.data.data || [];
}

export const categorySearch = ref('');
export const selectedCategoryId = ref(null);
export const expandedCategoryIds = ref(new Set());
export const categorySearchResults = ref([]);
export const categorySearchLoading = ref(false);

export async function searchCategories(query) {
    categorySearch.value = query;
    categorySearchLoading.value = true;
    categorySearchResults.value = [];

    if (!query || !query.trim()) {
        categorySearchLoading.value = false;
        return;
    }

    try {
        const response = await axios.get('/api/admin/categories/search', {
            params: { q: query.trim() }
        });
        categorySearchResults.value = response.data.data || [];

        // Auto-expand ancestors of search results
        for (const result of categorySearchResults.value) {
            if (result.ancestor_ids) {
                for (const ancestorId of result.ancestor_ids) {
                    expandedCategoryIds.value.add(ancestorId);
                }
            }
        }
    } catch (error) {
        console.error('Category search failed:', error);
        categorySearchResults.value = [];
    } finally {
        categorySearchLoading.value = false;
    }
}

export function toggleCategoryExpand(categoryId) {
    if (expandedCategoryIds.value.has(categoryId)) {
        expandedCategoryIds.value.delete(categoryId);
    } else {
        expandedCategoryIds.value.add(categoryId);
    }
}

export function expandAllCategories() {
    const collectIds = (categories) => {
        const ids = [];
        for (const cat of categories) {
            ids.push(cat.id);
            if (cat.children?.length) {
                ids.push(...collectIds(cat.children));
            }
        }
        return ids;
    };
    expandedCategoryIds.value = new Set(collectIds(adminCategories.value));
}

export function collapseAllCategories() {
    expandedCategoryIds.value.clear();
}

export function selectCategory(categoryId) {
    selectedCategoryId.value = categoryId;
}

export function clearCategorySelection() {
    selectedCategoryId.value = null;
}

export async function load() {
    loading.value = true;

    try {
        await Promise.all([
            loadAdminProducts(),
            loadMeta(),
            loadAdminCategories(),
        ]);
    } finally {
        loading.value = false;
    }
}

export async function loadCategoryAttributeConfig(categoryId) {
    const response = await axios.get(
        `/api/admin/categories/${categoryId}/attributes`
    );

    return response.data.data || [];
}

export async function saveCategoryAttributeConfig(
    categoryId,
    configurations
) {
    const response = await axios.put(
        `/api/admin/categories/${categoryId}/attributes`,
        { attributes: configurations }
    );

    return response.data.data || [];
}

export async function createCategory(data) {
    const response = await axios.post('/api/admin/categories', data);

    return response.data.data;
}

export async function updateCategory(categoryId, data) {
    const response = await axios.put(
        `/api/admin/categories/${categoryId}`,
        data
    );

    return response.data.data;
}

export async function removeCategory(categoryId) {
    await axios.delete(`/api/admin/categories/${categoryId}`);
}

export async function createAttribute(data) {
    const response = await axios.post('/api/admin/attributes', data);

    return response.data.data;
}

export async function updateAttribute(attributeId, data) {
    const response = await axios.put(
        `/api/admin/attributes/${attributeId}`,
        data
    );

    return response.data.data;
}

export async function removeAttribute(attributeId) {
    await axios.delete(`/api/admin/attributes/${attributeId}`);
}

export async function createAttributeValue(attributeId, data) {
    const response = await axios.post(
        `/api/admin/attributes/${attributeId}/values`,
        data
    );

    return response.data.data;
}

export async function updateAttributeValue(
    attributeId,
    valueId,
    data
) {
    const response = await axios.put(
        `/api/admin/attributes/${attributeId}/values/${valueId}`,
        data
    );

    return response.data.data;
}

export async function removeAttributeValue(attributeId, valueId) {
    await axios.delete(
        `/api/admin/attributes/${attributeId}/values/${valueId}`
    );
}

export function addVariant() {
    form.value.variants.push({
        sku: '',
        price: null,
        compare_at_price: null,
        stock: 0,
        is_active: true,
        attribute_value_ids: [],
    });
}

export function removeVariant(index) {
    form.value.variants.splice(index, 1);
}

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

export async function save() {
    saving.value = true;

    try {
        const { images, ...payload } = form.value;

        const response = await axios.post(
            '/api/admin/products',
            payload
        );

        await loadAdminProducts();

        return response.data;
    } catch (error) {
        const errors = error.response?.data?.errors;
        const message = errors
            ? Object.values(errors).flat().join(' ')
            : error.response?.data?.message || 'ذخیره محصول انجام نشد.';
        throw new Error(message);
    } finally {
        saving.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Load Product For Edit
|--------------------------------------------------------------------------
*/

export async function loadProduct(id) {
    loading.value = true;

    try {
        const response = await axios.get(
            `/api/admin/products/${id}`
        );

        const product = response.data;

        const productCategoryIds = (product.categories || [])
            .map(category => category.id);

        const categoryAttributeSets = productCategoryIds
            .map(categoryId => categoryAttributes.value[categoryId] || []);

        const effectiveAttributeIds = categoryAttributeSets.length
            ? categoryAttributeSets[0]
                .filter(attribute =>
                    categoryAttributeSets.slice(1).every(set =>
                        set.some(item => item.id === attribute.id)
                    )
                )
                .map(attribute => attribute.id)
            : [];

        editingProductId.value = product.id;

        form.value = {
            name: product.name || '',
            slug: product.slug || '',
            sku: product.sku || '',

            short_description:
                product.short_description || '',

            description:
                product.description || '',

            price:
                product.price ?? 0,

            compare_at_price:
                product.compare_at_price ?? null,

            stock:
                product.stock ?? 0,

            is_active:
                Boolean(product.is_active),

            is_featured:
                Boolean(product.is_featured),

            category_ids:
                (product.categories || [])
                    .map(category => category.id),

            attribute_value_ids:
                (product.attribute_values || [])
                    .map(value => value.id),

            custom_attribute_values:
                (product.custom_attribute_values || [])
                    .filter(value =>
                        effectiveAttributeIds.includes(value.attribute_id)
                    )
                    .map(value => ({
                        attribute_id: value.attribute_id,
                        value: value.value_type === 'number'
                            ? value.value_number
                            : value.value_type === 'boolean'
                                ? Boolean(value.value_boolean)
                                : value.value_text,
                    })),

            variants:
                (product.variants || [])
                    .map(variant => ({
                        id: variant.id,

                        sku: variant.sku || '',

                        price:
                            variant.price ?? null,

                        compare_at_price:
                            variant.compare_at_price ?? null,

                        stock:
                            variant.stock ?? 0,

                        is_active:
                            Boolean(variant.is_active),

                        attribute_value_ids:
                            (variant.attribute_values || [])
                                .map(value => value.id),
                    })),
                    images:
    (product.images || []).map(image => ({
        id: image.id,
        path: image.path,
        alt_text: image.alt_text || '',
        is_primary: Boolean(image.is_primary),
        sort_order: image.sort_order ?? 0,
    })),
        };

        return product;

    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

export async function updateProduct() {
    if (!editingProductId.value) {
        console.log('[PRODUCT SAVE] updateProduct: no editingProductId');
        return;
    }

    console.log('[PRODUCT SAVE] updateProduct called', editingProductId.value);

    saving.value = true;

    try {
        const { images, ...payload } = form.value;

        console.log('[PRODUCT SAVE] sending request', { url: `/api/admin/products/${editingProductId.value}`, payload });

        const response = await axios.put(
            `/api/admin/products/${editingProductId.value}`,
            payload
        );

        console.log('[PRODUCT SAVE] response', response.status, response.data);

        await loadAdminProducts();

        return response.data;

    } catch (error) {
        console.error('[PRODUCT SAVE] error', error);
        const errors = error.response?.data?.errors;
        const message = errors
            ? Object.values(errors).flat().join(' ')
            : error.response?.data?.message || 'بروزرسانی محصول انجام نشد.';
        throw new Error(message);
    } finally {
        saving.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Product Images
|--------------------------------------------------------------------------
*/

export async function uploadProductImage(
    productId,
    file,
    altText = ''
) {
    if (!productId || !file) {
        return;
    }

    const formData = new FormData();

    formData.append('image', file);

    if (altText) {
        formData.append(
            'alt_text',
            altText
        );
    }

    const response = await axios.post(
        `/api/admin/products/${productId}/images`,
        formData,
        {
            headers: {
                'Content-Type':
                    'multipart/form-data',
            },
        }
    );

    if (!form.value.images) {
        form.value.images = [];
    }

    form.value.images.push(
        response.data
    );

    return response.data;
}
export async function setPrimaryProductImage(
    productId,
    imageId
) {
    if (!productId || !imageId) return;

    const response = await axios.patch(
        `/api/admin/products/${productId}/images/${imageId}/primary`
    );

    form.value.images = response.data;

    return response.data;
}




export async function reorderProductImages(
    productId,
    imageIds
) {
    if (!productId || !Array.isArray(imageIds)) {
        return;
    }

    const response = await axios.put(
        `/api/admin/products/${productId}/images/reorder`,
        {
            image_ids: imageIds,
        }
    );

    form.value.images = response.data;

    return response.data;
}
export async function removeProductImage(
    productId,
    imageId
) {
    if (!productId || !imageId) return;

    const response = await axios.delete(
        `/api/admin/products/${productId}/images/${imageId}`
    );

    form.value.images = response.data;

    return response.data;
}

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

export async function removeProduct(id) {
    if (!confirm('این محصول حذف شود؟')) {
        return;
    }

    await axios.delete(
        `/api/admin/products/${id}`
    );

    await loadAdminProducts();
}

/*
|--------------------------------------------------------------------------
| Vehicle CRUD (Admin)
|--------------------------------------------------------------------------
*/

export async function loadAdminBrands() {
    const response = await axios.get('/api/admin/vehicles/brands');
    return response.data.data || [];
}

export async function createBrand(data) {
    const response = await axios.post('/api/admin/vehicles/brands', data);
    return response.data;
}

export async function updateBrand(id, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${id}`, data);
    return response.data;
}

export async function deleteBrand(id) {
    await axios.delete(`/api/admin/vehicles/brands/${id}`);
}

export async function loadAdminModels(brandId) {
    const response = await axios.get(`/api/admin/vehicles/brands/${brandId}/models`);
    return response.data.data || [];
}

export async function createModel(brandId, data) {
    const response = await axios.post(`/api/admin/vehicles/brands/${brandId}/models`, data);
    return response.data;
}

export async function updateModel(brandId, modelId, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${brandId}/models/${modelId}`, data);
    return response.data;
}

export async function deleteModel(brandId, modelId) {
    await axios.delete(`/api/admin/vehicles/brands/${brandId}/models/${modelId}`);
}

export async function loadAdminGenerations(brandId, modelId) {
    const response = await axios.get(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations`);
    return response.data.data || [];
}

export async function createGeneration(brandId, modelId, data) {
    const response = await axios.post(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations`, data);
    return response.data;
}

export async function updateGeneration(brandId, modelId, genId, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}`, data);
    return response.data;
}

export async function deleteGeneration(brandId, modelId, genId) {
    await axios.delete(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}`);
}

export async function loadAdminTrims(brandId, modelId, genId) {
    const response = await axios.get(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims`);
    return response.data.data || [];
}

export async function createTrim(brandId, modelId, genId, data) {
    const response = await axios.post(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims`, data);
    return response.data;
}

export async function updateTrim(brandId, modelId, genId, trimId, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}`, data);
    return response.data;
}

export async function deleteTrim(brandId, modelId, genId, trimId) {
    await axios.delete(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}`);
}

export async function loadAdminEngines(brandId, modelId, genId, trimId) {
    const response = await axios.get(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}/engines`);
    return response.data.data || [];
}

export async function createEngine(brandId, modelId, genId, trimId, data) {
    const response = await axios.post(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}/engines`, data);
    return response.data;
}

export async function updateEngine(brandId, modelId, genId, trimId, engineId, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}/engines/${engineId}`, data);
    return response.data;
}

export async function deleteEngine(brandId, modelId, genId, trimId, engineId) {
    await axios.delete(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}/engines/${engineId}`);
}

/*
|--------------------------------------------------------------------------
| Vehicle Hierarchy (Public API - for selectors)
|--------------------------------------------------------------------------
*/

export async function loadBrands() {
    const response = await axios.get('/api/vehicles/brands');
    vehicleBrands.value = response.data.data || [];
    return vehicleBrands.value;
}

export async function loadBrandModels(brandId) {
    const response = await axios.get(`/api/vehicles/brands/${brandId}/models`);
    return response.data.data || [];
}

export async function loadModelGenerations(modelId) {
    const response = await axios.get(`/api/vehicles/models/${modelId}/generations`);
    return response.data.data || [];
}

export async function loadGenerationTrims(genId) {
    const response = await axios.get(`/api/vehicles/generations/${genId}/trims`);
    return response.data.data || [];
}

export async function loadTrimEngines(trimId) {
    const response = await axios.get(`/api/vehicles/trims/${trimId}/engines`);
    return response.data.data || [];
}

export async function loadAllVehicleEngines() {
    try {
        const response = await axios.get('/api/admin/vehicles/engines');
        vehicleEngines.value = response.data.data || [];
        return vehicleEngines.value;
    } catch (error) {
        console.error('Failed to load all vehicle engines:', error);
        vehicleEngines.value = [];
        return [];
    }
}

export async function loadArticleFormData() {
    try {
        await Promise.all([
            loadAdminCategories(),
            loadAdminProducts(),
            loadBrands(),
            loadAllVehicleEngines(),
        ]);
    } catch (error) {
        console.error('Failed to load article form data:', error);
    }
}

/*
|--------------------------------------------------------------------------
| Product ↔ Vehicle Compatibility
|--------------------------------------------------------------------------
*/

export async function loadProductCompatibility(productId) {
    const response = await axios.get(`/api/admin/products/${productId}/vehicle-compat`);
    return response.data.data || [];
}

export async function attachProductCompatibility(productId, engineIds) {
    const response = await axios.post(`/api/admin/products/${productId}/vehicle-compat`, {
        vehicle_engine_ids: engineIds,
    });
    return response.data;
}

export async function detachProductCompatibility(productId, engineId) {
    await axios.delete(`/api/admin/products/${productId}/vehicle-compat/${engineId}`);
}

/*
|--------------------------------------------------------------------------
| Orders (Admin)
|--------------------------------------------------------------------------
*/

export const adminOrders = ref([]);
export const adminOrder = ref(null);
export const adminOrderLoading = ref(false);
export const adminOrderError = ref('');
export const adminOrderSuccess = ref('');

export const adminOrderSearch = ref('');
export const adminOrderStatusFilter = ref('');
export const adminOrderPaymentFilter = ref('');
export const adminOrderPage = ref(1);
export const adminOrderTotalPages = ref(1);
export const adminOrderTotal = ref(0);

export async function loadAdminOrders() {
    adminOrderLoading.value = true;
    adminOrderError.value = '';
    adminOrderSuccess.value = '';

    try {
        const params = {
            page: adminOrderPage.value,
        };

        if (adminOrderSearch.value) {
            params.search = adminOrderSearch.value;
        }

        if (adminOrderStatusFilter.value) {
            params.status = adminOrderStatusFilter.value;
        }

        if (adminOrderPaymentFilter.value) {
            params.payment_status = adminOrderPaymentFilter.value;
        }

        const response = await axios.get('/api/admin/orders', { params });

        adminOrders.value = response.data.data || [];
        adminOrderTotalPages.value = response.data.last_page || 1;
        adminOrderTotal.value = response.data.total || 0;

        if (adminOrderPage.value > adminOrderTotalPages.value) {
            adminOrderPage.value = adminOrderTotalPages.value;
        }
    } catch (error) {
        adminOrderError.value = error.response?.data?.message || 'دریافت سفارش‌ها انجام نشد.';
    } finally {
        adminOrderLoading.value = false;
    }
}

export async function loadAdminOrder(id) {
    adminOrderLoading.value = true;
    adminOrderError.value = '';
    adminOrder.value = null;

    try {
        const response = await axios.get(`/api/admin/orders/${id}`);
        adminOrder.value = response.data.order || null;
        return adminOrder.value;
    } catch (error) {
        adminOrderError.value = error.response?.data?.message || 'دریافت سفارش انجام نشد.';
    } finally {
        adminOrderLoading.value = false;
    }
}

export async function updateAdminOrderStatus(id, status, cancelledReason = null) {
    adminOrderError.value = '';
    adminOrderSuccess.value = '';

    try {
        const response = await axios.patch(`/api/admin/orders/${id}/status`, {
            status,
            cancelled_reason: cancelledReason,
        });

        adminOrderSuccess.value = 'وضعیت سفارش با موفقیت تغییر یافت.';
        return response.data;
    } catch (error) {
        adminOrderError.value = error.response?.data?.errors?.status?.[0] || error.response?.data?.message || 'تغییر وضعیت انجام نشد.';
        throw error;
    }
}

export function getAllowedTransitions(currentStatus) {
    const transitions = {
        pending: ['confirmed', 'cancelled'],
        confirmed: ['processing', 'cancelled'],
        processing: ['shipped'],
        shipped: ['delivered'],
        delivered: [],
        cancelled: [],
    };
    return transitions[currentStatus] || [];
}

export function getStatusLabel(status) {
    const labels = {
        pending: 'در انتظار پرداخت',
        confirmed: 'تأیید شده',
        processing: 'در حال پردازش',
        shipped: 'ارسال شده',
        delivered: 'تحویل شده',
        cancelled: 'لغو شده',
    };
    return labels[status] || status;
}

/*
|--------------------------------------------------------------------------
| Notification (Admin)
|--------------------------------------------------------------------------
*/

export const adminNotification = ref({
    visible: false,
    type: 'success',
    title: '',
    message: '',
});

export function showAdminNotification(type, title, message) {
    adminNotification.value = {
        visible: true,
        type,
        title,
        message,
    };
}

export function hideAdminNotification() {
    adminNotification.value.visible = false;
}

/*
|--------------------------------------------------------------------------
| Users (Admin)
|--------------------------------------------------------------------------
*/

export const adminUsers = ref([]);
export const adminUserDetail = ref(null);
export const adminUserLoading = ref(false);
export const adminUserError = ref('');
export const adminUserSuccess = ref('');

export const adminUserSearch = ref('');
export const adminUserStatusFilter = ref('');
export const adminUserPage = ref(1);
export const adminUserTotalPages = ref(1);
export const adminUserTotal = ref(0);

export async function loadAdminUsers() {
    adminUserLoading.value = true;
    adminUserError.value = '';
    adminUserSuccess.value = '';

    try {
        const params = {
            page: adminUserPage.value,
        };

        if (adminUserSearch.value) {
            params.search = adminUserSearch.value;
        }

        if (adminUserStatusFilter.value) {
            params.status = adminUserStatusFilter.value;
        }

        const response = await axios.get('/api/admin/users', { params });

        adminUsers.value = response.data.data || [];
        adminUserTotalPages.value = response.data.last_page || 1;
        adminUserTotal.value = response.data.total || 0;

        if (adminUserPage.value > adminUserTotalPages.value) {
            adminUserPage.value = adminUserTotalPages.value;
        }
    } catch (error) {
        adminUserError.value = error.response?.data?.message || 'دریافت کاربران انجام نشد.';
    } finally {
        adminUserLoading.value = false;
    }
}

export async function loadAdminUser(id) {
    adminUserLoading.value = true;
    adminUserError.value = '';
    adminUserDetail.value = null;

    try {
        const response = await axios.get(`/api/admin/users/${id}`);
        adminUserDetail.value = response.data.user || null;
        return adminUserDetail.value;
    } catch (error) {
        adminUserError.value = error.response?.data?.message || 'دریافت کاربر انجام نشد.';
    } finally {
        adminUserLoading.value = false;
    }
}

export async function updateAdminUserStatus(id, isActive) {
    adminUserError.value = '';
    adminUserSuccess.value = '';

    try {
        const response = await axios.patch(`/api/admin/users/${id}/status`, {
            is_active: isActive,
        });

        adminUserSuccess.value = 'وضعیت کاربر با موفقیت تغییر یافت.';
        return response.data;
    } catch (error) {
        adminUserError.value = error.response?.data?.errors?.is_active?.[0] || error.response?.data?.message || 'تغییر وضعیت انجام نشد.';
        throw error;
    }
}

/*
|--------------------------------------------------------------------------
| Comments (Admin)
|--------------------------------------------------------------------------
*/

export const adminComments = ref([]);
export const adminCommentLoading = ref(false);
export const adminCommentError = ref('');
export const adminCommentSuccess = ref('');

export const adminCommentSearch = ref('');
export const adminCommentStatusFilter = ref('');
export const adminCommentTypeFilter = ref('');
export const adminCommentPage = ref(1);
export const adminCommentTotalPages = ref(1);
export const adminCommentTotal = ref(0);

export const adminCommentBusy = ref({});

export async function loadAdminComments() {
    adminCommentLoading.value = true;
    adminCommentError.value = '';
    adminCommentSuccess.value = '';

    try {
        const params = {
            page: adminCommentPage.value,
        };

        if (adminCommentSearch.value) {
            params.search = adminCommentSearch.value;
        }

        if (adminCommentStatusFilter.value) {
            params.status = adminCommentStatusFilter.value;
        }

        if (adminCommentTypeFilter.value) {
            params.commentable_type = adminCommentTypeFilter.value;
        }

        const response = await axios.get('/api/admin/comments', { params });

        adminComments.value = response.data.data || [];
        adminCommentTotalPages.value = response.data.last_page || 1;
        adminCommentTotal.value = response.data.total || 0;

        if (adminCommentPage.value > adminCommentTotalPages.value) {
            adminCommentPage.value = adminCommentTotalPages.value;
        }
    } catch (error) {
        adminCommentError.value = error.response?.data?.message || 'دریافت نظرات انجام نشد.';
    } finally {
        adminCommentLoading.value = false;
    }
}

export async function updateAdminCommentStatus(id, status) {
    adminCommentError.value = '';
    adminCommentSuccess.value = '';
    adminCommentBusy.value[id] = true;

    try {
        const response = await axios.patch(`/api/admin/comments/${id}/status`, {
            status,
        });

        adminCommentSuccess.value = response.data?.message || 'وضعیت نظر با موفقیت به‌روزرسانی شد.';
        return response.data;
    } catch (error) {
        adminCommentError.value = error.response?.data?.errors?.status?.[0] || error.response?.data?.message || 'تغییر وضعیت انجام نشد.';
        throw error;
    } finally {
        adminCommentBusy.value[id] = false;
    }
}

export async function deleteAdminComment(id) {
    adminCommentError.value = '';
    adminCommentSuccess.value = '';
    adminCommentBusy.value[id] = true;

    try {
        await axios.delete(`/api/admin/comments/${id}`);
        adminCommentSuccess.value = 'نظر با موفقیت حذف شد.';
        return true;
    } catch (error) {
        adminCommentError.value = error.response?.data?.message || 'حذف نظر انجام نشد.';
        throw error;
    } finally {
        adminCommentBusy.value[id] = false;
    }
}

/*
|--------------------------------------------------------------------------
| Articles (Admin)
|--------------------------------------------------------------------------
*/

export const adminArticles = ref([]);
export const adminArticleLoading = ref(false);
export const adminArticleError = ref('');
export const adminArticleSuccess = ref('');

export const articleFormErrors = ref({});
let lastSubmittedArticleForm = null;
let articleValidationSummary = false;

const articleFieldLabels = {
    title: 'عنوان مقاله',
    slug: 'اسلاگ مقاله',
    excerpt: 'خلاصه مقاله',
    content: 'متن کامل مقاله',
    featured_image: 'آدرس تصویر',
    status: 'وضعیت',
    published_at: 'تاریخ انتشار',
    meta_title: 'Meta Title',
    meta_description: 'Meta Description',
    canonical_url: 'Canonical URL',
    is_featured: 'مقاله ویژه',
    author_id: 'نویسنده',
    categories: 'دسته‌بندی‌های',
    products: 'محصولات',
    vehicles: 'موتورهای خودرو',
    brands: 'برندها',
};

function baseArticleField(field) {
    return field.includes('.') ? field.split('.')[0] : field;
}

function translateArticleError(field, message) {
    const base = baseArticleField(field);
    const label = articleFieldLabels[base] || field;
    const text = String(message || '').toLowerCase();

    if (text.includes('required')) {
        if (base === 'title') return 'عنوان مقاله الزامی است.';
        if (base === 'slug') return 'اسلاگ مقاله الزامی است.';
        return `${label} الزامی است.`;
    }

    if (text.includes('already been taken')) {
        if (base === 'slug') return 'این اسلاگ قبلاً استفاده شده است.';
        return `${label} تکراری است.`;
    }

    if (text.includes('greater than 255')) {
        if (base === 'title') return 'عنوان مقاله نباید بیشتر از ۲۵۵ کاراکتر باشد.';
        if (base === 'slug') return 'اسلاگ مقاله نباید بیشتر از ۲۵۵ کاراکتر باشد.';
        return `${label} نباید بیشتر از ۲۵۵ کاراکتر باشد.`;
    }

    if (text.includes('greater than 2048')) {
        if (base === 'featured_image') return 'آدرس تصویر نباید بیشتر از ۲۰۴۸ کاراکتر باشد.';
        if (base === 'canonical_url') return 'Canonical URL نباید بیشتر از ۲۰۴۸ کاراکتر باشد.';
        return `${label} نباید بیشتر از ۲۰۴۸ کاراکتر باشد.`;
    }

    if (base === 'published_at' && (text.includes('date') || text.includes('valid'))) {
        return 'تاریخ انتشار معتبر نیست.';
    }

    if (text.includes('is invalid') || text.includes('selected')) {
        switch (base) {
            case 'categories':
                return 'دسته‌بندی انتخاب‌شده معتبر نیست.';
            case 'products':
                return 'محصول انتخاب‌شده معتبر نیست.';
            case 'vehicles':
                return 'موتور خودروی انتخاب‌شده معتبر نیست.';
            case 'brands':
                return 'برند انتخاب‌شده معتبر نیست.';
            case 'author_id':
                return 'نویسنده انتخاب‌شده معتبر نیست.';
            case 'status':
                return 'وضعیت انتخاب‌شده معتبر نیست.';
            default:
                return `مقدار «${label}» معتبر نیست.`;
        }
    }

    if (base === 'is_featured' || text.includes('boolean')) {
        return 'مقدار «مقاله ویژه» معتبر نیست.';
    }

    if (text.includes('string')) {
        return `مقدار «${label}» معتبر نیست.`;
    }

    return `مقدار «${label}» معتبر نیست.`;
}

function translateArticleValidationErrors(errors) {
    const result = {};
    for (const [field, messages] of Object.entries(errors)) {
        if (Array.isArray(messages) && messages.length) {
            result[field] = translateArticleError(field, messages[0]);
        }
    }
    return result;
}

export function clearArticleFieldError(field) {
    if (!(field in articleFormErrors.value)) {
        return;
    }
    const next = { ...articleFormErrors.value };
    delete next[field];
    articleFormErrors.value = next;
    if (!Object.keys(next).length) {
        adminArticleError.value = '';
        articleValidationSummary = false;
    }
}

export const adminArticleSearch = ref('');
export const adminArticleStatusFilter = ref('');
export const adminArticlePage = ref(1);
export const adminArticleTotalPages = ref(1);
export const adminArticleTotal = ref(0);

export const articleEditingId = ref(null);
export const showArticleEditor = ref(false);
export const articleSaving = ref(false);
export const articleForm = ref(createEmptyArticleForm());

export function createEmptyArticleForm() {
    return {
        title: '',
        slug: '',
        excerpt: '',
        content: '',
        featured_image: '',
        status: 'draft',
        published_at: null,
        meta_title: '',
        meta_description: '',
        canonical_url: '',
        is_featured: false,
        author_id: null,
        categories: [],
        products: [],
        vehicles: [],
        brands: [],
    };
}

export function openCreateArticle() {
    articleEditingId.value = null;
    articleForm.value = createEmptyArticleForm();
    showArticleEditor.value = true;
    adminArticleError.value = '';
    adminArticleSuccess.value = '';
    articleFormErrors.value = {};
    lastSubmittedArticleForm = null;
    articleValidationSummary = false;
    loadArticleFormData();
}

export function openEditArticle(article) {
    articleEditingId.value = article.id;
    articleForm.value = {
        title: article.title || '',
        slug: article.slug || '',
        excerpt: article.excerpt || '',
        content: article.content || '',
        featured_image: article.featured_image || '',
        status: article.status || 'draft',
        published_at: article.published_at || null,
        meta_title: article.meta_title || '',
        meta_description: article.meta_description || '',
        canonical_url: article.canonical_url || '',
        is_featured: Boolean(article.is_featured),
        author_id: article.author_id || null,
        categories: article.categories?.map(c => c.id) || [],
        products: article.products?.map(p => p.id) || [],
        vehicles: article.vehicles?.map(v => v.id) || [],
        brands: article.brands?.map(b => b.id) || [],
    };
    showArticleEditor.value = true;
    adminArticleError.value = '';
    adminArticleSuccess.value = '';
    articleFormErrors.value = {};
    lastSubmittedArticleForm = null;
    articleValidationSummary = false;
    loadArticleFormData();
}

export async function loadAdminArticles() {
    adminArticleLoading.value = true;
    adminArticleError.value = '';
    adminArticleSuccess.value = '';

    try {
        const params = {
            page: adminArticlePage.value,
        };

        if (adminArticleSearch.value) {
            params.search = adminArticleSearch.value;
        }

        if (adminArticleStatusFilter.value) {
            params.status = adminArticleStatusFilter.value;
        }

        const response = await axios.get('/api/admin/articles', { params });

        adminArticles.value = response.data.data || [];
        adminArticleTotalPages.value = response.data.last_page || 1;
        adminArticleTotal.value = response.data.total || 0;

        if (adminArticlePage.value > adminArticleTotalPages.value) {
            adminArticlePage.value = adminArticleTotalPages.value;
        }
    } catch (error) {
        adminArticleError.value = error.response?.data?.message || 'دریافت مقالات انجام نشد.';
    } finally {
        adminArticleLoading.value = false;
    }
}

export async function loadAdminArticle(id) {
    adminArticleLoading.value = true;
    adminArticleError.value = '';

    try {
        const response = await axios.get(`/api/admin/articles/${id}`);
        return response.data;
    } catch (error) {
        adminArticleError.value = error.response?.data?.message || 'دریافت مقاله انجام نشد.';
        throw error;
    } finally {
        adminArticleLoading.value = false;
    }
}

export async function saveArticle() {
    articleSaving.value = true;
    adminArticleError.value = '';
    adminArticleSuccess.value = '';
    articleFormErrors.value = {};

    const form = articleForm.value;
    const clientErrors = {};
    for (const field of ['title', 'slug']) {
        if (!String(form[field] ?? '').trim()) {
            clientErrors[field] = `${articleFieldLabels[field]} الزامی است.`;
        }
    }
    if (Object.keys(clientErrors).length) {
        articleFormErrors.value = clientErrors;
        lastSubmittedArticleForm = JSON.parse(JSON.stringify(form));
        articleValidationSummary = true;
        adminArticleError.value = 'لطفاً خطاهای فرم را بررسی کنید.';
        articleSaving.value = false;
        return;
    }

    lastSubmittedArticleForm = JSON.parse(JSON.stringify(form));

    try {
        const data = { ...form };

        if (articleEditingId.value) {
            const response = await axios.put(`/api/admin/articles/${articleEditingId.value}`, data);
            loadAdminArticles();
            adminArticleSuccess.value = 'مقاله به‌روزرسانی شد.';
            articleFormErrors.value = {};
            lastSubmittedArticleForm = null;
            articleValidationSummary = false;
            return response.data;
        } else {
            const response = await axios.post('/api/admin/articles', data);
            loadAdminArticles();
            adminArticleSuccess.value = 'مقاله ایجاد شد.';
            articleFormErrors.value = {};
            lastSubmittedArticleForm = null;
            articleValidationSummary = false;
            return response.data;
        }
    } catch (error) {
        const backendErrors = error.response?.data?.errors;
        if (backendErrors && typeof backendErrors === 'object' && !Array.isArray(backendErrors)) {
            articleFormErrors.value = translateArticleValidationErrors(backendErrors);
            articleValidationSummary = true;
            adminArticleError.value = 'لطفاً خطاهای فرم را بررسی کنید.';
        } else {
            articleValidationSummary = false;
            adminArticleError.value =
                error.response?.data?.message || 'ارتباط با سرور برقرار نشد. لطفاً دوباره تلاش کنید.';
        }
        throw error;
    } finally {
        articleSaving.value = false;
    }
}

watch(
    articleForm,
    (newForm) => {
        if (!lastSubmittedArticleForm || !articleValidationSummary) {
            return;
        }
        const keys = Object.keys(articleFormErrors.value || {});
        if (!keys.length) {
            return;
        }
        const snapshot = lastSubmittedArticleForm;
        const next = { ...articleFormErrors.value };
        let changed = false;
        for (const key of keys) {
            if (JSON.stringify(newForm[key]) !== JSON.stringify(snapshot[key])) {
                delete next[key];
                changed = true;
            }
        }
        if (changed) {
            articleFormErrors.value = next;
            if (!Object.keys(next).length) {
                adminArticleError.value = '';
                articleValidationSummary = false;
            }
        }
    },
    { deep: true },
);

export async function deleteArticle(id) {
    if (!confirm('آیا از حذف این مقاله مطمئن هستید؟')) {
        return;
    }

    adminArticleError.value = '';
    adminArticleSuccess.value = '';

    try {
        await axios.delete(`/api/admin/articles/${id}`);
        await loadAdminArticles();
        adminArticleSuccess.value = 'مقاله حذف شد.';
    } catch (error) {
        adminArticleError.value = error.response?.data?.message || 'حذف مقاله انجام نشد.';
    }
}

export function goToArticlePage(page) {
    if (page < 1 || page > adminArticleTotalPages.value) return;
    adminArticlePage.value = page;
    loadAdminArticles();
}

export function closeArticleForm() {
    articleEditingId.value = null;
    showArticleEditor.value = false;
    articleForm.value = createEmptyArticleForm();
    adminArticleError.value = '';
    adminArticleSuccess.value = '';
    articleFormErrors.value = {};
    lastSubmittedArticleForm = null;
    articleValidationSummary = false;
}
