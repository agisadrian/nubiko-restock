<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

const page = usePage();
const isAdmin = computed(() => (page.props.auth as any)?.user?.isAdmin ?? false);

interface ProductItem {
    id: number;
    sku: string;
    nama_produk: string;
    kategori: string | null;
    harga: string;
    stok_minimum: number;
}

interface Toast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

const products = ref<ProductItem[]>([]);
const loading = ref(true);
const searchQuery = ref('');

const form = ref({
    sku: '',
    nama_produk: '',
    kategori: '',
    harga: 0,
    stok_minimum: 10,
});
const editingId = ref<number | null>(null);
const submitting = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});

const toasts = ref<Toast[]>([]);
let toastIdCounter = 0;
function showToast(type: 'success' | 'error', message: string) {
    const id = toastIdCounter++;
    toasts.value.push({ id, type, message });
    setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id); }, 4000);
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function fetchProducts() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (searchQuery.value) params.set('search', searchQuery.value);
        const res = await fetch(`/api/products?${params.toString()}`);
        const json = await res.json();
        products.value = json.data;
    } catch (e) {
        showToast('error', 'Gagal memuat daftar produk.');
    } finally {
        loading.value = false;
    }
}

watch(searchQuery, () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(fetchProducts, 400);
});

function resetForm() {
    form.value = { sku: '', nama_produk: '', kategori: '', harga: 0, stok_minimum: 10 };
    editingId.value = null;
    fieldErrors.value = {};
}

function startEdit(product: ProductItem) {
    editingId.value = product.id;
    form.value = {
        sku: product.sku,
        nama_produk: product.nama_produk,
        kategori: product.kategori || '',
        harga: parseFloat(product.harga),
        stok_minimum: product.stok_minimum,
    };
}

async function submitForm() {
    submitting.value = true;
    fieldErrors.value = {};
    try {
        const url = editingId.value ? `/api/products/${editingId.value}` : '/api/products';
        const method = editingId.value ? 'PATCH' : 'POST';

        const res = await fetch(url, {
            method,
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
        if (!res.ok) throw new Error();

        showToast('success', editingId.value ? 'Produk berhasil diupdate.' : 'Produk berhasil ditambahkan.');
        resetForm();
        await fetchProducts();
    } catch (e) {
        showToast('error', 'Gagal menyimpan produk.');
    } finally {
        submitting.value = false;
    }
}

async function deleteProduct(product: ProductItem) {
    if (!confirm(`Hapus produk "${product.nama_produk}"?`)) return;
    try {
        const res = await fetch(`/api/products/${product.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
        });
        if (!res.ok) throw new Error();
        showToast('success', 'Produk dihapus.');
        await fetchProducts();
    } catch (e) {
        showToast('error', 'Gagal menghapus produk.');
    }
}

function formatRupiah(value: string) {
    return 'Rp' + parseFloat(value).toLocaleString('id-ID');
}

onMounted(fetchProducts);
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
            <div class="mb-6">
                <h1 class="text-lg font-semibold text-gray-900">Master Produk</h1>
                <p class="text-sm text-gray-500">Kelola SKU, kategori, harga, dan ambang stok minimum</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ editingId ? 'Edit Produk' : 'Tambah Produk Baru' }}</h2>
                <form @submit.prevent="submitForm" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-start">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">SKU</label>
                        <input type="text" v-model="form.sku" required placeholder="NB-001"
                            :class="['border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2', fieldErrors.sku ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-indigo-500']" />
                        <p v-if="fieldErrors.sku" class="text-xs text-red-600">{{ fieldErrors.sku[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Nama Produk</label>
                        <input type="text" v-model="form.nama_produk" required placeholder="Nubiko Serum 30ml"
                            :class="['border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2', fieldErrors.nama_produk ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-indigo-500']" />
                        <p v-if="fieldErrors.nama_produk" class="text-xs text-red-600">{{ fieldErrors.nama_produk[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Kategori</label>
                        <input type="text" v-model="form.kategori" placeholder="Skincare"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Harga (Rp)</label>
                        <input type="number" v-model.number="form.harga" min="0" step="100"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Stok Minimum</label>
                        <input type="number" v-model.number="form.stok_minimum" min="0"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="flex gap-2 mt-5">
                        <button type="submit" :disabled="submitting"
                            class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg px-4 py-2 transition h-[38px]">
                            {{ submitting ? 'Menyimpan...' : (editingId ? 'Update' : 'Tambah') }}
                        </button>
                        <button v-if="editingId" type="button" @click="resetForm"
                            class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 h-[38px]">
                            Batal
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-sm font-semibold text-gray-900">Daftar Produk ({{ products.length }})</h2>
                    <input v-model="searchQuery" type="text" placeholder="Cari SKU / nama produk..."
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <p v-if="loading" class="text-sm text-gray-500 py-6 text-center">Memuat data...</p>

                <div v-else class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-indigo-50 text-gray-700">
                                <th class="text-left px-3 py-2 font-semibold">SKU</th>
                                <th class="text-left px-3 py-2 font-semibold">Nama Produk</th>
                                <th class="text-left px-3 py-2 font-semibold">Kategori</th>
                                <th class="text-left px-3 py-2 font-semibold">Harga</th>
                                <th class="text-left px-3 py-2 font-semibold">Stok Min.</th>
                                <th class="text-left px-3 py-2 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in products" :key="p.id" class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-2 font-mono text-xs">{{ p.sku }}</td>
                                <td class="px-3 py-2">{{ p.nama_produk }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ p.kategori || '-' }}</td>
                                <td class="px-3 py-2">{{ formatRupiah(p.harga) }}</td>
                                <td class="px-3 py-2">{{ p.stok_minimum }}</td>
                                <td class="px-3 py-2 space-x-2">
                                    <button @click="startEdit(p)" class="text-xs px-2 py-1 border border-gray-300 rounded-md hover:bg-gray-100">Edit</button>
                                    <button v-if="isAdmin" @click="deleteProduct(p)" class="text-xs px-2 py-1 border border-red-300 text-red-600 rounded-md hover:bg-red-50">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="products.length === 0">
                                <td colspan="6" class="text-center text-gray-400 py-8">Belum ada produk master.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px); }
</style>