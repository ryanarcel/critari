<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

interface StudentAssignment {
    id: number;
    title: string;
    status: string;
    score: number | string | null;
    max_score: number | null;
    overall_max_score: number | null;
}

const props = defineProps<{
    status?: string | null;
    user: {
        name: string | null;
    };
    assignments: StudentAssignment[];
}>();

const form = useForm({
    code: '',
});

const formatScore = (value: number | string | null) => {
    if (value === null || value === '') {
        return '';
    }

    const numeric = Number(value);

    return Number.isFinite(numeric) ? numeric : value;
};

const statusLabel = (status: string) => {
    if (status === 'graded') {
        return 'Graded';
    }

    if (status === 'pending') {
        return 'Submitted';
    }

    return 'Not started';
};

const join = () => {
    form.post(route('student.join'));
};

const logout = () => {
    router.post(route('tenant.logout'));
};
</script>

<template>
    <Head title="Student home" />

    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-3 sm:py-4">
                <Link href="/" class="shrink-0 text-xl font-black leading-none tracking-tight text-slate-900">
                    critari<span class="text-indigo-600">.</span>
                </Link>
                <nav class="flex shrink-0 items-center gap-4 sm:gap-6">
                    <span v-if="user.name" class="hidden max-w-[8rem] truncate text-sm leading-none text-slate-600 sm:inline">{{ user.name }}</span>
                    <button
                        type="button"
                        class="inline-flex h-11 items-center text-sm font-medium leading-none text-slate-600 hover:text-slate-900"
                        @click="logout"
                    >
                        Sign out
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:space-y-8 sm:py-10">
            <p v-if="status" class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                {{ status }}
            </p>

            <div>
                <h1 class="text-2xl font-bold text-slate-900">Your assignments</h1>
                <p class="mt-2 text-slate-600">
                    Enter the code from your teacher to get access.
                </p>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="join">
                    <label class="sr-only" for="code">Assignment code</label>
                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        autocomplete="off"
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm uppercase tracking-widest text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="ABC123"
                    />
                    <button
                        type="submit"
                        :disabled="form.processing || form.code.trim() === ''"
                        class="min-h-11 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ form.processing ? 'Joining...' : 'Join' }}
                    </button>
                </form>
                <p v-if="form.errors.code" class="mt-3 text-sm text-red-600">{{ form.errors.code }}</p>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Assignments</h2>
                <p v-if="assignments.length === 0" class="mt-3 text-sm text-slate-500">
                    You have not joined any assignments yet.
                </p>
                <ul v-else class="mt-4 divide-y divide-slate-200">
                    <li v-for="assignment in assignments" :key="assignment.id" class="py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="route('student.assignments.show', assignment.id)"
                                    class="font-medium text-indigo-600 hover:text-indigo-700"
                                >
                                    {{ assignment.title }}
                                </Link>
                                <p class="text-sm text-slate-500">
                                    {{ statusLabel(assignment.status) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <p
                                    v-if="assignment.status === 'graded' && assignment.score !== null"
                                    class="inline-flex min-h-11 items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-bold tabular-nums text-white"
                                >
                                    {{ formatScore(assignment.score) }}
                                    <span class="mx-1 font-medium text-indigo-200">/</span>
                                    {{ assignment.overall_max_score }}
                                </p>
                                <Link
                                    :href="route('student.assignments.show', assignment.id)"
                                    class="min-h-11 text-sm font-medium text-slate-600 hover:text-slate-900"
                                >
                                    Open
                                </Link>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
