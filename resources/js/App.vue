<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';

/*
|--------------------------------------------------------------------------
| Temporary Products
|--------------------------------------------------------------------------
| فعلاً برای تست UI نگه داشته شده‌اند.
| در مرحله بعد با /api/products جایگزین می‌شوند.
*/

const products = ref([]);

/*
|--------------------------------------------------------------------------
| Config
|--------------------------------------------------------------------------
*/

const categoryNames = {
    clothing: 'لباس',
    bags: 'کیف',
    shoes: 'کفش',
    glasses: 'عینک',
    accessories: 'اکسسوری',
};

const subcategoryNames = {
    mantos: 'مانتو',
    shirts: 'شومیز',
    'evening-dresses': 'لباس مجلسی',
    'casual-dresses': 'لباس روزمره',

    handbags: 'کیف دستی',
    'shoulder-bags': 'کیف دوشی',
    'evening-bags': 'کیف مجلسی',

    'women-shoes': 'کفش زنانه',
    sandals: 'صندل',
    boots: 'بوت',

    'optical-glasses': 'عینک طبی',
    sunglasses: 'عینک آفتابی',

    jewelry: 'زیورآلات',
    watches: 'ساعت',
    'other-accessories': 'سایر',
};

const materialNames = {
    linen: 'لینن',
    cotton: 'نخ',
    silk: 'ابریشم',
    satin: 'ساتن',
    wool: 'پشم',
    leather: 'چرم',
    canvas: 'برزنت',
    acetate: 'استات',
    metal: 'فلز',
};

const colorNames = {
    black: 'مشکی',
    cream: 'کرم',
    pink: 'صورتی',
    brown: 'قهوه‌ای',
    white: 'سفید',
};

const categoryDescription = {
    clothing:
        'انتخابی از لباس‌های NOORÉ برای استایل روزمره و لحظه‌های خاص.',
    bags:
        'کیف‌های منتخب NOORÉ برای کامل کردن استایل شما.',
    shoes:
        'کفش‌هایی با فرم ظریف و طراحی ماندگار برای هر استایل.',
    glasses:
        'عینک‌های منتخب NOORÉ برای تکمیل ظاهر شما.',
    accessories:
        'اکسسوری‌های ظریف برای ساختن جزئیات متفاوت در استایل.',
};

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const state = reactive({
    categories: [],
    subcategories: [],
    size: null,
    color: null,
    material: null,
    frameMaterial: null,
    maxPrice: 5000000,
    stock: false,
    sort: 'popular',
    visibleCount: 16,
    bagType: null,
});
const apiCategories = ref([]);
const categoryFilters = ref([]);
const mobileFiltersOpen = ref(false);
const mobileNavOpen = ref(false);
const isDark = ref(false);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const faNumber = (number) =>
    Number(number).toLocaleString('fa-IR');

const formatPrice = (price) =>
    `${faNumber(price)} تومان`;

const activeCategory = computed(() => {
    return state.categories.length === 1
        ? state.categories[0]
        : null;
});

const filteredProducts = computed(() => {
    let result = [...products.value];

if (state.categories.length) {
    result = result.filter((product) =>
        product.categories?.some((productCategory) => {
            return state.categories.some((selectedSlug) => {
                if (productCategory.slug === selectedSlug) {
                    return true;
                }

                const parentCategory = apiCategories.value.find(
                    (category) =>
                        category.slug === selectedSlug
                );

                return parentCategory?.children?.some(
                    (child) =>
                        child.slug === productCategory.slug
                );
            });
        })
    );
}
if (state.subcategories.length) {
    result = result.filter((product) =>
        product.categories?.some((productCategory) =>
            state.subcategories.includes(productCategory.slug)
        )
    );
}

 if (state.size) {
    result = result.filter(
        (product) =>
            product.attributes?.size?.some(
                (item) => item.value === state.size
            ) ||
            product.attributes?.['shoe-size']?.some(
                (item) => item.value === state.size
            )
    );
}

 if (state.color) {
    result = result.filter(
        (product) =>
            product.attributes?.color?.some(
                (item) => item.value === state.color
            )
    );
}

   if (state.material) {
    result = result.filter(
        (product) =>
            product.attributes?.material?.some(
                (item) => item.value === state.material
            )
    );
}
if (state.bagType) {
    result = result.filter(
        (product) =>
            product.attributes?.['bag-type']?.some(
                (item) => item.value === state.bagType
            )
    );
}

    result = result.filter(
        (product) => product.price <= state.maxPrice
    );

if (state.stock) {
    result = result.filter(
        (product) => product.in_stock
    );
}

    if (state.sort === 'cheap') {
        result.sort((a, b) => a.price - b.price);
    } else if (state.sort === 'expensive') {
        result.sort((a, b) => b.price - a.price);
    } else if (state.sort === 'newest') {
        result.reverse();
    } else {
        result.sort(
            (a, b) => b.popularity - a.popularity
        );
    }

    return result;
});

const visibleProducts = computed(() =>
    filteredProducts.value.slice(
        0,
        state.visibleCount
    )
);

const availableSubcategories = computed(() => {
    if (!state.categories.length) {
        return [];
    }

    const children = [];

    state.categories.forEach((selectedSlug) => {
        const category = apiCategories.value.find(
            (item) => item.slug === selectedSlug
        );

        if (category?.children?.length) {
            children.push(
                ...category.children.map(
                    (child) => child.slug
                )
            );
        }
    });

    return [...new Set(children)];
});

const availableMaterials = computed(() => {
    if (!state.categories.length) {
        return [];
    }
        if (
        state.categories.length === 1 &&
        state.categories[0] === 'glasses'
    ) {
        return [];
    }

    const filter = categoryFilters.value.find(
        (item) => item.slug === 'material'
    );

    if (!filter?.values?.length) {
        return [];
    }

    let categoryProducts = products.value.filter((product) =>
        product.categories?.some((productCategory) =>
            state.categories.some((selectedSlug) => {

                if (productCategory.slug === selectedSlug) {
                    return true;
                }

                const parentCategory = apiCategories.value.find(
                    (category) =>
                        category.slug === selectedSlug
                );

                return parentCategory?.children?.some(
                    (child) =>
                        child.slug === productCategory.slug
                );
            })
        )
    );

    if (state.subcategories.length) {
        categoryProducts = categoryProducts.filter((product) =>
            product.categories?.some((productCategory) =>
                state.subcategories.includes(
                    productCategory.slug
                )
            )
        );
    }

    const usedMaterialValues = new Set();

    categoryProducts.forEach((product) => {
        product.attributes?.material?.forEach((material) => {
            usedMaterialValues.add(material.value);
        });
    });

    return filter.values.filter((material) =>
        usedMaterialValues.has(material.value)
    );
});

