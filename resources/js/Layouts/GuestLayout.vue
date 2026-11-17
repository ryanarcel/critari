<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-dvh flex-col overflow-x-hidden bg-slate-50">
        <div
            class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,#1e293b10_1px,transparent_1px),linear-gradient(to_bottom,#1e293b10_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"
        ></div>

        <nav
            class="relative z-10 border-b border-indigo-700 bg-indigo-600 px-4 py-3 pt-[max(0.75rem,env(safe-area-inset-top))] sm:px-6"
        >
            <Link href="/" class="text-xl font-black tracking-tight text-white">
                critari<span class="text-indigo-200">.</span>
            </Link>
        </nav>

        <div class="relative z-10 flex flex-1 items-center justify-center px-4 py-10">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
