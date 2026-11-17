<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PaperCapturePanel from '@/Components/PaperCapturePanel.vue';

interface Score {
    criterion_name: string | null;
    score: number | string | null;
    feedback: string | null;
}

interface QuestionGrade {
    question_id: number | null;
    prompt: string | null;
    response: string;
    captured?: boolean;
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
    source: 'app' | 'paper';
    answered_count: number;
    submitted_at: string | null;
    question_grades: QuestionGrade[];
}

interface AssignmentShow {
    id: number;
    title: string;
    join_code: string | null;
    max_score: number | null;
    overall_max_score: number | null;
    grades_released_at: string | null;
    questions: { id: number; prompt: string }[];
    criteria: { id: number; name: string }[];
    students: {
        id: number;
        name: string;
        submission?: { status: string; score: number | string | null; source?: string | null } | null;
    }[];
    submissions: Paper[];
}

const props = defineProps<{
    status?: string | null;
    user: { name: string };
    assignment: AssignmentShow;
}>();

const axios = (window as any).axios;
const errorMessage = ref('');
const gradingId = ref<number | null>(null);
const expandedId = ref<number | null>(null);
const copied = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const captureOpen = ref(false);
const photoFile = ref<File | null>(null);
const photoKey = ref(0);

const startPhotograph = () => {
    captureOpen.value = true;

    if (props.assignment.questions.length < 2) {
        fileInput.value?.click();
    }
};

const openCamera = () => {
    fileInput.value?.click();
};

const onPhotoChosen = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    photoFile.value = file;
    photoKey.value += 1;
    captureOpen.value = true;
};

const closeCapture = () => {
    captureOpen.value = false;
    photoFile.value = null;
};

const paperSaved = () => {
    router.reload({ only: ['assignment'] });
};

const formatScore = (value: number | string | null) => {
    if (value === null || value === '') {
        return '';
    }

    const numeric = Number(value);

    return Number.isFinite(numeric) ? numeric : value;
};

const isCaptured = (grade: QuestionGrade) =>
    grade.captured ?? (grade.response ?? '').trim() !== '';

const assignmentMaxScore = computed(() => props.assignment.overall_max_score);

const gradeValue = (grade: QuestionGrade) =>
    isCaptured(grade) ? grade.score : 0;

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

const gradesReleased = computed(() => props.assignment.grades_released_at !== null);
const gradedCount = computed(
    () => props.assignment.submissions.filter((paper) => paper.status === 'graded').length,
);
const updatingGrades = ref(false);

const releaseGrades = () => {
    if (updatingGrades.value) {
        return;
    }

    updatingGrades.value = true;
    router.post(route('assignments.release-grades', props.assignment.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            updatingGrades.value = false;
        },
    });
};

