<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

interface User {
    name: string;
    school: string;
    department: string;
    avatar: string;
}

interface StatItem {
    totalPapersGraded: number;
    activeAssignments: number;
    timeSavedHours: number;
}

interface Assignment {
    id: number;
    title: string;
    class: string;
    submissions_completed: number;
    submissions_total: number;
    status: string;
    created_at: string;
}

interface Rubric {
    id: number;
    name: string;
    description: string;
}

interface DashboardProps {
    user: User;
    stats: StatItem;
    assignments: Assignment[];
    recentRubrics: Rubric[];
}

const page = usePage();
const props = computed(() => page.props as DashboardProps);
const selectedPeriod = ref('Period 2');
const showUserMenu = ref(false);

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Graded':
            return 'bg-green-100 text-green-800';
        case 'Grading':
            return 'bg-blue-100 text-blue-800';
        case 'Draft':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const handleLogout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-slate-50">
        <!-- Header -->
        <header class="border-b border-slate-200 bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <!-- Logo -->
                    <div class="flex items-center space-x-8">
                        <Link href="/" class="text-xl font-black tracking-tight text-slate-900">
                            critari<span class="text-indigo-600">.</span>
                        </Link>

                        <!-- Navigation -->
                        <nav class="hidden space-x-6 md:flex">
                            <Link
                                href="/dashboard"
                                class="text-sm font-medium text-slate-700 transition-colors hover:text-slate-900"
                            >
                                Dashboard
                            </Link>
                            <Link
                                href="/assignments"
                                class="text-sm font-medium text-slate-700 transition-colors hover:text-slate-900"
                            >
                                Assignments
                            </Link>
                            <Link
                                href="/classes"
                                class="text-sm font-medium text-slate-700 transition-colors hover:text-slate-900"
                            >
                                Classes
                            </Link>
                            <a
                                href="#"
                                class="text-sm font-medium text-slate-700 transition-colors hover:text-slate-900"
                            >
                                Rubric Library
                            </a>
                        </nav>
                    </div>

                    <!-- Period Selector & Avatar -->
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2">
                            <button
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                            >
                                {{ selectedPeriod }}
                                <svg
                                    class="ml-1 inline h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 14l-7 7m0 0l-7-7m7 7V3"
                                    ></path>
                                </svg>
                            </button>
                        </div>

                        <!-- User Avatar & Menu -->
                        <div class="relative">
                            <button
                                @click="showUserMenu = !showUserMenu"
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white transition-transform hover:scale-110"
                            >
                                {{ props.user.avatar }}
                            </button>

                            <!-- Dropdown Menu -->
                            <div
                                v-show="showUserMenu"
                                class="absolute right-0 top-12 z-10 w-48 rounded-lg border border-slate-200 bg-white shadow-lg"
                            >
                                <Link
                                    href="/profile"
                                    class="block px-4 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50"
                                >
                                    Profile
                                </Link>
                                <button
                                    @click="handleLogout"
                                    class="w-full px-4 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50"
                                >
                                    Logout
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Welcome Section -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900">
                        Welcome back, {{ props.user.name }}! 👋
                    </h1>
                    <p class="mt-1 text-slate-600">
                        {{ props.user.school }} • {{ props.user.department }}
                    </p>
                </div>

                <Link
                    href="/assignments/create"
                    class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white transition-all hover:bg-indigo-500"
                >
                    + Create New Assignment
                </Link>
            </div>

            <!-- Stats Cards -->
            <div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-3">
                <!-- Total Papers Graded -->
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-slate-600">TOTAL PAPERS GRADED</div>
                    <div class="mt-3 text-4xl font-bold text-slate-900">
                        {{ props.stats.totalPapersGraded }}
                    </div>
                </div>

                <!-- Active Assignments -->
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-slate-600">ACTIVE ASSIGNMENTS</div>
                    <div class="mt-3 text-4xl font-bold text-slate-900">
                        {{ props.stats.activeAssignments }}
                    </div>
                </div>

                <!-- Time Saved -->
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-slate-600">TIME SAVED THIS MONTH</div>
                    <div class="mt-3 text-4xl font-bold text-slate-900">
                        ~{{ props.stats.timeSavedHours }} Hours
                    </div>
                </div>
            </div>

            <!-- Active Assignments Section -->
            <div class="mb-8">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-slate-900">ACTIVE ASSIGNMENTS</h2>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-slate-200 bg-slate-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-sm font-semibold text-slate-900"
                                    >
                                        Assignment Name
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-sm font-semibold text-slate-900"
                                    >
                                        Class
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-sm font-semibold text-slate-900"
                                    >
                                        Submissions
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-sm font-semibold text-slate-900"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-sm font-semibold text-slate-900"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr
                                    v-for="assignment in props.assignments"
                                    :key="assignment.id"
                                    class="transition-colors hover:bg-slate-50"
                                >
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900">
                                        {{ assignment.title }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ assignment.class }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ assignment.submissions_completed }} /
                                        {{ assignment.submissions_total }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span
                                            :class="[
                                                'inline-block rounded-full px-3 py-1 text-xs font-semibold',
                                                getStatusColor(assignment.status),
                                            ]"
                                        >
                                            [ {{ assignment.status }} ]
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex space-x-2">
                                            <button
                                                v-if="assignment.status === 'Graded'"
                                                class="text-indigo-600 transition-colors hover:text-indigo-700"
                                            >
                                                [ Review & Release ]
                                            </button>
                                            <button
                                                v-else-if="assignment.status === 'Grading'"
                                                class="text-indigo-600 transition-colors hover:text-indigo-700"
                                            >
                                                [ View Progress ]
                                            </button>
                                            <template v-else>
                                                <button
                                                    class="text-indigo-600 transition-colors hover:text-indigo-700"
                                                >
                                                    [ Edit ]
                                                </button>
                                                <button
                                                    class="text-indigo-600 transition-colors hover:text-indigo-700"
                                                >
                                                    [ Upload ]
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="props.assignments.length === 0" class="px-6 py-12 text-center">
                        <p class="text-slate-600">No assignments yet. Create one to get started!</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <!-- Build Custom Rubric -->
                <div class="rounded-lg border-2 border-dashed border-slate-300 bg-white p-8">
                    <div
                        class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100"
                    >
                        <svg
                            class="h-6 w-6 text-indigo-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            ></path>
                        </svg>
                    </div>
                    <h3 class="mb-2 font-bold text-slate-900">[ + Build Custom Rubric ]</h3>
                    <p class="text-sm text-slate-600">
                        Use AI Assistant or create manual matrix templates for reuse.
                    </p>
                </div>

                <!-- Recently Saved Rubrics -->
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 font-bold text-slate-900">RECENTLY SAVED RUBRICS</h3>
                    <ul class="space-y-2">
                        <li v-for="rubric in props.recentRubrics" :key="rubric.id" class="flex">
                            <span class="text-slate-600">•</span>
                            <a
                                href="#"
                                class="ml-2 text-sm text-indigo-600 transition-colors hover:text-indigo-700"
                            >
                                {{ rubric.name }}
                            </a>
                        </li>
                        <li v-if="props.recentRubrics.length === 0" class="text-sm text-slate-600">
                            No rubrics saved yet
                        </li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</template>
