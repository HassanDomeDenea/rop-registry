<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BellRing,
    Camera,
    ClipboardCheck,
    DatabaseBackup,
    History,
    LayoutGrid,
    Settings,
    Stethoscope,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCaptureStatus } from '@/composables/useCaptureStatus';
import { useI18n } from '@/composables/useI18n';
import { dashboard } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import audits from '@/routes/audits';
import backups from '@/routes/backups';
import captures from '@/routes/captures';
import patients from '@/routes/patients';
import reminders from '@/routes/reminders';
import review from '@/routes/review';
import statistics from '@/routes/statistics';
import visits from '@/routes/visits';
import type { NavItem } from '@/types';

const page = usePage();
const { isRtl, t } = useI18n();
const { pending: pendingCaptures } = useCaptureStatus();

const registryItems = computed<NavItem[]>(() => [
    { title: t('Dashboard'), href: dashboard(), icon: LayoutGrid },
    { title: t('Patients'), href: patients.index(), icon: Users },
    { title: t('Visits'), href: visits.index(), icon: Stethoscope },
    ...(page.props.features.captures
        ? [
              {
                  title: t('Camera inbox'),
                  href: captures.index(),
                  icon: Camera,
                  badge: pendingCaptures.value,
              },
          ]
        : []),
    {
        title: t('Reminders'),
        href: reminders.index(),
        icon: BellRing,
        badge: page.props.reminderCounts?.attention,
    },
    { title: t('Statistics'), href: statistics.index(), icon: BarChart3 },
]);

const maintenanceItems = computed<NavItem[]>(() => [
    {
        title: t('Review queue'),
        href: review.index(),
        icon: ClipboardCheck,
        badge: page.props.reminderCounts?.review,
    },
    { title: t('Audit log'), href: audits.index(), icon: History },
]);

const systemItems = computed<NavItem[]>(() => [
    { title: t('Backups'), href: backups.index(), icon: DatabaseBackup },
    { title: t('Settings'), href: editAppearance(), icon: Settings },
]);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        :side="isRtl ? 'right' : 'left'"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :label="t('Registry')" :items="registryItems" />
            <NavMain :label="t('Data quality')" :items="maintenanceItems" />
            <NavMain :label="t('System')" :items="systemItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
