<script setup lang="ts">
import { Copy, Download, RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import FavouriteButton from '@/components/studio/FavouriteButton.vue';
import { Button } from '@/components/ui/button';
import { useGenerationActions } from '@/composables/useGenerationActions';
import { imageAvailable } from '@/lib/generation';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation; now: number }>();
const emit = defineEmits<{ reuse: [Generation] }>();
const { copyPrompt, downloadUrl } = useGenerationActions();

const canDownload = computed(() => imageAvailable(props.generation, props.now));
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <FavouriteButton
            :generation="generation"
            show-label
            class="h-9 border bg-card px-3 hover:bg-accent"
        />
        <Button v-if="canDownload" variant="outline" as-child>
            <a :href="downloadUrl(generation)" download>
                <Download /> Download
            </a>
        </Button>
        <Button
            v-else-if="generation.status === 'succeeded'"
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
