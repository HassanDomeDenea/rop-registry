<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import CaptureBanner from '@/components/registry/CaptureBanner.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader />
            <CaptureBanner />
            <main class="mx-auto w-full max-w-[1500px] flex-1 space-y-6 p-6">
                <slot />
            </main>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
