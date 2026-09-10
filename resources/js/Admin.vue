```vue
<script setup>
import {
    ref,
    computed,
    onMounted,
} from 'vue';

import {
    products,
    categories,
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
    reorderProductImages
} from './admin-state';


async function openEditProduct(id) {
    try {
        await loadProduct(id);

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

const errorMessage = ref('');

const successMessage = ref('');

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
        key: 'categories',
        label: 'دسته‌بندی‌ها',
        icon: '◫',
    },
    {
        key: 'attributes',
        label: 'ویژگی‌ها',
        icon: '◇',
    },
    {
        key: 'users',
        label: 'کاربران',
        icon: '◎',
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
    const map = new Map();

    for (const categoryId of form.value.category_ids) {
        const list =
            categoryAttributes.value[categoryId] || [];

        for (const attribute of list) {
            if (!map.has(attribute.id)) {
                map.set(attribute.id, attribute);
            }
        }
    }

    return Array.from(map.values()).sort(
        (a, b) =>
            (a.sort_order ?? 0) -
            (b.sort_order ?? 0)
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

function categoryName(categoryId) {
    for (const category of categories.value) {
        if (category.id === categoryId) {
            return category.name;
        }

        for (const child of category.children || []) {
            if (child.id === categoryId) {
                return child.name;
            }
        }
    }

    return '';
}

function selectedCategoryNames() {
    return form.value.category_ids
        .map(categoryName)
        .filter(Boolean);
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

function changeSection(value) {
    section.value = value;
}

async function submitProduct() {
    errorMessage.value = '';
    successMessage.value = '';

    try {
        let product;

        if (editingProductId.value) {
            product = await updateProduct();
        } else {
            product = await save();

            const productId = product?.id;

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
        }

        successMessage.value =
            'محصول با موفقیت ذخیره شد.';

        clearSelectedImages();

        resetForm();

        showProductModal.value = false;

    } catch (error) {
        imageUploading.value = false;

        console.error(
            'Failed to save product:',
            error
        );

        errorMessage.value =
            error.response?.data?.message ||
            'ذخیره محصول انجام نشد.';
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

onMounted(async () => {
    try {
        await load();
    } catch (error) {
        console.error(error);

        errorMessage.value =
            'دریافت اطلاعات پنل مدیریت انجام نشد.';
    }
});
</script>

<template>
    <div
        dir="rtl"
        class="admin-shell"
    >
        <!-- ================================================= -->
        <!-- SIDEBAR -->
        <!-- ================================================= -->

        <aside class="sidebar">
            <div class="brand">
                ROYA
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
                    ROYA ADMIN · v1.0
                </small>
            </div>
        </aside>

        <!-- ================================================= -->
        <!-- MAIN -->
        <!-- ================================================= -->

        <main class="main">
            <!-- HEADER -->
  <div
        v-if="successMessage"
        class="alert success"
    >
        {{ successMessage }}
    </div>
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
                    <span>مدیر</span>
                </div>
            </header>

            <!-- GLOBAL ERROR -->

            <div
                v-if="errorMessage && !showProductModal"
                class="alert error"
            >
                {{ errorMessage }}
            </div>

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

                                <button
                                    type="button"
                                    class="delete-button"
                                    @click="
                                        deleteProduct(
                                            product.id
                                        )
                                    "
                                >
                                    حذف
                                </button>
                            </div>
                        </div>

                        <div
                            v-else
                            class="empty"
                        >
                            <div class="empty-icon">
                                ◈
                            </div>

                            <h3>
                                هنوز محصولی ندارید
                            </h3>

                            <p>
                                اولین محصول فروشگاه
                                ROYA را اضافه کنید.
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
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    ساختار فروشگاه
                                </p>

                                <h2>
                                    دسته‌بندی‌ها
                                </h2>
                            </div>
                        </div>

                        <div class="category-list">
                            <div
                                v-for="category in categories"
                                :key="category.id"
                                class="category-item"
                            >
                                <div>
                                    <strong>
                                        {{
                                            category.name
                                        }}
                                    </strong>

                                    <small>
                                        /
                                        {{
                                            category.slug
                                        }}
                                    </small>
                                </div>

                                <span>
                                    {{
                                        category.children
                                            ?.length || 0
                                    }}
                                    زیردسته
                                </span>
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
                        </div>

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

                                <span>
                                    {{
                                        attribute.values
                                            ?.length || 0
                                    }}
                                    مقدار
                                </span>
                            </div>
                        </div>
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
                placeholder="مثلاً مانتو مشکی ROYA"
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
                                <div
                                    v-for="category in categories"
                                    :key="
                                        category.id
                                    "
                                    class="category-group"
                                >
                                    <label
                                        class="check-card"
                                    >
                                        <input
                                            v-model="
                                                form.category_ids
                                            "
                                            type="checkbox"
                                            :value="
                                                category.id
                                            "
                                        />

                                        <span>
                                            {{
                                                category.name
                                            }}
                                        </span>
                                    </label>

                                    <div
                                        v-if="
                                            category.children
                                                ?.length
                                        "
                                        class="children"
                                    >
                                        <label
                                            v-for="child in category.children"
                                            :key="
                                                child.id
                                            "
                                            class="check-card child"
                                        >
                                            <input
                                                v-model="
                                                    form.category_ids
                                                "
                                                type="checkbox"
                                                :value="
                                                    child.id
                                                "
                                            />

                                            <span>
                                                {{
                                                    child.name
                                                }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
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
                                        v-if="
                                            attribute.values
                                                ?.length
                                        "
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
                                    </div>

                                    <div
                                        v-if="
                                            selectedAttributes.length
                                        "
                                        class="variant-attributes"
                                    >
                                        <div
                                            v-for="attribute in selectedAttributes"
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

                                    <small>
                                        اگر محصول سایز، رنگ یا
                                        ترکیب متفاوت دارد، Variant
                                        اضافه کنید.
                                    </small>
                                </div>
                            </div>
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

@media (max-width: 700px) {
    .selected-images-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

}
</style>
```

### بعد از جایگزینی

در ترمینال:

```powershell
npm run dev
```

اگر Vite از قبل در حال اجراست، اول:

```powershell
Ctrl + C
```

و دوباره:

```powershell
npm run dev
```

بعد برو:

```text
http://127.0.0.1:8000/admin
```

### یک نکته مهم

در این مرحله اگر صفحه باز شد، **هنوز هیچ محصولی ایجاد نکن** تا اول خود پنل را بررسی کنیم.

باید بتوانی:

**محصولات → افزودن محصول**

را بزنی و فرم را ببینی.

همچنین قسمت ویژگی‌ها باید با انتخاب دسته‌بندی، ویژگی‌های مربوط به همان دسته را نشان دهد؛ این همان معماری‌ای است که برای مثال باعث می‌شود:

* لباس → سایز، رنگ، جنس
* کفش → سایز کفش، رنگ، جنس
* عینک → نوع عینک، جنس فریم، رنگ
* کیف → نوع کیف، جنس، رنگ

از هم تفکیک شوند.

اگر این نسخه **بدون خطا بالا آمد**، مرحله بعدی را روی همین کد می‌سازیم: **ویرایش محصول + API `show/update`**؛ بعد می‌رویم سراغ مدیریت کامل دسته‌بندی و Attributeها.
