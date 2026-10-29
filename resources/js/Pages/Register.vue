<script setup lang="ts">
import { ref } from 'vue';

interface Props {
    errors?: Record<string, string>;
    registration_status?: 'pending' | 'approved' | null;
}
defineProps<Props>();

const form = ref({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
const submitting = ref(false);
const formErrors = ref<string[]>([]);

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function submit() {
    submitting.value = true;
    formErrors.value = [];
    try {
        const res = await fetch('/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(form.value),
        });

        if (res.redirected) {
            window.location.href = res.url;
            return;
        }

        if (res.status === 422) {
            const json = await res.json();
            formErrors.value = json.errors
                ? (Object.values(json.errors) as string[][]).flat()
                : ['Data tidak valid.'];
        } else {
            formErrors.value = ['Terjadi kesalahan. Coba lagi.'];
        }
    } catch (e) {
        formErrors.value = ['Tidak bisa terhubung ke server.'];
    }
    submitting.value = false;
}
</script>

<template>
    <div class="min-h-dvh bg-gray-50 flex items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-sm bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold">NB</div>
                <div>
                    <h1 class="text-base font-semibold text-gray-900">Daftar Akun</h1>
                    <p class="text-xs text-gray-500">Akun perlu disetujui admin sebelum bisa login</p>
                </div>
            </div>

            <!-- Browser ini sudah pernah daftar -->
            <div v-if="registration_status === 'pending'"
                class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                <p class="font-semibold mb-1">Menunggu disetujui oleh admin</p>
                <p class="text-xs">Pendaftaran kamu sudah kami terima. Kamu bisa login setelah admin menyetujui akunmu.</p>
            </div>

            <div v-else-if="registration_status === 'approved'"
                class="text-sm text-green-800 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
                <p class="font-semibold mb-1">Akun sudah disetujui</p>
                <p class="text-xs mb-3">Silakan login dengan akun yang sudah kamu daftarkan.</p>
                <a href="/login"
                    class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg px-4 py-2 transition">
                    Ke halaman login
                </a>
            </div>

            <!-- Form daftar -->
            <template v-else>
                <div v-if="formErrors.length || (errors && Object.keys(errors).length)"
                    class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-4">
                    <p v-for="(msg, i) in formErrors" :key="'f' + i">{{ msg }}</p>
                    <p v-for="(msg, key) in errors" :key="key">{{ msg }}</p>
                </div>

                <form @submit.prevent="submit" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Nama</label>
                        <input type="text" v-model="form.name" required
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Email</label>
                        <input type="email" v-model="form.email" required
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Password</label>
                        <input type="password" v-model="form.password" required minlength="6"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Konfirmasi Password</label>
                        <input type="password" v-model="form.password_confirmation" required
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <button type="submit" :disabled="submitting"
                        class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg px-4 py-2 transition">
                        {{ submitting ? 'Mendaftar...' : 'Daftar' }}
                    </button>
                </form>
            </template>

            <p class="text-xs text-gray-500 text-center mt-4">
                Sudah punya akun? <a href="/login" class="text-indigo-600 font-medium">Login di sini</a>
            </p>
        </div>
    </div>
</template>