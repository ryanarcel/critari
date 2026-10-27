<template>
    <div class="space-y-6">
        <section
            v-for="(grade, gradeIndex) in displayedGrades"
            :key="grade.question_id ?? `paper-${gradeIndex}`"
            class="rounded-xl border border-slate-100 bg-white p-8 shadow-sm"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h3 class="text-sm font-semibold text-slate-800">
                    <span v-if="grade.prompt">
                        {{ gradeIndex + 1 }}. {{ grade.prompt }}
                    </span>
                    <span v-else>Student response</span>
                </h3>
                <p class="text-sm font-semibold text-indigo-600">
                    {{ formatScore(grade.score) }} / {{ grade.max_score }}
                </p>
            </div>
            <div
                class="mt-4 whitespace-pre-wrap rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-700"
            >
                {{ grade.response }}
            </div>
            <p v-if="grade.overall_feedback" class="mt-4 text-sm text-slate-700">
                {{ grade.overall_feedback }}
            </p>
            <div class="mt-4 space-y-3">
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
                            {{ formatScore(score.score) }} /
                            {{ getMaxScorePerCriterion() }}
                        </p>
                    </div>
                    <p v-if="score.feedback" class="mt-2 text-sm leading-relaxed text-slate-600">
                        <span class="font-medium text-slate-500">Reasoning. </span>
                        {{ score.feedback }}
                    </p>
                </div>
            </div>
        </section>

        <div class="rounded-xl border border-slate-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-700">Overall total</span>
                <span class="text-2xl font-bold text-indigo-600">
                    {{ overallScore }} / {{ wizard.maxScore }}
                </span>
            </div>
            <p
                v-if="displayedGrades.length > 1"
                class="mt-2 text-xs text-slate-500"
            >
                Each response is graded against the full rubric. The overall total is the sum of
                those scores.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useWizardStore, type QuestionGrade } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();

const displayedGrades = computed<QuestionGrade[]>(() => {
    if (wizard.questionGrades.length > 0) {
        return wizard.questionGrades;
    }

    return wizard.questions.map((question) => ({
        question_id: Number(question.id) || null,
        prompt: question.prompt,
        response: wizard.studentAnswers[question.id] ?? wizard.studentAnswer,
        score: Object.values(wizard.aiScores).reduce((sum, value) => sum + value, 0),
        max_score: wizard.maxScore,
        overall_feedback: null,
        scores: wizard.criteria.map((criterion) => ({
            criterion_name: criterion.name,
            score: wizard.aiScores[criterion.id] ?? 0,
            feedback: wizard.aiFeedback[criterion.id] ?? null,
        })),
    }));
});

const overallScore = computed(() => {
    if (wizard.questionGrades.length > 0) {
        return wizard.questionGrades.reduce(
            (sum, grade) => sum + Number(grade.score || 0),
            0,
        );
    }

    return Object.values(wizard.aiScores).reduce((sum, value) => sum + value, 0);
});

const formatScore = (value: number | string | null) => {
    if (value === null || value === '') {
        return '';
    }

    const numeric = Number(value);

    return Number.isFinite(numeric) ? numeric : value;
};

const getMaxScorePerCriterion = () => {
    if (wizard.levels.length === 0) {
        return 10;
    }

    const lastLevel = wizard.levels[wizard.levels.length - 1];
    const range = lastLevel.range || '0-10';
    const parts = range.split('-');

    return parseInt(parts[1] || '10');
};
</script>
