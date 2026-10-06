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
const selected = ref<WarehouseItem | null>(null);
const produkSearch = ref('');

const colors = ['bg-orange-100 text-orange-600', 'bg-indigo-100 text-indigo-600', 'bg-emerald-100 text-emerald-600', 'bg-pink-100 text-pink-600', 'bg-sky-100 text-sky-600'];

function colorFor(index: number) {
    return colors[index % colors.length];
}

function openDetail(item: WarehouseItem) {
    selected.value = item;
    produkSearch.value = '';
}

function closeDetail() {
    selected.value = null;
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

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <button v-for="(w, index) in items" :key="w.warehouse" @click="openDetail(w)"
                    class="text-left bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-150">
                    <div class="flex items-center gap-3 mb-3">
                        <div :class="['w-9 h-9 rounded-lg flex items-center justify-center font-semibold text-xs shrink-0', colorFor(index)]">
                            {{ w.warehouse.slice(0, 2).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ w.warehouse }}</p>
                            <p class="text-xs text-gray-500">{{ w.total_produk }} jenis produk</p>
                        </div>
                    </div>
                    <p class="text-xl font-bold text-gray-900">{{ w.total_qty.toLocaleString() }}</p>
                    <p class="text-xs text-gray-400">{{ w.total_kiriman }} kiriman · lihat detail →</p>
                </button>
            </div>
        </div>

        <!-- Modal detail -->
        <transition name="modal">
            <div v-if="selected" @click.self="closeDetail" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-lg w-full max-w-lg max-h-[85dvh] flex flex-col">
                    <div class="p-4 sm:p-5 border-b border-gray-100 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">{{ selected.warehouse }}</h2>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ selected.total_produk }} jenis produk · {{ selected.total_kiriman }} kiriman ·
                                <span class="font-semibold text-gray-700">{{ selected.total_qty.toLocaleString() }} qty</span>
                            </p>
                        </div>
                        <button @click="closeDetail" class="text-gray-400 hover:text-gray-600 text-lg leading-none shrink-0 p-1 -m-1">✕</button>
                    </div>

                    <div class="p-5 pb-3">
                        <input v-model="produkSearch" type="text" placeholder="Cari produk..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    </div>

                    <div class="flex-1 overflow-y-auto px-5 pb-5">
                        <div class="flex flex-col gap-1.5">
                            <div v-for="p in selected.produk.filter(p => p.nama_produk.toLowerCase().includes(produkSearch.toLowerCase()))"
                                :key="p.nama_produk"
                                class="flex items-center justify-between text-sm bg-gray-50 rounded-lg px-3 py-2">
                                <span class="text-gray-700 truncate pr-2">{{ p.nama_produk }}</span>
                                <div class="text-right shrink-0">
                                    <span class="font-semibold text-gray-900">{{ p.total_qty }}</span>
                                    <span class="text-xs text-gray-400 ml-1">({{ p.jumlah_kiriman }}x)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </AppLayout>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
.modal-enter-active .bg-white, .modal-leave-active .bg-white {
    transition: transform 0.2s ease, opacity 0.2s ease;
}
.modal-enter-from .bg-white, .modal-leave-to .bg-white {
    transform: scale(0.96);
    opacity: 0;
}
</style>