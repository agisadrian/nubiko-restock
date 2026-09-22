<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';

interface StockOutItem {
    No: number;
    'Tanggal Keluar': string;
    'Nama Produk': string;
    QTY: number;
    Keterangan: string | null;
    id: number;
}

interface Toast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

const items = ref<StockOutItem[]>([]);
const loading = ref(true);
const errorMsg = ref('');
const totalQty = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const searchQuery = ref('');

const form = ref({
    tanggal_keluar: '',
    nama_produk: '',
    qty: 1,
    keterangan: '',
});
const submitting = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});

// Daftar produk yang sudah ada di Restock, dipakai sebagai pilihan di form Stok Keluar
const produkOptions = ref<string[]>([]);
const loadingProduk = ref(false);

async function fetchProdukOptions() {
    loadingProduk.value = true;
    try {
        const res = await fetch('/api/restock/produk-list');
        if (!res.ok) throw new Error('Gagal ambil daftar produk');
        const json = await res.json();
        produkOptions.value = json.data;
    } catch (e) {
        showToast('error', 'Gagal memuat daftar produk dari Restock.');
    } finally {
        loadingProduk.value = false;
    }
}

const toasts = ref<Toast[]>([]);
let toastIdCounter = 0;
function showToast(type: 'success' | 'error', message: string) {
    const id = toastIdCounter++;
    toasts.value.push({ id, type, message });
    setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id); }, 4000);
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function fetchData(page = 1) {
    loading.value = true;
    errorMsg.value = '';
    try {
        const params = new URLSearchParams();
        params.set('page', String(page));
        if (searchQuery.value) params.set('search', searchQuery.value);

        const res = await fetch(`/api/stock-out?${params.toString()}`);
        if (!res.ok) throw new Error('Gagal ambil data');
        const json = await res.json();

        items.value = json.data;
        currentPage.value = json.current_page;
        lastPage.value = json.last_page;
        total.value = json.total;
        totalQty.value = json.total_qty;
    } catch (e) {
        errorMsg.value = 'Gagal memuat data. Coba refresh halaman.';
        showToast('error', 'Gagal memuat data dari server.');
    } finally {
        loading.value = false;
    }
}

watch(searchQuery, () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => fetchData(1), 400);
});

function goToPage(page: number) {
    if (page < 1 || page > lastPage.value) return;
    fetchData(page);
}

async function submitForm() {
    submitting.value = true;
    fieldErrors.value = {};
    try {
        const res = await fetch('/api/stock-out', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(form.value),
        });

        if (res.status === 422) {
            const data = await res.json();
            fieldErrors.value = data.errors || {};
            showToast('error', 'Beberapa isian belum valid.');
            return;
        }
        if (!res.ok) throw new Error('Gagal menambah data');

        form.value = { tanggal_keluar: '', nama_produk: '', qty: 1, keterangan: '' };
        showToast('success', 'Data stok keluar berhasil ditambahkan.');
        await fetchData(currentPage.value);
    } catch (e) {
        showToast('error', 'Gagal menambah data. Coba lagi.');
    } finally {
        submitting.value = false;
    }
}

