<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { ImagePlus, Star } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import GalleryLightbox from '@/components/gallery/GalleryLightbox.vue';
import Pagination from '@/components/Pagination.vue';
import GalleryTile from '@/components/gallery/GalleryTile.vue';
import { Button } from '@/components/ui/button';
import { useGenerationActions } from '@/composables/useGenerationActions';
import { isInFlight } from '@/lib/generation';
import { dashboard, gallery } from '@/routes';
import type { Generation, Paginated } from '@/types';

type Filter = 'all' | 'favourites' | 'ready' | 'failed';
type Sort = 'newest' | 'oldest' | 'cost';

const props = defineProps<{
    generations: Paginated<Generation>;
    filter: Filter;
    sort: Sort;
    counts: Record<Filter, number>;
    markup_percent: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Gallery', href: gallery() }] },
});

const filters: { value: Filter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'favourites', label: 'Favourites' },
    { value: 'ready', label: 'Ready' },
    { value: 'failed', label: 'Failed' },
];

const sorts: { value: Sort; label: string }[] = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'cost', label: 'Highest cost' },
];

function visit(params: { filter?: Filter; sort?: Sort; page?: number }) {
    router.get(
        gallery.url({
            query: { filter: props.filter, sort: props.sort, ...params },
        }),
        {},
        {
            preserveScroll: params.page === undefined,
            preserveState: true,
            replace: true,
        },
    );
}

// In the favourites view, un-starring hides the tile right away.
const { isFavourite } = useGenerationActions();
const items = computed(() =>
    props.filter === 'favourites'
        ? props.generations.data.filter((g) => isFavourite(g))
        : props.generations.data,
);

const lightbox = ref<number | null>(null);

const { start, stop } = usePoll(
    3000,
    { only: ['generations', 'counts'] },
    { autoStart: false },
);
watch(
    () => props.generations.data.some(isInFlight),
    (active) => (active ? start() : stop()),
    { immediate: true },
);

const now = ref(Date.now());
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => (clock = setInterval(() => (now.value = Date.now()), 30_000)));
onBeforeUnmount(() => clearInterval(clock));
</script>

<template>
    <Head title="Gallery" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Gallery</h1>
                <p class="text-sm text-muted-foreground">
                    Everything you’ve made, kept safe even after Pixelforge’s
                    24-hour links expire. Star the keepers.
                </p>
            </div>
            <Button as-child>
                <Link :href="dashboard()"><ImagePlus /> New image</Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div
                role="tablist"
                aria-label="Filter"
                class="inline-flex gap-1 rounded-lg bg-studio-choice p-1"
            >
                <button
                    v-for="f in filters"
                    :key="f.value"
                    role="tab"
                    type="button"
                    :aria-selected="filter === f.value"
                    class="inline-flex items-center gap-1.5 rounded-[calc(var(--radius-lg)-3px)] px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground aria-selected:bg-card aria-selected:text-foreground aria-selected:shadow-pop"
                    @click="visit({ filter: f.value, page: 1 })"
                >
                    <Star
                        v-if="f.value === 'favourites'"
                        class="size-3.5"
                        :class="{
                            'fill-amber-400 text-amber-400':
                                filter === 'favourites',
                        }"
                    />
                    {{ f.label }}
                    <span class="font-mono text-xs text-muted-foreground">{{
                        counts[f.value]
                    }}</span>
                </button>
            </div>

            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                Sort
                <select
                    :value="sort"
                    class="h-9 rounded-lg border border-input bg-card px-2 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @change="
                        visit({
                            sort: ($event.target as HTMLSelectElement)
                                .value as Sort,
                            page: 1,
                        })
                    "
                >
                    <option v-for="s in sorts" :key="s.value" :value="s.value">
                        {{ s.label }}
                    </option>
                </select>
            </label>
        </div>

        <div
            v-if="!items.length"
            class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed p-16 text-center"
        >
            <Star
                v-if="filter === 'favourites'"
                class="size-8 text-muted-foreground"
            />
            <ImagePlus v-else class="size-8 text-muted-foreground" />
            <p class="max-w-sm text-sm text-muted-foreground">
                {{
                    {
                        all: 'No images yet. Head to the studio to make your first one.',
                        favourites:
                            'No favourites yet. Tap the star on any image to keep it here.',
                        ready: 'No finished images yet.',
                        failed: 'Nothing has failed. Nice.',
                    }[filter]
                }}
            </p>
        </div>

        <div
            v-else
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6"
        >
            <GalleryTile
                v-for="(g, i) in items"
                :key="g.id"
                :generation="g"
                :now="now"
                @open="lightbox = i"
            />
        </div>
        <Pagination
            :current-page="generations.meta.current_page"
            :last-page="generations.meta.last_page"
            :total="generations.meta.total"
            :from="generations.meta.from"
            :to="generations.meta.to"
            noun="images"
        />

        <GalleryLightbox
            v-model:index="lightbox"
            :items="items"
            :now="now"
            :markup-percent="markup_percent"
        />
    </div>
</template>
