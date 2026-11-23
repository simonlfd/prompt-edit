<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CircleCheck, CircleX } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatUsdExact, isNegative } from '@/lib/money';
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
        users: (Row & { user: { id: number; name: string; email: string } })[];
        total: Row;
        pixelforge_available: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Spend report', href: reportRoute() }],
    },
});

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
            <form class="flex items-end gap-2" @submit.prevent="apply">
                <div class="grid gap-1">
                    <Label for="start" class="text-xs">From</Label>
                    <Input
                        id="start"
                        v-model="start"
                        type="date"
                        class="w-40"
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="end" class="text-xs">To</Label>
                    <Input id="end" v-model="end" type="date" class="w-40" />
                </div>
                <Button type="submit" variant="outline">Show</Button>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Credit spend</p>
                <p class="font-mono text-xl font-medium tabular-nums">
                    {{ money(report.total.charged) }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Provider cost</p>
                <p class="font-mono text-xl font-medium tabular-nums">
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
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Profit</p>
                <p
                    class="font-mono text-xl font-medium tabular-nums"
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

        <section class="overflow-x-auto rounded-xl border bg-card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">By day</h2>
            <table class="w-full text-sm">
                <thead class="text-xs text-muted-foreground">
                    <tr class="border-b">
                        <th class="px-4 py-2 text-left font-medium">
                            Date (UTC)
                        </th>
                        <th class="px-4 py-2 text-right font-medium">Images</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Charged
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Provider cost
                        </th>
                        <th class="px-4 py-2 text-right font-medium">Profit</th>
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
                                    >({{ day.pixelforge.generations }})</span
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
                            <span v-else class="font-sans text-muted-foreground"
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
        </section>

        <section class="overflow-x-auto rounded-xl border bg-card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">By user</h2>
            <p
                v-if="!report.users.length"
                class="px-4 py-6 text-sm text-muted-foreground"
            >
                No completed generations in this period.
            </p>
            <table v-else class="w-full text-sm">
                <thead class="text-xs text-muted-foreground">
                    <tr class="border-b">
                        <th class="px-4 py-2 text-left font-medium">User</th>
                        <th class="px-4 py-2 text-right font-medium">Images</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Charged
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Provider cost
                        </th>
                        <th class="px-4 py-2 text-right font-medium">Profit</th>
                    </tr>
                </thead>
                <tbody class="font-mono tabular-nums">
                    <tr
                        v-for="row in report.users"
                        :key="row.user.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-2 font-sans">
                            {{ row.user.name }}
                            <span class="block text-xs text-muted-foreground">{{
                                row.user.email
                            }}</span>
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
        </section>
    </div>
</template>
