<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { Line, Doughnut, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    BarElement,
    CategoryScale,
    LinearScale,
    PointElement,
    ArcElement,
    Filler,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, BarElement, CategoryScale, LinearScale, PointElement, ArcElement, Filler);

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

interface TrendData { periode: string; total_qty: number; total_kiriman?: number; }
interface KotaData { kota: string; total_qty: number; }
interface ProdukItem { nama_produk: string; sisa_stok: number; }

const loading = ref(true);
const restockTrend = ref<TrendData[]>([]);
const stockOutTrend = ref<TrendData[]>([]);
const byKota = ref<KotaData[]>([]);
const produkMenipis = ref<ProdukItem[]>([]);

const selectedKota = ref<string | null>(null);
const produkPerKota = ref<{ nama_produk: string; total_qty: number }[]>([]);
const loadingDetail = ref(false);
const lainnyaDetail = ref<{ kota: string; total_qty: number }[]>([]);
const viewMode = ref<'none' | 'lainnya-list' | 'produk-detail'>('none');

function selectKota(kota: string) {
    if (kota === 'Lainnya') {
        if (viewMode.value === 'lainnya-list') {
            viewMode.value = 'none';
            return;
        }
        viewMode.value = 'lainnya-list';
        selectedKota.value = null;
        return;
    }

    if (selectedKota.value === kota && viewMode.value === 'produk-detail') {
        viewMode.value = 'none';
        selectedKota.value = null;
        return;
    }
    loadProdukDetail(kota);
}

async function loadProdukDetail(kota: string) {
    selectedKota.value = kota;
    viewMode.value = 'produk-detail';
    loadingDetail.value = true;
    try {
        const res = await fetch('/api/warehouse');
        const json = await res.json();
        const found = json.data.find((w: any) => w.warehouse === kota);
        produkPerKota.value = found ? found.produk : [];
    } catch (e) {
        produkPerKota.value = [];
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetail() {
    viewMode.value = 'none';
    selectedKota.value = null;
}
const granularity = ref<'day' | 'month'>('month');
const chartDateFrom = ref('');
const chartDateTo = ref('');

const chartPresets = [
    { label: '30 Hari', days: 30 },
    { label: '3 Bulan', days: 90 },
    { label: '6 Bulan', days: 180 },
    { label: '1 Tahun', days: 365 },
];

function formatDateForApi(date: Date): string {
    return date.toISOString().split('T')[0];
}

function applyChartPreset(days: number) {
    const today = new Date();
    const from = new Date();
    from.setDate(today.getDate() - days);
    chartDateFrom.value = formatDateForApi(from);
    chartDateTo.value = formatDateForApi(today);
}

function clearChartDate() {
    chartDateFrom.value = '';
    chartDateTo.value = '';
}

async function fetchChartData() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (chartDateFrom.value) params.set('date_from', chartDateFrom.value);
        if (chartDateTo.value) params.set('date_to', chartDateTo.value);
        params.set('granularity', granularity.value);

        const headers = { 'X-CSRF-TOKEN': getCsrfToken() };

        const [restockRes, stockOutRes, produkRes] = await Promise.all([
            fetch(`/api/restock/chart-stats?${params.toString()}`, { headers }),
            fetch(`/api/stock-out/chart-stats?${params.toString()}`, { headers }),
            fetch('/api/produk', { headers }),
        ]);

        const restockJson = await restockRes.json();
        const stockOutJson = await stockOutRes.json();
        const produkJson = await produkRes.json();

        restockTrend.value = restockJson.monthly;
        byKota.value = restockJson.by_kota;
        stockOutTrend.value = stockOutJson.data;
        restockTrend.value = restockJson.monthly;
        byKota.value = restockJson.by_kota;
        lainnyaDetail.value = restockJson.lainnya_detail || [];

        produkMenipis.value = (produkJson.data as ProdukItem[])
            .slice()
            .sort((a, b) => a.sisa_stok - b.sisa_stok)
            .slice(0, 8);
    } catch (e) {
        console.error('Gagal memuat data chart', e);
    } finally {
        loading.value = false;
    }
}

watch([chartDateFrom, chartDateTo, granularity], () => fetchChartData());
onMounted(() => applyChartPreset(180));

// Gabungkan periode dari restock & stock-out biar sumbu-X sinkron
const combinedLabels = computed(() => {
    const labels = new Set([...restockTrend.value.map(t => t.periode), ...stockOutTrend.value.map(t => t.periode)]);
    return Array.from(labels);
});

function makeGradient(ctx: CanvasRenderingContext2D, area: any, colorTop: string, colorBottom: string) {
    const gradient = ctx.createLinearGradient(0, area.top, 0, area.bottom);
    gradient.addColorStop(0, colorTop);
    gradient.addColorStop(1, colorBottom);
    return gradient;
}

