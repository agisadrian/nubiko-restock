<script setup lang="ts">
import { ref } from 'vue';

interface Props {
    errors?: Record<string, string>;
}
defineProps<Props>();

const form = ref({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
const submitting = ref(false);

function submit() {
    submitting.value = true;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('/register', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(form.value),
    }).then((res) => {
        if (res.redirected) {
            window.location.href = res.url;
        } else {
            submitting.value = false;
            window.location.reload();
        }
    });
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
        <div class="w-full max-w-sm bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-lg bg-orange-600 text-white flex items-center justify-center font-bold">NB</div>
                <div>
                    <h1 class="text-base font-semibold text-gray-900">Nubiko Dashboard</h1>
                    <p class="text-xs text-gray-500">Buat akun baru</p>
                </div>
            </div>

            <div v-if="errors && Object.keys(errors).length" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-4">
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
                    <input type="password" v-model="form.password" required minlength="8"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500">Konfirmasi Password</label>
                    <input type="password" v-model="form.password_confirmation" required minlength="8"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <button type="submit" :disabled="submitting"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg px-4 py-2 transition">
                    {{ submitting ? 'Memproses...' : 'Daftar' }}
                </button>
            </form>

            <p class="text-xs text-gray-500 text-center mt-4">
                Sudah punya akun?
                <a href="/login" class="text-indigo-600 hover:underline">Masuk di sini</a>
            </p>
        </div>
    </div>
</template>