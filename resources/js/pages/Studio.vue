<script setup lang="ts">
import { Head, usePage, usePoll } from '@inertiajs/vue3';
import { ImagePlus, Wallet } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BuyCreditsDialog from '@/components/studio/BuyCreditsDialog.vue';
import GenerateForm from '@/components/studio/GenerateForm.vue';
import GenerationCard from '@/components/studio/GenerationCard.vue';
import { Button } from '@/components/ui/button';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { dashboard } from '@/routes';
import type { Generation, StudioOptions } from '@/types';

const props = defineProps<{
    generations: { data: Generation[] };
    options: StudioOptions;
    credit_packs: number[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Studio', href: dashboard() }],
    },
});

const page = usePage();
const buyOpen = ref(false);

// Live status: poll only while something is still in flight.
const inFlight = computed(() =>
    props.generations.data.some((g) =>
        ['pending', 'queued', 'processing'].includes(g.status),
    ),
);

const { start, stop } = usePoll(
    2500,
    { only: ['generations', 'balance'] },
    { autoStart: false },
);

watch(inFlight, (active) => (active ? start() : stop()), { immediate: true });

// A clock for relative times and retry countdowns.
const now = ref(Date.now());
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => (clock = setInterval(() => (now.value = Date.now()), 1000)));
onBeforeUnmount(() => clearInterval(clock));
</script>

<template>
    <Head title="Studio" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Studio</h1>
                <p class="text-sm text-muted-foreground">
                    Describe an image, see what it costs, and generate it.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <div
                    class="flex items-center gap-2 rounded-lg border bg-card px-3 py-1.5"
                    :title="`Exactly ${formatUsdExact(page.props.balance)}`"
                >
                    <Wallet class="size-4 text-muted-foreground" />
                    <span class="text-xs text-muted-foreground">Balance</span>
                    <span class="font-mono text-sm font-medium tabular-nums">
                        {{ formatUsd(page.props.balance) }}
                    </span>
                </div>
                <Button variant="outline" @click="buyOpen = true">
                    Buy credits
                </Button>
            </div>
        </div>

        <div
            class="grid gap-6 lg:grid-cols-[24rem_minmax(0,1fr)] lg:items-start"
        >
            <section
                aria-labelledby="new-generation"
                class="rounded-2xl border bg-card p-5 lg:sticky lg:top-4"
            >
                <h2 id="new-generation" class="mb-4 text-base font-semibold">
                    New image
                </h2>
                <p
                    v-if="!options.sizes.length"
                    class="text-sm text-destructive"
                >
                    Pixelforge’s price list is unavailable, so we can’t take new
                    generations right now. Try again in a minute.
                </p>
                <GenerateForm
                    v-else
                    :options="options"
                    @buy-credits="buyOpen = true"
                />
            </section>

            <section aria-labelledby="history" class="min-w-0">
                <div class="mb-4 flex items-baseline justify-between">
                    <h2 id="history" class="text-base font-semibold">
                        Your images
                    </h2>
                    <span v-if="inFlight" class="text-xs text-muted-foreground">
                        Updating live
                    </span>
                </div>

                <div
                    v-if="!generations.data.length"
                    class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed p-12 text-center"
                >
                    <ImagePlus class="size-8 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">
                        No images yet. Write a prompt to make your first one.
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3"
                    aria-live="polite"
                >
                    <GenerationCard
                        v-for="generation in generations.data"
                        :key="generation.id"
                        :generation="generation"
                        :now="now"
                    />
                </div>
            </section>
        </div>

        <BuyCreditsDialog v-model:open="buyOpen" :packs="credit_packs" />
    </div>
</template>
