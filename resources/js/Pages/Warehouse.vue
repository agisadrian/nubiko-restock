<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';

interface ProdukPerWarehouse {
    nama_produk: string;
    total_qty: number;
    jumlah_kiriman: number;
}

interface WarehouseItem {
    warehouse: string;
    total_qty: number;
    total_kiriman: number;
    total_produk: number;
    produk: ProdukPerWarehouse[];
}

const items = ref<WarehouseItem[]>([]);
const loading = ref(true);
const expanded = ref<Set<string>>(new Set());

function toggleExpand(warehouse: string) {
    if (expanded.value.has(warehouse)) {
        expanded.value.delete(warehouse);
    } else {
        expanded.value.add(warehouse);
    }
    expanded.value = new Set(expanded.value);
}

async function fetchData() {
    loading.value = true;
    try {
        const res = await fetch('/api/warehouse');
        const json = await res.json();
        items.value = json.data;
    } catch (e) {
        console.error('Gagal memuat data warehouse', e);
    } finally {
        loading.value = false;
    }
}

onMounted(fetchData);
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto">
            <div class="mb-6">
                <h1 class="text-lg font-semibold text-gray-900">Warehouse</h1>
                <p class="text-sm text-gray-500">Ringkasan pengiriman per warehouse & produk yang pernah dikirim</p>
            </div>

            <p v-if="loading" class="text-sm text-gray-500 py-6 text-center">Memuat data...</p>
            <p v-else-if="items.length === 0" class="text-sm text-gray-400 py-6 text-center">Belum ada data.</p>

            <div v-else class="flex flex-col gap-3">
                <div v-for="w in items" :key="w.warehouse" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <button @click="toggleExpand(w.warehouse)" class="w-full flex items-center justify-between gap-3 p-4 hover:bg-gray-50 transition text-left">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center font-semibold text-xs">
                                {{ w.warehouse.slice(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ w.warehouse }}</p>
                                <p class="text-xs text-gray-500">{{ w.total_produk }} jenis produk · {{ w.total_kiriman }} kiriman</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-xs text-gray-500">Total Qty</p>
                                <p class="text-lg font-bold text-gray-900">{{ w.total_qty.toLocaleString() }}</p>
                            </div>
                            <span class="text-gray-400 text-sm">{{ expanded.has(w.warehouse) ? '▲' : '▼' }}</span>
                        </div>
                    </button>

                    <div v-if="expanded.has(w.warehouse)" class="border-t border-gray-100 px-4 py-3 bg-gray-50">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-gray-500">
                                    <th class="text-left py-1.5 font-medium">Nama Produk</th>
                                    <th class="text-left py-1.5 font-medium">Total Qty</th>
                                    <th class="text-left py-1.5 font-medium">Jumlah Kiriman</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in w.produk" :key="p.nama_produk" class="border-t border-gray-200">
                                    <td class="py-1.5">{{ p.nama_produk }}</td>
                                    <td class="py-1.5 font-medium">{{ p.total_qty }}</td>
                                    <td class="py-1.5 text-gray-500">{{ p.jumlah_kiriman }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>