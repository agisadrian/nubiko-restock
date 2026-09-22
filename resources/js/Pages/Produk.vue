<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';

interface ProdukItem {
    nama_produk: string;
    total_masuk: number;
    total_keluar: number;
    sisa_stok: number;
}

const items = ref<ProdukItem[]>([]);
const loading = ref(true);
const errorMsg = ref('');
const searchQuery = ref('');

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function fetchData() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const params = new URLSearchParams();
        if (searchQuery.value) params.set('search', searchQuery.value);

        const res = await fetch(`/api/produk?${params.toString()}`);
        if (!res.ok) throw new Error('Gagal ambil data');
        const json = await res.json();
        items.value = json.data;
    } catch (e) {
        errorMsg.value = 'Gagal memuat data. Coba refresh halaman.';
    } finally {
        loading.value = false;
    }
}

watch(searchQuery, () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(fetchData, 400);
});

const totalProduk = computed(() => items.value.length);
const totalSisaStok = computed(() => items.value.reduce((sum, i) => sum + i.sisa_stok, 0));
const produkStokMenipis = computed(() => items.value.filter(i => i.sisa_stok <= 10 && i.sisa_stok >= 0).length);
const produkStokHabis = computed(() => items.value.filter(i => i.sisa_stok <= 0).length);

onMounted(fetchData);
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto">
            <div class="mb-6">
                <h1 class="text-lg font-semibold text-gray-900">Produk</h1>
                <p class="text-sm text-gray-500">Sisa stok tiap produk (total masuk − total keluar)</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Produk</p>
                    <p class="text-2xl font-bold text-gray-900">{{ totalProduk }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Sisa Stok</p>
                    <p class="text-2xl font-bold text-gray-900">{{ totalSisaStok.toLocaleString() }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Stok Menipis (≤10)</p>
                    <p class="text-2xl font-bold text-amber-600">{{ produkStokMenipis }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Stok Habis</p>
                    <p class="text-2xl font-bold text-red-600">{{ produkStokHabis }}</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-sm font-semibold text-gray-900">Daftar Produk & Sisa Stok</h2>
                    <input v-model="searchQuery" type="text" placeholder="Cari nama produk..."
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>

                <p v-if="loading" class="text-sm text-gray-500 py-6 text-center">Memuat data...</p>
                <p v-else-if="errorMsg" class="text-sm text-red-600 py-6 text-center">{{ errorMsg }}</p>

                <div v-else class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-orange-50 text-gray-700">
                                <th class="text-left px-3 py-2 font-semibold">Nama Produk</th>
                                <th class="text-left px-3 py-2 font-semibold">Total Masuk</th>
                                <th class="text-left px-3 py-2 font-semibold">Total Keluar</th>
                                <th class="text-left px-3 py-2 font-semibold">Sisa Stok</th>
                                <th class="text-left px-3 py-2 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.nama_produk" class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-2">{{ item.nama_produk }}</td>
                                <td class="px-3 py-2 text-emerald-600">+{{ item.total_masuk }}</td>
                                <td class="px-3 py-2 text-red-500">-{{ item.total_keluar }}</td>
                                <td class="px-3 py-2 font-semibold">{{ item.sisa_stok }}</td>
                                <td class="px-3 py-2">
                                    <span v-if="item.sisa_stok <= 0" class="inline-block px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Stok Habis</span>
                                    <span v-else-if="item.sisa_stok <= 10" class="inline-block px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Stok Menipis</span>
                                    <span v-else class="inline-block px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Aman</span>
                                </td>
                            </tr>
                            <tr v-if="items.length === 0">
                                <td colspan="5" class="text-center text-gray-400 py-8">Belum ada data produk.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>