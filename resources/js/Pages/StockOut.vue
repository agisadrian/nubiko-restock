<script setup lang="ts">
import { ref, onMounted, watch, computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { ClipboardList, PackageMinus, Search, FileSpreadsheet, FileText } from "lucide-vue-next";
import AppLayout from "../Layouts/AppLayout.vue";

const page = usePage();
const isAdmin = computed(
    () => (page.props.auth as any)?.user?.isAdmin ?? false,
);

interface StockOutItem {
    No: number;
    "Tanggal Keluar": string;
    SKU: string | null;
    "Nama Produk": string;
    QTY: number;
    Keterangan: string | null;
    id: number;
}

interface ProdukOption {
    sku: string;
    nama_produk: string;
    tipe: string;
}

interface PreviewRow {
    tanggal_keluar: string | null;
    sku: string | null;
    nama_produk: string;
    qty: number | null;
    keterangan: string | null;
    errors: string[];
    valid: boolean;
}

interface Toast {
    id: number;
    type: "success" | "error";
    message: string;
}

function getCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") || ""
    );
}

const items = ref<StockOutItem[]>([]);
const loading = ref(true);
const errorMsg = ref("");
const totalQty = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const searchQuery = ref("");
const dateFrom = ref("");
const dateTo = ref("");
const produkFilter = ref("");
const daftarProduk = ref<string[]>([]);

const presetRanges = [
    { label: 'Kemarin', days: 1 },
    { label: '7 hari', days: 7 },
    { label: '30 hari', days: 30 },
    { label: '90 hari', days: 90 },
    { label: '365 hari', days: 365 },
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

// ---- Pilihan produk (dari Master Produk) ----
const produkOptions = ref<ProdukOption[]>([]);
const produkSearch = ref("");

const form = ref({
    tanggal_keluar: "",
    sku: "",
    qty: 1,
    keterangan: "",
});
const submitting = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});

const filteredProduk = computed(() => {
    const q = produkSearch.value.trim().toLowerCase();
    if (!q) return produkOptions.value;
    return produkOptions.value.filter(
        (p) =>
            p.sku === form.value.sku ||
            `${p.sku} ${p.nama_produk}`.toLowerCase().includes(q),
    );
});

const selectedProduk = computed(
    () => produkOptions.value.find((p) => p.sku === form.value.sku) || null,
);

async function fetchDaftarProduk() {
    try {
        const res = await fetch("/api/products/options");
        const json = await res.json();
        produkOptions.value = json.data;
        daftarProduk.value = json.data.map((p: { nama_produk: string }) => p.nama_produk);
    } catch (e) {
        console.error("Gagal memuat daftar produk", e);
    }
}

// ---- Toast ----
const toasts = ref<Toast[]>([]);
let toastIdCounter = 0;
function showToast(type: "success" | "error", message: string) {
    const id = toastIdCounter++;
    toasts.value.push({ id, type, message });
    setTimeout(() => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }, 4000);
}

// ---- Data riwayat ----
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function fetchData(page = 1) {
    loading.value = true;
    errorMsg.value = "";
    try {
        const params = new URLSearchParams();
        if (produkFilter.value) params.set("nama_produk", produkFilter.value);
        params.set("page", String(page));
        if (searchQuery.value) params.set("search", searchQuery.value);
        if (dateFrom.value) params.set("date_from", dateFrom.value);
        if (dateTo.value) params.set("date_to", dateTo.value);

        const res = await fetch(`/api/stock-out?${params.toString()}`);
        if (!res.ok) throw new Error("Gagal ambil data");
        const json = await res.json();

        items.value = json.data;
        currentPage.value = json.current_page;
        lastPage.value = json.last_page;
        total.value = json.total;
        totalQty.value = json.total_qty;
    } catch (e) {
        errorMsg.value = "Gagal memuat data. Coba refresh halaman.";
        showToast("error", "Gagal memuat data dari server.");
    } finally {
        loading.value = false;
    }
}

