<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import RestockCharts from '../Components/RestockCharts.vue';

interface ProdukItem {
    nama_produk: string;
    total_masuk: number;
    total_keluar: number;
    sisa_stok: number;
}

const restockStats = ref({ total: 0, total_qty: 0, sudah: 0, belum: 0 });
const produkItems = ref<ProdukItem[]>([]);
const loading = ref(true);

async function fetchOverview() {
    loading.value = true;
    try {
        const [restockRes, produkRes] = await Promise.all([
            fetch('/api/restock?page=1'),
            fetch('/api/produk'),
        ]);
        const restockJson = await restockRes.json();
        const produkJson = await produkRes.json();

        restockStats.value = restockJson.stats;
        produkItems.value = produkJson.data;
    } catch (e) {
        console.error('Gagal memuat overview', e);
    } finally {
        loading.value = false;
    }
}

const totalSisaStok = computed(() => produkItems.value.reduce((sum, i) => sum + i.sisa_stok, 0));
const produkStokMenipis = computed(() => produkItems.value.filter(i => i.sisa_stok <= 10 && i.sisa_stok >= 0).length);
const produkStokHabis = computed(() => produkItems.value.filter(i => i.sisa_stok <= 0).length);

onMounted(fetchOverview);
</script>

<template>
    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <div class="mb-6">
                <h1 class="text-lg font-semibold text-gray-900">Dashboard</h1>
                <p class="text-sm text-gray-500">Ringkasan restock, stok keluar, dan sisa stok produk</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Restock Masuk</p>
                    <p class="text-2xl font-bold text-gray-900">{{ restockStats.total }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Belum Inbound</p>
                    <p class="text-2xl font-bold text-amber-600">{{ restockStats.belum }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Sisa Stok</p>
                    <p class="text-2xl font-bold text-gray-900">{{ totalSisaStok.toLocaleString() }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Produk Stok Menipis/Habis</p>
                    <p class="text-2xl font-bold text-red-600">{{ produkStokMenipis + produkStokHabis }}</p>
                </div>
            </div>

            <RestockCharts />

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Produk Perlu Perhatian</h2>
                <p v-if="loading" class="text-sm text-gray-500 text-center py-6">Memuat...</p>
                <div v-else-if="produkStokMenipis + produkStokHabis === 0" class="text-sm text-gray-400 text-center py-6">
                    Semua produk stoknya aman 👍
                </div>
                <div v-else class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-orange-50 text-gray-700">
                                <th class="text-left px-3 py-2 font-semibold">Nama Produk</th>
                                <th class="text-left px-3 py-2 font-semibold">Sisa Stok</th>
                                <th class="text-left px-3 py-2 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in produkItems.filter(i => i.sisa_stok <= 10)" :key="item.nama_produk"
                                class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-2">{{ item.nama_produk }}</td>
                                <td class="px-3 py-2 font-semibold">{{ item.sisa_stok }}</td>
                                <td class="px-3 py-2">
                                    <span v-if="item.sisa_stok <= 0" class="inline-block px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Stok Habis</span>
                                    <span v-else class="inline-block px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Stok Menipis</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>