const availableFrameMaterials = computed(() => {
    if (!state.categories.length) {
        return [];
    }

    const filter = categoryFilters.value.find(
        (item) => item.slug === 'frame-material'
    );

    if (!filter?.values?.length) {
        return [];
    }

    let categoryProducts = products.value.filter((product) =>
        product.categories?.some((productCategory) =>
            state.categories.some((selectedSlug) => {

                if (productCategory.slug === selectedSlug) {
                    return true;
                }

                const parentCategory = apiCategories.value.find(
                    (category) =>
                        category.slug === selectedSlug
                );

                return parentCategory?.children?.some(
                    (child) =>
                        child.slug === productCategory.slug
                );
            })
        )
    );

    if (state.subcategories.length) {
        categoryProducts = categoryProducts.filter((product) =>
            product.categories?.some((productCategory) =>
                state.subcategories.includes(
                    productCategory.slug
                )
            )
        );
    }

    const usedValues = new Set();

    categoryProducts.forEach((product) => {
        product.attributes?.['frame-material']?.forEach(
            (item) => {
                usedValues.add(item.value);
            }
        );
    });

    return filter.values.filter((item) =>
        usedValues.has(item.value)
    );
});

const availableColors = computed(() => {
    if (!state.categories.length) {
        return [];
    }

    const filter = categoryFilters.value.find(
        (item) => item.slug === 'color'
    );

    if (!filter?.values?.length) {
        return [];
    }

    let categoryProducts = products.value.filter((product) =>
        product.categories?.some((productCategory) =>
            state.categories.some((selectedSlug) => {

                if (productCategory.slug === selectedSlug) {
                    return true;
                }

                const parentCategory = apiCategories.value.find(
                    (category) =>
                        category.slug === selectedSlug
                );

                return parentCategory?.children?.some(
                    (child) =>
                        child.slug === productCategory.slug
                );
            })
        )
    );

    if (state.subcategories.length) {
        categoryProducts = categoryProducts.filter((product) =>
            product.categories?.some((productCategory) =>
                state.subcategories.includes(
                    productCategory.slug
                )
            )
        );
    }

    const usedColorValues = new Set();

    categoryProducts.forEach((product) => {
        product.attributes?.color?.forEach((color) => {
            usedColorValues.add(color.value);
        });
    });

    return filter.values.filter((color) =>
        usedColorValues.has(color.value)
    );
});

const availableSizes = computed(() => {
    if (!state.categories.length) {
        return [];
    }

    let categoryProducts = products.value.filter((product) =>
        product.categories?.some((productCategory) =>
            state.categories.some((selectedSlug) => {

                if (productCategory.slug === selectedSlug) {
                    return true;
                }

                const parentCategory = apiCategories.value.find(
                    (category) =>
                        category.slug === selectedSlug
                );

                return parentCategory?.children?.some(
                    (child) =>
                        child.slug === productCategory.slug
                );
            })
        )
    );

    if (state.subcategories.length) {
        categoryProducts = categoryProducts.filter((product) =>
            product.categories?.some((productCategory) =>
                state.subcategories.includes(
                    productCategory.slug
                )
            )
        );
    }

    const usedSizeValues = new Set();

    categoryProducts.forEach((product) => {

        product.attributes?.size?.forEach((size) => {
            usedSizeValues.add(size.value);
        });

        product.attributes?.['shoe-size']?.forEach((size) => {
            usedSizeValues.add(size.value);
        });

    });

    const filter = categoryFilters.value.find(
        (item) =>
            item.slug === 'size' ||
            item.slug === 'shoe-size'
    );

    if (!filter?.values?.length) {
        return [];
    }

    return filter.values
        .filter((size) =>
            usedSizeValues.has(size.value)
        )
        .map((size) => size.value);
});

const availableBagTypes = computed(() => {
    if (!state.categories.length) {
        return [];
    }

    let categoryProducts = products.value.filter((product) =>
        product.categories?.some((productCategory) =>
            state.categories.some((selectedSlug) => {

                if (productCategory.slug === selectedSlug) {
                    return true;
                }

                const parentCategory = apiCategories.value.find(
                    (category) =>
                        category.slug === selectedSlug
                );

                return parentCategory?.children?.some(
                    (child) =>
                        child.slug === productCategory.slug
                );
            })
        )
    );

    if (state.subcategories.length) {
        categoryProducts = categoryProducts.filter((product) =>
            product.categories?.some((productCategory) =>
                state.subcategories.includes(
                    productCategory.slug
                )
            )
        );
    }

    const usedValues = new Set();

    categoryProducts.forEach((product) => {
        product.attributes?.['bag-type']?.forEach((item) => {
            usedValues.add(item.value);
        });
    });

    const filter = categoryFilters.value.find(
        (item) => item.slug === 'bag-type'
    );
    console.log(
    'BAG TYPE FILTER:',
    JSON.stringify(filter, null, 2)
);

console.log(
    'BAG TYPE USED VALUES:',
    [...usedValues]
);

    if (!filter?.values?.length) {
        return [];
    }

    return filter.values.filter((item) =>
        usedValues.has(item.value)
    );
});

const categoryCounts = computed(() => {
    const counts = {};

    apiCategories.value.forEach((category) => {
        const categorySlugs = [
            category.slug,
            ...(category.children || []).map(
                (child) => child.slug
            ),
        ];

        counts[category.slug] = products.value.filter(
            (product) =>
                product.categories?.some(
                    (productCategory) =>
                        categorySlugs.includes(
                            productCategory.slug
                        )
                )
        ).length;
    });

    return counts;
});

const productCount = computed(
    () => filteredProducts.value.length
);