const hideGrades = () => {
    if (updatingGrades.value) {
        return;
    }

    updatingGrades.value = true;
    router.post(route('assignments.hide-grades', props.assignment.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            updatingGrades.value = false;
        },
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
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 sm:px-6 sm:py-4">
                <div class="flex min-w-0 items-center gap-4 sm:gap-8">
                    <Link href="/" class="shrink-0 text-xl font-black tracking-tight text-slate-900">
                        critari<span class="text-indigo-600">.</span>
                    </Link>
                    <Link
                        :href="route('dashboard')"
                        class="inline-flex h-11 items-center text-sm font-medium leading-none text-slate-600 hover:text-slate-900"
                    >
                        Dashboard
                    </Link>
                </div>
                <nav class="flex shrink-0 items-center gap-3 sm:gap-4">
                    <span class="hidden max-w-[8rem] truncate text-sm leading-none text-slate-600 sm:inline">{{
                        user.name
                    }}</span>
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

        <main class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:space-y-8 sm:px-6 sm:py-10">
            <p v-if="status" class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                {{ status }}
            </p>

            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm text-slate-500">Assignment</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">{{ assignment.title }}</h1>
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

            <section class="rounded-lg bg-white p-4 shadow-sm sm:p-6">
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

            <section class="rounded-lg bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Student grades</h2>
                        <p v-if="gradesReleased" class="mt-1 text-sm text-slate-500">
                            Students can see their scores and feedback.
                            {{ gradedCount }} of {{ assignment.submissions.length }} papers graded.
                        </p>
                        <p v-else class="mt-1 text-sm text-slate-500">
                            Students cannot see scores until you release them.
                            {{ gradedCount }} of {{ assignment.submissions.length }} papers graded.
                        </p>
                    </div>
                    <button
                        v-if="gradesReleased"
                        type="button"
                        :disabled="updatingGrades"
                        class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                        @click="hideGrades"
                    >
                        {{ updatingGrades ? 'Updating...' : 'Hide grades' }}
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="updatingGrades"
                        class="min-h-11 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                        @click="releaseGrades"
                    >
                        {{ updatingGrades ? 'Releasing...' : 'Release grades' }}
                    </button>
                </div>
            </section>

            <p v-if="errorMessage" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ errorMessage }}
            </p>

            <section class="rounded-lg bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Student papers</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Students can type in the app, or you can photograph a submission.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="min-h-11 w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500 sm:w-auto"
                        @click="startPhotograph"
                    >
                        Photograph a Submission
                    </button>
                    <input
                        id="paper-photo"
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        capture="environment"
                        class="hidden"
                        @change="onPhotoChosen"
                    />
                </div>

                <PaperCapturePanel
                    v-if="captureOpen"
                    :assignment-id="assignment.id"
                    :questions="assignment.questions"
                    :students="assignment.students"
                    :overall-max-score="assignment.overall_max_score"
                    :photo="photoFile"
                    :photo-key="photoKey"
                    @capture="openCamera"
                    @closed="closeCapture"
                    @graded="paperSaved"
                />

                <p v-if="assignment.submissions.length === 0" class="mt-3 text-sm text-slate-500">
                    No student papers yet. Share the code so students can join and submit, or
                    photograph a submission.
                </p>
                <ul v-else class="mt-4 divide-y divide-slate-200">
                    <li v-for="paper in assignment.submissions" :key="paper.id" class="py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium text-slate-900">{{ paper.student_name }}</p>
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium uppercase tracking-wide text-slate-600"
                                    >
                                        {{ paper.source === 'paper' ? 'Paper' : 'App' }}
                                    </span>
                                </div>
                                <p class="text-sm text-slate-500">
                                    <span class="capitalize">{{ paper.status }}</span>
                                    <span v-if="assignment.questions.length > 0">
                                        · {{ paper.answered_count }} of
                                        {{ assignment.questions.length }} questions captured
                                    </span>
                                </p>
                            </div>
                            <div class="flex w-full flex-wrap items-center gap-3 sm:w-auto sm:flex-nowrap">
                                <p
                                    v-if="paper.status === 'graded' && paper.score !== null"
                                    class="inline-flex min-h-11 items-center rounded-lg bg-indigo-50 px-3 py-1.5 text-base font-bold tabular-nums tracking-tight text-indigo-700 ring-1 ring-inset ring-indigo-200"
                                >
                                    {{ formatScore(paper.score) }}
                                    <span class="mx-1 font-semibold text-indigo-400">/</span>
                                    {{ assignmentMaxScore }}
                                </p>
                                <button
                                    type="button"
                                    class="min-h-11 text-sm font-medium text-slate-600 hover:text-slate-900"
                                    @click="expandedId = expandedId === paper.id ? null : paper.id"
                                >
                                    {{ expandedId === paper.id ? 'Hide' : 'View' }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="gradingId !== null"
                                    class="min-h-11 flex-1 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
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
                                    <div
                                        v-if="paper.status === 'graded'"
                                        class="flex shrink-0 flex-col items-end gap-1"
                                    >
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
                                <div
                                    class="whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm leading-relaxed text-slate-700"
                                >
                                    {{ grade.response || 'No answer captured for this question.' }}
                                </div>
                                <p
                                    v-if="paper.status === 'graded' && isCaptured(grade) && grade.overall_feedback"
                                    class="text-sm text-slate-700"
                                >
                                    {{ grade.overall_feedback }}
                                </p>
                                <div
                                    v-for="(score, index) in isCaptured(grade) ? grade.scores : []"
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
                        </div>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
