<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { catalog, categoryHref, categoryImage, loadCatalog } from '../catalog-state.js';

defineProps({ variant: { type: String, default: 'desktop' } });
const root = ref(null);
const trigger = ref(null);
const panel = ref(null);
const open = ref(false);
const activeIndex = ref(0);
const activeCategory = computed(() => catalog.categories[activeIndex.value] || catalog.categories[0]);
let leaveTimer;
let openedByHover = false;

const CategoryBranch = defineComponent({
    name: 'CategoryBranch',
    props: { items: { type: Array, default: () => [] }, mobile: Boolean },
    setup(props) {
        return () => h('ul', { class: 'sf-category-branch' }, props.items.map((item) => h('li', { key: item.slug },
            props.mobile && item.children?.length
                ? h('details', {}, [h('summary', {}, item.name), h('a', { href: categoryHref(item.slug) }, `مشاهده همه ${item.name}`), h(CategoryBranch, { items: item.children, mobile: true })])
                : [h('a', { href: categoryHref(item.slug) }, item.name), item.children?.length ? h(CategoryBranch, { items: item.children, mobile: props.mobile }) : null]
        )));
    },
});

function close(restoreFocus = false) {
    clearTimeout(leaveTimer);
    open.value = false;
    openedByHover = false;
    if (restoreFocus) trigger.value?.focus();
}
function show() { clearTimeout(leaveTimer); open.value = true; }
function hover() { if (matchMedia('(hover: hover)').matches) { openedByHover = true; show(); } }
function toggle() {
    if (openedByHover) { openedByHover = false; show(); }
    else if (open.value) close();
    else show();
}
function leave() { leaveTimer = setTimeout(() => { if (!root.value?.contains(document.activeElement)) close(); }, 160); }
function focusOut(event) { if (!root.value?.contains(event.relatedTarget)) close(); }
function outside(event) { if (!root.value?.contains(event.target)) close(); }
function keydown(event) { if (event.key === 'Escape' && open.value) { event.preventDefault(); close(true); } }
async function enter(event) {
    if (event.key !== 'ArrowDown') return;
    event.preventDefault(); show(); await nextTick();
    panel.value?.querySelector('button, a')?.focus();
}
function railKey(event, index) {
    const count = catalog.categories.length;
    let next;
    if (event.key === 'ArrowDown') next = (index + 1) % count;
    if (event.key === 'ArrowUp') next = (index - 1 + count) % count;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = count - 1;
    if (next === undefined) return;
    event.preventDefault(); activeIndex.value = next;
    panel.value?.querySelectorAll('.sf-category-tab')[next]?.focus();
}
onMounted(() => { loadCatalog(); document.addEventListener('pointerdown', outside); document.addEventListener('keydown', keydown); });
onBeforeUnmount(() => { clearTimeout(leaveTimer); document.removeEventListener('pointerdown', outside); document.removeEventListener('keydown', keydown); });
</script>

<template>
    <div ref="root" class="sf-mega" @focusout="focusOut">
        <template v-if="variant === 'desktop'">
            <button ref="trigger" class="sf-nav-link" type="button" :aria-expanded="open" aria-controls="sf-category-panel"
                @click="toggle" @keydown="enter" @mouseenter="hover" @mouseleave="leave">
                دسته‌بندی‌ها <i class="fa-solid fa-chevron-down" aria-hidden="true" />
            </button>
            <Transition name="sf-reveal">
                <nav v-if="open" id="sf-category-panel" ref="panel" class="sf-mega-panel" aria-label="دسته‌بندی محصولات" @mouseenter="show" @mouseleave="leave">
                    <div v-if="catalog.status === 'loading'" class="sf-menu-loading" role="status" aria-label="در حال دریافت دسته‌بندی‌ها"><span v-for="n in 4" :key="n" class="sf-skeleton" /></div>
                    <div v-else-if="catalog.status === 'error'" class="sf-shell-state" role="alert"><p>{{ catalog.error }}</p><button class="sf-button" @click="loadCatalog({ retry: true })">تلاش مجدد</button></div>
                    <div v-else-if="!activeCategory" class="sf-shell-state"><p>دسته‌بندی‌های فروشگاه به‌زودی در دسترس خواهند بود.</p><a href="/store" class="sf-text-link">مشاهده فروشگاه ←</a></div>
                    <div v-else class="sf-mega-layout">
                        <div class="sf-category-rail" aria-label="دسته‌های اصلی">
                            <button v-for="(category, index) in catalog.categories" :key="category.slug" class="sf-category-tab" :class="{ 'is-active': activeIndex === index }" :aria-pressed="activeIndex === index"
                                @mouseenter="activeIndex = index" @focus="activeIndex = index" @click="activeIndex = index" @keydown="railKey($event, index)">{{ category.name }} <span aria-hidden="true">←</span></button>
                        </div>
                        <div class="sf-category-content">
                            <div class="sf-section-heading"><h2 class="sf-type-h3">{{ activeCategory.name }}</h2><a :href="categoryHref(activeCategory.slug)" class="sf-text-link">مشاهده همه ←</a></div>
                            <CategoryBranch :items="activeCategory.children || []" />
                            <p v-if="!activeCategory.children?.length" class="sf-type-small">تمام محصولات این دسته را ببینید.</p>
                        </div>
                        <a v-if="categoryImage(activeCategory)" :href="categoryHref(activeCategory.slug)" class="sf-category-preview">
                            <img :src="categoryImage(activeCategory)" :alt="activeCategory.name" loading="lazy" @error="$event.target.style.visibility = 'hidden'" />
                            <span>{{ activeCategory.name }} <span aria-hidden="true">↗</span></span>
                        </a>
                    </div>
                </nav>
            </Transition>
        </template>
        <template v-else>
            <div v-if="catalog.status === 'loading'" class="sf-menu-loading" role="status" aria-label="در حال دریافت دسته‌بندی‌ها"><span v-for="n in 4" :key="n" class="sf-skeleton" /></div>
            <div v-else-if="catalog.status === 'error'" class="sf-shell-state" role="alert"><p>{{ catalog.error }}</p><button class="sf-button" @click="loadCatalog({ retry: true })">تلاش مجدد</button></div>
            <p v-else-if="!catalog.categories.length" class="sf-type-small">دسته‌بندی‌های فروشگاه به‌زودی در دسترس خواهند بود.</p>
            <CategoryBranch v-else :items="catalog.categories" mobile />
            <a href="/store" class="sf-text-link sf-mobile-view-all">مشاهده همه محصولات ←</a>
        </template>
    </div>
</template>
