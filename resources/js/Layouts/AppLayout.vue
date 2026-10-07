<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { LayoutDashboard, PackagePlus, PackageMinus, Package, Warehouse, Users, Tags, LogOut, Menu, X } from 'lucide-vue-next';
const page = usePage();
const isAdmin = computed(() => (page.props.auth as any)?.user?.isAdmin ?? false);

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

const currentPath = ref(window.location.pathname);

// Sidebar berupa drawer di layar < lg (1024px)
const sidebarOpen = ref(false);

function closeSidebar() {
    sidebarOpen.value = false;
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') closeSidebar();
}

function onResize() {
    if (window.innerWidth >= 1024) closeSidebar();
}

// Kunci scroll halaman saat drawer terbuka
watch(sidebarOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
    document.body.style.overflow = '';
});

const menuItems = computed(() => {
    const items = [
        { label: 'Dashboard', path: '/', icon: LayoutDashboard },
        { label: 'Restock Masuk', path: '/restock', icon: PackagePlus },
        { label: 'Stok Keluar', path: '/stok-keluar', icon: PackageMinus },
        { label: 'Produk', path: '/produk', icon: Package },
        { label: 'Warehouse', path: '/gudang', icon: Warehouse },
        { label: 'Master Produk', path: '/master-produk', icon: Tags },
    ];
    if (isAdmin.value) {
        items.push({ label: 'Persetujuan User', path: '/users', icon: Users });
    }
    return items;
});

async function logout() {
    await fetch('/logout', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrfToken() },
    });
    window.location.href = '/login';
}
</script>

<template>
    <div class="min-h-dvh bg-gray-50 lg:flex">
        <!-- Top bar (mobile & tablet) -->
        <header class="lg:hidden sticky top-0 z-30 h-14 flex items-center gap-3 px-4 bg-white border-b border-gray-200">
            <button type="button" @click="sidebarOpen = true" aria-label="Buka menu"
                class="-ml-2 p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition">
                <Menu :size="22" :stroke-width="2" />
            </button>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-orange-500 text-white flex items-center justify-center font-bold text-xs">NB</div>
                <span class="font-semibold text-gray-900 text-sm">Nubiko</span>
            </div>
        </header>

        <!-- Overlay drawer -->
        <transition name="fade">
            <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="closeSidebar"></div>
        </transition>

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 max-w-[85vw] bg-white border-r border-gray-200 flex flex-col shrink-0',
                'transition-transform duration-200 ease-out',
                'lg:sticky lg:top-0 lg:h-dvh lg:w-56 lg:max-w-none lg:translate-x-0 lg:z-auto',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]">
            <div class="flex items-center justify-between gap-2 px-5 py-5 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-lg bg-orange-500 text-white flex items-center justify-center font-bold text-sm">NB</div>
                    <span class="font-semibold text-gray-900 text-sm">Nubiko</span>
                </div>
                <button type="button" @click="closeSidebar" aria-label="Tutup menu"
                    class="lg:hidden -mr-2 p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">
                    <X :size="20" :stroke-width="2" />
                </button>
            </div>
            <nav class="flex-1 px-3 py-4 flex flex-col gap-1 overflow-y-auto">
                <a v-for="item in menuItems" :key="item.path" :href="item.path"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 lg:py-2 rounded-lg text-sm font-medium transition',
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
                    class="w-full flex items-center gap-3 px-3 py-2.5 lg:py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    <LogOut :size="18" :stroke-width="2" />
                    <span>Logout</span>
                </button>
            </div>
        </aside>

        <main class="flex-1 min-w-0 p-4 sm:p-6 overflow-x-hidden">
            <slot />
        </main>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
