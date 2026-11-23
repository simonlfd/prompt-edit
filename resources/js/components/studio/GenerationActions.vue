<script setup lang="ts">
import { Copy, Download, RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import RetryButton from '@/components/studio/RetryButton.vue';
import { Button } from '@/components/ui/button';
import { useGenerationActions } from '@/composables/useGenerationActions';
import { imageAvailable } from '@/lib/generation';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation; now: number }>();
const emit = defineEmits<{ reuse: [Generation] }>();
const { copyPrompt } = useGenerationActions();

const canDownload = computed(() => imageAvailable(props.generation, props.now));
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <RetryButton
            v-if="generation.status === 'failed'"
            :generation="generation"
        />
        <Button
            v-if="generation.status === 'succeeded' && !canDownload"
            variant="outline"
            disabled
            title="Pixelforge keeps images for 24 hours"
        >
            <Download /> Expired
        </Button>
        <Button variant="ghost" @click="copyPrompt(generation)">
            <Copy /> Copy prompt
        </Button>
        <Button variant="ghost" @click="emit('reuse', generation)">
            <RotateCcw /> Reuse settings
        </Button>
    </div>
</template>
