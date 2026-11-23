<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import { formatUsd, formatUsdExact } from '@/lib/money';
import type { Estimate } from '@/types';

defineProps<{
    estimate: Estimate | null;
    loading: boolean;
    error: string | null;
    markupPercent: number;
}>();

const open = ref(false);

type TokenKey = Exclude<keyof Estimate['tokens'], 'total_tokens'>;

const rows: { key: TokenKey; label: string }[] = [
    { key: 'input_text_tokens', label: 'Prompt text' },
    { key: 'input_image_tokens', label: 'Reference images' },
    { key: 'output_tokens', label: 'Image output' },
    { key: 'reasoning_tokens', label: 'Reasoning' },
];
</script>

<template>
    <div class="rounded-xl border bg-studio-page p-4" aria-live="polite">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <template v-else-if="estimate">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm text-muted-foreground"
                    >Estimated cost</span
                >
                <span
                    class="font-mono text-lg font-medium tabular-nums transition-opacity"
                    :class="{ 'opacity-50': loading }"
                    :title="formatUsdExact(estimate.expected_charge)"
                >
                    ~{{ formatUsd(estimate.expected_charge) }}
                </span>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                Between
                <span class="font-mono tabular-nums">{{
                    formatUsd(estimate.min_charge)
                }}</span>
                and
                <span class="font-mono tabular-nums">{{
                    formatUsd(estimate.max_charge)
                }}</span
                >. The final cost is confirmed when the image is ready. We
                reserve the maximum from your balance and refund the difference.
            </p>

            <button
                type="button"
                class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                :aria-expanded="open"
                @click="open = !open"
            >
                How it’s calculated
                <ChevronDown
                    class="size-3.5 transition-transform"
                    :class="{ 'rotate-180': open }"
                />
            </button>

            <div v-if="open" class="mt-2 space-y-1 text-xs">
                <div
                    v-for="row in rows"
                    :key="row.key"
                    class="flex justify-between gap-3"
                >
                    <span class="text-muted-foreground">{{ row.label }}</span>
                    <span class="font-mono tabular-nums">
                        <template v-if="row.key === 'reasoning_tokens'">
                            {{
                                estimate.reasoning_range.min.toLocaleString()
                            }}–{{
                                estimate.reasoning_range.max.toLocaleString()
                            }}
                        </template>
                        <template v-else-if="row.key === 'input_text_tokens'">
                            ~{{ estimate.tokens[row.key].toLocaleString() }}
                        </template>
                        <template v-else>
                            {{ estimate.tokens[row.key].toLocaleString() }}
                        </template>
                        tok × ${{ estimate.rates[row.key] }}/1M
                    </span>
                </div>
                <div class="flex justify-between gap-3 border-t pt-1">
                    <span class="text-muted-foreground">Service fee</span>
                    <span class="font-mono tabular-nums"
                        >+{{ markupPercent }}%</span
                    >
                </div>
                <p class="pt-1 text-muted-foreground">
                    Prompt tokens are known within ±{{
                        Math.round(estimate.text_token_variance * 100)
                    }}% and reasoning tokens vary per image, so both are only
                    exact once it finishes.
                </p>
            </div>
        </template>

        <div v-else class="h-14 animate-pulse rounded-md bg-studio-choice" />
    </div>
</template>
