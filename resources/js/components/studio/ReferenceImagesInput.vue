<script setup lang="ts">
import { Plus } from '@lucide/vue';
import ReferenceImageRow from '@/components/studio/ReferenceImageRow.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    max: number;
    errors: Record<string, string | undefined>;
}>();
const model = defineModel<string[]>({ required: true });

function add() {
    if (model.value.length < props.max) {
        model.value = [...model.value, ''];
    }
}

function remove(index: number) {
    model.value = model.value.filter((_, i) => i !== index);
}

function update(index: number, value: string) {
    model.value = model.value.map((url, i) => (i === index ? value : url));
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-baseline justify-between">
            <span class="text-sm font-medium">Reference images</span>
            <span class="font-mono text-xs text-muted-foreground">
                {{ model.length }} of {{ max }} · optional
            </span>
        </div>

        <ReferenceImageRow
            v-for="(url, index) in model"
            :key="index"
            :model-value="url"
            :position="index + 1"
            :error="errors[`reference_images.${index}`]"
            @update:model-value="update(index, $event)"
            @remove="remove(index)"
        />

        <Button
            v-if="model.length < max"
            type="button"
            variant="outline"
            size="sm"
            @click="add"
        >
            <Plus /> Add reference image
        </Button>
        <p class="text-xs text-muted-foreground">
            Each image adds 765 tokens to the cost. Pixelforge uses the URL as a
            style guide and doesn’t download it.
        </p>
        <p v-if="errors.reference_images" class="text-xs text-destructive">
            {{ errors.reference_images }}
        </p>
    </div>
</template>
