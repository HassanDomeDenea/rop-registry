<script setup lang="ts" generic="T extends { id: number | string }">
import { ArrowDown, ArrowUp, ChevronsUpDown } from '@lucide/vue';

/**
 * A generic, presentational table. Every cell can be customised through a
 * `cell-{key}` slot; sorting is delegated to the parent through the `sort` event.
 */
export type Column = {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'start' | 'center' | 'end';
    class?: string;
    headerClass?: string;
};

const props = defineProps<{
    columns: Column[];
    rows: T[];
    sort?: string;
    direction?: 'asc' | 'desc';
    clickable?: boolean;
    rowClass?: (row: T) => string | undefined;
    dense?: boolean;
}>();

const emit = defineEmits<{
    sort: [key: string, direction: 'asc' | 'desc'];
    rowClick: [row: T];
}>();

const alignment = {
    start: 'text-start',
    center: 'text-center',
    end: 'text-end',
};

function cell(row: T, key: string): unknown {
    return (row as Record<string, unknown>)[key];
}

function toggleSort(column: Column) {
    if (!column.sortable) {
        return;
    }

    emit(
        'sort',
        column.key,
        props.sort === column.key && props.direction === 'asc' ? 'desc' : 'asc',
    );
}

function onRowClick(row: T, event: MouseEvent) {
    // Clicks on links, buttons and inputs inside a row keep their own behaviour.
    if (
        !props.clickable ||
        (event.target as HTMLElement).closest('a, button, input, select')
    ) {
        return;
    }

    emit('rowClick', row);
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b bg-muted/40">
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-3 text-xs font-medium whitespace-nowrap text-muted-foreground first:ps-5 last:pe-5"
                        :class="[
                            dense ? 'py-2' : 'py-2.5',
                            alignment[column.align ?? 'start'],
                            column.headerClass,
                        ]"
                        :aria-sort="
                            sort === column.key
                                ? direction === 'asc'
                                    ? 'ascending'
                                    : 'descending'
                                : undefined
                        "
                    >
                        <button
                            v-if="column.sortable"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-sm transition-colors hover:text-foreground"
                            :class="
                                sort === column.key ? 'text-foreground' : ''
                            "
                            @click="toggleSort(column)"
                        >
                            {{ column.label }}
                            <ArrowUp
                                v-if="
                                    sort === column.key && direction === 'asc'
                                "
                                class="size-3.5"
                            />
                            <ArrowDown
                                v-else-if="sort === column.key"
                                class="size-3.5"
                            />
                            <ChevronsUpDown
                                v-else
                                class="size-3.5 opacity-40"
                            />
                        </button>
                        <template v-else>{{ column.label }}</template>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.id"
                    class="border-b transition-colors last:border-b-0 hover:bg-muted/40"
                    :class="[
                        clickable ? 'cursor-pointer' : '',
                        rowClass?.(row),
                    ]"
                    @click="onRowClick(row, $event)"
                >
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="px-3 align-middle first:ps-5 last:pe-5"
                        :class="[
                            dense ? 'py-2' : 'py-3',
                            alignment[column.align ?? 'start'],
                            column.class,
                        ]"
                    >
                        <slot
                            :name="`cell-${column.key}`"
                            :row="row"
                            :value="cell(row, column.key)"
                        >
                            {{ cell(row, column.key) ?? '—' }}
                        </slot>
                    </td>
                </tr>
            </tbody>
        </table>
        <slot v-if="rows.length === 0" name="empty" />
    </div>
</template>
