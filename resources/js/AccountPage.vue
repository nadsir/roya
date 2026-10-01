<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AccountLayout from './components/account/AccountLayout.vue';
import AccountNotice from './components/account/AccountNotice.vue';
import AccountState from './components/account/AccountState.vue';
import ProfileField from './components/account/ProfileField.vue';
import { state as authState, isLoggedIn, loadUser, logout, updateProfile } from './auth-state.js';
import { PROFILE_EDITABLE_FIELDS, PROFILE_FIXED_FIELDS, errorSummary, firstError, profileValidation } from './account-presentation.js';

// Only name and email can be updated: that is exactly what the profile endpoint
// accepts. The mobile number comes from the same response but is never submitted.
const form = ref({ name: authState.user?.name || '', email: authState.user?.email || '' });
const fieldErrors = ref({});
const saveError = ref('');
const saved = ref(false);
const saving = ref(false);
const loggingOut = ref(false);
const logoutError = ref('');

const ready = computed(() => isLoggedIn.value && authState.user);
const unchanged = computed(() => form.value.name.trim() === (authState.user?.name || '').trim()
    && form.value.email.trim() === (authState.user?.email || '').trim());

function fillFromUser() {
    form.value = { name: authState.user?.name || '', email: authState.user?.email || '' };
    fieldErrors.value = {};
}

onMounted(async () => {
    if (!isLoggedIn.value) await loadUser();
    if (!isLoggedIn.value) {
        window.location.href = '/login';
        return;
    }
    fillFromUser();
});

watch(() => authState.user?.id, (id) => { if (id) fillFromUser(); });

async function onSave() {
    if (saving.value || unchanged.value) return;
    saved.value = false;
    saveError.value = '';
    fieldErrors.value = profileValidation(form.value);
    if (Object.keys(fieldErrors.value).length) {
        saveError.value = 'لطفاً خطاهای فرم را برطرف کنید.';
        return;
    }

    saving.value = true;
    try {
        await updateProfile(form.value.name.trim(), form.value.email.trim());
        fieldErrors.value = {};
        saved.value = true;
    } catch (failure) {
        const errors = failure.response?.data?.errors;
        fieldErrors.value = errors || {};
        saveError.value = failure.response?.data?.message
            || errorSummary(errors)[0]
            || 'ذخیره تغییرات انجام نشد. لطفاً دوباره تلاش کنید.';
    } finally {
        saving.value = false;
    }
}

async function onLogout() {
    if (loggingOut.value) return;
    loggingOut.value = true;
    logoutError.value = '';
    try {
        await logout();
        window.location.href = '/';
    } catch (failure) {
        logoutError.value = 'خروج از حساب انجام نشد. لطفاً دوباره تلاش کنید.';
    } finally {
        loggingOut.value = false;
    }
}
</script>

<template>
    <AccountLayout
        active="profile"
        title="حساب کاربری"
        description="اطلاعات حساب، سفارش‌ها و علاقه‌مندی‌های شما از همین بخش مدیریت می‌شود."
    >
        <AccountState v-if="authState.loading" busy />

        <template v-else-if="!ready">
            <AccountState icon="fa-regular fa-user" title="حساب کاربری در دسترس نیست" description="برای مشاهده اطلاعات حساب وارد شوید.">
                <a class="sf-button" href="/login">ورود به حساب کاربری</a>
            </AccountState>
        </template>

        <template v-else>
            <section class="ac-card">
                <div class="ac-card-head">
                    <h2>اطلاعات حساب</h2>
                    <p>نام و ایمیل قابل ویرایش هستند.</p>
                </div>
                <div class="ac-card-body">
                    <AccountNotice v-if="saved && !saveError" tone="success" icon="fa-solid fa-circle-check">
                        تغییرات با موفقیت ذخیره شد.
                    </AccountNotice>

                    <AccountNotice v-if="saveError" tone="error" icon="fa-solid fa-circle-exclamation" :errors="errorSummary(fieldErrors)">
                        {{ saveError }}
                    </AccountNotice>

                    <form class="ac-form" novalidate @submit.prevent="onSave">
                        <ProfileField
                            v-for="field in PROFILE_EDITABLE_FIELDS"
                            :key="field.key"
                            v-model="form[field.key]"
                            :field="field"
                            :error="firstError(fieldErrors, field.key)"
                            :disabled="saving"
                        />

                        <ProfileField
                            v-for="field in PROFILE_FIXED_FIELDS"
                            :key="field.key"
                            :field="field"
                            :model-value="authState.user?.[field.key] || ''"
                            readonly
                        />

                        <div class="ac-actions">
                            <button type="submit" class="sf-button" :disabled="saving || unchanged">
                                {{ saving ? 'در حال ذخیره…' : 'ذخیره تغییرات' }}
                            </button>
                            <span v-if="!saving && unchanged" class="sf-type-caption">برای ذخیره، نام یا ایمیل را تغییر دهید.</span>
                        </div>
                    </form>
                </div>
            </section>

            <section class="ac-card">
                <div class="ac-card-head">
                    <h2>نشست و خروج</h2>
                    <p>با خروج، توکن این دستگاه باطل می‌شود.</p>
                </div>
                <div class="ac-card-body">
                    <AccountNotice v-if="logoutError" tone="error" icon="fa-solid fa-circle-exclamation">
                        {{ logoutError }}
                    </AccountNotice>
                    <div class="ac-actions">
                        <button type="button" class="ac-action-danger" :disabled="loggingOut" @click="onLogout">
                            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                            {{ loggingOut ? 'در حال خروج…' : 'خروج از حساب' }}
                        </button>
                        <a class="sf-text-link" href="/orders">سفارش‌های من</a>
                    </div>
                </div>
            </section>
        </template>
    </AccountLayout>
</template>
