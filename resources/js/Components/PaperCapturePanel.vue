<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface Question {
    id: number;
    prompt: string;
}

interface Student {
    id: number;
    name: string;
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

const currentQuestion = computed(() => props.questions[questionIndex.value] ?? null);

const questionNumber = computed(() => questionIndex.value + 1);

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

const hasNextQuestion = computed(() => questionIndex.value < props.questions.length - 1);

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

const readPhoto = async (file: File) => {
    if (!currentQuestion.value) {
        return;
    }

    reading.value = true;
    errorMessage.value = '';
    gradeResult.value = null;
    revokePreview();
    previewUrl.value = URL.createObjectURL(file);

    try {
        const compressed = await compressImage(file);
        const form = new FormData();
        form.append('question_id', String(currentQuestion.value.id));
        form.append('image', compressed);

        const response = await axios.post(`/assignments/${props.assignmentId}/paper-ocr`, form);
        const extracted = String(response.data.text ?? '').trim();
        const previous = appendNextPhoto.value ? currentText.value.trim() : '';

        currentText.value = previous && extracted ? `${previous}\n\n${extracted}` : extracted || previous;
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
        const submissionId = created.data.submission_id as number;

        await axios.put(`/assignments/${props.assignmentId}/paper-submissions/${submissionId}`, {
            student_name: selectedStudent.value.id === null ? selectedStudent.value.name : undefined,
            answers: Object.entries(drafts.value).map(([questionId, response]) => ({
                question_id: Number(questionId),
                response,
            })),
        });

        return submissionId;
    } catch (error: any) {
        errorMessage.value =
            error.response?.data?.message ||
            error.response?.data?.errors?.user_id?.[0] ||
            error.response?.data?.errors?.student_name?.[0] ||
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

    grading.value = true;
    errorMessage.value = '';

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

const goToNextQuestion = () => {
    if (!hasNextQuestion.value) {
        return;
    }

    questionIndex.value += 1;
    photoCount.value = 0;
    appendNextPhoto.value = false;
    emit('capture');
};

const addAnotherPhoto = () => {
    appendNextPhoto.value = true;
    emit('capture');
};

const retakePhoto = () => {
    appendNextPhoto.value = false;
    emit('capture');
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
    revokePreview();
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
                        Photograph a paper
                    </p>
                    <p v-if="currentQuestion && !gradeResult" class="mt-0.5 text-sm font-medium text-slate-900">
                        Question {{ questionNumber }} of {{ questions.length }}
                    </p>
                    <p v-else-if="gradeResult" class="mt-0.5 text-sm font-medium text-slate-900">
                        Graded
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

                <template v-if="gradeResult">
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

                    <p v-if="currentQuestion" class="text-sm text-slate-700">
                        {{ currentQuestion.prompt }}
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

                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="paper-text">
                            Extracted text
                        </label>
                        <textarea
                            id="paper-text"
                            v-model="currentText"
                            rows="7"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm leading-relaxed text-slate-900"
                            placeholder="Review and edit the transcribed answer."
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            {{ answeredCount }} of {{ questions.length }} questions captured
                        </p>
                    </div>
                </template>
            </div>

            <footer class="space-y-2 border-t border-slate-200 px-4 py-3">
                <template v-if="gradeResult">
                    <button
                        type="button"
                        class="min-h-11 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        @click="resetForNextPaper"
                    >
                        Photograph next paper
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
                        type="button"
                        :disabled="reading || saving || grading"
                        class="min-h-11 w-full rounded-lg px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                        :class="
                            hasNextQuestion
                                ? 'border border-slate-300 bg-white text-slate-900 hover:bg-slate-50'
                                : 'bg-indigo-600 text-white hover:bg-indigo-500'
                        "
                        @click="gradePaper"
                    >
                        {{
                            grading || saving
                                ? 'Grading...'
                                : hasNextQuestion
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
