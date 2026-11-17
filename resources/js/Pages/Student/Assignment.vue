<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

interface Score {
    criterion_name: string | null;
    score: number | string | null;
    feedback: string | null;
}

const props = defineProps<{
    status?: string | null;
    user: { name: string | null };
    assignment: {
        id: number;
        title: string;
        max_score: number | null;
        overall_max_score: number | null;
        grades_released: boolean;
        questions: { id: number; prompt: string; response: string }[];
    };
    submission: {
        status: string;
        score: number | string | null;
        question_grades: {
            question_id: number | null;
            prompt: string | null;
            response: string;
            captured?: boolean;
            score: number | string | null;
            max_score: number | null;
            overall_feedback: string | null;
            scores: Score[];
        }[];
    } | null;
}>();

const isGraded = computed(() => props.submission?.status === 'graded');
const gradesVisible = computed(
    () => isGraded.value && props.assignment.grades_released && props.submission !== null,
);

const isCaptured = (grade: { captured?: boolean; response: string }) =>
    grade.captured ?? grade.response.trim() !== '';

const visibleMaxScore = computed(() => props.assignment.overall_max_score);

const gradeValue = (grade: { captured?: boolean; response: string; score: number | string | null }) =>
    isCaptured(grade) ? grade.score : 0;

const form = useForm({
    answers: props.assignment.questions.map((question) => ({
        question_id: question.id,
        response: question.response,
    })),
});

const formatScore = (value: number | string | null) => {
    if (value === null || value === '') {
        return '';
    }

    const numeric = Number(value);

    return Number.isFinite(numeric) ? numeric : value;
};

const canSubmit = computed(
    () =>
        !isGraded.value &&
        form.answers.every((answer) => answer.response.trim() !== '') &&
        !form.processing,
);

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    form.post(route('student.assignments.submit', props.assignment.id));
};

const logout = () => {
    router.post(route('tenant.logout'));
};
</script>

<template>
    <Head :title="assignment.title" />

    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-3 sm:py-4">
                <Link href="/" class="shrink-0 text-xl font-black leading-none tracking-tight text-slate-900">
                    critari<span class="text-indigo-600">.</span>
                </Link>
                <nav class="flex shrink-0 items-center gap-4 sm:gap-6">
                    <Link
                        :href="route('student.home')"
                        class="inline-flex h-11 items-center text-sm font-medium leading-none text-slate-600 hover:text-slate-900"
                    >
                        Assignments
                    </Link>
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
                <p class="text-sm text-slate-500">Assignment</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">{{ assignment.title }}</h1>
            </div>

            <p
                v-if="isGraded && !gradesVisible"
                class="rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700"
            >
                Your answers were submitted. Grades will appear here when your teacher releases them.
            </p>

            <p v-if="form.errors.answers" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ form.errors.answers }}
            </p>

            <form class="space-y-6" @submit.prevent="submit">
                <section
                    v-for="(answer, index) in form.answers"
                    :key="answer.question_id"
                    class="rounded-lg bg-white p-4 shadow-sm sm:p-6"
                >
                    <label
                        :for="`answer-${answer.question_id}`"
                        class="block text-sm font-medium text-slate-800"
                    >
                        {{ index + 1 }}. {{ assignment.questions[index].prompt }}
                    </label>
                    <textarea
                        :id="`answer-${answer.question_id}`"
                        v-model="answer.response"
                        :disabled="isGraded"
                        rows="8"
                        class="mt-4 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-50"
                        placeholder="Write your answer..."
                    ></textarea>
                </section>

                <div v-if="!isGraded">
                    <button
                        type="submit"
                        :disabled="!canSubmit"
                        class="min-h-11 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                    >
                        {{
                            form.processing
                                ? 'Submitting...'
                                : submission
                                  ? 'Update answers'
                                  : 'Submit answers'
                        }}
                    </button>
                </div>
            </form>

            <section v-if="gradesVisible && submission" class="space-y-4 rounded-lg bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900">Your score</h2>
                    <p
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-lg font-bold tabular-nums text-white"
                    >
                        {{ formatScore(submission.score) }}
                        <span class="mx-1 font-medium text-indigo-200">/</span>
                        {{ visibleMaxScore }}
                    </p>
                </div>
                <div
                    v-for="(grade, gradeIndex) in submission.question_grades"
                    :key="grade.question_id ?? `paper-${gradeIndex}`"
                    class="space-y-3 rounded-lg border border-slate-200 p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="text-sm font-medium text-slate-800">
                            <span v-if="grade.prompt">
                                {{ gradeIndex + 1 }}. {{ grade.prompt }}
                            </span>
                            <span v-else>Your response</span>
                        </p>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <p
                                class="inline-flex items-center rounded-md px-2 py-1 text-sm font-bold tabular-nums"
                                :class="
                                    isCaptured(grade)
                                        ? 'bg-indigo-50 text-indigo-700'
                                        : 'bg-slate-100 text-slate-500'
                                "
                            >
                                {{ formatScore(gradeValue(grade)) }}
                                <span class="mx-1 font-medium opacity-60">/</span>
                                {{ grade.max_score }}
                            </p>
                            <p v-if="!isCaptured(grade)" class="text-xs font-medium text-slate-400">
                                Not captured
                            </p>
                        </div>
                    </div>
                    <p v-if="grade.overall_feedback" class="text-sm text-slate-700">
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
                        <p v-if="score.feedback" class="mt-2 text-sm leading-relaxed text-slate-600">
                            <span class="font-medium text-slate-500">Reasoning. </span>
                            {{ score.feedback }}
                        </p>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
