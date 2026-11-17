<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface Question {
    id: number;
    prompt: string;
}

interface ExistingSubmission {
    status: string;
    score: number | string | null;
    source?: string | null;
}

interface Student {
    id: number;
    name: string;
    submission?: ExistingSubmission | null;
}

interface Score {
    criterion_name: string | null;
    score: number | string | null;
    feedback: string | null;
}

interface QuestionGrade {
    question_id: number | null;
    prompt: string | null;
    score: number | string | null;
    max_score: number | null;
    overall_feedback: string | null;
    scores: Score[];
}

interface GradeResult {
    total_score: number | string | null;
    max_score: number | string | null;
    question_grades: QuestionGrade[];
}

const props = defineProps<{
    assignmentId: number;
    questions: Question[];
    students: Student[];
    overallMaxScore: number | null;
    photo: File | null;
    photoKey: number;
}>();

const emit = defineEmits<{
    capture: [];
    closed: [];
    graded: [];
}>();

const axios = (window as any).axios;

type QuestionScope = 'all' | number;

const questionOrdinals = [
    'First',
    'Second',
    'Third',
    'Fourth',
    'Fifth',
    'Sixth',
    'Seventh',
    'Eighth',
    'Ninth',
    'Tenth',
];

const needsScope = computed(() => props.questions.length > 1);
const defaultScope = (): QuestionScope | null => {
    if (needsScope.value) {
        return null;
    }

    return props.questions[0]?.id ?? 'all';
};
const step = ref<'scope' | 'review'>(needsScope.value ? 'scope' : 'review');
const selectedScope = ref<QuestionScope | null>(defaultScope());
const questionIndex = ref(0);
const drafts = ref<Record<number, string>>({});
const photoCount = ref(0);
const previewUrl = ref('');
const reading = ref(false);
const appendNextPhoto = ref(false);
const studentQuery = ref('');
const selectedStudent = ref<Student | { id: null; name: string } | null>(null);
const showMatches = ref(false);
const errorMessage = ref('');
const saving = ref(false);
const grading = ref(false);
const gradeResult = ref<GradeResult | null>(null);
const overridePrompt = ref(false);
const confirmedOverride = ref(false);
const detectedExisting = ref<ExistingSubmission | null>(null);

const scopedQuestions = computed(() => {
    if (selectedScope.value === 'all' || selectedScope.value === null) {
        return props.questions;
    }

    return props.questions.filter((question) => question.id === selectedScope.value);
});

const currentQuestion = computed(() => {
    if (selectedScope.value === 'all') {
        return scopedQuestions.value[questionIndex.value] ?? scopedQuestions.value[0] ?? null;
    }

    return scopedQuestions.value[0] ?? props.questions[questionIndex.value] ?? null;
});

const questionLabel = (index: number) => {
    const ordinal = questionOrdinals[index];

    return ordinal ? `${ordinal} question` : `Question ${index + 1}`;
};

const scopeLabel = computed(() => {
    if (selectedScope.value === 'all') {
        return 'All questions';
    }

    if (typeof selectedScope.value === 'number') {
        const index = props.questions.findIndex((question) => question.id === selectedScope.value);

        return index >= 0 ? questionLabel(index) : 'Question';
    }

    return '';
});

const coversAllQuestions = computed(() => selectedScope.value === 'all');

const existingSubmission = computed((): ExistingSubmission | null => {
    if (detectedExisting.value) {
        return detectedExisting.value;
    }

    const student = selectedStudent.value;

    if (!student || student.id === null || !('submission' in student)) {
        return null;
    }

    return student.submission ?? null;
});

const overrideMessage = computed(() => {
    const student = selectedStudent.value;
    const existing = existingSubmission.value;

    if (!student || !existing) {
        return '';
    }

    if (existing.status === 'graded') {
        if (existing.score !== null && existing.score !== '' && props.overallMaxScore !== null) {
            return `${student.name} already has a grade of ${existing.score} / ${props.overallMaxScore}. Assigning this photo will replace their answers and override that grade.`;
        }

        return `${student.name} already has a grade. Assigning this photo will replace their answers and override that grade.`;
    }

    return `${student.name} already submitted a response. Assigning this photo will replace those answers.`;
});

