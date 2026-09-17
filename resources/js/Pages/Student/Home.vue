<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

defineProps<{
    status?: string | null;
    user: {
        name: string | null;
    };
}>();

const logout = () => {
    router.post(route('tenant.logout'));
};
</script>

<template>
    <Head title="Student home" />

    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
                <Link href="/" class="text-xl font-black tracking-tight text-slate-900">
                    critari<span class="text-indigo-600">.</span>
                </Link>
                <button
                    type="button"
                    class="text-sm font-medium text-slate-600 hover:text-slate-900"
                    @click="logout"
                >
                    Sign out
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-16">
            <p v-if="status" class="mb-6 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                {{ status }}
            </p>
            <h1 class="text-2xl font-bold text-slate-900">
                You are signed in as a student{{ user.name ? `, ${user.name}` : '' }}.
            </h1>
            <p class="mt-3 text-slate-600">Assignments will show up here.</p>
        </main>
    </div>
</template>
