<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Database,
    DatabaseBackup,
    Download,
    FolderArchive,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/registry/ConfirmDialog.vue';
import DataTable from '@/components/registry/DataTable.vue';
import type { Column } from '@/components/registry/DataTable.vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import Pill from '@/components/registry/Pill.vue';
import type { PillTone } from '@/components/registry/Pill.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import backupRoutes from '@/routes/backups';

type Backup = { name: string; type: string; size: number; created_at: string };

const props = defineProps<{
    backups: Backup[];
    directory: string;
    keep: number;
}>();

const { t, formatBytes, formatDateTime } = useI18n();

const creating = ref<string | null>(null);
const deleting = ref<Backup | null>(null);

const rows = computed(() =>
    props.backups.map((backup) => ({ ...backup, id: backup.name })),
);

const columns = computed<Column[]>(() => [
    { key: 'created_at', label: t('Created') },
    { key: 'type', label: t('Contents') },
    { key: 'name', label: t('File'), class: 'text-muted-foreground' },
    { key: 'size', label: t('Size'), align: 'end', class: 'tabular-nums' },
    { key: 'actions', label: '', align: 'end' },
]);

const types: Record<string, { label: string; tone: PillTone }> = {
    full: { label: 'Database and attachments', tone: 'success' },
    database: { label: 'Database only', tone: 'info' },
    auto: { label: 'Automatic · database', tone: 'neutral' },
    safety: { label: 'Before restore', tone: 'warning' },
};

function create(type: 'full' | 'database') {
    router.post(
        backupRoutes.store.url(),
        { type },
        {
            preserveScroll: true,
            onStart: () => (creating.value = type),
            onFinish: () => (creating.value = null),
        },
    );
}

function destroy() {
    if (!deleting.value) {
        return;
    }

    router.delete(backupRoutes.destroy.url(deleting.value.name), {
        preserveScroll: true,
        onSuccess: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head :title="t('Backups')" />

    <PageHeader
        :title="t('Backups')"
        :description="
            t(
                'Download a backup regularly and keep a copy outside this computer',
            )
        "
    >
        <Button
            variant="outline"
            :disabled="creating !== null"
            @click="create('database')"
        >
            <Spinner v-if="creating === 'database'" />
            <Database v-else />
            {{ t('Back up database') }}
        </Button>
        <Button :disabled="creating !== null" @click="create('full')">
            <Spinner v-if="creating === 'full'" />
            <DatabaseBackup v-else />
            {{ t('Full backup') }}
        </Button>
    </PageHeader>

    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-xl border bg-card p-4 shadow-xs">
            <ShieldCheck class="size-5 text-success" />
            <p class="mt-2 text-sm font-medium">
                {{ t('Automatic daily backup') }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{
                    t(
                        'A database backup is created once a day while the registry is in use. The newest :count are kept.',
                        { count: keep },
                    )
                }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4 shadow-xs">
            <FolderArchive class="size-5 text-info" />
            <p class="mt-2 text-sm font-medium">{{ t('Backup folder') }}</p>
            <p class="mt-1 text-xs break-all text-muted-foreground" dir="ltr">
                {{ directory }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4 shadow-xs">
            <DatabaseBackup class="size-5 text-warning" />
            <p class="mt-2 text-sm font-medium">
                {{ t('Restoring a backup') }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ t('Run this command in the application folder:') }}
            </p>
            <code class="mt-1 block text-xs" dir="ltr">
                php artisan registry:restore &lt;file&gt;
            </code>
        </div>
    </div>

    <SectionCard :title="t('Available backups')" flush>
        <DataTable :columns="columns" :rows="rows">
            <template #cell-created_at="{ row }">
                <span class="tabular-nums">{{
                    formatDateTime(row.created_at)
                }}</span>
            </template>
            <template #cell-type="{ row }">
                <Pill :tone="types[row.type]?.tone ?? 'neutral'">
                    {{ t(types[row.type]?.label ?? row.type) }}
                </Pill>
            </template>
            <template #cell-name="{ row }">
                <span dir="ltr" class="text-xs">{{ row.name }}</span>
            </template>
            <template #cell-size="{ row }">{{
                formatBytes(row.size)
            }}</template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <Button as-child variant="outline" size="sm">
                        <a :href="backupRoutes.show.url(row.name)">
                            <Download />
                            {{ t('Download') }}
                        </a>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="text-muted-foreground hover:text-destructive"
                        :title="t('Delete')"
                        @click="deleting = row"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </template>
            <template #empty>
                <EmptyState
                    :icon="DatabaseBackup"
                    :title="t('No backups yet')"
                    :description="
                        t('Create the first backup with the buttons above.')
                    "
                />
            </template>
        </DataTable>
    </SectionCard>

    <ConfirmDialog
        :open="deleting !== null"
        :title="t('Delete this backup?')"
        :description="deleting?.name"
        :confirm-label="t('Delete')"
        @update:open="deleting = null"
        @confirm="destroy"
    />
</template>