const lineChartData = computed(() => {
    const masukMap = new Map(restockTrend.value.map(t => [t.periode, t.total_qty]));
    const keluarMap = new Map(stockOutTrend.value.map(t => [t.periode, t.total_qty]));
    return {
        labels: combinedLabels.value,
        datasets: [
            {
                label: 'Qty Masuk (Restock)',
                data: combinedLabels.value.map(l => masukMap.get(l) ?? 0),
                borderColor: '#f97316',
                backgroundColor: (context: any) => {
                    const { ctx, chartArea } = context.chart;
                    if (!chartArea) return 'rgba(249,115,22,0.15)';
                    return makeGradient(ctx, chartArea, 'rgba(249,115,22,0.35)', 'rgba(249,115,22,0.02)');
                },
                tension: 0.45,
                fill: true,
                borderWidth: 3,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointBackgroundColor: '#f97316',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverBorderWidth: 2,
            },
            {
                label: 'Qty Keluar',
                data: combinedLabels.value.map(l => keluarMap.get(l) ?? 0),
                borderColor: '#8b5cf6',
                backgroundColor: (context: any) => {
                    const { ctx, chartArea } = context.chart;
                    if (!chartArea) return 'rgba(139,92,246,0.15)';
                    return makeGradient(ctx, chartArea, 'rgba(139,92,246,0.35)', 'rgba(139,92,246,0.02)');
                },
                tension: 0.45,
                fill: true,
                borderWidth: 3,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointBackgroundColor: '#8b5cf6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverBorderWidth: 2,
            },
        ],
    };
});

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: {
        legend: { position: 'bottom' as const, labels: { usePointStyle: true, boxWidth: 8 } },
        tooltip: {
            backgroundColor: '#fff',
            titleColor: '#111827',
            bodyColor: '#374151',
            borderColor: '#e5e7eb',
            borderWidth: 1,
            padding: 10,
            cornerRadius: 8,
            displayColors: true,
        },
    },
    scales: {
        y: { beginAtZero: true, grid: { color: '#f1f5f9', drawTicks: false }, border: { display: false } },
        x: { grid: { display: false }, border: { display: false } },
    },
};

const donutColors = ['#f97316', '#fb923c', '#fdba74', '#ec4899', '#8b5cf6', '#06b6d4', '#10b981', '#94a3b8'];

const donutChartData = computed(() => ({
    labels: byKota.value.map(k => k.kota),
    datasets: [{ data: byKota.value.map(k => k.total_qty), backgroundColor: donutColors, borderWidth: 0 }],
}));

const donutChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    onClick: (_event: any, elements: any[]) => {
        if (elements.length > 0) {
            const index = elements[0].index;
            const kota = byKota.value[index]?.kota;
            if (kota) selectKota(kota);
        }
    },
    plugins: { legend: { position: 'right' as const, labels: { boxWidth: 10, font: { size: 11 } } } },
};

const barChartData = computed(() => ({
    labels: produkMenipis.value.map(p => p.nama_produk.length > 20 ? p.nama_produk.slice(0, 20) + '…' : p.nama_produk),
    datasets: [{
        label: 'Sisa Stok',
        data: produkMenipis.value.map(p => p.sisa_stok),
        backgroundColor: produkMenipis.value.map(p => p.sisa_stok <= 0 ? '#ef4444' : p.sisa_stok <= 10 ? '#f59e0b' : '#10b981'),
        borderRadius: 6,
    }],
}));

const barChartOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } },
};

const totalQtyAllKota = computed(() => byKota.value.reduce((sum, k) => sum + k.total_qty, 0));
</script>

