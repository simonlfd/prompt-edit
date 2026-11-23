<script setup lang="ts">
import { Clock, ImageOff, Sparkles, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CostBreakdown from '@/components/studio/CostBreakdown.vue';
import GenerationActions from '@/components/studio/GenerationActions.vue';
import StatusPill from '@/components/studio/StatusPill.vue';
import {
    aspectClass,
    expiresIn,
    formatDuration,
    formatElapsed,
    isInFlight,
    progressLabel,
    secondsBetween,
} from '@/lib/generation';
import type { Generation } from '@/types';

const props = defineProps<{
    generation: Generation | null;
    now: number;
    markupPercent: number;
}>();
const emit = defineEmits<{ reuse: [Generation] }>();

const imageFailed = ref(false);
watch(
    () => props.generation?.id,
    () => (imageFailed.value = false),
);

const g = computed(() => props.generation);

const timing = computed(() => {
    if (!g.value) {
        return '';
    }

    if (isInFlight(g.value)) {
        return formatElapsed(secondsBetween(g.value.created_at, props.now));
    }

    const took = g.value.completed_at
        ? formatDuration(
              secondsBetween(g.value.created_at, g.value.completed_at),
          )
        : null;

    return took
        ? g.value.status === 'succeeded'
            ? `Ready in ${took}`
            : `Failed after ${took}`
        : '';
});

const retryIn = computed(() => {
    const at = g.value?.next_attempt_at;
    const seconds = at
        ? Math.ceil(secondsBetween(new Date(props.now).toISOString(), at))
        : 0;

    return seconds > 0 ? seconds : null;
});

const expiry = computed(() =>
    g.value ? expiresIn(g.value.image_url_expires_at, props.now) : null,
);
</script>

<template>
    <!-- Empty: invite the first generation -->
    <div
        v-if="!g"
        class="flex min-h-[28rem] flex-col items-center justify-center gap-3 rounded-2xl border border-dashed bg-card p-10 text-center"
    >
        <div
            class="flex size-12 items-center justify-center rounded-xl bg-lime/20"
        >
            <Sparkles class="size-6 text-accent-vivid" />
        </div>
        <h2 class="text-lg font-semibold">Your image will appear here</h2>
        <p class="max-w-sm text-sm text-muted-foreground">
            Write a prompt and press
            <kbd
                class="rounded border bg-studio-page px-1.5 py-0.5 font-mono text-xs"
                >⌘</kbd
            >
            <kbd
                class="rounded border bg-studio-page px-1.5 py-0.5 font-mono text-xs"
                >Enter</kbd
            >
            to generate. You’ll see exactly what it costs before and after.
        </p>
    </div>

    <article
        v-else
        class="@container overflow-hidden rounded-2xl border bg-card"
        :aria-label="`Current image: ${g.prompt}`"
        aria-live="polite"
    >
        <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
            <StatusPill :generation="g" />
            <span
                class="inline-flex items-center gap-1.5 font-mono text-xs text-muted-foreground tabular-nums"
            >
                <Clock class="size-3.5" />{{ timing }}
            </span>
        </div>

        <!-- The image, or what's happening instead -->
        <div
            class="flex items-center justify-center bg-studio-page-alt p-4 md:p-6"
        >
            <div
                class="relative w-full max-w-[min(100%,36rem)] overflow-hidden rounded-xl"
                :class="aspectClass(g.size)"
            >
                <img
                    v-if="
                        g.status === 'succeeded' && g.image_url && !imageFailed
                    "
                    :key="g.id"
                    :src="g.image_url"
                    :alt="g.prompt"
                    class="size-full animate-in object-contain duration-500 zoom-in-95 fade-in motion-reduce:animate-none"
                    @error="imageFailed = true"
                />
                <div
                    v-else-if="g.status === 'succeeded'"
                    class="flex size-full flex-col items-center justify-center gap-2 bg-card text-center text-sm text-muted-foreground"
                >
                    <ImageOff class="size-6" />
                    Image link expired. Pixelforge keeps images for 24 hours.
                </div>
                <div
                    v-else-if="g.status === 'failed'"
                    class="flex size-full flex-col items-center justify-center gap-2 bg-card p-6 text-center"
                >
                    <TriangleAlert class="size-6 text-destructive" />
                    <p class="font-medium">{{ g.error?.message }}</p>
                    <p class="text-sm text-muted-foreground">
                        You weren’t charged.
                        <span class="font-mono text-xs">{{
                            g.error?.code
                        }}</span>
                    </p>
                </div>
                <div
                    v-else
                    class="relative flex size-full flex-col items-center justify-center gap-3 overflow-hidden bg-card text-center"
                >
                    <div
                        class="absolute inset-0 animate-pulse bg-gradient-to-br from-lime/10 via-studio-choice to-info/10 motion-reduce:animate-none"
                    />
                    <div class="relative flex flex-col items-center gap-2 p-6">
                        <Sparkles
                            class="size-6 animate-pulse text-accent-vivid motion-reduce:animate-none"
                        />
                        <p class="font-medium">{{ progressLabel(g) }}…</p>
                        <p
                            v-if="g.waiting_for_capacity"
                            class="max-w-xs text-sm text-muted-foreground"
                        >
                            {{ g.error?.message }} We’ll keep retrying<template
                                v-if="retryIn"
                                >, next try in {{ retryIn }}s</template
                            >. Nothing is charged while it waits.
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            Usually 5–30 seconds.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-5 p-4 md:p-5 @2xl:grid-cols-[minmax(0,1fr)_17rem]">
            <div class="min-w-0 space-y-3">
                <p class="text-base leading-relaxed">{{ g.prompt }}</p>
                <div
                    class="flex flex-wrap gap-1.5 font-mono text-xs text-muted-foreground"
                >
                    <span class="rounded-md bg-studio-choice px-2 py-0.5">{{
                        g.size.replace('x', '×')
                    }}</span>
                    <span class="rounded-md bg-studio-choice px-2 py-0.5">{{
                        g.quality
                    }}</span>
                    <span
                        v-if="g.reference_images.length"
                        class="rounded-md bg-studio-choice px-2 py-0.5"
                        >{{ g.reference_images.length }} reference{{
                            g.reference_images.length === 1 ? '' : 's'
                        }}</span
                    >
                    <span
                        v-if="expiry && g.status === 'succeeded'"
                        class="rounded-md bg-studio-choice px-2 py-0.5"
                        title="Pixelforge image links expire 24 hours after the image is made. Download it to keep it."
                        >download link: {{ expiry }}</span
                    >
                </div>
                <GenerationActions
                    :generation="g"
                    :now="now"
                    @reuse="emit('reuse', $event)"
                />
            </div>
            <CostBreakdown :generation="g" :markup-percent="markupPercent" />
        </div>
    </article>
</template>
