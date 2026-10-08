<script setup lang="ts">
import { Head, router, useForm, usePoll } from '@inertiajs/vue3';
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
import { computed, ref, watch } from 'vue';
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
type Task = {
    kind: 'backup' | 'restore';
    status: 'running' | 'failed';
    message: string | null;
};

const props = defineProps<{
    backups: Backup[];
    directory: string;
    customDirectory: string | null;
    keep: number;
    hosted: boolean;
    task: Task | null;
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
    notice.value = null;

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

// --- Hosted registry: background work and direct upload ----------------------

const running = computed(() => props.task?.status === 'running');
const notice = ref<string | null>(null);

const { start: startPolling, stop: stopPolling } = usePoll(
    3000,
    { only: ['backups', 'task'] },
    { autoStart: false },
);

watch(
    running,
    (now, before) => {
        if (now) {
            startPolling();

            return;
        }

        stopPolling();

        if (before && props.task === null) {
            notice.value = t('Finished. The list below is up to date.');
        }
    },
    { immediate: true },
);

const uploadInput = ref<HTMLInputElement | null>(null);
const upload = ref<{ name: string; percent: number } | null>(null);
const uploadError = ref<string | null>(null);

function send(
    file: File,
    target: { url: string; headers: Record<string, string> },
): Promise<void> {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();

        request.open('PUT', target.url);

        // The browser sets the host itself and refuses to have it set for it.
        Object.entries(target.headers)
            .filter(([name]) => name.toLowerCase() !== 'host')
            .forEach(([name, value]) => request.setRequestHeader(name, value));

        request.upload.onprogress = (event) => {
            if (upload.value && event.lengthComputable) {
                upload.value.percent = Math.floor(
                    (event.loaded / event.total) * 100,
                );
            }
        };
        request.onload = () =>
            request.status >= 200 && request.status < 300
                ? resolve()
                : reject(new Error(String(request.status)));
        request.onerror = () => reject(new Error('network'));
        request.send(file);
    });
}

/** A large archive goes straight to storage, then shows up in the list. */
async function uploadArchive(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    input.value = '';

    if (!file) {
        return;
    }

    notice.value = null;
    uploadError.value = null;
    upload.value = { name: file.name, percent: 0 };

    try {
        const response = await fetch(
            backupRoutes.upload.url({ query: { name: file.name } }),
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        await send(file, await response.json());

        notice.value = t(
            'The backup was uploaded. Choose Restore next to it to load it into the registry.',
        );
        router.reload({ only: ['backups'] });
    } catch {
        uploadError.value = t(
            'The backup could not be uploaded. Check the connection and try again.',
        );
    } finally {
        upload.value = null;
    }
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
    notice.value = null;

    restore.post(backupRoutes.restore.url(), {
        forceFormData: true,
        onSuccess: () => (restoreOpen.value = false),
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
            v-if="hosted"
            variant="outline"
            :disabled="upload !== null"
            @click="uploadInput?.click()"
        >
            <Spinner v-if="upload" />
            <Upload v-else />
            {{ t('Upload a backup') }}
        </Button>
        <Button v-else variant="outline" @click="openRestore(null)">
            <Upload />
            {{ t('Restore from a file') }}
        </Button>
        <input
            ref="uploadInput"
            type="file"
            accept=".zip,application/zip"
            class="hidden"
            @change="uploadArchive"
        />
        <Button
            variant="outline"
            :disabled="creating !== null || running"
            @click="create('database')"
        >
            <Spinner v-if="creating === 'database'" />
            <Database v-else />
            {{ t('Back up database') }}
        </Button>
        <Button
            :disabled="creating !== null || running"
            @click="create('full')"
        >
            <Spinner v-if="creating === 'full'" />
            <DatabaseBackup v-else />
            {{ t('Full backup') }}
        </Button>
    </PageHeader>

    <div
        v-if="running"
        class="flex items-center gap-3 rounded-xl border border-info/30 bg-info/10 px-4 py-3 text-sm text-info"
    >
        <Spinner />
        {{
            task?.kind === 'restore'
                ? t(
                      'The backup is being restored. This can take several minutes; this page updates by itself.',
                  )
                : t(
                      'The backup is being prepared. This can take several minutes; this page updates by itself.',
                  )
        }}
    </div>
    <div
        v-else-if="task?.status === 'failed'"
        class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
    >
        {{
            t('The last backup or restore did not finish: :message', {
                message: task.message ?? '',
            })
        }}
    </div>
    <div
        v-if="upload"
        class="space-y-2 rounded-xl border border-info/30 bg-info/10 px-4 py-3 text-sm text-info"
    >
        <p>
            {{
                t('Uploading :name… :percent%', {
                    name: upload.name,
                    percent: upload.percent,
                })
            }}
        </p>
        <div class="h-1.5 overflow-hidden rounded-full bg-info/20">
            <div
                class="h-full bg-info transition-[width]"
                :style="{ width: `${upload.percent}%` }"
            />
        </div>
    </div>
    <div
        v-if="uploadError"
        class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
    >
        {{ uploadError }}
    </div>
    <div
        v-if="notice"
        class="rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-success"
    >
        {{ notice }}
    </div>

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
        v-if="!hosted"
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
                        :disabled="running"
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
                        hosted
                            ? t(
                                  'Every patient, visit and attachment now in the registry is replaced by the contents of the backup. The sign-in account also becomes the one stored in the backup.',
                              )
                            : t(
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
