<script setup lang="ts">
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from '@lucide/vue';
import {
    CalendarCell,
    CalendarCellTrigger,
    CalendarGrid,
    CalendarGridBody,
    CalendarGridHead,
    CalendarGridRow,
    CalendarHeadCell,
    CalendarHeader,
    CalendarHeading,
    CalendarNext,
    CalendarPrev,
    CalendarRoot,
} from 'reka-ui';
import { computed, ref, shallowRef, watch } from 'vue';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useI18n } from '@/composables/useI18n';
import { parseTypedDate } from '@/lib/utils';

/**
 * A date field that always reads YYYY-MM-DD followed by the day name, the
 * same on every device. In a narrow column the day name moves below the field. The date can be typed (year first or day first) or
 * picked from a calendar. An empty value is null ("not recorded").
 */
defineOptions({ inheritAttrs: false });

const model = defineModel<string | number | boolean | null>();

const props = defineProps<{
    id?: string;
    min?: string;
    max?: string;
    invalid?: boolean;
}>();

const { t, locale, intlLocale, direction } = useI18n();

const isoValue = computed(() =>
    typeof model.value === 'string' && model.value !== '' ? model.value : null,
);

const text = ref(isoValue.value ?? '');

watch(isoValue, (value) => {
    if (parseTypedDate(text.value) !== value) {
        text.value = value ?? '';
    }
});

function onInput(event: Event) {
    text.value = (event.target as HTMLInputElement).value;

    if (text.value.trim() === '') {
        model.value = null;

        return;
    }

    const parsed = parseTypedDate(text.value);

    if (parsed !== null) {
        model.value = parsed;
    }
}

/** Show the stored date in the standard form, dropping anything unreadable. */
function onBlur() {
    text.value = isoValue.value ?? '';
}

const weekday = computed(() =>
    isoValue.value === null
        ? ''
        : new Intl.DateTimeFormat(intlLocale.value, {
              weekday: locale.value === 'ar' ? 'long' : 'short',
          }).format(new Date(`${isoValue.value}T00:00:00`)),
);

const open = ref(false);
const minValue = computed(() => (props.min ? parseDate(props.min) : undefined));
const maxValue = computed(() => (props.max ? parseDate(props.max) : undefined));
const todayValue = today(getLocalTimeZone());
const placeholder = shallowRef<DateValue>(todayValue);

const isTodayAllowed = computed(
    () =>
        !(minValue.value && todayValue.compare(minValue.value) < 0) &&
        !(maxValue.value && todayValue.compare(maxValue.value) > 0),
);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    if (isoValue.value) {
        placeholder.value = parseDate(isoValue.value);
    } else if (maxValue.value && todayValue.compare(maxValue.value) > 0) {
        placeholder.value = maxValue.value;
    } else {
        placeholder.value = todayValue;
    }
});

const calendarValue = computed({
    get: () => (isoValue.value ? parseDate(isoValue.value) : undefined),
    set: (value: DateValue | undefined) => {
        model.value = value ? value.toString() : null;
        open.value = false;
    },
});

function pick(value: string | null) {
    model.value = value;
    open.value = false;
}

const previousYear = (date: DateValue) => date.subtract({ years: 1 });
const nextYear = (date: DateValue) => date.add({ years: 1 });

const navigationButton =
    'inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-40';
</script>