const activeFilters = computed(() => {
    const filters = [];

    state.categories.forEach((category) => {
        filters.push({
            type: 'category',
            value: category,
            label: categoryNames[category],
        });
    });

    state.subcategories.forEach((subcategory) => {
        filters.push({
            type: 'subcategory',
            value: subcategory,
            label:
                subcategoryNames[subcategory] ||
                subcategory,
        });
    });

    if (state.size) {
        filters.push({
            type: 'size',
            value: state.size,
            label: `سایز ${state.size}`,
        });
    }

    if (state.color) {
        filters.push({
            type: 'color',
            value: state.color,
            label: colorNames[state.color],
        });
    }

    if (state.material) {
        filters.push({
            type: 'material',
            value: state.material,
            label: materialNames[state.material],
        });
    }

if (state.frameMaterial) {
    const frameMaterialFilter = categoryFilters.value.find(
        (item) => item.slug === 'frame-material'
    );

    const frameMaterial = frameMaterialFilter?.values?.find(
        (item) => item.value === state.frameMaterial
    );

    filters.push({
        type: 'frameMaterial',
        value: state.frameMaterial,
        label:
            frameMaterial?.label ||
            state.frameMaterial,
    });
}

    if (state.bagType) {
    const bagTypeFilter = categoryFilters.value.find(
        (item) => item.slug === 'bag-type'
    );

    const bagType = bagTypeFilter?.values?.find(
        (item) => item.value === state.bagType
    );

    filters.push({
        type: 'bagType',
        value: state.bagType,
        label: bagType?.label || state.bagType,
    });
}

    if (state.maxPrice < 5000000) {
        filters.push({
            type: 'price',
            value: state.maxPrice,
            label:
                `تا ${faNumber(state.maxPrice)}`,
        });
    }

    if (state.stock) {
        filters.push({
            type: 'stock',
            value: true,
            label: 'فقط موجود',
        });
    }

    return filters;
});

const pageTitle = computed(() => {
    if (!state.categories.length) {
        return 'فروشگاه NOORÉ';
    }

    return (
        state.categories
            .map(
                (category) =>
                    categoryNames[category]
            )
            .join('، ') + ' NOORÉ'
    );
});

const pageDescription = computed(() => {
    if (!state.categories.length) {
        return 'از لباس، کیف، کفش، عینک و اکسسوری‌های NOORÉ انتخاب کنید و استایل شخصی خود را بسازید.';
    }

    if (state.categories.length === 1) {
        return categoryDescription[
            state.categories[0]
        ];
    }

    return 'ترکیبی از محصولات منتخب NOORÉ را بر اساس سلیقه، رنگ، سایز، جنس و بودجه خود انتخاب کنید.';
});

const shoeSelected = computed(() => {
    return (
        state.categories.length === 1 &&
        state.categories[0] === 'shoes'
    );
});

const filterCount = computed(
    () => activeFilters.value.length
);

/*
|--------------------------------------------------------------------------
| Filter Methods
|--------------------------------------------------------------------------
*/

function toggleCategory(category) {
    const index =
        state.categories.indexOf(category);

    if (index === -1) {
        state.categories.push(category);
    } else {
        state.categories.splice(index, 1);
    }

    if (state.categories.length === 1) {
        loadCategoryFilters(state.categories[0]);
    } else {
        categoryFilters.value = [];
    }

    cleanInvalidFilters();
    state.visibleCount = 16;
}

function selectCategory(category) {
    state.categories = [category];

    categoryFilters.value = [];

    state.subcategories = [];
    state.size = null;
    state.color = null;
    state.material = null;
    state.bagType = null;
    state.visibleCount = 16;

    loadCategoryFilters(category);

    mobileNavOpen.value = false;

    window.scrollTo({
        top: 0,
        behavior: 'smooth',
    });
}

function toggleSubcategory(subcategory) {
    const index =
        state.subcategories.indexOf(
            subcategory
        );

    if (index === -1) {
        state.subcategories.push(
            subcategory
        );
    } else {
        state.subcategories.splice(index, 1);
    }

    state.visibleCount = 16;
}

function selectSize(size) {
    state.size =
        state.size === size
            ? null
            : size;

    state.visibleCount = 16;
}

function selectMaterial(material) {
    console.log('SELECTED MATERIAL:', material);

    state.material =
        state.material === material
            ? null
            : material;

    state.visibleCount = 16;
}
function selectFrameMaterial(frameMaterial) {
    state.frameMaterial =
        state.frameMaterial === frameMaterial
            ? null
            : frameMaterial;

    state.visibleCount = 16;
}
function selectBagType(bagType) {
    state.bagType =
        state.bagType === bagType
            ? null
            : bagType;

    state.visibleCount = 16;
}

function selectColor(color) {
    state.color =
        state.color === color
            ? null
            : color;

    state.visibleCount = 16;
}

function removeFilter(filter) {
    switch (filter.type) {
        case 'category':
            state.categories =
                state.categories.filter(
                    (item) =>
                        item !== filter.value
                );
            break;

        case 'subcategory':
            state.subcategories =
                state.subcategories.filter(
                    (item) =>
                        item !== filter.value
                );
            break;

        case 'size':
            state.size = null;
            break;

        case 'color':
            state.color = null;
            break;

        case 'material':
            state.material = null;
            break;

        case 'bagType':
            state.bagType = null;
            break;

        case 'price':
            state.maxPrice = 5000000;
            break;

        case 'stock':
            state.stock = false;
            break;
    }

    state.visibleCount = 16;
}

function cleanInvalidFilters() {
    state.subcategories =
        state.subcategories.filter(
            (item) =>
                availableSubcategories.value.includes(
                    item
                )
        );

if (
    state.material &&
    !availableMaterials.value.some(
        (item) =>
            item.value === state.material
    )
) {
    state.material = null;
}

if (
    state.bagType &&
    !availableBagTypes.value.some(
        (item) =>
            item.value === state.bagType
    )
) {
    state.bagType = null;
}

    if (
        state.size &&
        !availableSizes.value.includes(
            state.size
        )
    ) {
        state.size = null;
    }
}

function clearAllFilters() {
    state.categories = [];
    state.subcategories = [];
    state.size = null;
    state.color = null;
    state.material = null;
    state.bagType = null;
    state.maxPrice = 5000000;
    state.stock = false;
    state.visibleCount = 16;
}

function loadMore() {
    state.visibleCount += 8;
}

function openFilters() {
    mobileFiltersOpen.value = true;
    document.body.classList.add(
        'overflow-hidden'
    );
}

function closeFilters() {
    mobileFiltersOpen.value = false;
    document.body.classList.remove(
        'overflow-hidden'
    );
}

function toggleMobileNav() {
    mobileNavOpen.value =
        !mobileNavOpen.value;
}

