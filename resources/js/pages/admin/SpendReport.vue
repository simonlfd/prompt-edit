<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CircleCheck, CircleX } from '@lucide/vue';
import { computed, ref } from 'vue';
import RankedBars from '@/components/charts/RankedBars.vue';
import StackedColumnChart from '@/components/charts/StackedColumnChart.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatUsd, formatUsdExact, isNegative } from '@/lib/money';
import { report as reportRoute } from '@/routes/admin';

type Row = {
    generations: number;
    charged: string;
    provider_cost: string;
    profit: string;
    pixelforge: { generations: number; cost: string } | null;
    matches_pixelforge: boolean | null;
};

const props = defineProps<{
    report: {
        start_date: string;
        end_date: string;
        days: (Row & { date: string })[];
        // A Laravel length-aware paginator (biggest spenders first).
        users: {
            data: (Row & {
                user: { id: number; name: string; email: string };
            })[];
            current_page: number;
            last_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
        total: Row;
        top_users: (Row & {
            user: { id: number; name: string; email: string };
        })[];
        pixelforge_available: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Spend report', href: reportRoute() }],
    },
});

// Native date inputs differ a lot by browser. iOS centres the value, ignores the
// height and padding unless the native appearance is off, and collapses an empty field.
// Chrome on Android jams the value against the calendar icon.
const dateInput =
    'appearance-none pr-2 sm:w-40 [&::-webkit-calendar-picker-indicator]:ml-1 [&::-webkit-date-and-time-value]:min-h-[1.5em] [&::-webkit-date-and-time-value]:text-left';

const start = ref(props.report.start_date);
const end = ref(props.report.end_date);

function apply() {
    router.get(
        reportRoute.url({
            query: { start_date: start.value, end_date: end.value },
        }),
    );
}

const money = (value: string) => formatUsdExact(value).replace(/^(-?\$)/, '$1');

// ---- Charts (numbers are for geometry only; every figure shown is formatted from the exact strings) ----
const chronological = computed(() => [...props.report.days].reverse());
const dayLabel = (date: string) =>
    new Date(`${date}T00:00:00Z`).toLocaleDateString('en', {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });
const toNumber = (decimal: string) => Math.max(0, Number(decimal));

const dailySeries = computed(() => [
    {
        key: 'cost',
        label: 'Pixelforge’s cost',
        color: 'var(--viz-1)',
        values: chronological.value.map((d) => toNumber(d.provider_cost)),
    },
    {
        key: 'margin',
        label: 'Our margin',
        color: 'var(--viz-2)',
        values: chronological.value.map((d) => toNumber(d.profit)),
    },
]);

function dailyValue(_: number, key: string, i: number): string {
    const day = chronological.value[i];

    return formatUsd(key === 'cost' ? day.provider_cost : day.profit);
}

function dailyExtra(i: number) {
    const day = chronological.value[i];
    const rows = [
        {
            label: `Charged (${day.generations} image${day.generations === 1 ? '' : 's'})`,
            value: formatUsd(day.charged),
        },
    ];

    if (day.matches_pixelforge !== null && day.pixelforge) {
        rows.push({
            label: 'Pixelforge /v1/usage',
            value: day.matches_pixelforge
                ? '✓ matches'
                : `✗ ${formatUsd(day.pixelforge.cost)}`,
        });
    }

    return rows;
}

const compactUsd = (v: number) =>
    v === 0
        ? '$0'
        : v < 0.01
          ? `$${v.toFixed(3)}`
          : v < 10
            ? `$${v.toFixed(2)}`
            : `$${Math.round(v).toLocaleString()}`;

const topSpenders = computed(() =>
    props.report.top_users.map((row) => ({
        key: row.user.id,
        label: row.user.name,
        sublabel: `${row.generations} image${row.generations === 1 ? '' : 's'}`,
        value: toNumber(row.charged),
        display: formatUsd(row.charged),
    })),
);
</script>

