<script setup>
import { onMounted, ref } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { state, isLoggedIn, loadUser, logout, updateProfile } from './auth-state.js';

const editName = ref('');
const editEmail = ref('');
const saving = ref(false);
const saveSuccess = ref(false);
const saveError = ref('');

onMounted(async () => {
    if (!isLoggedIn.value) {
        await loadUser();
    }
    if (!isLoggedIn.value) {
        window.location.href = '/login';
        return;
    }
    editName.value = state.user?.name || '';
    editEmail.value = state.user?.email || '';
});

async function onSave() {
    saveSuccess.value = false;
    saveError.value = '';
    saving.value = true;

    try {
        await updateProfile(editName.value, editEmail.value);
        saveSuccess.value = true;
    } catch (e) {
        const msg = e.response?.data?.message;
        const errors = e.response?.data?.errors;
        if (errors?.email) {
            saveError.value = errors.email[0];
        } else if (msg) {
            saveError.value = msg;
        } else {
            saveError.value = 'تغییرات ذخیره نشد.';
        }
    } finally {
        saving.value = false;
    }
}

async function onLogout() {
    await logout();
    window.location.href = '/';
}
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased">
        <SiteHeader />

        <div class="max-w-lg mx-auto px-4 py-12">
            <!-- Loading -->
            <div v-if="state.loading" class="text-center py-16">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-brand-accent"></i>
            </div>

            <template v-else-if="isLoggedIn">
                <div class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-200">
                        <div class="w-10 h-10 rounded-full bg-brand-accent/10 flex items-center justify-center">
                            <i class="fa-solid fa-user text-brand-accent"></i>
                        </div>
                        <div>
                            <h1 class="text-sm font-black text-ink">{{ state.user?.name }}</h1>
                            <p class="text-[11px] text-slate-500">{{ state.user?.email }}</p>
                        </div>
                    </div>

                    <div v-if="saveSuccess" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-xs text-emerald-600">
                        تغییرات با موفقیت ذخیره شد.
                    </div>

                    <div v-if="saveError" class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-600">
                        {{ saveError }}
                    </div>

                    <form class="space-y-4" @submit.prevent="onSave">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1.5">نام</label>
                            <input
                                v-model="editName"
                                type="text"
                                required
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-ink focus:border-brand-accent/60 focus:outline-none transition-colors"
                            />
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1.5">ایمیل</label>
                            <input
                                v-model="editEmail"
                                type="email"
                                required
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-ink focus:border-brand-accent/60 focus:outline-none transition-colors"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="saving"
                            class="w-full rounded-lg bg-brand-accent text-dark-900 py-2.5 text-sm font-bold hover:bg-brand-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i v-if="saving" class="fa-solid fa-spinner fa-spin ml-1"></i>
                            {{ saving ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                        </button>
                    </form>

                    <div class="mt-6 pt-4 border-t border-gray-200 space-y-2">
                        <a href="/orders" class="block w-full text-center rounded-lg border border-gray-200 py-2.5 text-xs font-bold text-slate-600 hover:border-gray-400 transition-colors">مشاهده سفارش‌های من</a>
                        <a
                            href="/store"
                            class="block w-full text-center rounded-lg border border-gray-200 py-2.5 text-xs font-bold text-slate-600 hover:border-gray-400 transition-colors"
                        >
                            <i class="fa-solid fa-store ml-1 text-[10px]"></i>
                            بازگشت به فروشگاه
                        </a>
                        <button
                            type="button"
                            class="w-full text-center rounded-lg py-2.5 text-xs font-bold text-red-500 hover:text-red-600 hover:bg-red-50 transition-colors"
                            @click="onLogout"
                        >
                            <i class="fa-solid fa-right-from-bracket ml-1 text-[10px]"></i>
                            خروج از حساب
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <SiteFooter />
    </div>
</template>