const currentText = computed({
    get: () => (currentQuestion.value ? drafts.value[currentQuestion.value.id] ?? '' : ''),
    set: (value: string) => {
        if (!currentQuestion.value) {
            return;
        }

        drafts.value = {
            ...drafts.value,
            [currentQuestion.value.id]: value,
        };
    },
});

const hasNextQuestion = computed(
    () => !coversAllQuestions.value && questionIndex.value < props.questions.length - 1,
);

const unansweredQuestions = computed(() =>
    props.questions.filter((question) => (drafts.value[question.id] ?? '').trim() === ''),
);

const answeredCount = computed(
    () => Object.values(drafts.value).filter((text) => text.trim() !== '').length,
);

const query = computed(() => studentQuery.value.trim());

const matches = computed(() => {
    if (query.value === '') {
        return props.students.slice(0, 8);
    }

    const needle = query.value.toLowerCase();

    return props.students
        .filter((student) => student.name.toLowerCase().includes(needle))
        .slice(0, 8);
});

const canAddWalkIn = computed(() => {
    if (query.value === '') {
        return false;
    }

    return !props.students.some(
        (student) => student.name.toLowerCase() === query.value.toLowerCase(),
    );
});

const ensureStudent = (): boolean => {
    if (selectedStudent.value !== null && selectedStudent.value.name.trim() !== '') {
        return true;
    }

    if (query.value === '') {
        errorMessage.value = 'Choose or add a student before grading.';

        return false;
    }

    const exact = props.students.find(
        (student) => student.name.toLowerCase() === query.value.toLowerCase(),
    );

    selectedStudent.value = exact ?? { id: null, name: query.value };
    studentQuery.value = selectedStudent.value.name;
    showMatches.value = false;

    return true;
};

const formatScore = (value: number | string | null) => {
    if (value === null || value === '') {
        return '';
    }

    const numeric = Number(value);

    return Number.isFinite(numeric) ? numeric : value;
};

const revokePreview = () => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = '';
    }
};

const compressImage = async (file: File): Promise<File> => {
    if (typeof createImageBitmap !== 'function') {
        return file;
    }

    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height, 1));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    const context = canvas.getContext('2d');

    if (!context) {
        bitmap.close();

        return file;
    }

    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', 0.72),
    );

    if (!blob) {
        return file;
    }

    return new File([blob], 'paper.jpg', { type: 'image/jpeg' });
};

const setDraft = (questionId: number, value: string) => {
    drafts.value = {
        ...drafts.value,
        [questionId]: value,
    };
};

const onDraftInput = (questionId: number, event: Event) => {
    const target = event.target as HTMLTextAreaElement;

    setDraft(questionId, target.value);
};

const applyExtractedText = (questionId: number, extracted: string) => {
    const previous = appendNextPhoto.value ? (drafts.value[questionId] ?? '').trim() : '';
    const next = previous && extracted ? `${previous}\n\n${extracted}` : extracted || previous;

    setDraft(questionId, next);
};

const readPhoto = async (file: File) => {
    if (selectedScope.value === null) {
        return;
    }

    reading.value = true;
    errorMessage.value = '';
    gradeResult.value = null;
    revokePreview();
    previewUrl.value = URL.createObjectURL(file);
    step.value = 'review';

    try {
        const compressed = await compressImage(file);
        const form = new FormData();
        form.append('image', compressed);

        if (coversAllQuestions.value) {
            form.append('all_questions', '1');
        } else if (currentQuestion.value) {
            form.append('question_id', String(currentQuestion.value.id));
        }

        const response = await axios.post(`/assignments/${props.assignmentId}/paper-ocr`, form);

        if (coversAllQuestions.value && Array.isArray(response.data.answers)) {
            response.data.answers.forEach((answer: { question_id?: number; text?: string }) => {
                if (answer.question_id) {
                    applyExtractedText(answer.question_id, String(answer.text ?? '').trim());
                }
            });
        } else if (currentQuestion.value) {
            applyExtractedText(
                currentQuestion.value.id,
                String(response.data.text ?? '').trim(),
            );
        }

        photoCount.value += 1;
    } catch (error: any) {
        errorMessage.value =
            error.response?.data?.message || 'Could not read the photo. Try another picture.';
    } finally {
        appendNextPhoto.value = false;
        reading.value = false;
    }
};

