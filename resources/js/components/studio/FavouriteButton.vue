<script setup lang="ts">
import { Star } from '@lucide/vue';
import { useGenerationActions } from '@/composables/useGenerationActions';
import { cn } from '@/lib/utils';
import type { Generation } from '@/types';

const props = defineProps<{
    generation: Generation;
    class?: string;
    showLabel?: boolean;
}>();
const { isFavourite, toggleFavourite } = useGenerationActions();
</script>

<template>
    <button
        type="button"
        :aria-pressed="isFavourite(generation)"
        :aria-label="
            isFavourite(generation)
                ? 'Remove from favourites'
                : 'Add to favourites'
        "
        :title="
            isFavourite(generation)
                ? 'Remove from favourites'
                : 'Add to favourites'
        "
        :class="
            cn(
                'group/fav inline-flex items-center gap-1.5 rounded-lg text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                props.class,
            )
        "
        @click.stop="toggleFavourite(generation)"
    >
        <Star
            class="size-4 transition-transform duration-150 group-active/fav:scale-125 motion-reduce:transition-none"
            :class="
                isFavourite(generation)
                    ? 'fill-amber-400 text-amber-400'
                    : 'text-current'
            "
        />
        <span v-if="showLabel">{{
            isFavourite(generation) ? 'Favourited' : 'Favourite'
        }}</span>
    </button>
</template>
