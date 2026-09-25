<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';

interface UserItem {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    created_at: string;
}

interface Toast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

const users = ref<UserItem[]>([]);
const loading = ref(true);

const toasts = ref<Toast[]>([]);
let toastIdCounter = 0;
function showToast(type: 'success' | 'error', message: string) {
    const id = toastIdCounter++;
    toasts.value.push({ id, type, message });
    setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id); }, 4000);
}

const pendingUsers = computed(() => users.value.filter(u => u.status === 'pending'));
const otherUsers = computed(() => users.value.filter(u => u.status !== 'pending'));

async function fetchUsers() {
    loading.value = true;
    try {
        const res = await fetch('/api/users');
        const json = await res.json();
        users.value = json.data;
    } catch (e) {
        showToast('error', 'Gagal memuat daftar user.');
    } finally {
        loading.value = false;
    }
}

async function approveUser(user: UserItem) {
    try {
        const res = await fetch(`/api/users/${user.id}/approve`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
        });
        if (!res.ok) throw new Error();
        showToast('success', `${user.name} disetujui.`);
        await fetchUsers();
    } catch (e) {
        showToast('error', 'Gagal menyetujui user.');
    }
}

async function rejectUser(user: UserItem) {
    if (!confirm(`Tolak akun "${user.name}"?`)) return;
    try {
        const res = await fetch(`/api/users/${user.id}/reject`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
        });
        if (!res.ok) throw new Error();
        showToast('success', `${user.name} ditolak.`);
        await fetchUsers();
    } catch (e) {
        showToast('error', 'Gagal menolak user.');
    }
}

async function deleteUser(user: UserItem) {
    if (!confirm(`Hapus akun "${user.name}" secara permanen?`)) return;
    try {
        const res = await fetch(`/api/users/${user.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
        });
        if (!res.ok) {
            const data = await res.json().catch(() => null);
            throw new Error(data?.message);
        }
        showToast('success', `${user.name} dihapus.`);
        await fetchUsers();
    } catch (e: any) {
        showToast('error', e.message || 'Gagal menghapus user.');
    }
}

function statusBadgeClass(status: string) {
    if (status === 'approved') return 'bg-emerald-100 text-emerald-700';
    if (status === 'rejected') return 'bg-red-100 text-red-700';
    return 'bg-amber-100 text-amber-700';
}

function statusLabel(status: string) {
    if (status === 'approved') return 'Disetujui';
    if (status === 'rejected') return 'Ditolak';
    return 'Menunggu';
}

onMounted(fetchUsers);
</script>

<template>
    <AppLayout>
        <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 w-80">
            <transition-group name="toast">
                <div v-for="toast in toasts" :key="toast.id"
                    :class="['rounded-lg shadow-md px-4 py-3 text-sm font-medium text-white flex items-start gap-2', toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600']">
                    <span>{{ toast.type === 'success' ? '✓' : '✕' }}</span>
                    <span>{{ toast.message }}</span>
                </div>
            </transition-group>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <h1 class="text-lg font-semibold text-gray-900">Persetujuan User</h1>
                <p class="text-sm text-gray-500">Kelola akun yang mendaftar dan butuh persetujuan</p>
            </div>

            <p v-if="loading" class="text-sm text-gray-500 py-6 text-center">Memuat data...</p>

            <template v-else>
                <div v-if="pendingUsers.length > 0" class="bg-white rounded-xl border border-amber-200 shadow-sm p-5 mb-6">
                    <h2 class="text-sm font-semibold text-amber-700 mb-4">⏳ Menunggu Persetujuan ({{ pendingUsers.length }})</h2>
                    <div class="flex flex-col gap-2">
                        <div v-for="user in pendingUsers" :key="user.id"
                            class="flex items-center justify-between gap-3 p-3 bg-amber-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ user.name }}</p>
                                <p class="text-xs text-gray-500">{{ user.email }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="approveUser(user)"
                                    class="px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700">
                                    Setujui
                                </button>
                                <button @click="rejectUser(user)"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                                    Tolak
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Semua User</h2>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-gray-600">
                                    <th class="text-left px-3 py-2 font-semibold">Nama</th>
                                    <th class="text-left px-3 py-2 font-semibold">Email</th>
                                    <th class="text-left px-3 py-2 font-semibold">Role</th>
                                    <th class="text-left px-3 py-2 font-semibold">Status</th>
                                    <th class="text-left px-3 py-2 font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in otherUsers" :key="user.id" class="border-t border-gray-100">
                                    <td class="px-3 py-2">{{ user.name }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ user.email }}</td>
                                    <td class="px-3 py-2 capitalize">{{ user.role }}</td>
                                    <td class="px-3 py-2">
                                        <span :class="['inline-block px-2.5 py-1 rounded-full text-xs font-medium', statusBadgeClass(user.status)]">
                                            {{ statusLabel(user.status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <button @click="deleteUser(user)" class="text-xs px-2 py-1 border border-red-300 text-red-600 rounded-md hover:bg-red-50">Hapus</button>
                                    </td>
                                </tr>
                                <tr v-if="otherUsers.length === 0">
                                    <td colspan="5" class="text-center text-gray-400 py-6">Belum ada user lain.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px); }
</style>