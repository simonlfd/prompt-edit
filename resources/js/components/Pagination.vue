<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        currentPage: number;
        lastPage: number;
        total: number;
        from: number | null;
        to: number | null;
        /** Query parameter, for pages with more than one paginated list. */
        pageName?: string;
        /** e.g. "images", "entries" */
        noun?: string;
    }>(),
    { pageName: 'page', noun: 'results' },
);

const page = usePage();

/** The current URL (keeping filters and sort) with just this list's page changed. */
function urlFor(n: number): string {
    const url = new URL(page.url, 'http://placeholder');
    url.searchParams.set(props.pageName, String(n));

    return url.pathname + url.search;
}

/** 1 … 4 5 [6] 7 8 … 20 */
const pages = computed<(number | '…')[]>(() => {
    const { currentPage: c, lastPage: last } = props;
    const wanted = new Set(
        [1, last, c - 2, c - 1, c, c + 1, c + 2].filter(
            (n) => n >= 1 && n <= last,
        ),
    );
    const sorted = [...wanted].sort((a, b) => a - b);
    const out: (number | '…')[] = [];

    sorted.forEach((n, i) => {
        if (i > 0 && n - sorted[i - 1] > 1) {
            out.push('…');
        }
        out.push(n);
    });

    return out;
});

const linkClass =
    'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 font-mono text-xs tabular-nums transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <nav
        v-if="total > 0"
        class="flex flex-wrap items-center justify-between gap-3 text-sm"
        :aria-label="`Pages of ${noun}`"
    >
        <p class="text-muted-foreground">
            <template v-if="lastPage > 1">
                <span class="font-mono tabular-nums">{{ from }}–{{ to }}</span>
                of
            </template>
            <span class="font-mono tabular-nums">{{
                total.toLocaleString()
            }}</span>
            {{ noun }}
        </p>

        <div v-if="lastPage > 1" class="flex items-center gap-1">
            <Link
                v-if="currentPage > 1"
                :href="urlFor(currentPage - 1)"
                preserve-scroll
                :class="[linkClass, 'hover:bg-accent']"
                aria-label="Previous page"
                rel="prev"
            >
                <ChevronLeft class="size-4" />
            </Link>
            <span v-else :class="[linkClass, 'opacity-40']" aria-hidden="true"
                ><ChevronLeft class="size-4"
            /></span>

            <template v-for="(p, i) in pages" :key="`${p}-${i}`">
                <span v-if="p === '…'" class="px-1 text-muted-foreground"
                    >…</span
                >
                <span
                    v-else-if="p === currentPage"
                    :class="[
                        linkClass,
                        'bg-card font-semibold shadow-pop ring-1 ring-border',
                    ]"
                    aria-current="page"
                    >{{ p }}</span
                >
                <Link
                    v-else
                    :href="urlFor(p)"
                    preserve-scroll
                    :class="[
                        linkClass,
                        'text-muted-foreground hover:bg-accent hover:text-foreground',
                    ]"
                    :aria-label="`Page ${p}`"
                    >{{ p }}</Link
                >
            </template>

            <Link
                v-if="currentPage < lastPage"
                :href="urlFor(currentPage + 1)"
                preserve-scroll
                :class="[linkClass, 'hover:bg-accent']"
                aria-label="Next page"
                rel="next"
            >
                <ChevronRight class="size-4" />
            </Link>
            <span v-else :class="[linkClass, 'opacity-40']" aria-hidden="true"
                ><ChevronRight class="size-4"
            /></span>
        </div>
    </nav>
</template>
