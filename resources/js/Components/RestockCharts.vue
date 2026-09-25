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
                backgroundColor: 'rgba(249,115,22,0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 3,
            },
            {
                label: 'Qty Keluar',
                data: combinedLabels.value.map(l => keluarMap.get(l) ?? 0),
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239,68,68,0.08)',
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                borderDash: [5, 4],
            },
        ],
    };
});

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: { legend: { position: 'bottom' as const, labels: { usePointStyle: true, boxWidth: 8 } } },
    scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } },
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
               <h2 class="text-sm font-semibold text-gray-900 mb-4">Qty per Warehouse</h2>
                <div class="h-56 relative">
                    <p v-if="loading" class="text-sm text-gray-400 text-center pt-20">Memuat...</p>
                    <p v-else-if="byKota.length === 0" class="text-sm text-gray-400 text-center pt-20">Tidak ada data.</p>
                    <Doughnut v-else :data="donutChartData" :options="donutChartOptions" />
                </div>
                <p v-if="!loading && byKota.length > 0" class="text-center text-xs text-gray-500 mt-2">
                    Total: <span class="font-semibold text-gray-800">{{ totalQtyAllKota.toLocaleString() }}</span> qty
                </p>
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