watch(
    () => props.photoKey,
    () => {
        if (props.photo) {
            void readPhoto(props.photo);
        }
    },
    { immediate: true },
);

const selectStudent = (student: Student | { id: null; name: string }) => {
    selectedStudent.value = student;
    studentQuery.value = student.name;
    showMatches.value = false;
    overridePrompt.value = false;
    confirmedOverride.value = false;
    detectedExisting.value = null;
};

const addWalkIn = () => {
    if (!canAddWalkIn.value) {
        return;
    }

    selectStudent({ id: null, name: query.value });
};

const clearStudent = () => {
    selectedStudent.value = null;
    studentQuery.value = '';
    showMatches.value = false;
    overridePrompt.value = false;
    confirmedOverride.value = false;
    detectedExisting.value = null;
};

const persistPaper = async (): Promise<number | null> => {
    if (!ensureStudent() || !selectedStudent.value) {
        return null;
    }

    if (answeredCount.value === 0) {
        errorMessage.value = 'Add an answer before grading.';

        return null;
    }

    saving.value = true;
    errorMessage.value = '';

    try {
        const payload =
            selectedStudent.value.id === null
                ? { student_name: selectedStudent.value.name }
                : { user_id: selectedStudent.value.id };

        const created = await axios.post(
            `/assignments/${props.assignmentId}/paper-submissions`,
            payload,
        );

        if (created.data.requires_override && !confirmedOverride.value) {
            detectedExisting.value = {
                status: created.data.status ?? 'pending',
                score: created.data.score ?? null,
            };
            overridePrompt.value = true;

            return null;
        }

        const submissionId = created.data.submission_id as number;

        await axios.put(`/assignments/${props.assignmentId}/paper-submissions/${submissionId}`, {
            student_name: selectedStudent.value.id === null ? selectedStudent.value.name : undefined,
            override: confirmedOverride.value || undefined,
            answers: Object.entries(drafts.value)
                .filter(([, response]) => response.trim() !== '')
                .map(([questionId, response]) => ({
                    question_id: Number(questionId),
                    response,
                })),
        });

        return submissionId;
    } catch (error: any) {
        if (error.response?.status === 422 && error.response?.data?.errors?.override) {
            detectedExisting.value = detectedExisting.value ?? {
                status: 'pending',
                score: null,
            };
            overridePrompt.value = true;

            return null;
        }

        errorMessage.value =
            error.response?.data?.message ||
            error.response?.data?.errors?.user_id?.[0] ||
            error.response?.data?.errors?.student_name?.[0] ||
            error.response?.data?.errors?.override?.[0] ||
            'Could not save this paper.';

        return null;
    } finally {
        saving.value = false;
    }
};

const gradePaper = async () => {
    if (reading.value || saving.value || grading.value) {
        return;
    }

    if (!ensureStudent()) {
        return;
    }

    if (existingSubmission.value && !confirmedOverride.value) {
        overridePrompt.value = true;

        return;
    }

    grading.value = true;
    errorMessage.value = '';
    overridePrompt.value = false;

    try {
        const submissionId = await persistPaper();

        if (!submissionId) {
            return;
        }

        const response = await axios.post(`/submissions/${submissionId}/assess`, {
            submission_id: submissionId,
        });

        const questionGrades = (response.data.question_grades ?? []).filter(
            (grade: QuestionGrade) => (grade.scores?.length ?? 0) > 0,
        );

        gradeResult.value = {
            total_score: response.data.total_score ?? null,
            max_score: response.data.max_score ?? props.overallMaxScore,
            question_grades: questionGrades,
        };
        emit('graded');
    } catch (error: any) {
        errorMessage.value = error.response?.data?.message || 'Grading failed. Try again.';
    } finally {
        grading.value = false;
    }
};

