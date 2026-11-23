<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatUsd } from '@/lib/money';
import { retry } from '@/routes/generations';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation }>();
const busy = ref(false);

function tryAgain() {
    busy.value = true;
    router.post(
        retry.url(props.generation.id),
        {},
        { onFinish: () => (busy.value = false) },
    );
}
</script>

<template>
    <Button :disabled="busy" @click="tryAgain">
        <RefreshCw :class="{ 'animate-spin': busy }" />
        Try again
        <span class="font-mono text-xs font-normal opacity-70"
            >~{{ formatUsd(generation.estimated_charge) }}</span
        >
    </Button>
</template>