function toggleTheme() {
    isDark.value = !isDark.value;

    document.documentElement.classList.toggle(
        'dark',
        isDark.value
    );

    localStorage.setItem(
        'theme',
        isDark.value ? 'dark' : 'light'
    );
}

function productDescription(product) {
    const categorySlug = product.categories?.[0]?.slug;

    switch (categorySlug) {
        case 'mantos':
        case 'shirts':
        case 'evening-dresses':
        case 'casual-dresses':
            return 'Elegant wardrobe';

        case 'handbags':
        case 'shoulder-bags':
        case 'evening-bags':
            return 'Essential bags';

        case 'women-shoes':
        case 'sandals':
        case 'boots':
            return 'Modern footwear';

        case 'sunglasses':
        case 'optical-glasses':
            return 'The eyewear edit';

        default:
            return 'Finishing details';
    }
}

function primaryImage(product) {
    const image =
        product.images?.find(image => image.is_primary)?.path
        || product.images?.[0]?.path
        || '';

    return image ? `/storage/${image}` : '';
}

/*
|--------------------------------------------------------------------------
| Watchers
|--------------------------------------------------------------------------
*/

watch(
    () => state.categories,
    () => {
        cleanInvalidFilters();
    },
    { deep: true }
);
async function loadProducts() {
    try {
        const response = await axios.get('/api/products');

        products.value = response.data.data;
        console.log(
    'SHOE PRODUCT:',
    JSON.stringify(
        products.value.find(
            product => product.name === 'کفش زنانه کلاسیک'
        ),
        null,
        2
    )
);

        console.log('PRODUCT API RAW:', response.data);
        console.log('PRODUCT COUNT:', response.data.data?.length);

        // بقیه کدهای قبلی...
    } catch (error) {
        console.error('API ERROR:', error);
    }
}
async function loadCategories() {
    try {
        const response = await axios.get('/api/categories');

        apiCategories.value = response.data.data;

        console.log(
            'API CATEGORIES:',
            apiCategories.value
        );
    } catch (error) {
        console.error(
            'CATEGORIES API ERROR:',
            error
        );
    }
}
async function loadCategoryFilters(categorySlug) {
    try {
        const response = await axios.get(
            `http://127.0.0.1:8000/api/categories/${categorySlug}/filters`
        );

        categoryFilters.value = response.data.data;
        console.log(
    'AVAILABLE BAG TYPES AFTER API:',
    availableBagTypes.value
);

       console.log(
    'CATEGORY FILTERS:',
    JSON.stringify(categoryFilters.value, null, 2)
);
    } catch (error) {
        console.error(
            'CATEGORY FILTERS API ERROR:',
            error
        );
    }
}

onMounted(() => {
    console.log('APP VUE IS RUNNING');
    const savedTheme =
        localStorage.getItem('theme');

    if (savedTheme === 'dark') {
        isDark.value = true;

        document.documentElement.classList.add(
            'dark'
        );
    }
    loadProducts();
    loadCategories();
     console.log(
        'TEST BAG TYPES:',
        availableBagTypes.value
    );
});
</script>

