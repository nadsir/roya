import { ref } from 'vue';
import axios from 'axios';

export const products = ref([]);
export const categories = ref([]);
export const attributes = ref([]);
export const categoryAttributes = ref({});

export const loading = ref(false);
export const saving = ref(false);
export const editingProductId = ref(null);

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
        variants: [],
        images: [],
    };
}

export const form = ref(createEmptyForm());

export function resetForm() {
    form.value = createEmptyForm();
    editingProductId.value = null;
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

export async function load() {
    loading.value = true;

    try {
        await Promise.all([
            loadProducts(),
            loadMeta(),
        ]);
    } finally {
        loading.value = false;
    }
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
        const response = await axios.post(
            '/api/admin/products',
            form.value
        );

        await loadProducts();

        return response.data;
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
        return;
    }

    saving.value = true;

    try {
        const response = await axios.put(
            `/api/admin/products/${editingProductId.value}`,
            form.value
        );

        await loadProducts();

        return response.data;

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

    await loadProducts();
}