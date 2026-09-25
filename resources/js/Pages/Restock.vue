<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

const page = usePage();
const isAdmin = computed(() => (page.props.auth as any)?.user?.isAdmin ?? false);

interface RestockItem {
    No: number;
    Bulan: string;
    'Tanggal Kirim': string;
    Warehouse: string;
    'Nama Produk': string;
    QTY: number;
    Status: string;
    row_number: number;
}

interface Toast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

interface PreviewRow {
    tanggal_kirim: string | null;
    kota_asal_gudang: string;
    nama_produk: string;
    qty: number | null;
    status: string | null;
    errors: string[];
    valid: boolean;
}

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

const items = ref<RestockItem[]>([]);
const loading = ref(true);
const errorMsg = ref('');

const stats = ref({ total: 0, total_qty: 0, sudah: 0, belum: 0 });
const currentPage = ref(1);
const lastPage = ref(1);

const searchQuery = ref('');
const statusFilter = ref('');

const dateFrom = ref('');
const dateTo = ref('');

const presetRanges = [
    { label: 'Kemarin', days: 1 },
    { label: '7 hari terakhir', days: 7 },
    { label: '30 hari terakhir', days: 30 },
    { label: '90 hari terakhir', days: 90 },
    { label: '365 hari terakhir', days: 365 },
];

function formatDateForApi(date: Date): string {
    return date.toISOString().split('T')[0];
}

function applyPreset(days: number) {
    const today = new Date();
    const from = new Date();
    from.setDate(today.getDate() - (days - 1));
    dateFrom.value = formatDateForApi(from);
    dateTo.value = formatDateForApi(today);
}

function clearDateFilter() {
    dateFrom.value = '';
    dateTo.value = '';
}

const form = ref({
    tanggal_kirim: '',
    kota_asal_gudang: '',
    nama_produk: '',
    qty: 1,
    status: 'belum' as 'belum' | 'sudah',
});
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

async function fetchData(page = 1) {
    loading.value = true;
    errorMsg.value = '';
    try {
        const params = new URLSearchParams();
        params.set('page', String(page));
        if (searchQuery.value) params.set('search', searchQuery.value);
        if (statusFilter.value) params.set('status', statusFilter.value);
        if (dateFrom.value) params.set('date_from', dateFrom.value);
        if (dateTo.value) params.set('date_to', dateTo.value);

        const res = await fetch(`/api/restock?${params.toString()}`);
        if (!res.ok) throw new Error('Gagal ambil data');
        const json = await res.json();

        items.value = json.data;
        currentPage.value = json.current_page;
        lastPage.value = json.last_page;
        stats.value = json.stats;
    } catch (e) {
        errorMsg.value = 'Gagal memuat data. Coba refresh halaman.';
        showToast('error', 'Gagal memuat data dari server.');
    } finally {
        loading.value = false;
    }
}

