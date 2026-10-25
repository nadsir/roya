<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { showAdminNotification } from './admin-state.js';

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const success = ref('');

const form = ref({
    enabled: true,
    eyebrow: 'YOUR NEXT CHAPTER',
    heading: 'انتخاب بعدی، به سلیقه شما.',
    intro: '',
    delivery_enabled: true,
    delivery_title: 'استایل شما، در سراسر ایران',
    delivery_description: '',
    delivery_image: null,
    delivery_cta_label: 'مشاهده محصولات',
    delivery_cta_url: '/store',
    wholesale_enabled: true,
    wholesale_title: 'همکاری عمده با رویا گالری',
    wholesale_description: '',
    wholesale_image: null,
    wholesale_cta_label: 'درخواست همکاری عمده',
    whatsapp_number: '09126612898',
    whatsapp_message: 'سلام، برای دریافت اطلاعات خرید عمده محصولات رویا گالری و شرایط همکاری پیام می‌دهم.',
});

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/admin/next-chapter');
        form.value = { ...form.value, ...data };
    } catch (e) {
        error.value = e.response?.data?.message || 'دریافت تنظیمات انجام نشد.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        const { data } = await axios.put('/api/admin/next-chapter', form.value);
        form.value = { ...form.value, ...data };
        success.value = 'تنظیمات ذخیره شد.';
        showAdminNotification('success', 'موفقیت', 'تنظیمات بخش ذخیره شد.');
    } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || 'ذخیره انجام نشد.');
        showAdminNotification('error', 'خطا', error.value);
    } finally {
        saving.value = false;
    }
}

async function uploadImage(panel, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('panel', panel);
    fd.append('image', file);
    try {
        const { data } = await axios.post('/api/admin/next-chapter/images', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        form.value = { ...form.value, ...data };
        showAdminNotification('success', 'موفقیت', 'تصویر ذخیره شد.');
    } catch (e) {
        const errors = e.response?.data?.errors;
        const msg = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || 'آپلود تصویر انجام نشد.');
        showAdminNotification('error', 'خطا', msg);
    } finally {
        event.target.value = '';
    }
}

onMounted(load);
</script>

<template>
    <section class="nextchapter-admin">
        <header class="admin-header">
            <h1>مدیریت ارسال سراسری و فروش عمده</h1>
            <p>مدیریت بخش «انتخاب بعدی، به سلیقه شما» در صفحه اصلی</p>
        </header>

        <div v-if="loading" class="state">در حال بارگذاری...</div>
        <div v-else-if="error && !form.heading" class="state error">{{ error }}</div>

        <form v-else class="form" @submit.prevent="save">
            <div class="form-section">
                <h2>تنظیمات عمومی</h2>
                <label class="checkbox"><input type="checkbox" v-model="form.enabled" /> فعال بودن بخش</label>
                <label>Eyebrow (انگلیسی)<input v-model="form.eyebrow" type="text" /></label>
                <label>عنوان اصلی<input v-model="form.heading" type="text" /></label>
                <label>متن معرفی<textarea v-model="form.intro" rows="3"></textarea></label>
            </div>

            <div class="form-section">
                <h2>پنل ارسال به سراسر ایران</h2>
                <label class="checkbox"><input type="checkbox" v-model="form.delivery_enabled" /> فعال</label>
                <label>عنوان<input v-model="form.delivery_title" type="text" /></label>
                <label>توضیحات<textarea v-model="form.delivery_description" rows="3"></textarea></label>
                <label>متن دکمه<input v-model="form.delivery_cta_label" type="text" /></label>
                <label>لینک دکمه<input v-model="form.delivery_cta_url" type="text" /></label>
                <div class="image-row">
                    <img v-if="form.delivery_image" :src="form.delivery_image" alt="delivery" class="preview" />
                    <div v-else class="preview empty">بدون تصویر</div>
                    <input type="file" accept="image/*" @change="uploadImage('delivery', $event)" />
                </div>
            </div>

            <div class="form-section">
                <h2>پنل فروش عمده</h2>
                <label class="checkbox"><input type="checkbox" v-model="form.wholesale_enabled" /> فعال</label>
                <label>عنوان<input v-model="form.wholesale_title" type="text" /></label>
                <label>توضیحات<textarea v-model="form.wholesale_description" rows="3"></textarea></label>
                <label>متن دکمه<input v-model="form.wholesale_cta_label" type="text" /></label>
                <label>شماره واتساپ<input v-model="form.whatsapp_number" type="text" dir="ltr" /></label>
                <label>پیام پیش‌فرض واتساپ<textarea v-model="form.whatsapp_message" rows="3"></textarea></label>
                <div class="image-row">
                    <img v-if="form.wholesale_image" :src="form.wholesale_image" alt="wholesale" class="preview" />
                    <div v-else class="preview empty">بدون تصویر</div>
                    <input type="file" accept="image/*" @change="uploadImage('wholesale', $event)" />
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn-save" :disabled="saving">
                    {{ saving ? 'در حال ذخیره...' : 'ذخیره تنظیمات' }}
                </button>
                <span v-if="success" class="success">{{ success }}</span>
                <span v-if="error" class="error">{{ error }}</span>
            </div>
        </form>
    </section>
</template>

<style scoped>
.nextchapter-admin { max-width: 960px; margin: 0 auto; padding: 24px 20px; color: #1a1a1a; }
.admin-header h1 { margin: 0 0 4px; font-size: 24px; font-weight: 700; }
.admin-header p { margin: 0 0 20px; color: #666; font-size: 14px; }
.state { padding: 40px; text-align: center; color: #666; }
.state.error { color: #c9545e; }
.form { display: grid; gap: 20px; }
.form-section { border: 1px solid #e8e8e4; border-radius: 12px; padding: 20px; background: #fff; display: grid; gap: 12px; }
.form-section h2 { margin: 0 0 8px; font-size: 16px; font-weight: 700; }
.form-section label { display: grid; gap: 6px; font-size: 13px; font-weight: 600; color: #333; }
.form-section input, .form-section textarea { padding: 10px 12px; border: 1px solid #e8e8e4; border-radius: 8px; font-family: inherit; font-size: 14px; }
.checkbox { display: flex !important; align-items: center; gap: 8px; font-weight: 500 !important; }
.image-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.preview { width: 160px; height: 100px; object-fit: cover; border: 1px solid #e8e8e4; border-radius: 8px; background: #f5f5f2; }
.preview.empty { display: grid; place-items: center; color: #aaa; font-size: 12px; }
.actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.btn-save { padding: 12px 24px; border: 0; border-radius: 8px; background: #111; color: #fff; font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
.success { color: #16a34a; font-size: 13px; }
.error { color: #c9545e; font-size: 13px; }
</style>