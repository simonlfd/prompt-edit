<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import GenerationThumb from '@/components/studio/GenerationThumb.vue';
import { useGenerationActions } from '@/composables/useGenerationActions';
import { gallery } from '@/routes';
import type { Generation } from '@/types';

const props = defineProps<{
    recent: Generation[];
    favourites: Generation[];
    selectedId: string | null;
}>();
defineEmits<{ select: [Generation] }>();

const { isFavourite } = useGenerationActions();
const tab = ref<'recent' | 'favourites'>('recent');

// Reflect optimistic favourite toggles immediately: hide un-starred, include freshly starred recents.
const favouriteList = computed(() => {
    const byId = new Map<string, Generation>();
    [...props.favourites, ...props.recent].forEach((g) => byId.set(g.id, g));

    return [...byId.values()]
        .filter((g) => isFavourite(g))
        .sort((a, b) => b.created_at.localeCompare(a.created_at));
});

const items = computed(() =>
    tab.value === 'recent' ? props.recent : favouriteList.value,
);
</script>

<template>
    <aside class="rounded-2xl border bg-card p-3" aria-label="Your images">
        <div
            role="tablist"
            class="grid grid-cols-2 gap-1 rounded-lg bg-studio-choice p-1"
        >
            <button
                v-for="t in ['recent', 'favourites'] as const"
                :key="t"
                role="tab"
                type="button"
                :aria-selected="tab === t"
                class="rounded-[calc(var(--radius-lg)-3px)] px-1 py-1 text-xs font-medium whitespace-nowrap text-muted-foreground transition-colors aria-selected:bg-card aria-selected:text-foreground aria-selected:shadow-pop"
                @click="tab = t"
            >
                {{ t === 'recent' ? 'Recent' : 'Favourites' }}
                <span class="font-mono text-[10px] text-muted-foreground">{{
                    t === 'recent' ? recent.length : favouriteList.length
                }}</span>
            </button>
        </div>

        <div
            v-if="items.length"
            class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6 min-[88rem]:grid-cols-2"
        >
            <GenerationThumb
                v-for="g in items"
                :key="g.id"
                :generation="g"
                :selected="g.id === selectedId"
                @select="$emit('select', g)"
            />
        </div>
        <p v-else class="px-2 py-6 text-center text-xs text-muted-foreground">
            {{
                tab === 'recent'
                    ? 'Images you make show up here.'
                    : 'Star an image to keep it close at hand.'
            }}
        </p>

        <Link
            :href="gallery()"
            class="mt-3 flex items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-foreground"
        >
            Open gallery <ArrowRight class="size-3.5" />
        </Link>
    </aside>
</template>