<template>
    <div
        class="min-h-screen bg-cream text-ink transition-colors duration-300 dark:bg-[#1c1721] dark:text-white"
    >

        <!-- =====================================================
             ANNOUNCEMENT
        ====================================================== -->

        <div
            class="bg-ink px-4 py-2 text-center text-[11px] tracking-wide text-white"
        >
            ارسال رایگان برای سفارش‌های بالای ۲ میلیون تومان

            <span class="mx-2 opacity-30">·</span>

            تعویض آسان تا ۷ روز
        </div>


        <!-- =====================================================
             HEADER
        ====================================================== -->

        <header
            class="sticky top-0 z-40 border-b border-black/5 bg-cream/90 backdrop-blur-xl dark:border-white/10 dark:bg-[#1c1721]/90"
        >

            <div
                class="mx-auto flex h-[76px] max-w-7xl items-center justify-between px-5 lg:px-8"
            >

                <!-- Mobile Menu -->

                <button
                    type="button"
                    class="rounded-full p-2 hover:bg-black/5 dark:hover:bg-white/10 lg:hidden"
                    aria-label="منو"
                    @click="toggleMobileNav"
                >
                    ☰
                </button>


                <!-- Logo -->

                <a
                    href="#"
                    class="flex items-center gap-3"
                >
                    <span
                        class="serif text-3xl tracking-wide"
                    >
                        NOORÉ
                    </span>

                    <span
                        class="hidden text-[9px] tracking-[.3em] text-plum dark:text-rose sm:block"
                    >
                        WOMEN'S GALLERY
                    </span>
                </a>


                <!-- Desktop Nav -->

                <nav
                    class="hidden items-center gap-8 text-sm lg:flex"
                >

                    <a
                        href="#"
                        class="hover:text-plum"
                    >
                        جدیدها
                    </a>

                    <a
                        href="#"
                        class="font-semibold text-plum dark:text-rose"
                        @click.prevent="
                            selectCategory('clothing')
                        "
                    >
                        لباس
                    </a>

                    <a
                        href="#"
                        class="hover:text-plum"
                        @click.prevent="
                            selectCategory('bags')
                        "
                    >
                        کیف
                    </a>

                    <a
                        href="#"
                        class="hover:text-plum"
                        @click.prevent="
                            selectCategory('shoes')
                        "
                    >
                        کفش
                    </a>

                    <a
                        href="#"
                        class="hover:text-plum"
                        @click.prevent="
                            selectCategory('glasses')
                        "
                    >
                        عینک
                    </a>

                    <a
                        href="#"
                        class="hover:text-plum"
                        @click.prevent="
                            selectCategory('accessory')
                        "
                    >
                        اکسسوری
                    </a>

                </nav>


                <!-- Actions -->

                <div class="flex items-center gap-1">

                    <button
                        type="button"
                        class="rounded-full p-2.5 hover:bg-black/5 dark:hover:bg-white/10"
                        aria-label="تغییر تم"
                        @click="toggleTheme"
                    >
                        {{ isDark ? '☾' : '☼' }}
                    </button>


                    <button
                        type="button"
                        class="relative rounded-full p-2.5 hover:bg-black/5 dark:hover:bg-white/10"
                    >
                        ♡

                        <span
                            class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-plum text-[9px] text-white"
                        >
                            2
                        </span>
                    </button>


                    <button
                        type="button"
                        class="rounded-full bg-ink px-4 py-2 text-xs text-white dark:bg-white dark:text-ink"
                    >
                        ورود
                    </button>

                </div>

            </div>


            <!-- Mobile Navigation -->

            <div
                v-if="mobileNavOpen"
                class="border-t border-black/5 px-5 py-5 dark:border-white/10 lg:hidden"
            >

                <div class="grid gap-4 text-sm">

                    <a href="#">
                        جدیدها
                    </a>

                    <a
                        href="#"
                        @click.prevent="
                            selectCategory('clothing')
                        "
                    >
                        لباس
                    </a>

                    <a
                        href="#"
                        @click.prevent="
                            selectCategory('bags')
                        "
                    >
                        کیف
                    </a>

                    <a
                        href="#"
                        @click.prevent="
                            selectCategory('shoes')
                        "
                    >
                        کفش
                    </a>

                    <a
                        href="#"
                        @click.prevent="
                            selectCategory('glasses')
                        "
                    >
                        عینک
                    </a>

                    <a
                        href="#"
                        @click.prevent="
                            selectCategory('accessories')
                        "
                    >
                        اکسسوری
                    </a>

                </div>

            </div>

        </header>


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <section
            class="border-b border-black/5 dark:border-white/10"
        >

            <div
                class="mx-auto max-w-7xl px-5 py-12 lg:px-8 lg:py-16"
            >

                <div
                    class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between"
                >

                    <div>

                        <p
                            class="mb-3 text-[10px] font-semibold tracking-[.3em] text-plum dark:text-rose"
                        >
                            THE NOORÉ COLLECTION
                        </p>

                        <h1
                            class="serif text-5xl leading-none md:text-6xl"
                        >
                            {{ pageTitle }}
                        </h1>

                        <p
                            class="mt-5 max-w-xl text-sm leading-8 text-black/50 dark:text-white/50"
                        >
                            {{ pageDescription }}
                        </p>

                    </div>

                    <div
                        class="text-sm text-black/45 dark:text-white/45"
                    >
                        {{ faNumber(productCount) }}
                        محصول
                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SHOP TOOLBAR
        ====================================================== -->

        <section
            class="sticky top-[76px] z-30 border-b border-black/5 bg-cream/95 backdrop-blur-xl dark:border-white/10 dark:bg-[#1c1721]/95"
        >

            <div
                class="mx-auto max-w-7xl px-5 lg:px-8"
            >

                <div
                    class="flex h-[68px] items-center justify-between gap-4"
                >

                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full bg-ink px-5 py-2.5 text-xs text-white lg:hidden dark:bg-white dark:text-ink"
                        @click="openFilters"
                    >
                        ☷
                        فیلترها

                        <span
                            v-if="filterCount"
                            class="rounded-full bg-rose px-2 py-0.5 text-[9px] text-ink"
                        >
                            {{ faNumber(filterCount) }}
                        </span>
                    </button>


                    <div
                        class="hidden text-sm font-semibold lg:block"
                    >
                        فیلتر محصولات
                    </div>


                    <!-- Active Filters -->

                    <div
                        class="no-scrollbar hidden flex-1 items-center gap-2 overflow-x-auto px-2 md:flex"
                    >

                        <span
                            v-for="filter in activeFilters"
                            :key="
                                `${filter.type}-${filter.value}`
                            "
                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-blush px-3 py-2 text-[9px] text-plum dark:bg-white/10 dark:text-rose"
                        >

                            {{ filter.label }}

                            <button
                                type="button"
                                class="mr-1 opacity-60 hover:opacity-100"
                                @click="
                                    removeFilter(filter)
                                "
                            >
                                ×
                            </button>

                        </span>

                    </div>


                    <div class="relative">

                        <select
                            v-model="state.sort"
                            class="appearance-none rounded-full border border-black/10 bg-transparent py-2.5 pl-10 pr-4 text-xs outline-none dark:border-white/10"
                        >
                            <option value="popular">
                                محبوب‌ترین
                            </option>

                            <option value="newest">
                                جدیدترین
                            </option>

                            <option value="cheap">
                                ارزان‌ترین
                            </option>

                            <option value="expensive">
                                گران‌ترین
                            </option>
                        </select>

                        <span
                            class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2"
                        >
                            ↕
                        </span>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SHOP
        ====================================================== -->

        <main
            class="mx-auto max-w-7xl px-5 py-8 lg:px-8"
        >

            <div
                class="grid gap-8 lg:grid-cols-[250px_minmax(0,1fr)]"
            >

                <!-- =================================================
                     DESKTOP FILTER
                ================================================== -->

                <aside class="hidden lg:block">

                    <div
                        class="sticky top-[160px] max-h-[calc(100vh-180px)] overflow-y-auto pr-1 filter-scroll"
                    >

                        <div
                            class="mb-7 flex items-center justify-between"
                        >

                            <h2
                                class="text-sm font-semibold"
                            >
                                فیلترها
                            </h2>

                            <button
                                type="button"
                                class="text-[11px] text-plum underline underline-offset-4 dark:text-rose"
                                @click="
                                    clearAllFilters()
                                "
                            >
                                پاک کردن همه
                            </button>

                        </div>


                        <!-- Categories -->

                        <div
                            class="border-b border-black/10 pb-6 dark:border-white/10"
                        >

                            <h3
                                class="mb-4 text-xs font-semibold"
                            >
                                دسته‌بندی
                            </h3>

                            <div
                                class="space-y-3 text-xs"
                            >

                                <label
                                    v-for="
                                        category in apiCategories
                                    "
                                    :key="category.id"
                                    class="flex cursor-pointer items-center justify-between"
                                >

                                    <span
                                        class="flex items-center gap-2"
                                    >

                                        <input
                                            type="checkbox"
                                            class="check"
                                            :checked="
                                                state.categories.includes(
                                                    category.slug
                                                )
                                            "
                                            @change="
                                                toggleCategory(
                                                    category.slug
                                                )
                                            "
                                        />

                                        {{ category.name }}

                                    </span>

                                    <span
                                        class="text-black/30 dark:text-white/30"
                                    >
                                       {{
    faNumber(
        categoryCounts[
            category.slug
        ] ?? 0
    )
}}
                                    </span>

                                </label>

                            </div>

                        </div>


                        <!-- Subcategories -->

                        <div
                            v-if="
                                availableSubcategories.length
                            "
                            class="border-b border-black/10 py-6 dark:border-white/10"
                        >

                            <h3
                                class="mb-4 text-xs font-semibold"
                            >
                                {{
                                    activeCategory
                                        ? `نوع ${categoryNames[activeCategory]}`
                                        : 'نوع محصول'
                                }}
                            </h3>

                            <div
                                class="flex flex-wrap gap-2"
                            >

                                <button
                                    v-for="
                                        option in availableSubcategories
                                    "
                                    :key="option"
                                    type="button"
                                    class="filter-option rounded-full border border-black/10 px-3 py-2 text-[10px] dark:border-white/10"
                                    :class="{
                                        active:
                                            state.subcategories.includes(
                                                option
                                            ),
                                    }"
                                    @click="
                                        toggleSubcategory(
                                            option
                                        )
                                    "
                                >
                                    {{
                                        subcategoryNames[
                                            option
                                        ] || option
                                    }}
                                </button>

                            </div>

                        </div>


                        <!-- Price -->

                        <div
                            class="border-b border-black/10 py-6 dark:border-white/10"
                        >

                            <div
                                class="mb-4 flex items-center justify-between"
                            >

                                <h3
                                    class="text-xs font-semibold"
                                >
                                    محدوده قیمت
                                </h3>

                                <span
                                    class="text-[10px] text-plum dark:text-rose"
                                >
                                    تا
                                    {{
                                        faNumber(
                                            state.maxPrice
                                        )
                                    }}
                                </span>

                            </div>

                            <input
                                v-model.number="
                                    state.maxPrice
                                "
                                type="range"
                                min="500000"
                                max="5000000"
                                step="100000"
                                class="price-range w-full"
                            />

                            <div
                                class="mt-3 flex justify-between text-[10px] text-black/35 dark:text-white/35"
                            >
                                <span>
                                    ۵۰۰ هزار
                                </span>

                                <span>
                                    ۵ میلیون
                                </span>
                            </div>

                        </div>


                        <!-- Sizes -->

                        <div
                            v-if="availableSizes.length"
                            class="border-b border-black/10 py-6 dark:border-white/10"
                        >

                            <h3
                                class="mb-4 text-xs font-semibold"
                            >
                                {{
                                    shoeSelected
                                        ? 'سایز کفش'
                                        : 'سایز'
                                }}
                            </h3>

                            <div
                                class="flex flex-wrap gap-2"
                            >

                                <button
                                    v-for="
                                        size in availableSizes
                                    "
                                    :key="size"
                                    type="button"
                                    class="filter-option rounded-full border border-black/10 px-4 py-2 text-[10px] dark:border-white/10"
                                    :class="{
                                        active:
                                            state.size ===
                                            size,
                                    }"
                                    @click="
                                        selectSize(size)
                                    "
                                >
                                    {{ size }}
                                </button>

                            </div>

                        </div>


                        <!-- Material -->

                        <div
                            v-if="availableMaterials.length"
                            class="border-b border-black/10 py-6 dark:border-white/10"
                        >

                            <h3
                                class="mb-4 text-xs font-semibold"
                            >
                                جنس
                            </h3>

                            <div
                                class="flex flex-wrap gap-2"
                            >

                                <button
                                    v-for="
                                        material in availableMaterials
                                    "
                                    :key="material.id"
                                    type="button"
                                    class="filter-option rounded-full border border-black/10 px-3 py-2 text-[10px] dark:border-white/10"
                                    :class="{
                                        active:
                                            state.material ===
                                            material.value,
                                    }"
                                    @click="
                                        selectMaterial(
                                            material.value
                                        )
                                    "
                                >
                                    {{ material.label }}
                                </button>

                            </div>

                        </div>

                        <!-- Frame Material -->

