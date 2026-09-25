<script setup lang="ts">
import { ref } from 'vue';
import { LayoutDashboard, PackagePlus, PackageMinus, Package, Warehouse, LogOut } from 'lucide-vue-next';

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

const currentPath = ref(window.location.pathname);

const menuItems = [
    { label: 'Dashboard', path: '/', icon: LayoutDashboard },
    { label: 'Restock Masuk', path: '/restock', icon: PackagePlus },
    { label: 'Stok Keluar', path: '/stok-keluar', icon: PackageMinus },
    { label: 'Produk', path: '/produk', icon: Package },
    { label: 'Warehouse', path: '/gudang', icon: Warehouse },
];

async function logout() {
    await fetch('/logout', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrfToken() },
    });
    window.location.href = '/login';
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex">
        <aside class="w-56 bg-white border-r border-gray-200 flex flex-col shrink-0">
            <div class="flex items-center gap-2 px-5 py-5 border-b border-gray-100">
                <div class="w-9 h-9 rounded-lg bg-orange-500 text-white flex items-center justify-center font-bold text-sm">NB</div>
                <span class="font-semibold text-gray-900 text-sm">Nubiko</span>
            </div>
            <nav class="flex-1 px-3 py-4 flex flex-col gap-1">
                <a v-for="item in menuItems" :key="item.path" :href="item.path"
                    :class="[
                        'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition',
                        currentPath === item.path
                            ? 'bg-orange-50 text-orange-600'
                            : 'text-gray-600 hover:bg-gray-50'
                    ]">
                    <component :is="item.icon" :size="18" :stroke-width="2" />
                    <span>{{ item.label }}</span>
                </a>
            </nav>
            <div class="px-3 py-4 border-t border-gray-100">
                <button @click="logout"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    <LogOut :size="18" :stroke-width="2" />
                    <span>Logout</span>
                </button>
            </div>
        </aside>

        <main class="flex-1 p-6 overflow-x-hidden">
            <slot />
        </main>
    </div>
</template>