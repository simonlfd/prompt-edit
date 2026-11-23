<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatUsd } from '@/lib/money';
import { store } from '@/routes/credits';

defineProps<{ packs: number[] }>();
const open = defineModel<boolean>('open', { required: true });

const page = usePage();
const buying = ref<number | null>(null);

function buy(amount: number) {
    buying.value = amount;
    router.post(
        store.url(),
        { amount },
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
            onFinish: () => (buying.value = null),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Buy credits</DialogTitle>
                <DialogDescription>
                    Your balance is
                    <span class="font-mono text-foreground">{{
                        formatUsd(page.props.balance ?? '0')
                    }}</span
                    >. This is a demo, so no payment is taken.
                </DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-3 gap-3">
                <Button
                    v-for="pack in packs"
                    :key="pack"
                    variant="outline"
                    class="h-16 flex-col gap-0.5 text-base"
                    :disabled="buying !== null"
                    @click="buy(pack)"
                >
                    <span class="font-mono font-medium">${{ pack }}</span>
                    <span class="text-xs font-normal text-muted-foreground">
                        {{ buying === pack ? 'Adding…' : 'Add' }}
                    </span>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
