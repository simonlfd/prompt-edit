<script setup lang="ts">
import { Head, usePage, usePoll } from '@inertiajs/vue3';
import { Wallet } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BuyCreditsDialog from '@/components/studio/BuyCreditsDialog.vue';
import GenerateForm from '@/components/studio/GenerateForm.vue';
import GenerationRail from '@/components/studio/GenerationRail.vue';
import GenerationStage from '@/components/studio/GenerationStage.vue';
import { Button } from '@/components/ui/button';
import { isInFlight } from '@/lib/generation';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { dashboard } from '@/routes';
import type { Generation, StudioOptions } from '@/types';

const props = defineProps<{
    generations: { data: Generation[] };
    favourites: { data: Generation[] };
    options: StudioOptions;
    credit_packs: number[];
    reuse: Generation | null;
    focus: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Studio', href: dashboard() }],
    },
});

const page = usePage();
const buyOpen = ref(false);
const form = ref<InstanceType<typeof GenerateForm> | null>(null);

const recent = computed(() => props.generations.data);
const all = computed(() => {
    const byId = new Map<string, Generation>();
    [...props.favourites.data, ...recent.value].forEach((g) =>
        byId.set(g.id, g),
    );

    return byId;
});

// The stage shows the selected generation, defaulting to the newest.
const selectedId = ref<string | null>(
    props.focus ?? recent.value[0]?.id ?? null,
);
const current = computed(
    () =>
        (selectedId.value && all.value.get(selectedId.value)) ||
        recent.value[0] ||
        null,
);

// When a new generation appears (you just submitted one), bring it on stage.
watch(
    () => recent.value[0]?.id,
    (newest, previous) => {
        if (newest && newest !== previous) {
            selectedId.value = newest;
        }
    },
);

// Live status: poll only while something is in flight.
const inFlightCount = computed(() => recent.value.filter(isInFlight).length);
const { start, stop } = usePoll(
    2000,
    { only: ['generations', 'favourites', 'balance'] },
    { autoStart: false },
);
watch(inFlightCount, (n) => (n > 0 ? start() : stop()), { immediate: true });

// Tell people when something finishes, even if they're looking at another image.
const lastStatus = new Map(recent.value.map((g) => [g.id, g.status]));
watch(recent, (list) => {
    for (const g of list) {
        const before = lastStatus.get(g.id);

        if (before && isInFlight({ ...g, status: before }) && !isInFlight(g)) {
            const view = {
                label: 'View',
                onClick: () => (selectedId.value = g.id),
            };

            if (g.status === 'succeeded') {
                toast.success('Your image is ready', {
                    description: g.prompt.slice(0, 80),
                    action: view,
                });
            } else {
                toast.error('Generation failed. You weren’t charged.', {
                    description: g.error?.message ?? undefined,
                    action: view,
                });
            }
        }

        lastStatus.set(g.id, g.status);
    }
});

function reuse(g: Generation) {
    form.value?.fill(g);
}

onMounted(() => {
    if (props.reuse) {
        form.value?.fill(props.reuse);
    }
});

// A clock for elapsed timers and retry countdowns.
const now = ref(Date.now());
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => (clock = setInterval(() => (now.value = Date.now()), 1000)));
onBeforeUnmount(() => clearInterval(clock));

const title = computed(() =>
    inFlightCount.value ? `(${inFlightCount.value}) Generating…` : 'Studio',
);
</script>

<template>
    <Head :title="title" />

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
            class="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)] lg:items-start min-[88rem]:grid-cols-[22rem_minmax(0,1fr)_13rem]"
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
                    ref="form"
                    :options="options"
                    @buy-credits="buyOpen = true"
                />
            </section>

            <section aria-label="Current image" class="min-w-0">
                <GenerationStage
                    :generation="current"
                    :now="now"
                    :markup-percent="options.markup_percent"
                    @reuse="reuse"
                />
            </section>

            <GenerationRail
                class="lg:col-start-2 min-[88rem]:sticky min-[88rem]:top-4 min-[88rem]:col-start-3 min-[88rem]:row-start-1"
                :recent="recent"
                :favourites="favourites.data"
                :selected-id="current?.id ?? null"
                @select="selectedId = $event.id"
            />
        </div>

        <BuyCreditsDialog v-model:open="buyOpen" :packs="credit_packs" />
    </div>
</template>