watch([searchQuery, produkFilter, dateFrom, dateTo], () => {
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
        const res = await fetch("/api/stock-out", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": getCsrfToken(),
            },
            body: JSON.stringify(form.value),
        });

        if (res.status === 422) {
            const data = await res.json();
            fieldErrors.value = data.errors || {};
            showToast("error", "Beberapa isian belum valid.");
            return;
        }
        if (!res.ok) throw new Error("Gagal menambah data");

        form.value = { tanggal_keluar: "", sku: "", qty: 1, keterangan: "" };
        produkSearch.value = "";
        showToast("success", "Data stok keluar berhasil ditambahkan.");
        await fetchData(currentPage.value);
    } catch (e) {
        showToast("error", "Gagal menambah data. Coba lagi.");
    } finally {
        submitting.value = false;
    }
}

async function deleteItem(item: StockOutItem) {
    if (!confirm(`Hapus data "${item["Nama Produk"]}"?`)) return;
    try {
        const res = await fetch(`/api/stock-out/${item.id}`, {
            method: "DELETE",
            headers: { "X-CSRF-TOKEN": getCsrfToken() },
        });
        if (!res.ok) throw new Error("Gagal hapus data");
        showToast("success", "Data berhasil dihapus.");
        await fetchData(currentPage.value);
    } catch (e) {
        showToast("error", "Gagal menghapus data.");
    }
}

function exportStockOut(format: "excel" | "csv" | "pdf") {
    const params = new URLSearchParams();
    if (produkFilter.value) params.set("nama_produk", produkFilter.value);
    if (searchQuery.value) params.set("search", searchQuery.value);
    if (dateFrom.value) params.set("date_from", dateFrom.value);
    if (dateTo.value) params.set("date_to", dateTo.value);

    const url = `/api/stock-out/export-${format}?${params.toString()}`;
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", "");
    document.body.appendChild(link);
    link.click();
    link.remove();
}

// ---- Import dengan preview ----
const importing = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const showImportModal = ref(false);
const previewRows = ref<PreviewRow[]>([]);
const confirmingImport = ref(false);
const importProgress = ref(0);
const importPhase = ref<"idle" | "importing" | "done">("idle");
const importedCount = ref(0);

const validRowsCount = computed(
    () => previewRows.value.filter((r) => r.valid).length,
);
const invalidRowsCount = computed(
    () => previewRows.value.filter((r) => !r.valid).length,
);

async function handleImport(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    importing.value = true;
    const formData = new FormData();
    formData.append("file", file);

    try {
        const res = await fetch("/api/stock-out/import-preview", {
            method: "POST",
            headers: { "X-CSRF-TOKEN": getCsrfToken() },
            body: formData,
        });
        if (!res.ok) throw new Error("Gagal membaca file");
        const json = await res.json();

        if (!json.data || json.data.length === 0) {
            showToast("error", "Tidak ada baris data di file itu.");
            return;
        }

        previewRows.value = json.data;
        importPhase.value = "idle";
        showImportModal.value = true;
    } catch (e) {
        showToast(
            "error",
            "Gagal membaca file. Pastikan formatnya .xlsx, .xls, atau .csv.",
        );
    } finally {
        importing.value = false;
        if (fileInput.value) fileInput.value.value = "";
    }
}

async function confirmImport(onlyValid: boolean) {
    const source = onlyValid
        ? previewRows.value.filter((r) => r.valid)
        : previewRows.value;
    const rowsToImport = source.map((r) => ({
        tanggal_keluar: r.tanggal_keluar,
        sku: r.sku,
        qty: r.qty,
        keterangan: r.keterangan,
    }));

    if (rowsToImport.length === 0) {
        showToast("error", "Tidak ada data valid untuk diimport.");
        return;
    }

    confirmingImport.value = true;
    importPhase.value = "importing";
    importProgress.value = 0;
    importedCount.value = 0;

    const batchSize = 100;
    const batches: (typeof rowsToImport)[] = [];
    for (let i = 0; i < rowsToImport.length; i += batchSize) {
        batches.push(rowsToImport.slice(i, i + batchSize));
    }

    try {
        for (let i = 0; i < batches.length; i++) {
            const res = await fetch("/api/stock-out/import-confirm", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": getCsrfToken(),
                },
                body: JSON.stringify({ rows: batches[i] }),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => null);
                throw new Error(
                    data?.message || "Import gagal di batch " + (i + 1),
                );
            }

            importedCount.value += batches[i].length;
            importProgress.value = Math.round(((i + 1) / batches.length) * 100);
        }

        importPhase.value = "done";
        showToast("success", `${importedCount.value} data berhasil diimport!`);
        setTimeout(() => {
            showImportModal.value = false;
            previewRows.value = [];
            importPhase.value = "idle";
        }, 1200);
        await fetchData(1);
    } catch (e: any) {
        showToast(
            "error",
            `${e.message || "Gagal import."} ${importedCount.value} data sempat masuk sebelum error.`,
        );
        importPhase.value = "idle";
    } finally {
        confirmingImport.value = false;
    }
}

