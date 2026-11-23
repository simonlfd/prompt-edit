<script setup lang="ts">
import { computed } from 'vue';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation }>();

const look = computed(() => {
    const g = props.generation;

    if (g.status === 'pending' && g.waiting_for_capacity) {
        return {
            label: 'Waiting for capacity',
            dot: 'bg-warning',
            pill: 'bg-warning/12 text-foreground',
            pulse: false,
        };
    }

    return {
        pending: {
            label: 'Submitting',
            dot: 'bg-muted-foreground',
            pill: 'bg-studio-choice',
            pulse: true,
        },
        queued: {
            label: 'Queued',
            dot: 'bg-muted-foreground',
            pill: 'bg-studio-choice',
            pulse: false,
        },
        processing: {
            label: 'Generating',
            dot: 'bg-info',
            pill: 'bg-info/12',
            pulse: true,
        },
        succeeded: {
            label: 'Ready',
            dot: 'bg-lime',
            pill: 'bg-lime/15',
            pulse: false,
        },
        failed: {
            label: 'Failed',
            dot: 'bg-destructive',
            pill: 'bg-destructive/10 text-destructive',
            pulse: false,
        },
    }[g.status];
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
        :class="look.pill"
    >
        <span class="relative flex size-2">
            <span
                v-if="look.pulse"
                class="absolute inline-flex size-full animate-ping rounded-full opacity-60 motion-reduce:hidden"
                :class="look.dot"
            />
            <span
                class="relative inline-flex size-2 rounded-full"
                :class="look.dot"
            />
        </span>
        {{ look.label }}
    </span>
</template>