<template>
    <div class="mb-6">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div class="flex bg-white border border-gray-200 rounded-lg p-1">
                <button @click="granularity = 'day'"
                    :class="['px-3 py-1 text-xs font-medium rounded-md transition', granularity === 'day' ? 'bg-orange-500 text-white' : 'text-gray-500 hover:bg-gray-50']">
                    Harian
                </button>
                <button @click="granularity = 'month'"
                    :class="['px-3 py-1 text-xs font-medium rounded-md transition', granularity === 'month' ? 'bg-orange-500 text-white' : 'text-gray-500 hover:bg-gray-50']">
                    Bulanan
                </button>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <input type="date" v-model="chartDateFrom" class="border border-gray-300 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
                <span class="text-gray-400 text-xs">—</span>
                <input type="date" v-model="chartDateTo" class="border border-gray-300 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
                <button v-for="p in chartPresets" :key="p.label" @click="applyChartPreset(p.days)" type="button"
                    class="text-xs px-2 py-1 border border-gray-300 rounded-md hover:bg-gray-100 text-gray-600">
                    {{ p.label }}
                </button>
                <button @click="clearChartDate" type="button" class="text-xs px-2 py-1 border border-gray-300 rounded-md hover:bg-gray-100 text-gray-600">
                    Semua
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <!-- Line chart -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Tren Restock Masuk vs Keluar</h2>
                <div class="h-72">
                    <p v-if="loading" class="text-sm text-gray-400 text-center pt-24">Memuat grafik...</p>
                    <p v-else-if="combinedLabels.length === 0" class="text-sm text-gray-400 text-center pt-24">Tidak ada data untuk rentang ini.</p>
                    <Line v-else :data="lineChartData" :options="lineChartOptions" />
                </div>
            </div>

            <!-- Donut chart -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <h2 class="text-sm font-semibold text-gray-900 mb-1">Qty per Warehouse</h2>
    <p class="text-xs text-gray-400 mb-3">Klik salah satu bagian chart untuk detail produk</p>
    <div class="h-56 relative">
        <p v-if="loading" class="text-sm text-gray-400 text-center pt-20">Memuat...</p>
        <p v-else-if="byKota.length === 0" class="text-sm text-gray-400 text-center pt-20">Tidak ada data.</p>
        <Doughnut v-else :data="donutChartData" :options="donutChartOptions" />
    </div>
    <p v-if="!loading && byKota.length > 0" class="text-center text-xs text-gray-500 mt-2 mb-3">
        Total: <span class="font-semibold text-gray-800">{{ totalQtyAllKota.toLocaleString() }}</span> qty
    </p>

    <!-- Panel: daftar warehouse di dalam "Lainnya" -->
<div v-if="viewMode === 'lainnya-list'" class="border-t border-gray-100 pt-3 mt-2">
    <div class="flex items-center justify-between mb-2">
        <p class="text-xs font-semibold text-gray-700">📦 Warehouse dalam "Lainnya"</p>
        <button @click="closeDetail" class="text-xs text-gray-400 hover:text-gray-600">✕ Tutup</button>
    </div>
    <p class="text-xs text-gray-400 mb-2">Klik salah satu untuk lihat detail produknya</p>
    <div class="max-h-48 overflow-y-auto flex flex-col gap-1.5">
        <button v-for="w in lainnyaDetail" :key="w.kota" @click="loadProdukDetail(w.kota)"
            class="flex items-center justify-between text-xs bg-gray-50 hover:bg-indigo-50 rounded-md px-2 py-1.5 transition text-left">
            <span class="text-gray-700 truncate pr-2">{{ w.kota }}</span>
            <span class="font-semibold text-gray-900 shrink-0">{{ w.total_qty }}</span>
        </button>
        <p v-if="lainnyaDetail.length === 0" class="text-xs text-gray-400 text-center py-4">Tidak ada data.</p>
    </div>
</div>

<!-- Panel: breakdown produk per warehouse -->
<div v-if="viewMode === 'produk-detail'" class="border-t border-gray-100 pt-3 mt-2">
    <div class="flex items-center justify-between mb-2">
        <p class="text-xs font-semibold text-gray-700">📍 {{ selectedKota }}</p>
        <button @click="closeDetail" class="text-xs text-gray-400 hover:text-gray-600">✕ Tutup</button>
    </div>
    <p v-if="loadingDetail" class="text-xs text-gray-400 text-center py-4">Memuat...</p>
    <div v-else class="max-h-48 overflow-y-auto flex flex-col gap-1.5">
        <div v-for="p in produkPerKota" :key="p.nama_produk" class="flex items-center justify-between text-xs bg-gray-50 rounded-md px-2 py-1.5">
            <span class="text-gray-700 truncate pr-2">{{ p.nama_produk }}</span>
            <span class="font-semibold text-gray-900 shrink-0">{{ p.total_qty }}</span>
        </div>
        <p v-if="produkPerKota.length === 0" class="text-xs text-gray-400 text-center py-4">Tidak ada data produk.</p>
    </div>
</div>
</div>
        </div>

        <!-- Bar chart: produk stok menipis -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">8 Produk dengan Stok Paling Menipis</h2>
            <div class="h-64">
                <p v-if="loading" class="text-sm text-gray-400 text-center pt-24">Memuat...</p>
                <p v-else-if="produkMenipis.length === 0" class="text-sm text-gray-400 text-center pt-24">Belum ada data produk.</p>
                <Bar v-else :data="barChartData" :options="barChartOptions" />
            </div>
        </div>
    </div>
</template>