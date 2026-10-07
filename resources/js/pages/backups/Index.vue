<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Database,
    DatabaseBackup,
    Download,
    FolderArchive,
    History,
    ShieldCheck,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/registry/ConfirmDialog.vue';
import DataTable from '@/components/registry/DataTable.vue';
import type { Column } from '@/components/registry/DataTable.vue';
import EmptyState from '@/components/registry/EmptyState.vue';
import FormField from '@/components/registry/FormField.vue';
import PageHeader from '@/components/registry/PageHeader.vue';
import Pill from '@/components/registry/Pill.vue';
import type { PillTone } from '@/components/registry/Pill.vue';
import SectionCard from '@/components/registry/SectionCard.vue';
import TextInput from '@/components/registry/TextInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import backupRoutes from '@/routes/backups';

type Backup = { name: string; type: string; size: number; created_at: string };

const props = defineProps<{
    backups: Backup[];
    directory: string;
    customDirectory: string | null;
    keep: number;
}>();

const { t, formatBytes, formatDateTime } = useI18n();

const CONFIRMATION = 'RESTORE';

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
    auto: { label: 'Automatic · database only', tone: 'neutral' },
    safety: {
        label: 'Before restore · database and attachments',
        tone: 'warning',
    },
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

// --- Backup folder -----------------------------------------------------------

const folder = useForm<Record<string, string | number | boolean | null>>({
    path: props.customDirectory,
});

function saveFolder() {
    folder.put(backupRoutes.settings.url(), { preserveScroll: true });
}

// --- Restore -----------------------------------------------------------------

const restoreOpen = ref(false);
const restoreTarget = ref<Backup | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const restore = useForm<{
    confirmation: string | number | boolean | null;
    backup: string | null;
    archive: File | null;
}>({
    confirmation: null,
    backup: null,
    archive: null,
});

function openRestore(backup: Backup | null) {
    restore.reset();
    restore.clearErrors();
    restoreTarget.value = backup;
    restore.backup = backup?.name ?? null;
    restoreOpen.value = true;
}

function pickArchive(event: Event) {
    restore.archive = (event.target as HTMLInputElement).files?.[0] ?? null;
}

const canRestore = computed(
    () =>
        restore.confirmation === CONFIRMATION &&
        (restore.backup !== null || restore.archive !== null),
);

function submitRestore() {
    restore.post(backupRoutes.restore.url(), { forceFormData: true });
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
        <Button variant="outline" @click="openRestore(null)">
            <Upload />
            {{ t('Restore from a file') }}
        </Button>
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
            <DatabaseBackup class="size-5 text-success" />
            <p class="mt-2 text-sm font-medium">{{ t('Full backup') }}</p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{
                    t(
                        'The database and every attached picture and PDF. This is the one to use for moving the registry to another computer.',
                    )
                }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4 shadow-xs">
            <ShieldCheck class="size-5 text-info" />
            <p class="mt-2 text-sm font-medium">
                {{ t('Automatic daily backup') }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{
                    t(
                        'Created once a day while the registry is in use; the newest :count are kept. It holds the database only, not the attached files.',
                        { count: keep },
                    )
                }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4 shadow-xs">
            <History class="size-5 text-warning" />
            <p class="mt-2 text-sm font-medium">{{ t('Restoring') }}</p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{
                    t(
                        'Restoring replaces everything with the backup. The current state is saved first as a "before restore" backup.',
                    )
                }}
            </p>
        </div>
    </div>

    <SectionCard
        :title="t('Backup folder')"
        :description="
            t(
                'Choose a folder on a second drive or in a synced folder, so a disk failure does not take the backups with it',
            )
        "
        :icon="FolderArchive"
    >
        <form class="flex items-start gap-3" @submit.prevent="saveFolder">
            <FormField
                class="flex-1"
                :label="t('Folder path')"
                :error="folder.errors.path"
                :hint="t('In use now: :path', { path: directory })"
            >
                <TextInput
                    v-model="folder.path"
                    dir="ltr"
                    placeholder="D:\ROP-Backups"
                />
            </FormField>
            <Button
                type="submit"
                variant="outline"
                class="mt-5.5"
                :disabled="folder.processing"
            >
                {{ t('Save') }}
            </Button>
        </form>
    </SectionCard>

    <SectionCard :title="t('Available backups')" flush>
        <DataTable :columns="columns" :rows="rows">
            <template #cell-created_at="{ row }">
                <span class="tabular-nums">
                    {{ formatDateTime(row.created_at) }}
                </span>
            </template>
            <template #cell-type="{ row }">
                <Pill :tone="types[row.type]?.tone ?? 'neutral'">
                    {{ t(types[row.type]?.label ?? row.type) }}
                </Pill>
            </template>
            <template #cell-name="{ row }">
                <span dir="ltr" class="text-xs">{{ row.name }}</span>
            </template>
            <template #cell-size="{ row }">
                {{ formatBytes(row.size) }}
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <Button as-child variant="outline" size="sm">
                        <a :href="backupRoutes.show.url(row.name)">
                            <Download />
                            {{ t('Download') }}
                        </a>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="openRestore(row)"
                    >
                        <History />
                        {{ t('Restore') }}
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

    <Dialog v-model:open="restoreOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('Restore a backup') }}</DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            'Every patient, visit and attachment now in the registry is replaced by the contents of the backup. You will be signed out and sign in again with the account stored in the backup.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitRestore">
                <div
                    v-if="restoreTarget"
                    class="rounded-md border bg-muted/50 px-3 py-2 text-sm"
                >
                    <p class="font-medium tabular-nums">
                        {{ formatDateTime(restoreTarget.created_at) }}
                    </p>
                    <p class="text-xs text-muted-foreground" dir="ltr">
                        {{ restoreTarget.name }}
                    </p>
                    <p
                        v-if="
                            restoreTarget.type === 'auto' ||
                            restoreTarget.type === 'database'
                        "
                        class="mt-1 text-xs text-warning"
                    >
                        {{
                            t(
                                'This backup holds the database only: the attached files on this computer are kept as they are.',
                            )
                        }}
                    </p>
                </div>
                <FormField
                    v-else
                    :label="t('Backup file (.zip)')"
                    :error="restore.errors.archive"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".zip,application/zip"
                        class="block w-full rounded-md border border-input text-sm file:me-3 file:border-0 file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium"
                        @change="pickArchive"
                    />
                </FormField>

                <FormField
                    :label="t('Type :word to confirm', { word: CONFIRMATION })"
                    :error="restore.errors.confirmation"
                >
                    <TextInput
                        v-model="restore.confirmation"
                        dir="ltr"
                        autocomplete="off"
                        :placeholder="CONFIRMATION"
                    />
                </FormField>

                <InputError :message="restore.errors.backup" />

                <div
                    v-if="restore.progress"
                    class="h-1.5 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="h-full bg-primary transition-[width]"
                        :style="{ width: `${restore.progress.percentage}%` }"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="restoreOpen = false"
                    >
                        {{ t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="!canRestore || restore.processing"
                    >
                        <Spinner v-if="restore.processing" />
                        {{ t('Restore') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        :title="t('Delete this backup?')"
        :description="deleting?.name"
        :confirm-label="t('Delete')"
        @update:open="deleting = null"
        @confirm="destroy"
    />
</template>
