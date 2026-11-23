<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    Download,
    ImageOff,
    TriangleAlert,
    RotateCcw,
} from '@lucide/vue';
import { computed } from 'vue';
import CostBreakdown from '@/components/studio/CostBreakdown.vue';
import FavouriteButton from '@/components/studio/FavouriteButton.vue';
import StatusPill from '@/components/studio/StatusPill.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useGenerationActions } from '@/composables/useGenerationActions';
import { aspectClass, expiresIn, imageAvailable } from '@/lib/generation';
import { absoluteTime } from '@/lib/time';
import { dashboard } from '@/routes';
import type { Generation } from '@/types';

const props = defineProps<{
    items: Generation[];
    now: number;
    markupPercent: number;
}>();
const index = defineModel<number | null>('index', { required: true });

const { copyPrompt, downloadUrl } = useGenerationActions();

const g = computed(() =>
    index.value === null ? null : (props.items[index.value] ?? null),
);
const open = computed({
    get: () => g.value !== null,
    set: (value: boolean) => {
        if (!value) {
            index.value = null;
        }
    },
});

function step(delta: number) {
    if (index.value === null) {
        return;
    }

    const next = index.value + delta;

    if (next >= 0 && next < props.items.length) {
        index.value = next;
    }
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowRight') {
        step(1);
    } else if (event.key === 'ArrowLeft') {
        step(-1);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            v-if="g"
            class="max-h-[92dvh] gap-0 overflow-y-auto p-0 sm:max-w-5xl"
            @keydown="onKeydown"
        >
            <div class="grid md:grid-cols-[minmax(0,1fr)_20rem]">
                <div
                    class="relative flex items-center justify-center bg-studio-page-alt p-4 md:p-6"
                >
                    <div
                        class="w-full max-w-xl overflow-hidden rounded-xl"
                        :class="aspectClass(g.size)"
                    >
                        <img
                            v-if="g.status === 'succeeded' && g.image_url"
                            :key="g.id"
                            :src="g.image_url"
                            :alt="g.prompt"
                            class="size-full animate-in object-contain duration-300 fade-in motion-reduce:animate-none"
                        />
                        <div
                            v-else
                            class="flex size-full flex-col items-center justify-center gap-2 bg-card p-6 text-center text-sm text-muted-foreground"
                        >
                            <TriangleAlert
                                v-if="g.status === 'failed'"
                                class="size-6 text-destructive"
                            />
                            <ImageOff v-else class="size-6" />
                            {{
                                g.status === 'failed'
                                    ? g.error?.message
                                    : g.status === 'succeeded'
                                      ? 'Image link expired'
                                      : 'Still generating…'
                            }}
                        </div>
                    </div>
                    <button
                        v-if="index! > 0"
                        type="button"
                        class="absolute top-1/2 left-3 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-card/90 shadow-pop hover:bg-card"
                        aria-label="Previous image"
                        @click="step(-1)"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        v-if="index! < items.length - 1"
                        type="button"
                        class="absolute top-1/2 right-3 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-card/90 shadow-pop hover:bg-card"
                        aria-label="Next image"
                        @click="step(1)"
                    >
                        <ChevronRight class="size-5" />
                    </button>
                </div>

                <div class="flex flex-col gap-4 p-5">
                    <div class="flex items-center justify-between gap-2 pr-8">
                        <StatusPill :generation="g" />
                        <span class="font-mono text-xs text-muted-foreground"
                            >{{ index! + 1 }} / {{ items.length }}</span
                        >
                    </div>
                    <DialogTitle
                        class="text-base leading-relaxed font-normal"
                        >{{ g.prompt }}</DialogTitle
                    >
                    <DialogDescription class="font-mono text-xs">
                        {{ g.size.replace('x', '×') }} · {{ g.quality }} ·
                        {{ absoluteTime(g.created_at) }}
                        <template
                            v-if="
                                expiresIn(g.image_url_expires_at, now) &&
                                g.status === 'succeeded'
                            "
                        >
                            · link {{ expiresIn(g.image_url_expires_at, now) }}
                        </template>
                    </DialogDescription>

                    <div class="flex flex-wrap gap-2">
                        <FavouriteButton
                            :generation="g"
                            show-label
                            class="h-9 border bg-card px-3 hover:bg-accent"
                        />
                        <Button
                            v-if="imageAvailable(g, now)"
                            variant="outline"
                            as-child
                        >
                            <a :href="downloadUrl(g)" download
                                ><Download /> Download</a
                            >
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Copy prompt"
                            title="Copy prompt"
                            @click="copyPrompt(g)"
                        >
                            <Copy />
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link :href="dashboard({ query: { reuse: g.id } })"
                                ><RotateCcw /> Reuse</Link
                            >
                        </Button>
                    </div>

                    <CostBreakdown
                        :generation="g"
                        :markup-percent="markupPercent"
                    />
                    <p class="hidden text-xs text-muted-foreground md:block">
                        Tip: use <kbd class="font-mono">←</kbd>
                        <kbd class="font-mono">→</kbd> to browse.
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
