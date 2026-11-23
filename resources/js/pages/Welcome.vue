<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Copy,
    HardDriveDownload,
    Images,
    Lock,
    ReceiptText,
    RotateCcw,
    ShieldCheck,
    Sparkles,
    Unlock,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

// A real generation from the Studio screenshot above: every figure here is its actual bill.
const bill = [
    { label: 'Prompt text', detail: '16 tok × $5.00/1M', amount: '$0.00008' },
    {
        label: 'Reference image',
        detail: '765 tok × $10.00/1M',
        amount: '$0.00765',
    },
    {
        label: 'Image output',
        detail: '6,450 tok × $40.00/1M',
        amount: '$0.25800',
    },
    { label: 'Reasoning', detail: '1,811 tok × $12.50/1M', amount: '$0.02264' },
];

const steps = [
    {
        icon: ReceiptText,
        title: 'Estimate',
        figure: '~$0.4025',
        body: 'Priced live as you type, from Pixelforge’s published rates. Prompt and reasoning tokens vary, so you see a range: $0.3478 to $0.4572.',
    },
    {
        icon: Lock,
        title: 'Reserve the worst case',
        figure: '$0.4572',
        body: 'We hold the most it could possibly cost, so a finished image can never take your balance below zero.',
    },
    {
        icon: Unlock,
        title: 'Charge the exact cost',
        figure: '$0.3605',
        body: 'When it’s done: Pixelforge’s exact token cost plus 25%, to the nanodollar. The other $0.0967 goes straight back.',
    },
];

const features = [
    {
        icon: Activity,
        title: 'Live status, plain language',
        body: 'Submitting, in the queue, painting, ready. If Pixelforge is busy you see “waiting for capacity” and a countdown, not a spinner.',
    },
    {
        icon: ShieldCheck,
        title: 'Charged exactly once',
        body: 'Duplicate and out-of-order webhooks are expected and handled. Tested against all 240 orderings of the events.',
    },
    {
        icon: RotateCcw,
        title: 'Failures are free',
        body: 'Blocked prompts, overloads and failed renders cost nothing, and the reservation comes back. One click to try again.',
    },
    {
        icon: HardDriveDownload,
        title: 'Images that last',
        body: 'Pixelforge links expire after 24 hours. We keep our own private copy, so your gallery still works next week.',
    },
    {
        icon: Images,
        title: 'Gallery and favourites',
        body: 'Filter, sort by cost, star the keepers, and download them with sensible filenames. Reuse any image’s settings.',
    },
    {
        icon: Copy,
        title: 'Books that balance',
        body: 'Every cent is on a ledger, and admins see daily spend, cost and profit reconciled against Pixelforge’s own usage report.',
    },
];

const failureModes = [
    {
        keyword: '[fail]',
        outcome: 'Accepted, then fails. Shown with a reason, charged $0.00.',
    },
    {
        keyword: '[overloaded]',
        outcome:
            'Retried with backoff, “waiting for capacity”, then gives up free.',
    },
    {
        keyword: '[duplicate]',
        outcome: 'Every webhook arrives twice. Recorded once.',
    },
    {
        keyword: '[blocked]',
        outcome: 'Rejected by content policy. Explained, not charged.',
    },
    { keyword: '[slow]', outcome: '60–90 seconds of work, with a live timer.' },
];
</script>