function cancelImport() {
    showImportModal.value = false;
    previewRows.value = [];
    importPhase.value = "idle";
}

onMounted(() => {
    fetchData(1);
    fetchDaftarProduk();
});
</script>

<template>
    <AppLayout>
        <div
            class="fixed top-16 lg:top-4 left-4 right-4 sm:left-auto z-[60] flex flex-col gap-2 sm:w-80 pointer-events-none"
        >
            <transition-group name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :class="[
                        'rounded-lg shadow-md px-4 py-3 text-sm font-medium text-white flex items-start gap-2',
                        toast.type === 'success'
                            ? 'bg-emerald-600'
                            : 'bg-red-600',
                    ]"
                >
                    <span>{{ toast.type === "success" ? "✓" : "✕" }}</span>
                    <span>{{ toast.message }}</span>
                </div>
            </transition-group>
        </div>

        <div class="max-w-5xl mx-auto">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6"
            >
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">
                        Stok Keluar
                    </h1>
                    <p class="text-sm text-gray-500">
                        Catat barang yang keluar dari gudang/toko
                    </p>
                </div>
                <div>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv,.xlsx,.xls"
                        class="hidden"
                        @change="handleImport"
                    />
                    <button
                        @click="fileInput?.click()"
                        :disabled="importing"
                        title="Kolom file: tanggal_keluar, sku, qty, keterangan"
                        class="w-full sm:w-auto bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-50 text-sm font-medium text-gray-700 rounded-lg px-4 py-2 transition"
                    >
                        {{
                            importing
                                ? "Membaca file..."
                                : "📥 Import Excel/CSV"
                        }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-6">
                <div
                    class="bg-white rounded-xl border border-gray-200 shadow-sm p-4"
                >
                    <div
                        class="w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center mb-3"
                    >
                        <ClipboardList :size="18" :stroke-width="2" />
                    </div>
                    <p class="text-xs text-gray-500 mb-1">
                        Total Transaksi Keluar
                    </p>
                    <p class="text-2xl font-bold text-gray-900">{{ total }}</p>
                </div>
                <div
                    class="bg-white rounded-xl border border-gray-200 shadow-sm p-4"
                >
                    <div
                        class="w-9 h-9 rounded-lg bg-red-100 text-red-600 flex items-center justify-center mb-3"
                    >
                        <PackageMinus :size="18" :stroke-width="2" />
                    </div>
                    <p class="text-xs text-gray-500 mb-1">Total Qty Keluar</p>
                    <p class="text-2xl font-bold text-red-600">
                        {{ totalQty }}
                    </p>
                </div>
            </div>

            <div
                class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5 mb-6"
            >
                <h2 class="text-sm font-semibold text-gray-900 mb-4">
                    Catat Stok Keluar
                </h2>
                <form @submit.prevent="submitForm" class="flex flex-col gap-4">
                    <div
                        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
                    >
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-gray-600"
                                >Tanggal Keluar</label
                            >
                            <input
                                type="date"
                                v-model="form.tanggal_keluar"
                                required
                                :class="[
                                    'border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2',
                                    fieldErrors.tanggal_keluar
                                        ? 'border-red-400 focus:ring-red-400'
                                        : 'border-gray-300 focus:ring-orange-500',
                                ]"
                            />
                            <p
                                v-if="fieldErrors.tanggal_keluar"
                                class="text-xs text-red-600"
                            >
                                {{ fieldErrors.tanggal_keluar[0] }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-gray-600"
                                >Qty</label
                            >
                            <input
                                type="number"
                                v-model.number="form.qty"
                                min="1"
                                required
                                :class="[
                                    'border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2',
                                    fieldErrors.qty
                                        ? 'border-red-400 focus:ring-red-400'
                                        : 'border-gray-300 focus:ring-orange-500',
                                ]"
                            />
                            <p
                                v-if="fieldErrors.qty"
                                class="text-xs text-red-600"
                            >
                                {{ fieldErrors.qty[0] }}
                            </p>
                        </div>
                        <div
                            class="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1"
                        >
                            <label class="text-xs font-medium text-gray-600"
                                >Keterangan (opsional)</label
                            >
                            <input
                                type="text"
                                v-model="form.keterangan"
                                placeholder="cth: terjual, rusak"
                                class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-gray-600"
                            >Produk (SKU)</label
                        >
                        <div
                            :class="[
                                'border rounded-lg overflow-hidden bg-white',
                                fieldErrors.sku
                                    ? 'border-red-400'
                                    : 'border-gray-300 focus-within:ring-2 focus-within:ring-orange-500',
                            ]"
                        >
                            <div class="relative border-b border-gray-200">
                                <Search
                                    :size="16"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                                />
                                <input
                                    type="text"
                                    v-model="produkSearch"
                                    placeholder="Cari SKU atau nama produk..."
                                    class="w-full pl-9 pr-3 py-2 text-sm focus:outline-none"
                                />
                            </div>
                            <select
                                v-model="form.sku"
                                required
                                class="w-full px-3 py-2.5 text-sm focus:outline-none bg-white"
                            >
                                <option value="" disabled>
                                    Pilih produk...
                                </option>
                                <option
                                    v-for="p in filteredProduk"
                                    :key="p.sku"
                                    :value="p.sku"
                                >
                                    {{ p.sku }} — {{ p.nama_produk
                                    }}{{
                                        p.tipe === "bundling"
                                            ? " (Bundling)"
                                            : ""
                                    }}
                                </option>
                            </select>
                        </div>
                        <p v-if="fieldErrors.sku" class="text-xs text-red-600">
                            {{ fieldErrors.sku[0] }}
                        </p>
                        <p
                            v-if="selectedProduk"
                            class="text-xs text-gray-500 flex items-center gap-1.5"
                        >
                            Tipe:
                            <span
                                :class="[
                                    'inline-block px-2 py-0.5 rounded-full text-xs font-medium',
                                    selectedProduk.tipe === 'bundling'
                                        ? 'bg-purple-100 text-purple-700'
                                        : 'bg-gray-100 text-gray-600',
                                ]"
                            >
                                {{
                                    selectedProduk.tipe === "bundling"
                                        ? "Bundling"
                                        : "Satuan"
                                }}
                            </span>
                        </p>
                        <p
                            v-if="produkOptions.length === 0"
                            class="text-xs text-gray-400"
                        >
                            Belum ada produk di Master Produk.
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-semibold rounded-lg px-5 py-2.5 transition w-full sm:w-auto sm:self-start"
                    >
                        {{ submitting ? "Menyimpan..." : "Catat Stok Keluar" }}
                    </button>
                </form>
            </div>

            <div
                class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5"
            >
                <div class="flex flex-col gap-3 mb-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">
                            Riwayat Stok Keluar
                        </h2>
                        <div class="flex flex-wrap items-center gap-2">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari SKU / nama produk..."
                                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-full sm:w-48 focus:outline-none focus:ring-2 focus:ring-orange-500"
                            />
                            <select v-model="produkFilter" class="w-full sm:w-64 truncate border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Semua Produk</option>
                                <option v-for="p in daftarProduk" :key="p" :value="p">{{ p }}</option>
                            </select>
                            <button
                                @click="exportStockOut('excel')"
                                type="button"
                                title="Download format Excel (.xlsx) dengan styling & ringkasan"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 border border-emerald-300 rounded-lg transition shadow-sm"
                            >
                                <FileSpreadsheet class="w-3.5 h-3.5 text-emerald-700" />
                                <span>Export Excel (.xlsx)</span>
                            </button>
                            <button
                                @click="exportStockOut('pdf')"
                                type="button"
                                title="Download format PDF (Cetak)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition shadow-sm"
                            >
                                <FileText class="w-3.5 h-3.5 text-rose-600" />
                                <span>Export PDF</span>
                            </button>
                        </div>
                    </div>
                    <!-- Date filter row -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <input
                                v-model="dateFrom"
                                type="date"
                                class="border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-orange-500"
                                title="Dari tanggal"
                            />
                            <span class="text-gray-400 text-xs">—</span>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-orange-500"
                                title="Sampai tanggal"
                            />
                        </div>
                        <button
                            v-for="r in presetRanges"
                            :key="r.days"
                            @click="applyPreset(r.days)"
                            type="button"
                            class="px-2 py-1 text-xs border border-gray-200 rounded-md hover:bg-orange-50 hover:border-orange-300 hover:text-orange-700 text-gray-600 transition"
                        >{{ r.label }}</button>
                        <button
                            v-if="dateFrom || dateTo"
                            @click="clearDateFilter"
                            type="button"
                            class="px-2 py-1 text-xs text-gray-400 hover:text-gray-600 underline"
                        >Reset</button>
                    </div>
                </div>

                <p
                    v-if="loading"
                    class="text-sm text-gray-500 py-6 text-center"
                >
                    Memuat data...
                </p>
                <p
                    v-else-if="errorMsg"
                    class="text-sm text-red-600 py-6 text-center"
                >
                    {{ errorMsg }}
                </p>

                <template v-else>
                    <div
                        class="overflow-x-auto rounded-lg border border-gray-200"
                    >
                        <table class="table-stack w-full text-sm">
                            <thead>
                                <tr class="bg-orange-50 text-gray-700">
                                    <th
                                        class="text-left px-3 py-2 font-semibold"
                                    >
                                        No
                                    </th>
                                    <th
                                        class="text-left px-3 py-2 font-semibold"
                                    >
                                        Tanggal Keluar
                                    </th>
                                    <th
                                        class="text-left px-3 py-2 font-semibold"
                                    >
                                        SKU
                                    </th>
                                    <th
                                        class="text-left px-3 py-2 font-semibold"
                                    >
                                        Nama Produk
                                    </th>
                                    <th
                                        class="text-left px-3 py-2 font-semibold"
                                    >
                                        Qty
                                    </th>
                                    <th
                                        class="text-left px-3 py-2 font-semibold"
                                    >
                                        Keterangan
                                    </th>
                                    <th
                                        class="text-left px-3 py-2 font-semibold"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in items"
                                    :key="item.id"
                                    class="border-t border-gray-100 hover:bg-gray-50"
                                >
                                    <td
                                        data-label="No"
                                        data-hide-mobile
                                        class="px-3 py-2"
                                    >
                                        {{ item.No }}
                                    </td>
                                    <td
                                        data-label="Tanggal Keluar"
                                        class="px-3 py-2"
                                    >
                                        {{ item["Tanggal Keluar"] }}
                                    </td>
                                    <td
                                        data-label="SKU"
                                        class="px-3 py-2 font-mono text-xs whitespace-nowrap"
                                    >
                                        {{ item.SKU || "-" }}
                                    </td>
                                    <td
                                        data-label="Nama Produk"
                                        class="px-3 py-2"
                                    >
                                        {{ item["Nama Produk"] }}
                                    </td>
                                    <td data-label="Qty" class="px-3 py-2">
                                        {{ item.QTY }}
                                    </td>
                                    <td
                                        data-label="Keterangan"
                                        class="px-3 py-2 text-gray-500"
                                    >
                                        {{ item.Keterangan || "-" }}
                                    </td>
                                    <td data-actions class="px-3 py-2">
                                        <button
                                            v-if="isAdmin"
                                            @click="deleteItem(item)"
                                            class="text-xs px-2 py-1 border border-red-300 text-red-600 rounded-md hover:bg-red-50"
                                        >
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="items.length === 0">
                                    <td
                                        colspan="7"
                                        class="text-center text-gray-400 py-8"
                                    >
                                        Belum ada data stok keluar.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3 mt-4"
                    >
                        <p class="text-xs text-gray-500">
                            Halaman {{ currentPage }} dari {{ lastPage }} ({{
                                total
                            }}
                            total data)
                        </p>
                        <div class="flex gap-2">
                            <button
                                @click="goToPage(currentPage - 1)"
                                :disabled="currentPage <= 1"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50"
                            >
                                ‹ Prev
                            </button>
                            <button
                                @click="goToPage(currentPage + 1)"
                                :disabled="currentPage >= lastPage"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50"
                            >
                                Next ›
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modal preview import -->
        <div
            v-if="showImportModal"
            class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-xl shadow-lg w-full max-w-3xl max-h-[85vh] flex flex-col"
            >
                <div class="p-5 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">
                        Preview Import Stok Keluar
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        <span class="text-emerald-600 font-medium"
                            >{{ validRowsCount }} baris valid</span
                        >
                        <span
                            v-if="invalidRowsCount > 0"
                            class="text-red-600 font-medium"
                        >
                            · {{ invalidRowsCount }} baris bermasalah</span
                        >
                    </p>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div
                        class="overflow-x-auto rounded-lg border border-gray-200"
                    >
                        <table class="w-full min-w-[640px] text-xs">
                            <thead>
                                <tr class="bg-gray-50 text-gray-600">
                                    <th
                                        class="text-left px-2 py-2 font-semibold"
                                    >
                                        #
                                    </th>
                                    <th
                                        class="text-left px-2 py-2 font-semibold"
                                    >
                                        Tanggal
                                    </th>
                                    <th
                                        class="text-left px-2 py-2 font-semibold"
                                    >
                                        SKU
                                    </th>
                                    <th
                                        class="text-left px-2 py-2 font-semibold"
                                    >
                                        Produk
                                    </th>
                                    <th
                                        class="text-left px-2 py-2 font-semibold"
                                    >
                                        Qty
                                    </th>
                                    <th
                                        class="text-left px-2 py-2 font-semibold"
                                    >
                                        Keterangan
                                    </th>
                                    <th
                                        class="text-left px-2 py-2 font-semibold"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, idx) in previewRows"
                                    :key="idx"
                                    :class="row.valid ? '' : 'bg-red-50'"
                                    class="border-t border-gray-100"
                                >
                                    <td class="px-2 py-1.5">{{ idx + 1 }}</td>
                                    <td class="px-2 py-1.5 whitespace-nowrap">
                                        {{ row.tanggal_keluar || "-" }}
                                    </td>
                                    <td
                                        class="px-2 py-1.5 font-mono whitespace-nowrap"
                                    >
                                        {{ row.sku || "-" }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        {{ row.nama_produk || "-" }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        {{ row.qty ?? "-" }}
                                    </td>
                                    <td class="px-2 py-1.5 text-gray-500">
                                        {{ row.keterangan || "-" }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <span
                                            v-if="row.valid"
                                            class="text-emerald-600"
                                            >✓ Valid</span
                                        >
                                        <span v-else class="text-red-600">{{
                                            row.errors.join(", ")
                                        }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="importPhase === 'importing' || importPhase === 'done'"
                    class="px-5 pb-3"
                >
                    <div
                        class="flex items-center justify-between text-xs text-gray-600 mb-1.5"
                    >
                        <span>{{
                            importPhase === "done"
                                ? "Selesai!"
                                : "Mengimport data..."
                        }}</span>
                        <span
                            >{{ importedCount }} / {{ validRowsCount }} ({{
                                importProgress
                            }}%)</span
                        >
                    </div>
                    <div
                        class="w-full h-2 bg-gray-100 rounded-full overflow-hidden"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="
                                importPhase === 'done'
                                    ? 'bg-emerald-500'
                                    : 'bg-orange-500'
                            "
                            :style="{ width: importProgress + '%' }"
                        ></div>
                    </div>
                </div>

                <div
                    v-if="importPhase === 'idle'"
                    class="p-5 border-t border-gray-100 flex flex-wrap items-center justify-end gap-2"
                >
                    <button
                        @click="cancelImport"
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        Batal
                    </button>
                    <button
                        v-if="invalidRowsCount > 0"
                        @click="confirmImport(true)"
                        :disabled="confirmingImport || validRowsCount === 0"
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-white bg-amber-500 rounded-lg hover:bg-amber-600 disabled:opacity-50"
                    >
                        Import {{ validRowsCount }} yang Valid Saja
                    </button>
                    <button
                        v-else
                        @click="confirmImport(false)"
                        :disabled="confirmingImport"
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-white bg-orange-500 rounded-lg hover:bg-orange-600 disabled:opacity-50"
                    >
                        Import {{ validRowsCount }} Data
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
</style>
