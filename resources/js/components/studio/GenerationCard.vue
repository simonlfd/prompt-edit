<script setup lang="ts">
import { ImageOff, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import StatusPill from '@/components/studio/StatusPill.vue';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { absoluteTime, relativeTime } from '@/lib/time';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation; now: number }>();

const imageFailed = ref(false);
const expanded = ref(false);

const g = computed(() => props.generation);
const inFlight = computed(() =>
    ['pending', 'queued', 'processing'].includes(g.value.status),
);

const aspect = computed(() => {
    const [w, h] = g.value.size.split('x').map(Number);

    return w === h ? 'aspect-square' : w > h ? 'aspect-[3/2]' : 'aspect-[2/3]';
});

const retryIn = computed(() => {
    if (!g.value.next_attempt_at) {
        return null;
    }

    const seconds = Math.ceil(
        (new Date(g.value.next_attempt_at).getTime() - props.now) / 1000,
    );

    return seconds > 0 ? seconds : null;
});

const tokenRows = computed(() =>
    g.value.usage
        ? [
              ['Prompt text', g.value.usage.input_text_tokens],
              ['Reference images', g.value.usage.input_image_tokens],
              ['Image output', g.value.usage.output_tokens],
              ['Reasoning', g.value.usage.reasoning_tokens],
          ]
        : [],
);
</script>

<template>
    <article
        class="flex flex-col overflow-hidden rounded-2xl border bg-card"
        :aria-label="`Generation: ${g.prompt}`"
    >
        <div
            class="relative flex items-center justify-center bg-studio-page-alt"
            :class="aspect"
        >
            <img
                v-if="g.status === 'succeeded' && g.image_url && !imageFailed"
                :src="g.image_url"
                :alt="g.prompt"
                loading="lazy"
                class="size-full object-cover"
                @error="imageFailed = true"
            />
            <div
                v-else-if="g.status === 'succeeded'"
                class="flex flex-col items-center gap-2 p-4 text-center text-xs text-muted-foreground"
            >
                <ImageOff class="size-5" />
                Image link expired. Pixelforge keeps images for 24 hours.
            </div>
            <div
                v-else-if="inFlight"
                class="absolute inset-0 animate-pulse bg-gradient-to-br from-studio-page-alt via-studio-choice to-studio-page-alt motion-reduce:animate-none"
            />
            <div
                v-else
                class="flex flex-col items-center gap-2 p-4 text-center text-xs text-muted-foreground"
            >
                <TriangleAlert class="size-5 text-destructive" />
                No image
            </div>

            <div class="absolute top-3 left-3">
                <StatusPill :generation="g" />
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <p
                class="cursor-pointer text-sm"
                :class="{ 'line-clamp-2': !expanded }"
                :title="expanded ? undefined : 'Show full prompt'"
                @click="expanded = !expanded"
            >
                {{ g.prompt }}
            </p>

            <p class="font-mono text-xs text-muted-foreground">
                {{ g.size.replace('x', '×') }} · {{ g.quality }}
                <template v-if="g.reference_images.length">
                    · {{ g.reference_images.length }}
                    {{ g.reference_images.length === 1 ? 'ref' : 'refs' }}
                </template>
            </p>

            <div
                v-if="g.status === 'failed' && g.error"
                class="rounded-lg bg-destructive/8 px-3 py-2 text-xs"
            >
                <p class="font-medium text-destructive">
                    {{ g.error.message }}
                </p>
                <p class="mt-0.5 text-muted-foreground">
                    You weren’t charged.
                    <span class="font-mono">{{ g.error.code }}</span>
                </p>
            </div>

            <div
                v-else-if="g.waiting_for_capacity && g.error"
                class="rounded-lg bg-warning/10 px-3 py-2 text-xs"
            >
                <p class="font-medium">
                    {{ g.error.message }} We’ll keep retrying.
                </p>
                <p class="mt-0.5 text-muted-foreground">
                    Nothing is charged while it waits.
                    <template v-if="retryIn"
                        >Next try in {{ retryIn }}s.</template
                    >
                </p>
            </div>

            <div
                class="mt-auto flex items-end justify-between gap-3 border-t pt-3"
            >
                <div class="text-xs">
                    <template v-if="g.status === 'succeeded'">
                        <p
                            class="font-mono text-sm font-medium tabular-nums"
                            :title="`Exactly ${formatUsdExact(g.actual_charge)}`"
                        >
                            {{ formatUsd(g.actual_charge) }}
                        </p>
                        <p
                            class="font-mono text-muted-foreground tabular-nums"
                            :title="`Estimated ${formatUsdExact(g.estimated_charge)}`"
                        >
                            est. {{ formatUsd(g.estimated_charge) }}
                        </p>
                    </template>
                    <template v-else-if="g.status === 'failed'">
                        <p class="font-mono text-sm font-medium tabular-nums">
                            $0.00
                            <span
                                class="font-sans text-xs font-normal text-muted-foreground"
                                >not charged</span
                            >
                        </p>
                        <p
                            class="font-mono text-muted-foreground tabular-nums line-through"
                        >
                            est. {{ formatUsd(g.estimated_charge) }}
                        </p>
                    </template>
                    <template v-else>
                        <p
                            class="font-mono text-sm font-medium tabular-nums"
                            :title="formatUsdExact(g.estimated_charge)"
                        >
                            ~{{ formatUsd(g.estimated_charge) }}
                        </p>
                        <p class="text-muted-foreground">
                            up to
                            <span class="font-mono tabular-nums">{{
                                formatUsd(g.max_charge)
                            }}</span>
                            reserved
                        </p>
                    </template>
                </div>
                <time
                    class="shrink-0 text-xs text-muted-foreground"
                    :datetime="g.created_at"
                    :title="absoluteTime(g.created_at)"
                >
                    {{ relativeTime(g.created_at, now) }}
                </time>
            </div>

            <details v-if="g.usage" class="text-xs">
                <summary
                    class="cursor-pointer text-muted-foreground hover:text-foreground"
                >
                    Token usage
                </summary>
                <dl class="mt-2 space-y-1">
                    <div
                        v-for="[label, count] in tokenRows"
                        :key="label"
                        class="flex justify-between"
                    >
                        <dt class="text-muted-foreground">{{ label }}</dt>
                        <dd class="font-mono tabular-nums">
                            {{ Number(count).toLocaleString() }}
                        </dd>
                    </div>
                </dl>
            </details>
        </div>
    </article>
</template>