<div
    v-if="availableFrameMaterials.length"
    class="border-b border-black/10 py-6 dark:border-white/10"
>
    <h3
        class="mb-4 text-xs font-semibold"
    >
        جنس فریم
    </h3>

    <div
        class="flex flex-wrap gap-2"
    >
        <button
            v-for="frameMaterial in availableFrameMaterials"
            :key="`frame-material-${frameMaterial.id}`"
            type="button"
            class="filter-option rounded-full border border-black/10 px-3 py-2 text-[10px] dark:border-white/10"
            :class="{
                active:
                    state.frameMaterial ===
                    frameMaterial.value,
            }"
            @click="
                selectFrameMaterial(
                    frameMaterial.value
                )
            "
        >
            {{ frameMaterial.label }}
        </button>
    </div>
</div>

                <!-- Bag Type -->

                <div
                    v-if="availableBagTypes.length"
                    class="border-b border-black/10 py-6 dark:border-white/10"
                >

                    <h3
                        class="mb-4 text-xs font-semibold"
                    >
                        نوع کیف
                    </h3>

                    <div
                        class="flex flex-wrap gap-2"
                    >

                        <button
                            v-for="bagType in availableBagTypes"
                           
                            type="button"
                            class="filter-option rounded-full border border-black/10 px-3 py-2 text-[10px] dark:border-white/10"
                            :class="{
                                active:
                                    state.bagType ===
                                    bagType.value,
                            }"
                            @click="
                                selectBagType(
                                    bagType.value
                                )
                            "
                        >
                            {{ bagType.label }}
                        </button>

                    </div>

                </div>
                        <!-- Colors -->

                        <div
                            class="border-b border-black/10 py-6 dark:border-white/10"
                        >

                            <h3
                                class="mb-4 text-xs font-semibold"
                            >
                                رنگ
                            </h3>

                            <div class="flex flex-wrap gap-3">

    <button
        v-for="color in availableColors"
        :key="color.id"
        type="button"
        :aria-label="color.label"
        class="color-filter h-7 w-7 rounded-full border-2 border-white shadow ring-1 ring-black/10"
        :style="{
            backgroundColor:
                color.hex_color || '#cccccc'
        }"
        :class="{
            active:
                state.color === color.value,
        }"
        @click="
            selectColor(color.value)
        "
    />