const chooseScope = (scope: QuestionScope) => {
    selectedScope.value = scope;

    if (scope === 'all') {
        questionIndex.value = 0;
    } else {
        const index = props.questions.findIndex((question) => question.id === scope);
        questionIndex.value = Math.max(0, index);
    }
};

const takePhoto = () => {
    if (selectedScope.value === null) {
        return;
    }

    emit('capture');
};

const goToNextQuestion = () => {
    if (!hasNextQuestion.value) {
        return;
    }

    const nextIndex = questionIndex.value + 1;
    const nextQuestion = props.questions[nextIndex];

    if (!nextQuestion) {
        return;
    }

    chooseScope(nextQuestion.id);
    photoCount.value = 0;
    appendNextPhoto.value = false;
    emit('capture');
};

const photographAnotherQuestion = () => {
    selectedScope.value = null;
    photoCount.value = 0;
    appendNextPhoto.value = false;
    gradeResult.value = null;
    revokePreview();
    step.value = 'scope';
};

const addAnotherPhoto = () => {
    appendNextPhoto.value = true;
    emit('capture');
};

const retakePhoto = () => {
    appendNextPhoto.value = false;
    emit('capture');
};

const confirmOverride = () => {
    confirmedOverride.value = true;
    overridePrompt.value = false;
    void gradePaper();
};

const cancelOverride = () => {
    overridePrompt.value = false;
};

const resetForNextPaper = () => {
    questionIndex.value = 0;
    drafts.value = {};
    photoCount.value = 0;
    appendNextPhoto.value = false;
    studentQuery.value = '';
    selectedStudent.value = null;
    showMatches.value = false;
    errorMessage.value = '';
    saving.value = false;
    grading.value = false;
    gradeResult.value = null;
    overridePrompt.value = false;
    confirmedOverride.value = false;
    detectedExisting.value = null;
    revokePreview();

    if (needsScope.value) {
        selectedScope.value = null;
        step.value = 'scope';

        return;
    }

    selectedScope.value = defaultScope();
    step.value = 'review';
    emit('capture');
};

const closeOverlay = () => {
    revokePreview();
    emit('closed');
};