<template>
    <div v-bind="$attrs" class="@container min-w-0">
        <Popover v-model:open="open">
            <PopoverAnchor as-child>
                <div
                    data-slot="date-input"
                    :aria-invalid="invalid || undefined"
                    class="flex h-9 w-full min-w-0 items-center gap-2 rounded-md border border-input bg-transparent ps-3 pe-1 text-sm shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30"
                >
                    <input
                        :id="id"
                        type="text"
                        dir="ltr"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="yyyy-mm-dd"
                        :value="text"
                        class="w-[5.75rem] shrink-0 bg-transparent tabular-nums outline-none placeholder:text-muted-foreground"
                        @input="onInput"
                        @blur="onBlur"
                        @keydown.enter="onBlur"
                    />
                    <span
                        class="hidden min-w-0 flex-1 truncate text-muted-foreground @[12.5rem]:block"
                    >
                        {{ weekday }}
                    </span>
                    <span class="flex-1 @[12.5rem]:hidden" />
                    <PopoverTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                            :aria-label="t('Choose a date')"
                        >
                            <CalendarDays class="size-4" />
                        </button>
                    </PopoverTrigger>
                </div>
            </PopoverAnchor>

            <PopoverContent align="start" class="w-auto p-3">
                <CalendarRoot
                    v-slot="{ grid, weekDays }"
                    v-model="calendarValue"
                    v-model:placeholder="placeholder"
                    :calendar-label="t('Choose a date')"
                    :locale="intlLocale"
                    :dir="direction"
                    :min-value="minValue"
                    :max-value="maxValue"
                    :week-starts-on="6"
                    :weekday-format="locale === 'ar' ? 'narrow' : 'short'"
                    fixed-weeks
                    initial-focus
                >
                    <CalendarHeader class="flex items-center gap-0.5">
                        <CalendarPrev
                            :prev-page="previousYear"
                            :class="navigationButton"
                        >
                            <ChevronsLeft class="size-4 rtl:rotate-180" />
                        </CalendarPrev>
                        <CalendarPrev :class="navigationButton">
                            <ChevronLeft class="size-4 rtl:rotate-180" />
                        </CalendarPrev>
                        <CalendarHeading
                            class="flex-1 text-center text-sm font-medium"
                        />
                        <CalendarNext :class="navigationButton">
                            <ChevronRight class="size-4 rtl:rotate-180" />
                        </CalendarNext>
                        <CalendarNext
                            :next-page="nextYear"
                            :class="navigationButton"
                        >
                            <ChevronsRight class="size-4 rtl:rotate-180" />
                        </CalendarNext>
                    </CalendarHeader>

                    <CalendarGrid
                        v-for="month in grid"
                        :key="month.value.toString()"
                        class="mt-3 w-full border-collapse"
                    >
                        <CalendarGridHead>
                            <CalendarGridRow class="flex">
                                <CalendarHeadCell
                                    v-for="day in weekDays"
                                    :key="day"
                                    class="w-9 text-xs font-normal text-muted-foreground"
                                >
                                    {{ day }}
                                </CalendarHeadCell>
                            </CalendarGridRow>
                        </CalendarGridHead>
                        <CalendarGridBody>
                            <CalendarGridRow
                                v-for="(weekDates, index) in month.rows"
                                :key="`week-${index}`"
                                class="mt-1 flex"
                            >
                                <CalendarCell
                                    v-for="weekDate in weekDates"
                                    :key="weekDate.toString()"
                                    :date="weekDate"
                                    class="p-0 text-center"
                                >
                                    <CalendarCellTrigger
                                        :day="weekDate"
                                        :month="month.value"
                                        class="inline-flex size-9 items-center justify-center rounded-md text-sm tabular-nums transition-colors outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 data-[disabled]:pointer-events-none data-[disabled]:opacity-30 data-[outside-view]:text-muted-foreground/50 data-[selected]:bg-primary data-[selected]:font-medium data-[selected]:text-primary-foreground [&[data-today]:not([data-selected])]:ring-1 [&[data-today]:not([data-selected])]:ring-primary/60"
                                    />
                                </CalendarCell>
                            </CalendarGridRow>
                        </CalendarGridBody>
                    </CalendarGrid>
                </CalendarRoot>

                <div
                    class="mt-3 flex items-center justify-between border-t pt-2"
                >
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-xs text-muted-foreground hover:text-foreground"
                        @click="pick(null)"
                    >
                        {{ t('Clear') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-xs font-medium text-primary hover:bg-accent disabled:opacity-40"
                        :disabled="!isTodayAllowed"
                        @click="pick(todayValue.toString())"
                    >
                        {{ t('Today') }}
                    </button>
                </div>
            </PopoverContent>
        </Popover>
        <p
            v-if="weekday"
            class="mt-1 text-xs text-muted-foreground @[12.5rem]:hidden"
        >
            {{ weekday }}
        </p>
    </div>
</template>