</div>

                        </div>


                        <!-- Stock -->

                        <div class="py-6">

                            <h3
                                class="mb-4 text-xs font-semibold"
                            >
                                وضعیت
                            </h3>

                            <label
                                class="flex cursor-pointer items-center gap-2 text-xs"
                            >

                                <input
                                    v-model="
                                        state.stock
                                    "
                                    type="checkbox"
                                    class="check"
                                />

                                فقط کالاهای موجود

                            </label>

                        </div>

                    </div>

                </aside>


                <!-- =================================================
                     PRODUCTS
                ================================================== -->

                <section>

                    <!-- Mobile Active Filters -->

                    <div
                        v-if="activeFilters.length"
                        class="no-scrollbar mb-5 flex gap-2 overflow-x-auto lg:hidden"
                    >

                        <span
                            v-for="filter in activeFilters"
                            :key="
                                `mobile-${filter.type}-${filter.value}`
                            "
                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-blush px-3 py-2 text-[9px] text-plum dark:bg-white/10 dark:text-rose"
                        >
                            {{ filter.label }}

                            <button
                                type="button"
                                @click="
                                    removeFilter(filter)
                                "
                            >
                                ×
                            </button>
                        </span>

                    </div>


                    <!-- Empty -->

                    <div
                        v-if="!filteredProducts.length"
                        class="py-24 text-center"
                    >

                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blush text-2xl dark:bg-white/10"
                        >
                            ◌
                        </div>

                        <h3
                            class="mt-5 font-semibold"
                        >
                            محصولی پیدا نشد
                        </h3>

                        <p
                            class="mt-2 text-xs text-black/40 dark:text-white/40"
                        >
                            فیلترها را کمی تغییر بده.
                        </p>

                        <button
                            type="button"
                            class="mt-5 rounded-full bg-ink px-6 py-3 text-xs text-white dark:bg-white dark:text-ink"
                            @click="
                                clearAllFilters()
                            "
                        >
                            پاک کردن فیلترها
                        </button>

                    </div>


                    <!-- Grid -->

                    <div
                        v-else
                        class="grid grid-cols-2 gap-x-3 gap-y-9 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4"
                    >

                        <article
                            v-for="product in visibleProducts"
                            :key="product.name"
                            class="product-card group fade-in"
                        >

                            <div
                                class="relative overflow-hidden rounded-[1.5rem] bg-sand dark:bg-[#302631]"
                            >

                                <span
                                    v-if="product.badge"
                                    class="absolute right-3 top-3 z-10 rounded-full bg-white/90 px-3 py-1.5 text-[9px] text-ink shadow-sm"
                                >
                                    {{ product.badge }}
                                </span>

                                <span
                                    v-if="!product.in_stock"
                                    class="absolute left-3 top-3 z-10 rounded-full bg-black/70 px-3 py-1.5 text-[9px] text-white"
                                >
                                    ناموجود
                                </span>

                                <button
                                    type="button"
                                    class="absolute left-3 top-3 z-10 rounded-full bg-white/90 p-2.5 text-sm opacity-0 shadow-sm transition group-hover:opacity-100"
                                    aria-label="افزودن به علاقه‌مندی‌ها"
                                >
                                    ♡
                                </button>

                                <img
                                    :src="
                                        primaryImage(product)
                                    "
                                    :alt="product.name"
                                    loading="lazy"
                                    class="product-image aspect-[.82] w-full object-cover transition duration-700"
                                />

                                <div
                                    class="quick-actions absolute bottom-3 left-3 right-3 flex translate-y-2 items-center gap-2 opacity-0 transition duration-300"
                                >

                                    <button
                                        type="button"
                                        class="flex-1 rounded-full bg-white/95 py-3 text-[10px] text-ink shadow-lg backdrop-blur"
                                    >
                                        افزودن سریع
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-full bg-white/95 px-4 py-3 text-[10px] text-ink shadow-lg backdrop-blur"
                                    >
                                        مشاهده
                                    </button>

                                </div>

                            </div>


                            <div class="mt-4">

                                <div
                                    class="flex items-start justify-between gap-3"
                                >

                                    <div class="min-w-0">

                                        <div
                                            class="mb-1 flex items-center gap-2"
                                        >

                                            <span
                                                class="text-[8px] text-plum dark:text-rose"
                                            >
                                                {{ product.categories?.[0]?.name || '' }}
                                            </span>

                                            

                                        </div>

                                        <h3
                                            class="truncate text-xs font-semibold sm:text-sm"
                                        >
                                            {{ product.name }}
                                        </h3>

                                        <p
                                            class="mt-1.5 text-[10px] text-black/40 dark:text-white/40"
                                        >
                                            {{
                                                productDescription(
                                                    product
                                                )
                                            }}
                                        </p>

                                    </div>

                                    <p
                                        class="whitespace-nowrap text-xs font-semibold"
                                    >
                                        {{
                                            formatPrice(
                                                product.price
                                            )
                                        }}
                                    </p>

                                </div>

<div
    v-if="
        product.attributes?.size?.length ||
        product.attributes?.['shoe-size']?.length
    "
    class="mt-3 flex gap-2"
>
    <!-- سایز معمولی لباس -->
    <template v-if="product.attributes?.size?.length">
        <span
            v-for="item in product.attributes.size"
            :key="`size-${item.id}`"
            class="text-[8px] text-black/35 dark:text-white/35"
        >
            {{ item.label }}
        </span>
    </template>

    <!-- سایز کفش -->
    <template v-else-if="product.attributes?.['shoe-size']?.length">
        <span
            v-for="item in product.attributes['shoe-size']"
            :key="`shoe-size-${item.id}`"
            class="text-[8px] text-black/35 dark:text-white/35"
        >
            {{ item.label }}
        </span>
    </template>
