<script setup>
import { ref } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import AccountNotice from './components/account/AccountNotice.vue';
import { register, isLoggedIn } from './auth-state.js';
import { errorSummary } from './account-presentation.js';
import '../css/account.css';

// The payload is unchanged: name, email, password, password_confirmation, mobile,
// which is exactly what the register endpoint validates. Every field now reports the
// backend validation message against its own control.
const fields = [
    { key: 'mobile', label: 'شماره موبایل', type: 'tel', autocomplete: 'tel', inputmode: 'tel', dir: 'ltr', maxlength: 32, placeholder: '09121234567' },
    { key: 'name', label: 'نام', type: 'text', autocomplete: 'name', maxlength: 255, placeholder: 'نام کامل' },
    { key: 'email', label: 'ایمیل', type: 'email', autocomplete: 'email', dir: 'ltr', maxlength: 255, placeholder: 'example@email.com' },
    { key: 'password', label: 'رمز عبور', type: 'password', autocomplete: 'new-password', minlength: 8, placeholder: 'حداقل ۸ کاراکتر' },
    { key: 'passwordConfirmation', label: 'تکرار رمز عبور', type: 'password', autocomplete: 'new-password', placeholder: 'رمز عبور را دوباره وارد کنید' },
];

const form = ref({ mobile: '', name: '', email: '', password: '', passwordConfirmation: '' });
const loading = ref(false);
const error = ref('');
const fieldErrors = ref({});

if (isLoggedIn.value) {
    window.location.href = '/account';
}

async function onSubmit() {
    if (loading.value) return;
    error.value = '';
    fieldErrors.value = {};
    loading.value = true;
    try {
        await register(form.value.name, form.value.email, form.value.password, form.value.passwordConfirmation, form.value.mobile);
        window.location.href = '/account';
    } catch (failure) {
        const errors = failure.response?.data?.errors;
        fieldErrors.value = errors || {};
        error.value = failure.response?.data?.message
            || errorSummary(errors)[0]
            || 'ثبت‌نام انجام نشد. لطفاً دوباره تلاش کنید.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="ac-auth" dir="rtl">
        <SiteHeader />

        <main class="sf-container ac-auth-main">
            <section class="ac-auth-card">
                <div class="ac-auth-head">
                    <span class="ac-auth-mark" aria-hidden="true"><i class="fa-regular fa-user-plus" /></span>
                    <h1 class="sf-type-h2">ساخت حساب کاربری</h1>
                    <p>برای ثبت سفارش و ذخیره علاقه‌مندی‌ها یک حساب بسازید.</p>
                </div>

                <AccountNotice v-if="error" tone="error" icon="fa-solid fa-circle-exclamation" :errors="errorSummary(fieldErrors)">
                    {{ error }}
                </AccountNotice>

                <form class="ac-auth-form" novalidate @submit.prevent="onSubmit">
                    <div v-for="field in fields" :key="field.key" class="ac-field">
                        <label :for="`register-${field.key}`">{{ field.label }}</label>
                        <input
                            :id="`register-${field.key}`"
                            v-model="form[field.key]"
                            :type="field.type"
                            :dir="field.dir || 'auto'"
                            :inputmode="field.inputmode"
                            :autocomplete="field.autocomplete"
                            :maxlength="field.maxlength"
                            :minlength="field.minlength"
                            :placeholder="field.placeholder"
                            required
                            :disabled="loading"
                            :aria-invalid="fieldErrors[field.key] ? 'true' : undefined"
                            :aria-describedby="fieldErrors[field.key] ? `register-${field.key}-error` : undefined"
                        />
                        <p v-if="fieldErrors[field.key]" :id="`register-${field.key}-error`" class="ac-field-error" role="alert">
                            {{ fieldErrors[field.key][0] }}
                        </p>
                    </div>

                    <button type="submit" class="sf-button" :disabled="loading">
                        {{ loading ? 'در حال ثبت‌نام…' : 'ثبت‌نام' }}
                    </button>
                </form>

                <p class="ac-auth-foot">
                    قبلاً ثبت‌نام کرده‌اید؟
                    <a href="/login">وارد شوید</a>
                </p>
            </section>
        </main>

        <SiteFooter />
    </div>
</template>