async function deleteItem(item: StockOutItem) {
    if (!confirm(`Hapus data "${item['Nama Produk']}"?`)) return;
    try {
        const res = await fetch(`/api/stock-out/${item.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
        });
        if (!res.ok) throw new Error('Gagal hapus data');
        showToast('success', 'Data berhasil dihapus.');
        await fetchData(currentPage.value);
    } catch (e) {
        showToast('error', 'Gagal menghapus data.');
    }
}

const importing = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

async function handleImport(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;
    importing.value = true;
    const formData = new FormData();
    formData.append('file', file);
    try {
        const res = await fetch('/api/stock-out/import', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            body: formData,
        });
        if (!res.ok) {
            const data = await res.json().catch(() => null);
            throw new Error(data?.message || 'Import gagal');
        }
        showToast('success', 'Data berhasil diimport!');
        await fetchData(1);
    } catch (e) {
        showToast('error', 'Gagal import file. Cek format kolomnya ya.');
    } finally {
        importing.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

onMounted(() => {
    fetchData(1);
    fetchProdukOptions();
});
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

        <div class="max-w-5xl mx-auto">
           <div class="flex items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-lg font-semibold text-gray-900">Stok Keluar</h1>
        <p class="text-sm text-gray-500">Catat barang yang keluar dari gudang/toko</p>
    </div>
    <div>
        <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="handleImport" />
        <button @click="fileInput?.click()" :disabled="importing"
            class="bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-50 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 transition">
            {{ importing ? 'Mengimport...' : '📥 Import Excel/CSV' }}
        </button>
    </div>
</div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Transaksi Keluar</p>
                    <p class="text-2xl font-bold text-gray-900">{{ total }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Qty Keluar</p>
                    <p class="text-2xl font-bold text-red-600">{{ totalQty }}</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Catat Stok Keluar</h2>
                <form @submit.prevent="submitForm" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-start">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Tanggal Keluar</label>
                        <input type="date" v-model="form.tanggal_keluar" required
                            :class="['border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2', fieldErrors.tanggal_keluar ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-orange-500']" />
                        <p v-if="fieldErrors.tanggal_keluar" class="text-xs text-red-600">{{ fieldErrors.tanggal_keluar[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Nama Produk</label>
                        <select v-model="form.nama_produk" required :disabled="loadingProduk"
                            :class="['border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 bg-white disabled:opacity-50', fieldErrors.nama_produk ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-orange-500']">
                            <option value="" disabled>{{ loadingProduk ? 'Memuat produk...' : 'Pilih produk dari Restock' }}</option>
                            <option v-for="produk in produkOptions" :key="produk" :value="produk">{{ produk }}</option>
                        </select>
                        <p v-if="!loadingProduk && produkOptions.length === 0" class="text-xs text-gray-400">Belum ada produk di Restock. Tambahkan data Restock dulu.</p>
                        <p v-if="fieldErrors.nama_produk" class="text-xs text-red-600">{{ fieldErrors.nama_produk[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Qty</label>
                        <input type="number" v-model.number="form.qty" min="1" required
                            :class="['border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2', fieldErrors.qty ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-orange-500']" />
                        <p v-if="fieldErrors.qty" class="text-xs text-red-600">{{ fieldErrors.qty[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Keterangan (opsional)</label>
                        <input type="text" v-model="form.keterangan" placeholder="cth: terjual, rusak"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    </div>
                    <button type="submit" :disabled="submitting"
                        class="bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-medium rounded-lg px-4 py-2 transition h-[38px] mt-5">
                        {{ submitting ? 'Menyimpan...' : 'Catat' }}
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-sm font-semibold text-gray-900">Riwayat Stok Keluar</h2>
                    <input v-model="searchQuery" type="text" placeholder="Cari nama produk..."
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>

                <p v-if="loading" class="text-sm text-gray-500 py-6 text-center">Memuat data...</p>
                <p v-else-if="errorMsg" class="text-sm text-red-600 py-6 text-center">{{ errorMsg }}</p>

                <template v-else>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-orange-50 text-gray-700">
                                    <th class="text-left px-3 py-2 font-semibold">No</th>
                                    <th class="text-left px-3 py-2 font-semibold">Tanggal Keluar</th>
                                    <th class="text-left px-3 py-2 font-semibold">Nama Produk</th>
                                    <th class="text-left px-3 py-2 font-semibold">Qty</th>
                                    <th class="text-left px-3 py-2 font-semibold">Keterangan</th>
                                    <th class="text-left px-3 py-2 font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in items" :key="item.id" class="border-t border-gray-100 hover:bg-gray-50">
                                    <td class="px-3 py-2">{{ item.No }}</td>
                                    <td class="px-3 py-2">{{ item['Tanggal Keluar'] }}</td>
                                    <td class="px-3 py-2">{{ item['Nama Produk'] }}</td>
                                    <td class="px-3 py-2">{{ item.QTY }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ item.Keterangan || '-' }}</td>
                                    <td class="px-3 py-2">
                                        <button @click="deleteItem(item)" class="text-xs px-2 py-1 border border-red-300 text-red-600 rounded-md hover:bg-red-50">Hapus</button>
                                    </td>
                                </tr>
                                <tr v-if="items.length === 0">
                                    <td colspan="6" class="text-center text-gray-400 py-8">Belum ada data stok keluar.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between mt-4">
                        <p class="text-xs text-gray-500">Halaman {{ currentPage }} dari {{ lastPage }} ({{ total }} total data)</p>
                        <div class="flex gap-2">
                            <button @click="goToPage(currentPage - 1)" :disabled="currentPage <= 1"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50">‹ Prev</button>
                            <button @click="goToPage(currentPage + 1)" :disabled="currentPage >= lastPage"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50">Next ›</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px); }
</style>