</div>
                            </div>

                        </article>

                    </div>


                    <!-- Load More -->

                    <div
                        v-if="
                            filteredProducts.length >
                            state.visibleCount
                        "
                        class="mt-14 flex justify-center"
                    >

                        <button
                            type="button"
                            class="rounded-full border border-black/10 px-7 py-3 text-xs transition hover:bg-ink hover:text-white dark:border-white/10"
                            @click="loadMore"
                        >
                            نمایش محصولات بیشتر
                        </button>

                    </div>

                </section>

            </div>

        </main>


        <!-- =====================================================
             MOBILE OVERLAY
        ====================================================== -->

        <div
            class="overlay fixed inset-0 z-[80] bg-black/40 backdrop-blur-sm lg:hidden"
            :class="
                mobileFiltersOpen
                    ? 'visible-overlay'
                    : 'hidden-overlay'
            "
            @click="closeFilters"
        />


        <!-- =====================================================
             MOBILE FILTER SHEET
        ====================================================== -->

        <div
            class="mobile-sheet fixed inset-x-0 bottom-0 z-[90] max-h-[88vh] overflow-hidden rounded-t-[2rem] bg-cream shadow-2xl dark:bg-[#241d27] lg:hidden"
            :class="
                mobileFiltersOpen
                    ? 'open'
                    : 'closed'
            "
        >

            <div
                class="flex items-center justify-between border-b border-black/5 px-5 py-5 dark:border-white/10"
            >

                <div>

                    <h2 class="font-semibold">
                        فیلتر محصولات
                    </h2>

                    <p
                        class="mt-1 text-[10px] text-black/40 dark:text-white/40"
                    >
                        انتخاب کن، ما بقیه را انجام می‌دهیم
                    </p>

                </div>

                <button
                    type="button"
                    class="rounded-full bg-black/5 px-3 py-2 dark:bg-white/10"
                    @click="closeFilters"
                >
                    ×
                </button>

            </div>


            <div
                class="filter-scroll max-h-[65vh] overflow-y-auto px-5"
            >

                <!-- Categories -->

                <div
                    class="border-b border-black/10 py-6 dark:border-white/10"
                >

                    <h3
                        class="mb-4 text-xs font-semibold"
                    >
                        دسته‌بندی
                    </h3>

                    <div
                        class="grid grid-cols-2 gap-3 text-xs"
                    >

                       <label
    v-for="category in apiCategories"
    :key="
        `mobile-${category.id}`
    "
    class="flex items-center gap-2"
>

                            <input
                                type="checkbox"
                                class="check"
                                :checked="
                                    state.categories.includes(
                                        category.slug
                                    )
                                "
                                @change="
                                    toggleCategory(
                                        category.slug
                                    )
                                "
                            />

                            {{  category.name }}

                        </label>

                    </div>

                </div>


                <!-- Subcategory -->

                <div
                    v-if="
                        availableSubcategories.length
                    "
                    class="border-b border-black/10 py-6 dark:border-white/10"
                >

                    <h3
                        class="mb-4 text-xs font-semibold"
                    >
                        {{
                            activeCategory
                                ? `نوع ${categoryNames[activeCategory]}`
                                : 'نوع محصول'
                        }}
                    </h3>

                    <div
                        class="flex flex-wrap gap-2"
                    >

                        <button
                            v-for="
                                option in availableSubcategories
                            "
                            :key="
                                `mobile-sub-${option}`
                            "
                            type="button"
                            class="filter-option rounded-full border border-black/10 px-3 py-2 text-[10px] dark:border-white/10"
                            :class="{
                                active:
                                    state.subcategories.includes(
                                        option
                                    ),
                            }"
                            @click="
                                toggleSubcategory(
                                    option
                                )
                            "
                        >
                            {{
                                subcategoryNames[
                                    option
                                ] || option
                            }}
                        </button>

                    </div>

                </div>


                <!-- Price -->

                <div
                    class="border-b border-black/10 py-6 dark:border-white/10"
                >

                    <div
                        class="mb-4 flex justify-between"
                    >

                        <h3
                            class="text-xs font-semibold"
                        >
                            محدوده قیمت
                        </h3>

                        <span
                            class="text-[10px] text-plum dark:text-rose"
                        >
                            تا
                            {{
                                faNumber(
                                    state.maxPrice
                                )
                            }}
                        </span>

                    </div>

                    <input
                        v-model.number="
                            state.maxPrice
                        "
                        type="range"
                        min="500000"
                        max="5000000"
                        step="100000"
                        class="price-range w-full"
                    />

                </div>


                <!-- Size -->

                <div
                    v-if="availableSizes.length"
                    class="border-b border-black/10 py-6 dark:border-white/10"
                >

                    <h3
                        class="mb-4 text-xs font-semibold"
                    >
                        {{
                            shoeSelected
                                ? 'سایز کفش'
                                : 'سایز'
                        }}
                    </h3>

                    <div
                        class="flex flex-wrap gap-2"
                    >

                        <button
                            v-for="
                                size in availableSizes
                            "
                            :key="
                                `mobile-size-${size}`
                            "
                            type="button"
                            class="filter-option rounded-full border border-black/10 px-4 py-2 text-[10px] dark:border-white/10"
                            :class="{
                                active:
                                    state.size ===
                                    size,
                            }"
                            @click="
                                selectSize(size)
                            "
                        >
                            {{ size }}
                        </button>

                    </div>

                </div>


                <!-- Material -->

               <div
    v-if="availableMaterials.length"
    class="border-b border-black/10 py-6 dark:border-white/10"
>
    <h3
        class="mb-4 text-xs font-semibold"
    >
        جنس
    </h3>

    <div
        class="flex flex-wrap gap-2"
    >
        <button
            v-for="material in availableMaterials"
            :key="`mobile-material-${material.id}`"
            type="button"
            class="filter-option rounded-full border border-black/10 px-3 py-2 text-[10px] dark:border-white/10"
            :class="{
                active:
                    state.material ===
                    material.value,
            }"
            @click="
                selectMaterial(
                    material.value
                )
            "
        >
            {{ material.label }}
        </button>
    </div>
</div>


                <!-- Colors -->

                <div class="py-6">

                    <h3
                        class="mb-4 text-xs font-semibold"
                    >
                        رنگ
                    </h3>

                    <div class="flex flex-wrap gap-3">

    <button
        v-for="color in availableColors"
        :key="`mobile-color-${color.id}`"
        type="button"
        :aria-label="color.label"
        class="mobile-color-filter h-8 w-8 rounded-full ring-1 ring-black/10"
        :style="{
            backgroundColor:
                color.hex_color || '#cccccc'
        }"
        :class="{
            active:
                state.color === color.value,
        }"
        @click="
            selectColor(color.value)
        "
    />

</div>

                </div>


                <!-- Stock -->

                <div
                    class="border-t border-black/10 py-6 dark:border-white/10"
                >

                    <label
                        class="flex items-center gap-2 text-xs"
                    >

                        <input
                            v-model="
                                state.stock
                            "
                            type="checkbox"
                            class="check"
                        />

                        فقط کالاهای موجود

                    </label>

                </div>

            </div>


            <div
                class="border-t border-black/5 p-5 dark:border-white/10"
            >

                <button
                    type="button"
                    class="w-full rounded-full bg-ink py-4 text-sm text-white dark:bg-white dark:text-ink"
                    @click="closeFilters"
                >
                    نمایش محصولات
                </button>

            </div>

        </div>

    </div>
</template>