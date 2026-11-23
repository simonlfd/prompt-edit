<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    Copy,
    RefreshCw,
    ShieldCheck,
    Webhook,
} from '@lucide/vue';
import { formatUsdExact } from '@/lib/money';
import { absoluteTime, relativeTime } from '@/lib/time';
import { operations, report } from '@/routes/admin';

type Day = {
    date: string;
    generations: number;
    provider_cost: string;
    pixelforge: { generations: number; cost: string } | null;
    matches_pixelforge: boolean | null;
};

type Event = {
    id: number;
    event_id: string;
    type: string;
    outcome: string;
    delivery_attempt: number | null;
    duplicate_deliveries: number;
    created_at: string;
    prompt: string | null;
    provider_generation_id: string | null;
};

defineProps<{
    webhooks: {
        received: number;
        duplicates_ignored: number;
        stale_ignored: number;
        unmatched: number;
        retried_deliveries: number;
        last_received_at: string | null;
    };
    generations: {
        in_flight: number;
        waiting_for_capacity: number;
        stuck: number;
        images_not_saved: number;
        failures: {
            code: string | null;
            message: string | null;
            count: number;
        }[];
    };
    reconciliation: {
        available: boolean;
        days: Day[];
        last_check: {
            checked_at: string;
            days: number;
            mismatches: { date: string; ours: string; pixelforge: string }[];
        } | null;
    };
    // A Laravel length-aware paginator, serialised as-is.
    events: {
        data: Event[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Operations', href: operations() }] },
});

const outcomeStyle: Record<string, string> = {
    applied: 'bg-lime/15 text-foreground',
    ignored_stale: 'bg-info/12 text-foreground',
    ping: 'bg-studio-choice',
    unmatched: 'bg-warning/15 text-foreground',
    ignored_unknown_type: 'bg-studio-choice',
};

const outcomeLabel: Record<string, string> = {
    applied: 'applied',
    ignored_stale: 'ignored: out of order',
    ping: 'test ping',
    unmatched: 'unknown generation',
    ignored_unknown_type: 'ignored: unknown type',
};
</script>

<template>
    <Head title="Operations" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Operations</h1>
            <p class="max-w-3xl text-sm text-muted-foreground">
                What the Pixelforge integration is doing: every webhook and how
                we handled it, work that’s retrying or stuck, and whether our
                cost records still match Pixelforge’s. Counts cover the last 24
                hours.
            </p>
        </div>

        <!-- Webhook health -->
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border bg-card p-4">
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Webhook class="size-3.5" /> Events received
                </p>
                <p class="font-mono text-2xl font-medium tabular-nums">
                    {{ webhooks.received }}
                </p>
                <p class="text-xs text-muted-foreground">
                    last
                    {{
                        webhooks.last_received_at
                            ? relativeTime(webhooks.last_received_at)
                            : 'never'
                    }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Copy class="size-3.5" /> Duplicates ignored
                </p>
                <p class="font-mono text-2xl font-medium tabular-nums">
                    {{ webhooks.duplicates_ignored }}
                </p>
                <p class="text-xs text-muted-foreground">
                    same event id, recorded once
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <ShieldCheck class="size-3.5" /> Out of order, ignored
                </p>
                <p class="font-mono text-2xl font-medium tabular-nums">
                    {{ webhooks.stale_ignored }}
                </p>
                <p class="text-xs text-muted-foreground">
                    older than what we knew
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <RefreshCw class="size-3.5" /> Retried deliveries
                </p>
                <p class="font-mono text-2xl font-medium tabular-nums">
                    {{ webhooks.retried_deliveries }}
                </p>
                <p class="text-xs text-muted-foreground">
                    first seen on attempt 2+
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Unknown generation</p>
                <p
                    class="font-mono text-2xl font-medium tabular-nums"
                    :class="{ 'text-warning': webhooks.unmatched }"
                >
                    {{ webhooks.unmatched }}
                </p>
                <p class="text-xs text-muted-foreground">
                    acknowledged and logged
                </p>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Reconciliation -->
            <section class="rounded-xl border bg-card">
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <h2 class="text-sm font-semibold">
                        Costs vs Pixelforge
                        <span class="font-normal text-muted-foreground"
                            >(last 7 days)</span
                        >
                    </h2>
                    <Link
                        :href="report()"
                        class="text-xs font-medium text-accent-vivid hover:underline"
                        >Full spend report →</Link
                    >
                </div>
                <p
                    v-if="!reconciliation.available"
                    class="px-4 py-6 text-sm text-muted-foreground"
                >
                    Pixelforge usage is unavailable right now.
                </p>
                <table v-else class="w-full text-sm">
                    <thead class="text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left font-medium">
                                Date (UTC)
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Ours
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Pixelforge
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                <span class="sr-only">Match</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="font-mono tabular-nums">
                        <tr
                            v-for="day in reconciliation.days"
                            :key="day.date"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-2 font-sans">{{ day.date }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ formatUsdExact(day.provider_cost) }}
                                <span class="text-muted-foreground"
                                    >({{ day.generations }})</span
                                >
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{
                                    day.pixelforge
                                        ? formatUsdExact(day.pixelforge.cost)
                                        : '—'
                                }}
                                <span
                                    v-if="day.pixelforge"
                                    class="text-muted-foreground"
                                    >({{ day.pixelforge.generations }})</span
                                >
                            </td>
                            <td class="px-4 py-2 text-right">
                                <CircleCheck
                                    v-if="day.matches_pixelforge"
                                    class="ml-auto size-4 text-accent-vivid"
                                    aria-label="Matches"
                                />
                                <CircleX
                                    v-else-if="day.matches_pixelforge === false"
                                    class="ml-auto size-4 text-destructive"
                                    aria-label="Does not match"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="border-t px-4 py-2 text-xs text-muted-foreground">
                    <template v-if="reconciliation.last_check">
                        Automatic check (hourly) ran
                        {{
                            relativeTime(reconciliation.last_check.checked_at)
                        }}:
                        <span
                            :class="
                                reconciliation.last_check.mismatches.length
                                    ? 'text-destructive'
                                    : 'text-accent-vivid'
                            "
                        >
                            {{
                                reconciliation.last_check.mismatches.length
                                    ? `${reconciliation.last_check.mismatches.length} day(s) differ`
                                    : 'all days match'
                            }} </span
                        >.
                    </template>
                    <template v-else
                        >The hourly automatic check hasn’t run yet.</template
                    >
                    A generation that finished moments ago can briefly differ
                    until its webhook lands.
                </p>
            </section>

            <!-- Generations -->
            <section class="rounded-xl border bg-card">
                <h2 class="border-b px-4 py-3 text-sm font-semibold">
                    Generations
                </h2>
                <dl class="grid grid-cols-2 gap-px bg-border sm:grid-cols-4">
                    <div class="bg-card p-4">
                        <dt class="text-xs text-muted-foreground">In flight</dt>
                        <dd class="font-mono text-xl tabular-nums">
                            {{ generations.in_flight }}
                        </dd>
                    </div>
                    <div class="bg-card p-4">
                        <dt class="text-xs text-muted-foreground">
                            Waiting for capacity
                        </dt>
                        <dd class="font-mono text-xl tabular-nums">
                            {{ generations.waiting_for_capacity }}
                        </dd>
                    </div>
                    <div class="bg-card p-4">
                        <dt class="text-xs text-muted-foreground">
                            Quiet &gt; 5 min
                        </dt>
                        <dd
                            class="font-mono text-xl tabular-nums"
                            :class="{ 'text-destructive': generations.stuck }"
                        >
                            {{ generations.stuck }}
                        </dd>
                    </div>
                    <div class="bg-card p-4">
                        <dt class="text-xs text-muted-foreground">
                            Images not saved
                        </dt>
                        <dd class="font-mono text-xl tabular-nums">
                            {{ generations.images_not_saved }}
                        </dd>
                    </div>
                </dl>
                <div class="border-t px-4 py-3">
                    <h3 class="mb-2 text-xs font-medium text-muted-foreground">
                        Failures in the last 24 hours (all free to users)
                    </h3>
                    <p
                        v-if="!generations.failures.length"
                        class="text-sm text-muted-foreground"
                    >
                        None.
                    </p>
                    <ul v-else class="space-y-1.5 text-sm">
                        <li
                            v-for="f in generations.failures"
                            :key="f.code ?? 'none'"
                            class="flex items-start justify-between gap-3"
                        >
                            <span
                                ><span class="font-mono text-xs">{{
                                    f.code
                                }}</span>
                                <span class="text-muted-foreground"
                                    >· {{ f.message }}</span
                                ></span
                            >
                            <span class="font-mono tabular-nums">{{
                                f.count
                            }}</span>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <!-- Event log -->
        <section class="overflow-x-auto rounded-xl border bg-card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">
                Webhook events
            </h2>
            <p
                v-if="!events.data.length"
                class="px-4 py-6 text-sm text-muted-foreground"
            >
                No webhooks yet.
            </p>
            <table v-else class="w-full text-sm">
                <thead class="text-xs text-muted-foreground">
                    <tr class="border-b">
                        <th class="px-4 py-2 text-left font-medium">
                            Received
                        </th>
                        <th class="px-4 py-2 text-left font-medium">Event</th>
                        <th class="px-4 py-2 text-left font-medium">
                            Generation
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Attempt
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Duplicates
                        </th>
                        <th class="px-4 py-2 text-left font-medium">Outcome</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in events.data"
                        :key="e.id"
                        class="border-b last:border-0"
                    >
                        <td
                            class="px-4 py-2 whitespace-nowrap text-muted-foreground"
                            :title="absoluteTime(e.created_at)"
                        >
                            {{ relativeTime(e.created_at) }}
                        </td>
                        <td class="px-4 py-2">
                            <span class="font-mono text-xs">{{ e.type }}</span
                            ><span
                                class="block font-mono text-[11px] text-muted-foreground"
                                >{{ e.event_id }}</span
                            >
                        </td>
                        <td
                            class="max-w-xs truncate px-4 py-2"
                            :title="e.prompt ?? undefined"
                        >
                            {{ e.prompt ?? e.provider_generation_id ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-right font-mono tabular-nums">
                            {{ e.delivery_attempt ?? '—' }}
                        </td>
                        <td
                            class="px-4 py-2 text-right font-mono tabular-nums"
                            :class="{ 'font-semibold': e.duplicate_deliveries }"
                        >
                            {{ e.duplicate_deliveries || '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs whitespace-nowrap"
                                :class="
                                    outcomeStyle[e.outcome] ??
                                    'bg-studio-choice'
                                "
                                >{{
                                    outcomeLabel[e.outcome] ?? e.outcome
                                }}</span
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
            <nav
                v-if="events.last_page > 1"
                class="flex justify-end gap-3 border-t px-4 py-2 text-sm"
            >
                <Link
                    v-if="events.prev_page_url"
                    :href="events.prev_page_url"
                    preserve-scroll
                    class="text-accent-vivid hover:underline"
                    >← Newer</Link
                >
                <span class="text-muted-foreground"
                    >Page {{ events.current_page }} of
                    {{ events.last_page }}</span
                >
                <Link
                    v-if="events.next_page_url"
                    :href="events.next_page_url"
                    preserve-scroll
                    class="text-accent-vivid hover:underline"
                    >Older →</Link
                >
            </nav>
        </section>
    </div>
</template>
