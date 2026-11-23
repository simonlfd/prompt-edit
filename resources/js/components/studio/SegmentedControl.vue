<script setup lang="ts">
type Option = { value: string; label: string; hint?: string };

defineProps<{ options: Option[]; label: string; name: string }>();
const model = defineModel<string>({ required: true });
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="label"
        class="grid auto-cols-fr grid-flow-col gap-1 rounded-lg bg-studio-choice p-1"
    >
        <label
            v-for="option in options"
            :key="option.value"
            class="flex cursor-pointer flex-col items-center rounded-[calc(var(--radius-lg)-3px)] px-2 py-1.5 text-center text-sm font-medium text-muted-foreground transition-colors duration-150 hover:text-foreground has-[:checked]:bg-card has-[:checked]:text-foreground has-[:checked]:shadow-pop has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
        >
            <input
                v-model="model"
                type="radio"
                class="sr-only"
                :name="name"
                :value="option.value"
            />
            <span>{{ option.label }}</span>
            <span
                v-if="option.hint"
                class="font-mono text-[11px] font-normal text-muted-foreground"
            >
                {{ option.hint }}
            </span>
        </label>
    </div>
</template>
