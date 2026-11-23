<script setup lang="ts">
import { Download, Loader, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import StatusPill from '@/components/studio/StatusPill.vue';
import FavouriteButton from '@/components/studio/FavouriteButton.vue';
import { useGenerationActions } from '@/composables/useGenerationActions';
import { imageAvailable, isInFlight } from '@/lib/generation';
import { formatUsd } from '@/lib/money';
import type { Generation } from '@/types';

defineProps<{ generation: Generation; now: number }>();
defineEmits<{ open: [] }>();

const { downloadUrl, isFavourite } = useGenerationActions();
const broken = ref(false);
</script>

<template>
    <figure
        class="group relative aspect-square overflow-hidden rounded-xl border bg-studio-page-alt"
    >
        <button
            type="button"
            class="absolute inset-0 z-0 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
            :aria-label="`Open: ${generation.prompt}`"
            @click="$emit('open')"
        >
            <img
                v-if="
                    generation.status === 'succeeded' &&
                    generation.image_url &&
                    !broken
                "
                :src="generation.image_url"
                :alt="generation.prompt"
                loading="lazy"
                class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none"
                @error="broken = true"
            />
            <span
                v-else
                class="flex size-full flex-col items-center justify-center gap-2 p-4 text-center text-xs text-muted-foreground"
            >
                <Loader
                    v-if="isInFlight(generation)"
                    class="size-5 animate-spin motion-reduce:animate-none"
                />
                <TriangleAlert
                    v-else-if="generation.status === 'failed'"
                    class="size-5 text-destructive"
                />
                <span class="line-clamp-3">{{
                    generation.status === 'succeeded'
                        ? 'Image link expired'
                        : generation.prompt
                }}</span>
            </span>
        </button>

        <div
            v-if="generation.status !== 'succeeded'"
            class="pointer-events-none absolute top-2 left-2"
        >
            <StatusPill :generation="generation" />
        </div>

        <!-- Hover / focus overlay -->
        <figcaption
            v-if="generation.status === 'succeeded'"
            class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/60 to-transparent p-3 pt-10 text-white opacity-0 transition-opacity duration-200 group-focus-within:opacity-100 group-hover:opacity-100"
        >
            <p class="line-clamp-2 text-sm">{{ generation.prompt }}</p>
            <p class="mt-1 font-mono text-xs text-white/70">
                {{ generation.size.replace('x', '×') }} ·
                {{ generation.quality }}
                <template v-if="generation.actual_charge !== null"
                    >· {{ formatUsd(generation.actual_charge) }}</template
                >
            </p>
        </figcaption>

        <div
            class="absolute top-2 right-2 flex gap-1.5 transition-opacity duration-200"
            :class="
                isFavourite(generation)
                    ? 'opacity-100'
                    : 'opacity-0 group-focus-within:opacity-100 group-hover:opacity-100'
            "
        >
            <a
                v-if="imageAvailable(generation, now)"
                :href="downloadUrl(generation)"
                download
                class="flex size-8 items-center justify-center rounded-lg bg-black/50 text-white backdrop-blur hover:bg-black/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Download"
                title="Download"
                @click.stop
            >
                <Download class="size-4" />
            </a>
            <FavouriteButton
                :generation="generation"
                class="size-8 justify-center bg-black/50 text-white backdrop-blur hover:bg-black/70"
            />
        </div>
    </figure>
</template>
