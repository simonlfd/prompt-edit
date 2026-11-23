<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, Lock, Unlock, Wallet } from '@lucide/vue';
import { ref } from 'vue';
import BuyCreditsDialog from '@/components/studio/BuyCreditsDialog.vue';
import { Button } from '@/components/ui/button';
import { formatUsd, formatUsdExact, isNegative } from '@/lib/money';
import { absoluteTime, relativeTime } from '@/lib/time';
import { billing, dashboard } from '@/routes';

type Entry = {
    id: number;
    type: 'purchase' | 'hold' | 'release' | 'charge';
    amount: string;
    balance_after: string;
    description: string | null;
    created_at: string;
    generation: { id: string; prompt: string; status: string } | null;
};

defineProps<{
    summary: {
        bought: string;
        spent: string;
        reserved: string;
        images_paid_for: number;
    };
    // A Laravel length-aware paginator, serialised as-is.
    entries: {
        data: Entry[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    credit_packs: number[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Billing', href: billing() }] },
});

const page = usePage();
const buyOpen = ref(false);

const kinds = {
    purchase: {
        label: 'Credits added',
        icon: ArrowDownLeft,
        tone: 'bg-lime/15 text-accent-vivid',
    },
    hold: {
        label: 'Reserved',
        icon: Lock,
        tone: 'bg-studio-choice text-muted-foreground',
    },
    release: {
        label: 'Reservation returned',
        icon: Unlock,
        tone: 'bg-studio-choice text-muted-foreground',
    },
    charge: {
        label: 'Charged',
        icon: ArrowUpRight,
        tone: 'bg-studio-choice text-foreground',
    },
} as const;
</script>

<template>
    <Head title="Billing" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Billing</h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    Every change to your balance. When you generate, we reserve
                    the most it could cost. When it finishes we return the
                    reservation and charge the exact cost; if it fails, you get
                    it all back.
                </p>
            </div>
            <Button @click="buyOpen = true"><Wallet /> Buy credits</Button>
        </div>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Balance</p>
                <p
                    class="font-mono text-2xl font-medium tabular-nums"
                    :title="formatUsdExact(page.props.balance)"
                >
                    {{ formatUsd(page.props.balance) }}
                </p>
                <p class="text-xs text-muted-foreground">available to spend</p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Reserved right now</p>
                <p
                    class="font-mono text-2xl font-medium tabular-nums"
                    :title="formatUsdExact(summary.reserved)"
                >
                    {{ formatUsd(summary.reserved) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    for images still generating
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Spent on images</p>
                <p
                    class="font-mono text-2xl font-medium tabular-nums"
                    :title="formatUsdExact(summary.spent)"
                >
                    {{ formatUsd(summary.spent) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    across {{ summary.images_paid_for }} image{{
                        summary.images_paid_for === 1 ? '' : 's'
                    }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Credits added</p>
                <p class="font-mono text-2xl font-medium tabular-nums">
                    {{ formatUsd(summary.bought) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    including welcome credit
                </p>
            </div>
        </section>

        <section class="overflow-x-auto rounded-xl border bg-card">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">History</h2>
            <p
                v-if="!entries.data.length"
                class="px-4 py-6 text-sm text-muted-foreground"
            >
                Nothing yet.
            </p>
            <table v-else class="w-full text-sm">
                <thead class="text-xs text-muted-foreground">
                    <tr class="border-b">
                        <th class="px-4 py-2 text-left font-medium">When</th>
                        <th class="px-4 py-2 text-left font-medium">What</th>
                        <th class="px-4 py-2 text-right font-medium">Amount</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Balance
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in entries.data"
                        :key="e.id"
                        class="border-b last:border-0"
                    >
                        <td
                            class="px-4 py-2.5 whitespace-nowrap text-muted-foreground"
                            :title="absoluteTime(e.created_at)"
                        >
                            {{ relativeTime(e.created_at) }}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-start gap-2.5">
                                <span
                                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md"
                                    :class="kinds[e.type].tone"
                                >
                                    <component
                                        :is="kinds[e.type].icon"
                                        class="size-3.5"
                                    />
                                </span>
                                <span class="min-w-0">
                                    <span class="font-medium">{{
                                        kinds[e.type].label
                                    }}</span>
                                    <Link
                                        v-if="e.generation"
                                        :href="
                                            dashboard({
                                                query: { g: e.generation.id },
                                            })
                                        "
                                        class="block max-w-md truncate text-xs text-muted-foreground hover:text-foreground hover:underline"
                                    >
                                        {{ e.generation.prompt }}
                                    </Link>
                                    <span
                                        v-else-if="e.description"
                                        class="block text-xs text-muted-foreground"
                                        >{{ e.description }}</span
                                    >
                                </span>
                            </div>
                        </td>
                        <td
                            class="px-4 py-2.5 text-right font-mono whitespace-nowrap tabular-nums"
                            :class="
                                isNegative(e.amount) ? '' : 'text-accent-vivid'
                            "
                            :title="formatUsdExact(e.amount)"
                        >
                            {{ isNegative(e.amount) ? '−' : '+'
                            }}{{ formatUsdExact(e.amount.replace('-', '')) }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right font-mono whitespace-nowrap text-muted-foreground tabular-nums"
                        >
                            {{ formatUsd(e.balance_after) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <nav
                v-if="entries.last_page > 1"
                class="flex justify-end gap-3 border-t px-4 py-2 text-sm"
            >
                <Link
                    v-if="entries.prev_page_url"
                    :href="entries.prev_page_url"
                    preserve-scroll
                    class="text-accent-vivid hover:underline"
                    >← Newer</Link
                >
                <span class="text-muted-foreground"
                    >Page {{ entries.current_page }} of
                    {{ entries.last_page }}</span
                >
                <Link
                    v-if="entries.next_page_url"
                    :href="entries.next_page_url"
                    preserve-scroll
                    class="text-accent-vivid hover:underline"
                    >Older →</Link
                >
            </nav>
        </section>

        <BuyCreditsDialog v-model:open="buyOpen" :packs="credit_packs" />
    </div>
</template>
