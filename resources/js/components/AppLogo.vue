<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <AppLogoIcon
        class="size-8! shrink-0 rounded-[9px] dark:ring-1 dark:ring-white/10"
    />
    <div class="ml-1 grid flex-1 text-left">
        <span
            class="truncate font-display text-[15px] leading-tight font-semibold tracking-tight"
        >
            PromptEdit
            <span class="font-normal text-muted-foreground">Studio</span>
        </span>
    </div>
</template>
