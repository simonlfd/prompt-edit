<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Activity, Receipt, ShieldCheck } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const points = [
    {
        icon: Receipt,
        title: 'Know the price first',
        body: 'See an estimate and the worst case before you generate. You pay Pixelforge’s exact token cost plus 25%, and failed images are free.',
    },
    {
        icon: Activity,
        title: 'Watch it happen',
        body: 'Every image shows live status, from queued to ready, with plain-language reasons when something goes wrong.',
    },
    {
        icon: ShieldCheck,
        title: 'Charged exactly once',
        body: 'Duplicate or out-of-order notifications from the provider can’t charge you twice or undo a finished image.',
    },
];
</script>

<template>
    <Head title="Welcome" />
    <div class="flex min-h-screen flex-col bg-studio-page text-foreground">
        <header
            class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-5 md:px-6"
        >
            <div class="flex items-center">
                <AppLogo />
            </div>
            <nav class="flex items-center gap-2">
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

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-14 px-4 py-12 md:px-6"
        >
            <div class="max-w-2xl">
                <h1
                    class="font-display text-5xl leading-[1.05] font-semibold tracking-tight md:text-6xl"
                >
                    Images from a prompt, priced to the nanodollar.
                </h1>
                <p class="mt-5 text-lg text-muted-foreground">
                    Write a prompt, choose a size and quality, add reference
                    images if you like, and see what it will cost before you
                    generate. New accounts start with $2.00 of credit.
                </p>
                <div class="mt-8 flex gap-3">
                    <Button size="lg" as-child>
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : register()
                            "
                        >
                            Start generating
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div
                    v-for="point in points"
                    :key="point.title"
                    class="rounded-2xl border bg-card p-5"
                >
                    <component
                        :is="point.icon"
                        class="size-5 text-accent-vivid"
                    />
                    <h2 class="mt-3 font-semibold">{{ point.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ point.body }}
                    </p>
                </div>
            </div>
        </main>

        <footer
            class="mx-auto w-full max-w-5xl px-4 py-6 text-xs text-muted-foreground md:px-6"
        >
            A PromptEdit take-home assessment. Images by Pixelforge, a simulated
            provider: no real AI, no real money.
        </footer>
    </div>
</template>