watch([searchQuery, statusFilter, dateFrom, dateTo], () => {
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
        const res = await fetch('/api/restock', {
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
            showToast('error', 'Beberapa isian belum valid. Cek form di bawah.');
            return;
        }
        if (!res.ok) throw new Error('Gagal menambah data');

        form.value = { tanggal_kirim: '', kota_asal_gudang: '', nama_produk: '', qty: 1, status: 'belum' };
        showToast('success', 'Data restock berhasil ditambahkan.');
        await fetchData(currentPage.value);
    } catch (e) {
        showToast('error', 'Gagal menambah data. Coba lagi.');
    } finally {
        submitting.value = false;
    }
}

async function toggleStatus(item: RestockItem) {
    const newStatus = item.Status === 'Sudah Inbound' ? 'belum' : 'sudah';
    try {
        const res = await fetch(`/api/restock/${item.row_number}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ status: newStatus }),
        });
        if (!res.ok) throw new Error('Gagal update status');
        showToast('success', `Status "${item['Nama Produk']}" diperbarui.`);
        await fetchData(currentPage.value);
    } catch (e) {
        showToast('error', 'Gagal update status.');
    }
}

async function deleteItem(item: RestockItem) {
    if (!confirm(`Hapus data "${item['Nama Produk']}"?`)) return;
    try {
        const res = await fetch(`/api/restock/${item.row_number}`, {
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

const showImportModal = ref(false);
const previewRows = ref<PreviewRow[]>([]);
const confirmingImport = ref(false);
const importProgress = ref(0);
const importPhase = ref<'idle' | 'importing' | 'done'>('idle');
const importedCount = ref(0);

const validRowsCount = computed(() => previewRows.value.filter(r => r.valid).length);
const invalidRowsCount = computed(() => previewRows.value.filter(r => !r.valid).length);

async function handleImport(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    importing.value = true;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const res = await fetch('/api/restock/import-preview', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            body: formData,
        });
        if (!res.ok) throw new Error('Gagal membaca file');
        const json = await res.json();
        previewRows.value = json.data;
        showImportModal.value = true;
    } catch (e) {
        showToast('error', 'Gagal membaca file. Cek format kolomnya ya.');
    } finally {
        importing.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

async function confirmImport(onlyValid: boolean) {
    const rowsToImport = onlyValid ? previewRows.value.filter(r => r.valid) : previewRows.value;
    if (rowsToImport.length === 0) {
        showToast('error', 'Tidak ada data valid untuk diimport.');
        return;
    }

    confirmingImport.value = true;
    importPhase.value = 'importing';
    importProgress.value = 0;
    importedCount.value = 0;

    const batchSize = 100;
    const batches: typeof rowsToImport[] = [];
    for (let i = 0; i < rowsToImport.length; i += batchSize) {
        batches.push(rowsToImport.slice(i, i + batchSize));
    }

    try {
        for (let i = 0; i < batches.length; i++) {
            const res = await fetch('/api/restock/import-confirm', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ rows: batches[i] }),
            });
            if (!res.ok) throw new Error('Import gagal di batch ' + (i + 1));

            importedCount.value += batches[i].length;
            importProgress.value = Math.round(((i + 1) / batches.length) * 100);
        }

        importPhase.value = 'done';
        showToast('success', `${importedCount.value} data berhasil diimport!`);
        setTimeout(() => {
            showImportModal.value = false;
            previewRows.value = [];
            importPhase.value = 'idle';
        }, 1200);
        await fetchData(1);
    } catch (e) {
        showToast('error', `Gagal import. ${importedCount.value} data sempat masuk sebelum error.`);
        importPhase.value = 'idle';
    } finally {
        confirmingImport.value = false;
    }
}

function cancelImport() {
    showImportModal.value = false;
    previewRows.value = [];
}

onMounted(() => fetchData(1));
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

        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Restock Masuk</h1>
                    <p class="text-sm text-gray-500">Kelola data pengiriman restock dari warehouse</p>
                </div>
                <div class="flex items-center gap-2">
                    <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="handleImport" />
                    <button @click="fileInput?.click()" :disabled="importing"
                        class="bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-50 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 transition">
                        {{ importing ? 'Membaca file...' : '📥 Import Excel/CSV' }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Entri</p>
                    <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Qty</p>
                    <p class="text-2xl font-bold text-gray-900">{{ stats.total_qty }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Sudah Inbound</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ stats.sudah }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Belum Inbound</p>
                    <p class="text-2xl font-bold text-amber-600">{{ stats.belum }}</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Tambah Restock Baru</h2>
                <form @submit.prevent="submitForm" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-start">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Tanggal Kirim</label>
                        <input type="date" v-model="form.tanggal_kirim" required
                            :class="['border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2', fieldErrors.tanggal_kirim ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-indigo-500']" />
                        <p v-if="fieldErrors.tanggal_kirim" class="text-xs text-red-600">{{ fieldErrors.tanggal_kirim[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Warehouse</label>
                        <input type="text" v-model="form.kota_asal_gudang" required placeholder="Bandung"
                            :class="['border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2', fieldErrors.kota_asal_gudang ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-indigo-500']" />
                        <p v-if="fieldErrors.kota_asal_gudang" class="text-xs text-red-600">{{ fieldErrors.kota_asal_gudang[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Nama Produk</label>
                        <input type="text" v-model="form.nama_produk" required placeholder="Nubiko Serum 30ml"
                            :class="['border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2', fieldErrors.nama_produk ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-indigo-500']" />
                        <p v-if="fieldErrors.nama_produk" class="text-xs text-red-600">{{ fieldErrors.nama_produk[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Qty</label>
                        <input type="number" v-model.number="form.qty" min="1" required
                            :class="['border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2', fieldErrors.qty ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-indigo-500']" />
                        <p v-if="fieldErrors.qty" class="text-xs text-red-600">{{ fieldErrors.qty[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">Status</label>
                        <select v-model="form.status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="belum">Belum Inbound</option>
                            <option value="sudah">Sudah Inbound</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="submitting"
                        class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg px-4 py-2 transition h-[38px] mt-5">
                        {{ submitting ? 'Menyimpan...' : 'Tambah' }}
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex flex-col gap-3 mb-4">
                    <h2 class="text-sm font-semibold text-gray-900">Daftar Restock</h2>
                    <div class="flex flex-wrap gap-2 items-center">
                        <input v-model="searchQuery" type="text" placeholder="Cari nama produk / warehouse..."
                            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <select v-model="statusFilter" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Status</option>
                            <option value="sudah">Sudah Inbound</option>
                            <option value="belum">Belum Inbound</option>
                        </select>
                        <input type="date" v-model="dateFrom"
                            class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <span class="text-gray-400 text-sm">—</span>
                        <input type="date" v-model="dateTo"
                            class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <button v-for="preset in presetRanges" :key="preset.label" @click="applyPreset(preset.days)" type="button"
                            class="text-xs px-2 py-1 border border-gray-300 rounded-md hover:bg-gray-100 text-gray-600">
                            {{ preset.label }}
                        </button>
                        <button @click="clearDateFilter" type="button"
                            class="text-xs px-2 py-1 border border-gray-300 rounded-md hover:bg-gray-100 text-gray-600">
                            Reset
                        </button>
                    </div>
                </div>

                <p v-if="loading" class="text-sm text-gray-500 py-6 text-center">Memuat data...</p>
                <p v-else-if="errorMsg" class="text-sm text-red-600 py-6 text-center">{{ errorMsg }}</p>

                <template v-else>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-indigo-50 text-gray-700">
                                    <th class="text-left px-3 py-2 font-semibold">No</th>
                                    <th class="text-left px-3 py-2 font-semibold">Bulan</th>
                                    <th class="text-left px-3 py-2 font-semibold">Tanggal Kirim</th>
                                    <th class="text-left px-3 py-2 font-semibold">Warehouse</th>
                                    <th class="text-left px-3 py-2 font-semibold">Nama Produk</th>
                                    <th class="text-left px-3 py-2 font-semibold">Qty</th>
                                    <th class="text-left px-3 py-2 font-semibold">Status</th>
                                    <th class="text-left px-3 py-2 font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in items" :key="item.row_number" class="border-t border-gray-100 hover:bg-gray-50">
                                    <td class="px-3 py-2">{{ item.No }}</td>
                                    <td class="px-3 py-2">{{ item.Bulan }}</td>
                                    <td class="px-3 py-2">{{ item['Tanggal Kirim'] }}</td>
                                    <td class="px-3 py-2">{{ item.Warehouse }}</td>
                                    <td class="px-3 py-2">{{ item['Nama Produk'] }}</td>
                                    <td class="px-3 py-2">{{ item.QTY }}</td>
                                    <td class="px-3 py-2">
                                        <span :class="['inline-block px-2.5 py-1 rounded-full text-xs font-medium', item.Status === 'Sudah Inbound' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
                                            {{ item.Status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 space-x-2">
                                        <button @click="toggleStatus(item)" class="text-xs px-2 py-1 border border-gray-300 rounded-md hover:bg-gray-100">Toggle</button>
                                        <button v-if="isAdmin" @click="deleteItem(item)" class="text-xs px-2 py-1 border border-red-300 text-red-600 rounded-md hover:bg-red-50">Hapus</button>
                                    </td>
                                </tr>
                                <tr v-if="items.length === 0">
                                    <td colspan="8" class="text-center text-gray-400 py-8">Tidak ada data yang cocok.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between mt-4">
                        <p class="text-xs text-gray-500">Halaman {{ currentPage }} dari {{ lastPage }} ({{ stats.total }} total data)</p>
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

        <div v-if="showImportModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-lg w-full max-w-3xl max-h-[85vh] flex flex-col">
                <div class="p-5 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Preview Import</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        <span class="text-emerald-600 font-medium">{{ validRowsCount }} baris valid</span>
                        <span v-if="invalidRowsCount > 0" class="text-red-600 font-medium"> · {{ invalidRowsCount }} baris bermasalah</span>
                    </p>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-gray-50 text-gray-600">
                                    <th class="text-left px-2 py-2 font-semibold">#</th>
                                    <th class="text-left px-2 py-2 font-semibold">Tanggal</th>
                                    <th class="text-left px-2 py-2 font-semibold">Warehouse</th>
                                    <th class="text-left px-2 py-2 font-semibold">Produk</th>
                                    <th class="text-left px-2 py-2 font-semibold">Qty</th>
                                    <th class="text-left px-2 py-2 font-semibold">Status</th>
                                    <th class="text-left px-2 py-2 font-semibold">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, idx) in previewRows" :key="idx"
                                    :class="row.valid ? '' : 'bg-red-50'" class="border-t border-gray-100">
                                    <td class="px-2 py-1.5">{{ idx + 1 }}</td>
                                    <td class="px-2 py-1.5">{{ row.tanggal_kirim || '-' }}</td>
                                    <td class="px-2 py-1.5">{{ row.kota_asal_gudang || '-' }}</td>
                                    <td class="px-2 py-1.5">{{ row.nama_produk || '-' }}</td>
                                    <td class="px-2 py-1.5">{{ row.qty ?? '-' }}</td>
                                    <td class="px-2 py-1.5">{{ row.status || '-' }}</td>
                                    <td class="px-2 py-1.5">
                                        <span v-if="row.valid" class="text-emerald-600">✓ Valid</span>
                                        <span v-else class="text-red-600">{{ row.errors.join(', ') }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="importPhase === 'importing' || importPhase === 'done'" class="px-5 pb-3">
                    <div class="flex items-center justify-between text-xs text-gray-600 mb-1.5">
                        <span>{{ importPhase === 'done' ? 'Selesai!' : 'Mengimport data...' }}</span>
                        <span>{{ importedCount }} / {{ validRowsCount }} ({{ importProgress }}%)</span>
                    </div>
                    <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="importPhase === 'done' ? 'bg-emerald-500' : 'bg-indigo-500'"
                            :style="{ width: importProgress + '%' }"
                        ></div>
                    </div>
                </div>

                <div v-if="importPhase === 'idle'" class="p-5 border-t border-gray-100 flex items-center justify-end gap-2">
                    <button @click="cancelImport" type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Batal
                    </button>
                    <button v-if="invalidRowsCount > 0" @click="confirmImport(true)" :disabled="confirmingImport || validRowsCount === 0" type="button"
                        class="px-4 py-2 text-sm font-medium text-white bg-amber-500 rounded-lg hover:bg-amber-600 disabled:opacity-50">
                        Import {{ validRowsCount }} yang Valid Saja
                    </button>
                    <button v-else @click="confirmImport(false)" :disabled="confirmingImport" type="button"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        {{ confirmingImport ? 'Mengimport...' : `Import ${validRowsCount} Data` }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(30px); }
</style>