<script setup lang="ts">
import { computed } from 'vue';
import { TOKEN_LABELS } from '@/lib/generation';
import { compareUsd, formatUsd, formatUsdExact } from '@/lib/money';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation; markupPercent: number }>();

const g = computed(() => props.generation);
const delta = computed(() => {
    const b = g.value.cost_breakdown;

    if (!b) {
        return null;
    }

    const sign = compareUsd(b.vs_estimate, '0');

    return sign === 0
        ? 'exactly as estimated'
        : `${formatUsd(b.vs_estimate.replace('-', ''))} ${sign > 0 ? 'more' : 'less'} than estimated`;
});
</script>

<template>
    <div class="rounded-xl border bg-studio-page p-4 text-sm">
        <!-- Finished: the real bill, line by line -->
        <template v-if="g.cost_breakdown">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-muted-foreground">You paid</span>
                <span
                    class="font-mono text-lg font-medium tabular-nums"
                    :title="`Exactly ${formatUsdExact(g.cost_breakdown.total)}`"
                    >{{ formatUsd(g.cost_breakdown.total) }}</span
                >
            </div>
            <p class="text-xs text-muted-foreground">
                Estimated {{ formatUsd(g.estimated_charge) }}, so {{ delta }}.
            </p>
            <dl class="mt-3 space-y-1 border-t pt-3 text-xs">
                <div
                    v-for="line in g.cost_breakdown.lines"
                    :key="line.key"
                    class="flex justify-between gap-3"
                >
                    <dt class="text-muted-foreground">
                        {{ TOKEN_LABELS[line.key] }}
                        <span class="block font-mono text-[11px]"
                            >{{ line.tokens.toLocaleString() }} tok × ${{
                                line.rate
                            }}/1M</span
                        >
                    </dt>
                    <dd class="font-mono tabular-nums">
                        {{ formatUsdExact(line.cost) }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3 border-t pt-1">
                    <dt class="text-muted-foreground">Pixelforge’s cost</dt>
                    <dd class="font-mono tabular-nums">
                        {{ formatUsdExact(g.cost_breakdown.provider_cost) }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">
                        Service fee ({{ markupPercent }}%)
                    </dt>
                    <dd class="font-mono tabular-nums">
                        {{ formatUsdExact(g.cost_breakdown.fee) }}
                    </dd>
                </div>
            </dl>
        </template>

        <!-- Failed: make "free" unmistakable -->
        <template v-else-if="g.status === 'failed'">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-muted-foreground">You paid</span>
                <span class="font-mono text-lg font-medium tabular-nums"
                    >$0.00</span
                >
            </div>
            <p class="text-xs text-muted-foreground">
                Failed generations are free. The
                {{ formatUsd(g.max_charge) }} we reserved is back in your
                balance.
            </p>
        </template>

        <!-- In flight -->
        <template v-else>
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-muted-foreground">Estimated</span>
                <span class="font-mono text-lg font-medium tabular-nums"
                    >~{{ formatUsd(g.estimated_charge) }}</span
                >
            </div>
            <p class="text-xs text-muted-foreground">
                Up to {{ formatUsd(g.max_charge) }} is reserved. You’ll pay the
                exact cost when it’s ready, and nothing if it fails.
            </p>
        </template>
    </div>
</template>
