<script setup>
import { ref } from 'vue';
import { editorialArt } from '../../editorial-art.js';
defineProps({ href: { type: String, default: '/store' } });
const failed = ref(false);
</script>
<template>
    <section class="hp-editorial" aria-labelledby="hp-editorial-title">
        <div class="hp-editorial-art"><img v-if="!failed" :src="editorialArt.src" :srcset="editorialArt.srcset" :sizes="editorialArt.sizes" :width="editorialArt.width" :height="editorialArt.height" loading="lazy" decoding="async" :alt="editorialArt.alt" @error="failed = true" /><span v-else class="hp-art-monogram" aria-hidden="true">G.</span><span class="hp-editorial-caption" lang="en" dir="ltr">THE ART OF EVERYDAY</span></div>
        <div class="hp-editorial-copy"><p class="hp-eyebrow" lang="en" dir="ltr">AN EVERYDAY EDIT</p><h2 id="hp-editorial-title">سادگی،<br /><em>با جزئیاتِ بیشتر.</em></h2><p>فرم‌های ساده و ترکیب‌های آرام؛ نقطه شروعی برای ساختن استایلی که به شما نزدیک‌تر است.</p><a :href="href" class="hp-primary-link">دیدن انتخاب‌ها <span aria-hidden="true">←</span></a><small>{{ editorialArt.caption }}</small></div>
    </section>
</template>
