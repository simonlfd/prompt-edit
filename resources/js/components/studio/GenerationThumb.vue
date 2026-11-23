<script setup lang="ts">
import { Loader, Star, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import { useGenerationActions } from '@/composables/useGenerationActions';
import { isInFlight } from '@/lib/generation';
import type { Generation } from '@/types';

defineProps<{ generation: Generation; selected: boolean }>();
defineEmits<{ select: [] }>();

const { isFavourite } = useGenerationActions();
const broken = ref(false);
</script>

<template>
    <button
        type="button"
        class="group relative aspect-square overflow-hidden rounded-lg border bg-studio-page-alt transition-shadow outline-none focus-visible:ring-2 focus-visible:ring-ring"
        :class="
            selected
                ? 'ring-2 ring-lime ring-offset-2 ring-offset-background'
                : 'hover:shadow-pop'
        "
        :aria-label="generation.prompt"
        :aria-current="selected ? 'true' : undefined"
        :title="generation.prompt"
        @click="$emit('select')"
    >
        <img
            v-if="
                generation.status === 'succeeded' &&
                generation.image_url &&
                !broken
            "
            :src="generation.image_url"
            alt=""
            loading="lazy"
            class="size-full object-cover transition-transform duration-300 group-hover:scale-105 motion-reduce:transition-none"
            @error="broken = true"
        />
        <span
            v-else-if="isInFlight(generation)"
            class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-lime/10 via-studio-choice to-info/10"
        >
            <Loader
                class="size-4 animate-spin text-muted-foreground motion-reduce:animate-none"
            />
        </span>
        <span
            v-else-if="generation.status === 'failed'"
            class="absolute inset-0 flex items-center justify-center"
        >
            <TriangleAlert class="size-4 text-destructive" />
        </span>
        <span
            v-else
            class="absolute inset-0 flex items-center justify-center p-2 text-[10px] text-muted-foreground"
            >Expired</span
        >
        <Star
            v-if="isFavourite(generation)"
            class="absolute top-1 right-1 size-3.5 fill-amber-400 text-amber-400 drop-shadow"
        />
    </button>
</template>
