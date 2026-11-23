<script setup lang="ts">
import { Table2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export type ColumnSeries = {
    key: string;
    label: string;
    /** A CSS colour, normally one of the --viz-* tokens. */
    color: string;
    values: number[];
};

const props = withDefaults(
    defineProps<{
        /** Accessible name, e.g. "Charged per day". */
        title: string;
        /** Short x-axis labels, one per column. */
        labels: string[];
        /** Longer labels for the tooltip and table; defaults to `labels`. */
        longLabels?: string[];
        series: ColumnSeries[];
        /** Formats a value for the y-axis ticks. */
        axisFormat?: (value: number) => string;
        /** Formats a series value for the tooltip and table (index = column). */
        valueFormat?: (
            value: number,
            seriesKey: string,
            index: number,
        ) => string;
        /** Extra tooltip rows after the series, e.g. a total. */
        extraRows?: (index: number) => { label: string; value: string }[];
        height?: number;
    }>(),
    {
        longLabels: undefined,
        axisFormat: (v: number) => v.toLocaleString(),
        valueFormat: (v: number) => v.toLocaleString(),
        extraRows: () => [],
        height: 220,
    },
);

// ---- Layout -------------------------------------------------------------
const root = ref<HTMLElement | null>(null);
const width = ref(640);
let observer: ResizeObserver | undefined;
onMounted(() => {
    observer = new ResizeObserver(
        ([entry]) => (width.value = Math.max(280, entry.contentRect.width)),
    );
    if (root.value) {
        observer.observe(root.value);
    }
});
onBeforeUnmount(() => observer?.disconnect());

const margin = { top: 10, right: 8, bottom: 26, left: 52 };
const innerW = computed(() => width.value - margin.left - margin.right);
const innerH = computed(() => props.height - margin.top - margin.bottom);
const n = computed(() => props.labels.length);
const band = computed(() => innerW.value / Math.max(1, n.value));
const barW = computed(() => Math.max(4, Math.min(24, band.value * 0.62)));

const totals = computed(() =>
    props.labels.map((_, i) =>
        props.series.reduce((sum, s) => sum + (s.values[i] ?? 0), 0),
    ),
);

/** Round, readable y-axis: 0 and ~4 evenly spaced "nice" ticks. */
const ticks = computed(() => {
    const max = Math.max(...totals.value, 0);
    if (max === 0) {
        return [0, 1];
    }
    const rough = max / 4;
    const magnitude = 10 ** Math.floor(Math.log10(rough));
    const step =
        [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((s) => s >= rough) ??
        rough;
    const top = Math.ceil(max / step) * step;

    return Array.from(
        { length: Math.round(top / step) + 1 },
        (_, i) => +(i * step).toPrecision(12),
    );
});
const yMax = computed(() => ticks.value[ticks.value.length - 1] || 1);
const y = (v: number) =>
    margin.top + innerH.value - (v / yMax.value) * innerH.value;
const x = (i: number) => margin.left + band.value * i + band.value / 2;

/** Show every k-th x label so they never collide. */
const labelEvery = computed(() =>
    Math.max(
        1,
        Math.ceil(n.value / Math.max(1, Math.floor(innerW.value / 64))),
    ),
);

type Segment = {
    key: string;
    color: string;
    x: number;
    y: number;
    w: number;
    h: number;
    top: boolean;
};
const columns = computed(() =>
    props.labels.map((_, i) => {
        const segments: Segment[] = [];
        let acc = 0;
        const visible = props.series.filter((s) => (s.values[i] ?? 0) > 0);
        visible.forEach((s, j) => {
            const v = s.values[i] ?? 0;
            const y0 = y(acc);
            const y1 = y(acc + v);
            acc += v;
            const top = j === visible.length - 1;
            // 2px surface gap between stacked segments (taken from the lower edge of each upper segment).
            const gap = j > 0 ? 2 : 0;
            segments.push({
                key: s.key,
                color: s.color,
                x: x(i) - barW.value / 2,
                y: y1,
                w: barW.value,
                h: Math.max(0, y0 - y1 - gap),
                top,
            });
        });

        return segments;
    }),
);

/** Rect with a 4px rounded data-end (top) and a square base. */
function topRounded(s: Segment): string {
    const r = Math.min(4, s.h, s.w / 2);

    return `M${s.x},${s.y + s.h} V${s.y + r} Q${s.x},${s.y} ${s.x + r},${s.y} H${s.x + s.w - r} Q${s.x + s.w},${s.y} ${s.x + s.w},${s.y + r} V${s.y + s.h} Z`;
}

// ---- Hover & keyboard ----------------------------------------------------
const active = ref<number | null>(null);
const long = computed(() => props.longLabels ?? props.labels);

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        e.preventDefault();
        const start = active.value ?? (e.key === 'ArrowRight' ? -1 : n.value);
        active.value = Math.min(
            n.value - 1,
            Math.max(0, start + (e.key === 'ArrowRight' ? 1 : -1)),
        );
    } else if (e.key === 'Escape') {
        active.value = null;
    }
}

const tooltipStyle = computed(() => {
    if (active.value === null) {
        return {};
    }
    const cx = x(active.value);
    const right = cx > width.value * 0.6;

    return {
        left: right ? 'auto' : `${cx + 16}px`,
        right: right ? `${width.value - cx + 16}px` : 'auto',
        top: `${margin.top}px`,
    };
});

const showTable = ref(false);
</script>

