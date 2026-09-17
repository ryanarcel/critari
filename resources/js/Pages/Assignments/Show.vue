<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface Score {
    criterion_name: string | null;
    score: number | string | null;
    feedback: string | null;
}

interface QuestionGrade {
    question_id: number | null;
    prompt: string | null;
    response: string;
    score: number | string | null;
    max_score: number | null;
    overall_feedback: string | null;
    scores: Score[];
}

interface Paper {
    id: number;
    student_name: string;
    student_response: string;
    overall_feedback: string | null;
    status: string;
    score: number | string | null;
    submitted_at: string | null;
    question_grades: QuestionGrade[];
}

interface AssignmentShow {
    id: number;
    title: string;
    join_code: string | null;
    max_score: number | null;
    overall_max_score: number | null;
    questions: { id: number; prompt: string }[];
    criteria: { id: number; name: string }[];
    submissions: Paper[];
}

const props = defineProps<{
    user: { name: string };
    assignment: AssignmentShow;
}>();

const axios = (window as any).axios;
const errorMessage = ref('');
const gradingId = ref<number | null>(null);
const expandedId = ref<number | null>(null);
const copied = ref(false);

const formatScore = (value: number | string | null) => {
    if (value === null || value === '') {
        return '';
    }

    const numeric = Number(value);

    return Number.isFinite(numeric) ? numeric : value;
};

const copyCode = async () => {
    if (!props.assignment.join_code) {
        return;
    }

    await navigator.clipboard.writeText(props.assignment.join_code);
    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 1500);
};

const gradePaper = (paper: Paper) => {
    if (gradingId.value) {
        return;
    }

    gradingId.value = paper.id;
    errorMessage.value = '';

    axios
        .post(`/submissions/${paper.id}/assess`, {
            submission_id: paper.id,
        })
        .then(() => {
            expandedId.value = paper.id;
            router.reload({ only: ['assignment'] });
        })
        .catch((error) => {
            errorMessage.value =
                error.response?.data?.message || 'Grading failed. Try again.';
        })
        .finally(() => {
            gradingId.value = null;
        });
};

const logout = () => {
    router.post(route('tenant.logout'));
};
</script>

<template>
    <Head :title="assignment.title" />

    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white shadow-sm">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
                <div class="flex items-center space-x-8">
                    <Link href="/" class="text-xl font-black tracking-tight text-slate-900">
                        critari<span class="text-indigo-600">.</span>
                    </Link>
                    <Link
                        :href="route('dashboard')"
                        class="text-sm font-medium text-slate-600 hover:text-slate-900"
                    >
                        Dashboard
                    </Link>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-slate-600">{{ user.name }}</span>
                    <button
                        type="button"
                        class="text-sm font-medium text-slate-600 hover:text-slate-900"
                        @click="logout"
                    >
                        Sign out
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-8 px-4 py-10 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Assignment</p>
                    <h1 class="mt-1 text-3xl font-bold text-slate-900">{{ assignment.title }}</h1>
                </div>
                <div
                    v-if="assignment.join_code"
                    class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Student code
                    </p>
                    <div class="mt-1 flex items-center gap-3">
                        <p class="font-mono text-xl tracking-widest text-slate-900">
                            {{ assignment.join_code }}
                        </p>
                        <button
                            type="button"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                            @click="copyCode"
                        >
                            {{ copied ? 'Copied' : 'Copy' }}
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Students enter this code in their account to join and submit.
                    </p>
                </div>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Questions
                </h2>
                <ol class="mt-4 list-decimal space-y-3 pl-5 text-slate-800">
                    <li v-for="question in assignment.questions" :key="question.id">
                        {{ question.prompt }}
                    </li>
                </ol>
                <p v-if="assignment.criteria.length" class="mt-6 text-sm text-slate-500">
                    Graded on
                    {{ assignment.criteria.map((criterion) => criterion.name).join(', ') }}
                    <span v-if="assignment.max_score">
                        · {{ assignment.max_score }} points per question
                    </span>
                    <span
                        v-if="
                            assignment.overall_max_score &&
                            assignment.questions.length > 1
                        "
                    >
                        · {{ assignment.overall_max_score }} overall
                    </span>
                </p>
            </section>

            <p v-if="errorMessage" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ errorMessage }}
            </p>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Student papers</h2>
                <p v-if="assignment.submissions.length === 0" class="mt-3 text-sm text-slate-500">
                    No student papers yet. Share the code so students can join and submit.
                </p>
                <ul v-else class="mt-4 divide-y divide-slate-200">
                    <li v-for="paper in assignment.submissions" :key="paper.id" class="py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ paper.student_name }}</p>
                                <p class="text-sm text-slate-500">
                                    <span class="capitalize">{{ paper.status }}</span>
                                    <span v-if="paper.status === 'graded' && paper.score !== null">
                                        · {{ formatScore(paper.score) }} /
                                        {{ assignment.overall_max_score }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="text-sm font-medium text-slate-600 hover:text-slate-900"
                                    @click="expandedId = expandedId === paper.id ? null : paper.id"
                                >
                                    {{ expandedId === paper.id ? 'Hide' : 'View' }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="gradingId !== null"
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="gradePaper(paper)"
                                >
                                    {{
                                        gradingId === paper.id
                                            ? 'Grading...'
                                            : paper.status === 'graded'
                                              ? 'Re-grade'
                                              : 'Grade with AI'
                                    }}
                                </button>
                            </div>
                        </div>

                        <div v-if="expandedId === paper.id" class="mt-4 space-y-4">
                            <div
                                v-for="(grade, gradeIndex) in paper.question_grades"
                                :key="grade.question_id ?? `paper-${gradeIndex}`"
                                class="space-y-3 rounded-lg border border-slate-200 p-4"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="text-sm font-medium text-slate-800">
                                        <span v-if="grade.prompt">
                                            {{ gradeIndex + 1 }}. {{ grade.prompt }}
                                        </span>
                                        <span v-else>Student response</span>
                                    </p>
                                    <p
                                        v-if="paper.status === 'graded'"
                                        class="text-sm font-semibold text-indigo-600"
                                    >
                                        {{ formatScore(grade.score) }} / {{ grade.max_score }}
                                    </p>
                                </div>
                                <div
                                    class="whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm leading-relaxed text-slate-700"
                                >
                                    {{ grade.response }}
                                </div>
                                <p
                                    v-if="paper.status === 'graded' && grade.overall_feedback"
                                    class="text-sm text-slate-700"
                                >
                                    {{ grade.overall_feedback }}
                                </p>
                                <div
                                    v-for="(score, index) in grade.scores"
                                    :key="index"
                                    class="rounded-lg border border-slate-200 p-3"
                                >
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-slate-800">
                                            {{ score.criterion_name }}
                                        </p>
                                        <p class="text-sm font-semibold text-indigo-600">
                                            {{ formatScore(score.score) }}
                                        </p>
                                    </div>
                                    <p v-if="score.feedback" class="mt-2 text-sm text-slate-600">
                                        {{ score.feedback }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
