<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { CircleAlert, Sparkles } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CostEstimate from '@/components/studio/CostEstimate.vue';
import ReferenceImagesInput from '@/components/studio/ReferenceImagesInput.vue';
import SegmentedControl from '@/components/studio/SegmentedControl.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { compareUsd, formatUsd } from '@/lib/money';
import { estimate as estimateRoute, store } from '@/routes/generations';
import type { Estimate, Generation, StudioOptions } from '@/types';

const props = defineProps<{ options: StudioOptions }>();
const emit = defineEmits<{ buyCredits: [] }>();

const page = usePage();
const balance = computed(() => page.props.balance ?? '0');

const form = useForm({
    prompt: '',
    size: props.options.sizes[0] ?? '1024x1024',
    quality: props.options.qualities.includes('medium')
        ? 'medium'
        : (props.options.qualities[0] ?? 'medium'),
    reference_images: [] as string[],
});

const sizeOptions = computed(() =>
    props.options.sizes.map((size) => {
        const [w, h] = size.split('x').map(Number);
        const shape = w === h ? 'Square' : w > h ? 'Landscape' : 'Portrait';

        return { value: size, label: shape, hint: size.replace('x', '×') };
    }),
);

const qualityOptions = computed(() =>
    props.options.qualities.map((q) => ({
        value: q,
        label: q.charAt(0).toUpperCase() + q.slice(1),
    })),
);

// Live estimate, from the same server-side calculator that charges.
const estimate = ref<Estimate | null>(null);
const estimating = ref(false);
const estimateError = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

const filledReferenceCount = computed(
    () => form.reference_images.filter((url) => url.trim() !== '').length,
);

async function fetchEstimate() {
    controller?.abort();
    controller = new AbortController();
    estimating.value = true;

    try {
        const response = await fetch(
            estimateRoute.url({
                query: {
                    prompt: form.prompt,
                    size: form.size,
                    quality: form.quality,
                    reference_images: filledReferenceCount.value,
                },
            }),
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );

        if (!response.ok) {
            throw new Error();
        }

        estimate.value = (await response.json()) as Estimate;
        estimateError.value = null;
    } catch (e) {
        if ((e as Error).name !== 'AbortError') {
            estimateError.value =
                'We can’t price this right now. Pixelforge’s price list is unavailable.';
        }
    } finally {
        estimating.value = false;
    }
}

watch(
    () => [form.prompt, form.size, form.quality, filledReferenceCount.value],
    () => {
        clearTimeout(timer);
        timer = setTimeout(fetchEstimate, 250);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
});

const cantAfford = computed(
    () =>
        estimate.value !== null &&
        compareUsd(estimate.value.max_charge, balance.value) > 0,
);

const promptLength = computed(() => [...form.prompt].length);
const canSubmit = computed(
    () =>
        form.prompt.trim() !== '' &&
        promptLength.value <= props.options.max_prompt_length &&
        !cantAfford.value &&
        !form.processing,
);

const showKeywords = ref(false);

// Not a form field: the server's "can't afford it" error.
const creditsError = computed(
    () => (form.errors as Record<string, string | undefined>).credits,
);

function submit() {
    form.post(store.url(), { preserveScroll: true });
}

const promptInput = ref<HTMLTextAreaElement | null>(null);

/** "Reuse settings": copy an earlier generation's settings into the form, ready to tweak. */
function fill(g: Generation) {
    form.prompt = g.prompt;
    form.size = g.size;
    form.quality = g.quality;
    form.reference_images = [...g.reference_images];
    form.clearErrors();
    promptInput.value?.focus();
    promptInput.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

defineExpose({ fill });

function onPromptKeydown(event: KeyboardEvent) {
    if (
        event.key === 'Enter' &&
        (event.metaKey || event.ctrlKey) &&
        canSubmit.value
    ) {
        submit();
    }
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div class="space-y-2">
            <div class="flex items-baseline justify-between">
                <Label for="prompt">Prompt</Label>
                <button
                    type="button"
                    class="text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                    @click="showKeywords = !showKeywords"
                >
                    Test keywords
                </button>
            </div>
            <div
                v-if="showKeywords"
                class="rounded-lg bg-studio-page-alt p-3 text-xs text-muted-foreground"
            >
                Add to a prompt to trigger Pixelforge behaviour on purpose:
                <code class="font-mono text-foreground">[fail]</code>,
                <code class="font-mono text-foreground">[slow]</code>,
                <code class="font-mono text-foreground">[duplicate]</code>,
                <code class="font-mono text-foreground">[blocked]</code>,
                <code class="font-mono text-foreground">[overloaded]</code>,
                <code class="font-mono text-foreground">[succeed]</code>.
            </div>
            <div class="relative">
                <textarea
                    id="prompt"
                    ref="promptInput"
                    v-model="form.prompt"
                    rows="5"
                    placeholder="Describe the image you want…"
                    class="field-sizing-content min-h-32 w-full resize-y rounded-lg border border-input bg-card px-3 py-2 pb-6 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 aria-invalid:border-destructive"
                    :aria-invalid="!!form.errors.prompt"
                    aria-describedby="prompt-count"
                    @keydown="onPromptKeydown"
                />
                <span
                    id="prompt-count"
                    class="pointer-events-none absolute right-3 bottom-2 font-mono text-[11px] tabular-nums"
                    :class="
                        promptLength > options.max_prompt_length
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                    "
                >
                    {{ promptLength.toLocaleString() }} /
                    {{ options.max_prompt_length.toLocaleString() }}
                </span>
            </div>
            <p v-if="form.errors.prompt" class="text-xs text-destructive">
                {{ form.errors.prompt }}
            </p>
        </div>

        <div class="space-y-2">
            <span class="text-sm font-medium">Size</span>
            <SegmentedControl
                v-model="form.size"
                name="size"
                label="Size"
                :options="sizeOptions"
            />
            <p v-if="form.errors.size" class="text-xs text-destructive">
                {{ form.errors.size }}
            </p>
        </div>

        <div class="space-y-2">
            <span class="text-sm font-medium">Quality</span>
            <SegmentedControl
                v-model="form.quality"
                name="quality"
                label="Quality"
                :options="qualityOptions"
            />
            <p v-if="form.errors.quality" class="text-xs text-destructive">
                {{ form.errors.quality }}
            </p>
        </div>

        <ReferenceImagesInput
            v-model="form.reference_images"
            :max="options.max_reference_images"
            :errors="form.errors"
        />

        <CostEstimate
            :estimate="estimate"
            :loading="estimating"
            :error="estimateError"
            :markup-percent="options.markup_percent"
        />

        <Alert v-if="creditsError || cantAfford" variant="destructive">
            <CircleAlert />
            <AlertTitle>Not enough credits</AlertTitle>
            <AlertDescription>
                <p>
                    {{
                        creditsError ??
                        `This could cost up to ${formatUsd(estimate?.max_charge)} and you have ${formatUsd(balance)}.`
                    }}
                </p>
                <Button
                    type="button"
                    size="sm"
                    class="mt-2"
                    @click="emit('buyCredits')"
                >
                    Buy credits
                </Button>
            </AlertDescription>
        </Alert>

        <Button type="submit" size="lg" class="w-full" :disabled="!canSubmit">
            <Spinner v-if="form.processing" />
            <Sparkles v-else />
            {{ form.processing ? 'Submitting…' : 'Generate image' }}
        </Button>
    </form>
</template>