onBeforeUnmount(() => {
    revokePreview();
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex justify-center bg-slate-900/70 p-0 sm:p-6">
        <div
            class="flex h-full w-full max-w-lg flex-col bg-white shadow-2xl sm:h-auto sm:max-h-full sm:rounded-2xl"
        >
            <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Photograph a Submission
                    </p>
                    <p v-if="step === 'scope'" class="mt-0.5 text-sm font-medium text-slate-900">
                        Which question is this photo for?
                    </p>
                    <p v-else-if="gradeResult" class="mt-0.5 text-sm font-medium text-slate-900">
                        Graded
                    </p>
                    <p v-else class="mt-0.5 text-sm font-medium text-slate-900">
                        {{ scopeLabel }}
                    </p>
                </div>
                <button
                    type="button"
                    class="min-h-11 min-w-11 text-sm font-medium text-slate-600 hover:text-slate-900"
                    @click="closeOverlay"
                >
                    Close
                </button>
            </header>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4">
                <p v-if="errorMessage" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ errorMessage }}
                </p>

                <template v-if="step === 'scope'">
                    <p class="text-sm text-slate-600">
                        Choose whether this photo answers one question or the whole assignment.
                    </p>
                    <div class="space-y-2">
                        <button
                            type="button"
                            class="flex min-h-11 w-full flex-col items-start rounded-xl border px-4 py-3 text-left transition"
                            :class="
                                selectedScope === 'all'
                                    ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500'
                                    : 'border-slate-200 hover:border-indigo-200 hover:bg-slate-50'
                            "
                            @click="chooseScope('all')"
                        >
                            <span class="text-sm font-semibold text-slate-900">All questions</span>
                            <span class="mt-0.5 text-xs text-slate-500">
                                This page has answers for every question.
                            </span>
                        </button>
                        <button
                            v-for="(question, index) in questions"
                            :key="question.id"
                            type="button"
                            class="flex min-h-11 w-full flex-col items-start rounded-xl border px-4 py-3 text-left transition"
                            :class="
                                selectedScope === question.id
                                    ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500'
                                    : 'border-slate-200 hover:border-indigo-200 hover:bg-slate-50'
                            "
                            @click="chooseScope(question.id)"
                        >
                            <span class="text-sm font-semibold text-slate-900">
                                {{ questionLabel(index) }}
                            </span>
                            <span class="mt-0.5 line-clamp-2 text-xs text-slate-500">
                                {{ question.prompt }}
                            </span>
                        </button>
                    </div>
                </template>

                <template v-else-if="gradeResult">
                    <div class="rounded-xl bg-indigo-50 px-4 py-6 text-center">
                        <p class="text-sm text-indigo-700">Overall score</p>
                        <p class="mt-1 text-3xl font-bold text-indigo-700">
                            {{ formatScore(gradeResult.total_score) }} /
                            {{ formatScore(gradeResult.max_score) }}
                        </p>
                        <p class="mt-2 text-sm text-indigo-800">
                            {{ selectedStudent?.name }}
                        </p>
                    </div>

                    <section
                        v-for="(grade, gradeIndex) in gradeResult.question_grades"
                        :key="grade.question_id ?? `grade-${gradeIndex}`"
                        class="space-y-3 rounded-xl border border-slate-200 p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-slate-800">
                                <span v-if="grade.prompt">
                                    {{ gradeIndex + 1 }}. {{ grade.prompt }}
                                </span>
                                <span v-else>This paper</span>
                            </p>
                            <p class="shrink-0 text-sm font-semibold text-indigo-600">
                                {{ formatScore(grade.score) }} /
                                {{ formatScore(grade.max_score) }}
                            </p>
                        </div>
                        <p v-if="grade.overall_feedback" class="text-sm text-slate-700">
                            {{ grade.overall_feedback }}
                        </p>
                        <div
                            v-for="(score, index) in grade.scores"
                            :key="index"
                            class="rounded-lg bg-slate-50 p-3"
                        >
                            <div class="flex items-center justify-between gap-2">
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
                    </section>
                </template>

                <template v-else>
                    <div v-if="previewUrl" class="overflow-hidden rounded-xl bg-slate-100">
                        <img :src="previewUrl" alt="Captured paper" class="max-h-48 w-full object-contain" />
                    </div>

                    <p v-if="reading" class="text-sm font-medium text-indigo-600">Reading photo...</p>

                    <p v-if="currentQuestion && !coversAllQuestions" class="text-sm text-slate-700">
                        {{ currentQuestion.prompt }}
                    </p>

                    <p
                        v-if="existingSubmission && overrideMessage"
                        class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"
                    >
                        {{ overrideMessage }}
                    </p>

                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="paper-student-search">
                            Student
                        </label>
                        <div v-if="selectedStudent" class="mt-1 flex items-center gap-2">
                            <span
                                class="flex-1 rounded-lg bg-slate-100 px-3 py-3 text-sm font-medium text-slate-900"
                            >
                                {{ selectedStudent.name }}
                            </span>
                            <button
                                type="button"
                                class="min-h-11 text-sm font-medium text-slate-600 hover:text-slate-900"
                                @click="clearStudent"
                            >
                                Change
                            </button>
                        </div>
                        <div v-else class="relative mt-1">
                            <input
                                id="paper-student-search"
                                v-model="studentQuery"
                                type="search"
                                autocomplete="off"
                                class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                placeholder="Search or type a name"
                                @focus="showMatches = true"
                            />
                            <div
                                v-if="showMatches && (matches.length || canAddWalkIn)"
                                class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                            >
                                <button
                                    v-for="student in matches"
                                    :key="student.id"
                                    type="button"
                                    class="block min-h-11 w-full px-3 py-2 text-left text-sm text-slate-900 hover:bg-slate-50"
                                    @click="selectStudent(student)"
                                >
                                    {{ student.name }}
                                </button>
                                <button
                                    v-if="canAddWalkIn"
                                    type="button"
                                    class="block min-h-11 w-full px-3 py-2 text-left text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                                    @click="addWalkIn"
                                >
                                    Add “{{ query }}”
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="coversAllQuestions" class="space-y-4">
                        <div v-for="(question, index) in questions" :key="question.id">
                            <label
                                class="block text-sm font-medium text-slate-700"
                                :for="`paper-text-${question.id}`"
                            >
                                {{ questionLabel(index) }}
                            </label>
                            <p class="mt-1 text-xs text-slate-500">{{ question.prompt }}</p>
                            <textarea
                                :id="`paper-text-${question.id}`"
                                :value="drafts[question.id] ?? ''"
                                rows="5"
                                class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm leading-relaxed text-slate-900"
                                placeholder="Confirm this transcription before grading."
                                @input="onDraftInput(question.id, $event)"
                            />
                        </div>
                        <p class="text-xs text-slate-500">
                            This is a draft from the photo. Edit it if the reading is wrong, then grade.
                            {{ answeredCount }} of {{ questions.length }} questions captured
                        </p>
                    </div>
                    <div v-else>
                        <label class="block text-sm font-medium text-slate-700" for="paper-text">
                            Extracted text
                        </label>
                        <textarea
                            id="paper-text"
                            v-model="currentText"
                            rows="7"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm leading-relaxed text-slate-900"
                            placeholder="Confirm this transcription before grading."
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            This is a draft from the photo. Edit it if the reading is wrong, then grade.
                            {{ answeredCount }} of {{ questions.length }} questions captured
                        </p>
                    </div>
                </template>
            </div>

            <footer class="space-y-2 border-t border-slate-200 px-4 py-3">
                <template v-if="step === 'scope'">
                    <button
                        type="button"
                        :disabled="selectedScope === null"
                        class="min-h-11 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="takePhoto"
                    >
                        Take photo
                    </button>
                </template>
                <template v-else-if="gradeResult">
                    <button
                        type="button"
                        class="min-h-11 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        @click="resetForNextPaper"
                    >
                        Photograph next submission
                    </button>
                </template>
                <template v-else-if="overridePrompt">
                    <p class="text-sm text-slate-700">{{ overrideMessage }}</p>
                    <button
                        type="button"
                        class="min-h-11 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        @click="confirmOverride"
                    >
                        {{
                            existingSubmission?.status === 'graded'
                                ? 'Replace and override grade'
                                : 'Replace existing response'
                        }}
                    </button>
                    <button
                        type="button"
                        class="min-h-11 w-full text-sm font-medium text-slate-600 hover:text-slate-900"
                        @click="cancelOverride"
                    >
                        Cancel
                    </button>
                </template>
                <template v-else>
                    <button
                        v-if="hasNextQuestion"
                        type="button"
                        :disabled="reading"
                        class="min-h-11 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="goToNextQuestion"
                    >
                        Next question
                    </button>
                    <button
                        v-else-if="needsScope && unansweredQuestions.length > 0 && !coversAllQuestions"
                        type="button"
                        :disabled="reading"
                        class="min-h-11 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="photographAnotherQuestion"
                    >
                        Photograph another question
                    </button>
                    <button
                        type="button"
                        :disabled="reading || saving || grading"
                        class="min-h-11 w-full rounded-lg px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                        :class="
                            hasNextQuestion || (needsScope && unansweredQuestions.length > 0 && !coversAllQuestions)
                                ? 'border border-slate-300 bg-white text-slate-900 hover:bg-slate-50'
                                : 'bg-indigo-600 text-white hover:bg-indigo-500'
                        "
                        @click="gradePaper"
                    >
                        {{
                            grading || saving
                                ? 'Grading...'
                                : hasNextQuestion || (needsScope && unansweredQuestions.length > 0 && !coversAllQuestions)
                                  ? 'Skip remaining and grade'
                                  : 'Grade with AI'
                        }}
                    </button>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            :disabled="reading"
                            class="min-h-11 flex-1 text-sm font-medium text-slate-600 hover:text-slate-900 disabled:opacity-50"
                            @click="retakePhoto"
                        >
                            Retake
                        </button>
                        <button
                            type="button"
                            :disabled="reading"
                            class="min-h-11 flex-1 text-sm font-medium text-slate-600 hover:text-slate-900 disabled:opacity-50"
                            @click="addAnotherPhoto"
                        >
                            Add another photo
                        </button>
                    </div>
                </template>
            </footer>
        </div>
    </div>
</template>
