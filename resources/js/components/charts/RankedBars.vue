<script setup lang="ts">
import { computed } from 'vue';

export type RankedRow = {
    key: string | number;
    label: string;
    sublabel?: string;
    value: number;
    display: string;
};

const props = withDefaults(
    defineProps<{
        title: string;
        rows: RankedRow[];
        /** One hue for ranked bars (a CSS colour, normally a --viz-* token). */
        color?: string;
        empty?: string;
    }>(),
    { color: 'var(--viz-1)', empty: 'Nothing to show yet.' },
);

const max = computed(() => Math.max(...props.rows.map((r) => r.value), 0));
const pct = (v: number) =>
    max.value === 0 ? 0 : Math.max(1.5, (v / max.value) * 100);
</script>

<template>
    <p v-if="!rows.length" class="text-sm text-muted-foreground">{{ empty }}</p>
    <ol v-else class="space-y-2.5" :aria-label="title">
        <li
            v-for="row in rows"
            :key="row.key"
            class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 sm:grid-cols-[minmax(0,11rem)_minmax(0,1fr)_auto]"
            :title="`${row.label}: ${row.display}`"
        >
            <span class="min-w-0 truncate text-sm">
                {{ row.label }}
                <span
                    v-if="row.sublabel"
                    class="block truncate text-xs text-muted-foreground"
                    >{{ row.sublabel }}</span
                >
            </span>
            <!-- Bar drops under the name where the list is narrow -->
            <span
                class="order-last col-span-2 h-2.5 sm:order-none sm:col-span-1"
                aria-hidden="true"
            >
                <span
                    class="block h-full rounded-r-[4px]"
                    :style="{ width: `${pct(row.value)}%`, background: color }"
                />
            </span>
            <span class="text-right font-mono text-sm tabular-nums">{{
                row.display
            }}</span>
        </li>
    </ol>
</template>