<template>
    <figure class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <!-- Legend: always present for 2+ series; mirrors the mark (rect). -->
            <ul
                v-if="series.length > 1"
                class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                <li
                    v-for="s in series"
                    :key="s.key"
                    class="inline-flex items-center gap-1.5"
                >
                    <span
                        class="size-2.5 rounded-[2px]"
                        :style="{ background: s.color }"
                    />{{ s.label }}
                </li>
            </ul>
            <span v-else />
            <button
                type="button"
                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                :aria-pressed="showTable"
                @click="showTable = !showTable"
            >
                <Table2 class="size-3.5" />
                {{ showTable ? 'View as chart' : 'View as table' }}
            </button>
        </div>

        <div
            v-show="!showTable"
            ref="root"
            class="relative"
            @pointerleave="active = null"
        >
            <svg
                :width="width"
                :height="height"
                class="block max-w-full overflow-visible outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                role="group"
                :aria-label="`${title}. Use the left and right arrow keys to read each column.`"
                tabindex="0"
                @keydown="onKeydown"
                @blur="active = null"
            >
                <!-- Recessive grid -->
                <g>
                    <line
                        v-for="t in ticks"
                        :key="t"
                        :x1="margin.left"
                        :x2="width - margin.right"
                        :y1="y(t)"
                        :y2="y(t)"
                        :stroke="
                            t === 0 ? 'var(--viz-axis)' : 'var(--viz-grid)'
                        "
                        stroke-width="1"
                        shape-rendering="crispEdges"
                    />
                    <text
                        v-for="t in ticks"
                        :key="`l${t}`"
                        :x="margin.left - 8"
                        :y="y(t)"
                        text-anchor="end"
                        dominant-baseline="middle"
                        class="fill-muted-foreground font-mono text-[10px] tabular-nums"
                    >
                        {{ axisFormat(t) }}
                    </text>
                </g>

                <!-- Hovered column wash -->
                <rect
                    v-if="active !== null"
                    :x="x(active) - band / 2"
                    :y="margin.top"
                    :width="band"
                    :height="innerH"
                    class="fill-foreground/5"
                />

                <!-- Columns -->
                <g v-for="(col, i) in columns" :key="i">
                    <template v-for="s in col" :key="s.key">
                        <path v-if="s.top" :d="topRounded(s)" :fill="s.color" />
                        <rect
                            v-else
                            :x="s.x"
                            :y="s.y"
                            :width="s.w"
                            :height="s.h"
                            :fill="s.color"
                        />
                    </template>
                </g>

                <!-- X labels -->
                <text
                    v-for="(label, i) in labels"
                    v-show="i % labelEvery === (n - 1) % labelEvery"
                    :key="`x${i}`"
                    :x="x(i)"
                    :y="height - 8"
                    text-anchor="middle"
                    class="fill-muted-foreground text-[10px]"
                >
                    {{ label }}
                </text>

                <!-- Hit targets: the whole band, bigger than the mark -->
                <rect
                    v-for="(_, i) in labels"
                    :key="`h${i}`"
                    :x="x(i) - band / 2"
                    :y="margin.top"
                    :width="band"
                    :height="innerH"
                    fill="transparent"
                    @pointerenter="active = i"
                />
            </svg>

            <div
                v-if="active !== null"
                class="pointer-events-none absolute z-10 min-w-44 rounded-lg border bg-popover px-3 py-2 text-xs shadow-pop"
                :style="tooltipStyle"
                role="status"
            >
                <p class="mb-1.5 font-medium">{{ long[active] }}</p>
                <dl class="space-y-1">
                    <div
                        v-for="s in [...series].reverse()"
                        :key="s.key"
                        class="flex items-center justify-between gap-4"
                    >
                        <dt
                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                        >
                            <span
                                class="h-0.5 w-3 rounded-full"
                                :style="{ background: s.color }"
                            />{{ s.label }}
                        </dt>
                        <dd class="font-mono font-semibold tabular-nums">
                            {{
                                valueFormat(
                                    s.values[active] ?? 0,
                                    s.key,
                                    active,
                                )
                            }}
                        </dd>
                    </div>
                    <div
                        v-for="row in extraRows(active)"
                        :key="row.label"
                        class="flex justify-between gap-4 border-t pt-1"
                    >
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd class="font-mono font-semibold tabular-nums">
                            {{ row.value }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Table view: every value reachable without hovering -->
        <div v-if="showTable" class="max-h-80 overflow-auto rounded-lg border">
            <table class="w-full text-xs">
                <caption class="sr-only">
                    {{
                        title
                    }}
                </caption>
                <thead class="sticky top-0 bg-card text-muted-foreground">
                    <tr class="border-b">
                        <th
                            scope="col"
                            class="px-3 py-1.5 text-left font-medium"
                        >
                            Period
                        </th>
                        <th
                            v-for="s in series"
                            :key="s.key"
                            scope="col"
                            class="px-3 py-1.5 text-right font-medium"
                        >
                            {{ s.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="font-mono tabular-nums">
                    <tr
                        v-for="(label, i) in long"
                        :key="i"
                        class="border-b last:border-0"
                    >
                        <th
                            scope="row"
                            class="px-3 py-1.5 text-left font-sans font-normal"
                        >
                            {{ label }}
                        </th>
                        <td
                            v-for="s in series"
                            :key="s.key"
                            class="px-3 py-1.5 text-right"
                        >
                            {{ valueFormat(s.values[i] ?? 0, s.key, i) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </figure>
</template>
