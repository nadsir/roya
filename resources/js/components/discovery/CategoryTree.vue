<script setup>
import { computed, ref } from 'vue';
import { categoryHref } from '../../catalog-state.js';
const props = defineProps({ items: Array, selected: String });
defineEmits(['select']);
const expanded = ref(new Map());
function contains(item) { return item.slug === props.selected || (item.children || []).some(contains); }
const activeBranches = computed(() => props.items.filter(contains).map((item) => item.slug));
function isExpanded(slug) { return expanded.value.get(slug) ?? activeBranches.value.includes(slug); }
function toggle(slug) { const next = new Map(expanded.value); next.set(slug, !isExpanded(slug)); expanded.value = next; }
function choose(event, slug, emit) { if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return; event.preventDefault(); emit('select', slug); }
</script>
<template>
    <ul class="dc-category-tree"><li v-for="item in items" :key="item.slug"><div><a :href="categoryHref(item.slug)" :aria-current="selected === item.slug ? 'page' : undefined" @click="choose($event, item.slug, $emit)">{{ item.name }}</a><button v-if="item.children?.length" type="button" :aria-label="`زیرمجموعه‌های ${item.name}`" :aria-expanded="isExpanded(item.slug)" @click="toggle(item.slug)">{{ isExpanded(item.slug) ? '−' : '+' }}</button></div><CategoryTree v-if="item.children?.length && isExpanded(item.slug)" :items="item.children" :selected="selected" @select="$emit('select', $event)" /></li></ul>
</template>
