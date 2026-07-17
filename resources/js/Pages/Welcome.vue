<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => (page.props.auth as { user: { name: string } | null })?.user ?? null);
</script>

<template>
    <Head title="Home" />

    <div
        class="min-h-screen overflow-x-hidden bg-white text-slate-100 antialiased selection:bg-indigo-500/30 selection:text-indigo-200"
    >
        <!-- Background grid + glow -->
        <div
            class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,#1e293b10_1px,transparent_1px),linear-gradient(to_bottom,#1e293b10_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"
        ></div>
        <div
            class="pointer-events-none absolute left-1/2 top-0 h-[400px] w-[1000px] -translate-x-1/2 rounded-full bg-gradient-to-r from-indigo-500/10 via-violet-500/5 to-transparent blur-[120px]"
        ></div>

        <!-- Nav -->
        <nav
            class="sticky top-0 z-50 border-b border-slate-900/80 bg-white px-6 py-4 backdrop-blur-xl"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <span class="text-xl font-black tracking-tight text-white">
                    critary<span class="text-indigo-500">.</span>
                </span>

                <div
                    class="hidden items-center space-x-8 text-xs font-semibold uppercase tracking-wider text-slate-400 md:flex"
                >
                    <a href="#pipeline" class="transition-colors hover:text-indigo-400">Pipeline</a>
                    <a href="#isolation" class="transition-colors hover:text-indigo-400"
                        >Data Security</a
                    >
                </div>

                <div class="flex items-center space-x-4">
                    <template v-if="user">
                        <Link
                            :href="route('dashboard')"
                            class="text-xs font-bold uppercase tracking-wider text-slate-400 transition-colors hover:text-white"
                        >
                            Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-xs font-bold uppercase tracking-wider text-slate-400 transition-colors hover:text-white"
                        >
                            Sign In
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-lg border border-slate-800 bg-slate-900 px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-200 transition-all hover:text-white"
                        >
                            Get Started
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <!-- Hero -->
        <header class="relative z-10 mx-auto max-w-6xl px-6 pb-24 pt-28 text-center">
            <div
                class="mb-8 inline-flex items-center space-x-2 rounded-full border border-slate-800/80 bg-slate-900 px-4 py-1.5 text-xs font-medium text-slate-400 shadow-xl"
            >
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-indigo-500"></span>
                <span class="font-mono text-slate-300"
                    >Pedagogical Evaluation Assistant Active</span
                >
            </div>

            <h1
                class="mx-auto mb-8 max-w-4xl text-4xl font-extrabold leading-[1.15] tracking-tight text-slate-900 sm:text-6xl"
            >
                End Teacher Burnout with
                <span
                    class="bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 bg-clip-text text-transparent"
                >
                    Instant Essay Evaluation
                </span>
            </h1>

            <p
                class="mx-auto mb-12 max-w-2xl text-base font-light leading-relaxed text-slate-400 sm:text-lg"
            >
                Stop spending your weekends staring at stacks of papers. Critary reads, scores, and
                generates deep inline feedback mapped perfectly to your classroom rubrics—cutting
                grading workloads from days to seconds.
            </p>

            <div
                class="mx-auto flex max-w-md flex-col items-center justify-center gap-4 sm:flex-row"
            >
                <Link
                    :href="route('demos.index')"
                    class="w-full rounded-xl bg-indigo-600 px-8 py-4 text-center text-sm font-bold tracking-wide text-white shadow-lg shadow-indigo-600/20 transition-all hover:bg-indigo-500 sm:w-auto"
                >
                    Try the demo
                </Link>
                <a
                    href="https://github.com"
                    target="_blank"
                    class="w-full rounded-xl border border-slate-800/80 bg-slate-900 px-8 py-4 text-center text-sm font-bold tracking-wide text-slate-300 transition-all hover:bg-slate-800/80 sm:w-auto"
                >
                    Source Repository
                </a>
            </div>
        </header>
    </div>
</template>