<template>
    <Head title="Spend report" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Spend report
                </h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    What users were charged, what Pixelforge charged us, and the
                    profit. Days are UTC and a generation counts on the day it
                    completed, the same way Pixelforge’s usage endpoint buckets
                    costs. Amounts are exact.
                </p>
            </div>
            <form
                class="grid w-full grid-cols-2 items-end gap-2 sm:flex sm:w-auto"
                @submit.prevent="apply"
            >
                <div class="grid gap-1">
                    <Label for="start" class="text-xs">From</Label>
                    <Input
                        id="start"
                        v-model="start"
                        type="date"
                        :class="dateInput"
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="end" class="text-xs">To</Label>
                    <Input
                        id="end"
                        v-model="end"
                        type="date"
                        :class="dateInput"
                    />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    class="col-span-2 sm:col-span-1"
                    >Show</Button
                >
            </form>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Credit spend</p>
                <p
                    class="font-mono text-base font-medium tabular-nums sm:text-xl"
                >
                    {{ money(report.total.charged) }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Provider cost</p>
                <p
                    class="font-mono text-base font-medium tabular-nums sm:text-xl"
                >
                    {{ money(report.total.provider_cost) }}
                </p>
                <p class="mt-1 flex items-center gap-1 text-xs">
                    <template v-if="report.total.matches_pixelforge === true">
                        <CircleCheck class="size-3.5 text-accent-vivid" />
                        Matches Pixelforge
                    </template>
                    <template
                        v-else-if="report.total.matches_pixelforge === false"
                    >
                        <CircleX class="size-3.5 text-destructive" />
                        Pixelforge reports
                        {{ money(report.total.pixelforge!.cost) }}
                    </template>
                    <span v-else class="text-muted-foreground">
                        Pixelforge usage unavailable
                    </span>
                </p>
            </div>
            <div class="col-span-2 rounded-xl border bg-card p-4 sm:col-span-1">
                <p class="text-xs text-muted-foreground">Profit</p>
                <p
                    class="font-mono text-base font-medium tabular-nums sm:text-xl"
                    :class="
                        isNegative(report.total.profit)
                            ? 'text-destructive'
                            : 'text-accent-vivid'
                    "
                >
                    {{ money(report.total.profit) }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <section class="rounded-xl border bg-card p-4">
                <h2 class="text-sm font-semibold">Charged per day</h2>
                <p class="mb-3 text-xs text-muted-foreground">
                    Each column is what users paid: Pixelforge’s cost plus our
                    25% margin. UTC days, by completion.
                </p>
                <StackedColumnChart
                    title="Charged per day, split into Pixelforge’s cost and our margin"
                    :labels="chronological.map((d) => dayLabel(d.date))"
                    :long-labels="chronological.map((d) => d.date)"
                    :series="dailySeries"
                    :axis-format="compactUsd"
                    :value-format="dailyValue"
                    :extra-rows="dailyExtra"
                />
            </section>
            <section class="rounded-xl border bg-card p-4">
                <h2 class="text-sm font-semibold">Top spenders</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Charged in this period.
                </p>
                <RankedBars
                    title="Top spenders by amount charged"
                    :rows="topSpenders"
                    empty="No completed generations in this period."
                />
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border bg-card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">By day</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left font-medium">
                                Date (UTC)
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Images
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Charged
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Provider cost
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Profit
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Pixelforge /v1/usage
                            </th>
                        </tr>
                    </thead>
                    <tbody class="font-mono tabular-nums">
                        <tr
                            v-for="day in report.days"
                            :key="day.date"
                            class="border-b last:border-0"
                            :class="{
                                'text-muted-foreground':
                                    day.generations === 0 &&
                                    !day.pixelforge?.generations,
                            }"
                        >
                            <td class="px-4 py-2 font-sans">{{ day.date }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ day.generations }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ money(day.charged) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ money(day.provider_cost) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right"
                                :class="{
                                    'text-destructive': isNegative(day.profit),
                                }"
                            >
                                {{ money(day.profit) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                <span
                                    v-if="day.pixelforge"
                                    class="inline-flex items-center gap-1.5"
                                >
                                    {{ money(day.pixelforge.cost) }}
                                    <span class="text-muted-foreground"
                                        >({{
                                            day.pixelforge.generations
                                        }})</span
                                    >
                                    <CircleCheck
                                        v-if="day.matches_pixelforge"
                                        class="size-3.5 text-accent-vivid"
                                        aria-label="Matches"
                                    />
                                    <CircleX
                                        v-else
                                        class="size-3.5 text-destructive"
                                        aria-label="Does not match"
                                    />
                                </span>
                                <span
                                    v-else
                                    class="font-sans text-muted-foreground"
                                    >—</span
                                >
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="font-mono font-semibold tabular-nums">
                        <tr class="border-t">
                            <td class="px-4 py-2 font-sans">Total</td>
                            <td class="px-4 py-2 text-right">
                                {{ report.total.generations }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ money(report.total.charged) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ money(report.total.provider_cost) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ money(report.total.profit) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{
                                    report.total.pixelforge
                                        ? money(report.total.pixelforge.cost)
                                        : '—'
                                }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">By user</h2>
            <p
                v-if="!report.users.data.length"
                class="px-4 py-6 text-sm text-muted-foreground"
            >
                No completed generations in this period.
            </p>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left font-medium">
                                User
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Images
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Charged
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Provider cost
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody class="font-mono tabular-nums">
                        <tr
                            v-for="row in report.users.data"
                            :key="row.user.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-2 font-sans">
                                {{ row.user.name }}
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ row.user.email }}</span
                                >
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ row.generations }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ money(row.charged) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ money(row.provider_cost) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ money(row.profit) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="report.users.total" class="border-t px-4 py-3">
                <Pagination
                    :current-page="report.users.current_page"
                    :last-page="report.users.last_page"
                    :total="report.users.total"
                    :from="report.users.from"
                    :to="report.users.to"
                    page-name="users_page"
                    noun="users"
                />
            </div>
        </section>
    </div>
</template>