<template>
    <Head title="Images from a prompt, priced to the nanodollar" />

    <div class="min-h-screen overflow-x-clip bg-studio-page text-foreground">
        <!-- Nav -->
        <header
            class="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-5 md:px-6"
        >
            <Link
                href="/"
                class="flex items-center gap-2.5"
                aria-label="PromptEdit Studio home"
            >
                <AppLogoIcon
                    class="size-8 rounded-[9px] dark:ring-1 dark:ring-white/10"
                />
                <span
                    class="font-display text-[17px] font-semibold tracking-tight"
                >
                    PromptEdit
                    <span class="font-normal text-muted-foreground"
                        >Studio</span
                    >
                </span>
            </Link>
            <nav class="flex items-center gap-1 sm:gap-2">
                <a
                    href="#pricing"
                    class="hidden rounded-md px-3 py-2 text-sm text-muted-foreground hover:text-foreground sm:inline"
                    >How pricing works</a
                >
                <a
                    href="#features"
                    class="hidden rounded-md px-3 py-2 text-sm text-muted-foreground hover:text-foreground sm:inline"
                    >Features</a
                >
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="dashboard()">Open studio</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Sign up</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <!-- Hero -->
        <section class="relative">
            <!-- Aurora glow -->
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-x-0 -top-24 h-[42rem] overflow-hidden [mask-image:linear-gradient(to_bottom,black_55%,transparent)]"
            >
                <div
                    class="absolute top-0 left-1/2 h-[36rem] w-[60rem] -translate-x-1/2 rounded-full bg-lime/25 blur-[120px] dark:bg-lime/12"
                />
                <div
                    class="absolute top-40 right-[8%] h-72 w-72 rounded-full bg-info/15 blur-[100px]"
                />
            </div>

            <div
                class="relative mx-auto max-w-6xl px-4 pt-12 text-center md:px-6 md:pt-20"
            >
                <p
                    class="mx-auto inline-flex items-center gap-2 rounded-full border bg-card/70 px-3 py-1 text-xs text-muted-foreground backdrop-blur"
                >
                    <span class="size-1.5 rounded-full bg-lime" />
                    New accounts start with $2.00 of credit
                </p>
                <h1
                    class="mx-auto mt-6 max-w-4xl font-display text-5xl leading-[1.02] font-semibold tracking-tight text-balance md:text-7xl"
                >
                    Images from a prompt, priced to the
                    <span class="relative whitespace-nowrap">
                        <span class="relative z-10">nanodollar.</span>
                        <span
                            aria-hidden="true"
                            class="absolute inset-x-0 bottom-[0.08em] -z-0 h-[0.28em] rounded-sm bg-lime/60"
                        />
                    </span>
                </h1>
                <p
                    class="mx-auto mt-6 max-w-2xl text-lg text-pretty text-muted-foreground"
                >
                    See what an image will cost before you make it. Pay the
                    exact cost when it’s done, and nothing if it fails. Watch it
                    happen live.
                </p>
                <div
                    class="mt-8 flex flex-wrap items-center justify-center gap-3"
                >
                    <Button size="lg" class="h-11 px-6 text-base" as-child>
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : register()
                            "
                        >
                            <Sparkles /> Start generating
                        </Link>
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        class="h-11 px-6 text-base"
                        as-child
                    >
                        <a href="#pricing">How pricing works <ArrowRight /></a>
                    </Button>
                </div>
            </div>

            <!-- Product shot -->
            <div class="relative mx-auto mt-14 max-w-6xl px-4 md:mt-16 md:px-6">
                <div
                    class="rounded-2xl border bg-card/60 p-1.5 shadow-[0_30px_80px_-20px_rgb(38_38_38/0.35)] backdrop-blur md:p-2 dark:shadow-[0_30px_80px_-20px_rgb(0_0_0/0.8)]"
                >
                    <div
                        class="flex items-center gap-1.5 px-2 pt-1 pb-2"
                        aria-hidden="true"
                    >
                        <span class="size-2.5 rounded-full bg-foreground/15" />
                        <span class="size-2.5 rounded-full bg-foreground/15" />
                        <span class="size-2.5 rounded-full bg-foreground/15" />
                    </div>
                    <img
                        src="/images/studio-light.webp"
                        width="2880"
                        height="1800"
                        alt="The Studio: a prompt form with a live cost estimate, the finished image with its exact bill, and recent images"
                        class="w-full rounded-xl border dark:hidden"
                    />
                    <img
                        src="/images/studio-dark.webp"
                        width="2880"
                        height="1800"
                        alt="The Studio in dark mode"
                        class="hidden w-full rounded-xl border dark:block"
                    />
                </div>
            </div>
        </section>

        <!-- Pricing -->
        <section
            id="pricing"
            class="mx-auto max-w-6xl scroll-mt-8 px-4 py-24 md:px-6 md:py-32"
        >
            <div class="max-w-2xl">
                <p
                    class="font-mono text-xs tracking-wider text-accent-vivid uppercase"
                >
                    How pricing works
                </p>
                <h2
                    class="mt-3 font-display text-4xl font-semibold tracking-tight md:text-5xl"
                >
                    No surprises, in either direction.
                </h2>
                <p class="mt-4 text-lg text-muted-foreground">
                    Pixelforge bills per token, and some tokens are only known
                    once the image is finished. Here’s the lighthouse from the
                    screenshot above, step by step.
                </p>
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
                <ol class="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                    <li
                        v-for="(step, i) in steps"
                        :key="step.title"
                        class="relative flex gap-4 rounded-2xl border bg-card p-5"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-lime/15 text-accent-vivid"
                        >
                            <component :is="step.icon" class="size-5" />
                        </span>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Step {{ i + 1 }}
                            </p>
                            <h3
                                class="flex flex-wrap items-baseline gap-x-2 font-semibold"
                            >
                                {{ step.title }}
                                <span class="font-mono text-base font-medium">{{
                                    step.figure
                                }}</span>
                            </h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ step.body }}
                            </p>
                        </div>
                    </li>
                </ol>

                <!-- The receipt -->
                <figure class="rounded-2xl border bg-card p-6 shadow-pop">
                    <figcaption class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold">You paid</p>
                            <p class="text-xs text-muted-foreground">
                                A lighthouse on a sea cliff at dawn · 1536×1024
                                · high
                            </p>
                        </div>
                        <p class="font-mono text-2xl font-medium">$0.3605</p>
                    </figcaption>
                    <dl class="mt-5 space-y-2.5 border-t pt-4 text-sm">
                        <div
                            v-for="line in bill"
                            :key="line.label"
                            class="flex justify-between gap-3"
                        >
                            <dt>
                                {{ line.label }}
                                <span
                                    class="block font-mono text-[11px] text-muted-foreground"
                                    >{{ line.detail }}</span
                                >
                            </dt>
                            <dd class="font-mono tabular-nums">
                                {{ line.amount }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 border-t pt-2.5">
                            <dt class="text-muted-foreground">
                                Pixelforge’s cost
                            </dt>
                            <dd class="font-mono tabular-nums">$0.28837</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">
                                Service fee (25%)
                            </dt>
                            <dd class="font-mono tabular-nums">$0.07209</dd>
                        </div>
                    </dl>
                    <p class="mt-5 rounded-lg bg-lime/12 px-3 py-2 text-xs">
                        <span class="font-medium"
                            >$0.0420 less than estimated</span
                        >, and the full $0.0967 difference from the reservation
                        went back to the balance.
                    </p>
                </figure>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="border-y bg-card/50">
            <div class="mx-auto max-w-6xl scroll-mt-8 px-4 py-24 md:px-6">
                <div class="max-w-2xl">
                    <p
                        class="font-mono text-xs tracking-wider text-accent-vivid uppercase"
                    >
                        What you get
                    </p>
                    <h2
                        class="mt-3 font-display text-4xl font-semibold tracking-tight md:text-5xl"
                    >
                        Clear about what it costs, what’s happening, and what
                        went wrong.
                    </h2>
                </div>
                <div
                    class="mt-12 grid gap-px overflow-hidden rounded-2xl border bg-border sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="f in features"
                        :key="f.title"
                        class="bg-card p-6"
                    >
                        <component
                            :is="f.icon"
                            class="size-5 text-accent-vivid"
                        />
                        <h3 class="mt-4 font-semibold">{{ f.title }}</h3>
                        <p class="mt-1.5 text-sm text-muted-foreground">
                            {{ f.body }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Robustness -->
        <section class="mx-auto max-w-6xl px-4 py-24 md:px-6 md:py-32">
            <div
                class="grid items-start gap-12 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]"
            >
                <div>
                    <p
                        class="font-mono text-xs tracking-wider text-accent-vivid uppercase"
                    >
                        Built for a flaky provider
                    </p>
                    <h2
                        class="mt-3 font-display text-4xl font-semibold tracking-tight"
                    >
                        Break it on purpose.
                    </h2>
                    <p class="mt-4 text-muted-foreground">
                        Pixelforge fails the way real AI providers do. Put one
                        of these in a prompt and watch the app cope. Every one
                        of them is covered by tests, and the money always comes
                        out right.
                    </p>
                    <Button class="mt-6" variant="outline" as-child>
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : register()
                            "
                            >Try it in the studio <ArrowRight
                        /></Link>
                    </Button>
                </div>
                <ul class="divide-y rounded-2xl border bg-card">
                    <li
                        v-for="f in failureModes"
                        :key="f.keyword"
                        class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:gap-5"
                    >
                        <code
                            class="w-32 shrink-0 font-mono text-sm font-medium"
                            >{{ f.keyword }}</code
                        >
                        <span class="text-sm text-muted-foreground">{{
                            f.outcome
                        }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Closing CTA -->
        <section class="mx-auto max-w-6xl px-4 pb-24 md:px-6">
            <div
                class="relative overflow-hidden rounded-3xl bg-[#262626] px-6 py-14 text-center text-white md:px-12 md:py-20"
            >
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -bottom-32 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-lime/30 blur-[100px]"
                />
                <AppLogoIcon
                    class="relative mx-auto size-14 rounded-2xl ring-1 ring-white/15"
                />
                <h2
                    class="relative mx-auto mt-6 max-w-2xl font-display text-4xl font-semibold tracking-tight md:text-5xl"
                >
                    Your first images are on us.
                </h2>
                <p class="relative mx-auto mt-4 max-w-xl text-white/70">
                    Sign up and get $2.00 of credit. That’s about 27
                    medium-quality square images, each priced before you press
                    go.
                </p>
                <Button
                    size="lg"
                    class="relative mt-8 h-11 px-6 text-base"
                    as-child
                >
                    <Link
                        :href="$page.props.auth.user ? dashboard() : register()"
                        ><Sparkles /> Start generating</Link
                    >
                </Button>
            </div>
        </section>

        <footer
            class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 border-t px-4 py-8 text-xs text-muted-foreground md:px-6"
        >
            <span class="flex items-center gap-2">
                <AppLogoIcon class="size-5 rounded-[5px]" />
                PromptEdit Studio, a take-home assessment.
            </span>
            <span
                >Images by Pixelforge, a simulated provider: no real AI, no real
                money.</span
            >
        </footer>
    </div>
</template>
