<script setup lang="ts">
import { ImageOff, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{ position: number; error?: string }>();
const emit = defineEmits<{ remove: [] }>();
const url = defineModel<string>({ required: true });

const broken = ref(false);
watch(url, () => (broken.value = false));

const showPreview = computed(
    () => /^https?:\/\/\S+$/i.test(url.value.trim()) && !broken.value,
);
const label = computed(() => `Reference image ${props.position}`);
</script>

<template>
    <div class="space-y-1">
        <div class="flex items-center gap-2">
            <div
                class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-studio-page-alt"
            >
                <img
                    v-if="showPreview"
                    :src="url"
                    alt=""
                    class="size-full object-cover"
                    @error="broken = true"
                />
                <ImageOff v-else class="size-4 text-muted-foreground" />
            </div>
            <Input
                v-model="url"
                type="url"
                inputmode="url"
                placeholder="https://example.com/style.png"
                :aria-label="`${label} URL`"
                :aria-invalid="!!error"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :aria-label="`Remove ${label.toLowerCase()}`"
                @click="emit('remove')"
            >
                <X />
            </Button>
        </div>
        <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
    </div>
</template>
