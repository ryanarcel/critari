<template>
    <div
        class="sticky bottom-0 z-20 mt-8 flex items-center justify-between gap-2 border-t border-slate-200 bg-white/95 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur sm:static sm:border-0 sm:bg-transparent sm:py-0 sm:backdrop-blur-none"
    >
        <button
            v-if="wizard.currentStep > 1"
            type="button"
            class="inline-flex min-h-11 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
            @click.prevent="wizard.prevStep"
        >
            <ArrowLeftIcon class="h-4 w-4" />
            <span>Back</span>
        </button>
        <div v-else></div>

        <div class="flex min-w-0 flex-1 items-center justify-end gap-2 sm:flex-none sm:gap-3">
            <button
                v-if="wizard.currentStep === 3 && wizard.mode === 'assignment'"
                type="button"
                :disabled="wizard.isSaving"
                class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm text-white shadow transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click.prevent="emit('publish')"
            >
                <span>{{ wizard.isSaving ? 'Creating...' : 'Create Assignment' }}</span>
                <ArrowRightIcon class="h-4 w-4" />
            </button>
            <button
                v-if="wizard.currentStep === 3 && wizard.mode === 'demo'"
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm text-white shadow transition hover:bg-indigo-700"
                @click.prevent="publishAndProceed"
            >
                <span>Publish & Continue</span>
                <ArrowRightIcon class="h-4 w-4" />
            </button>
            <button
                v-if="wizard.currentStep === 4 && !wizard.assessmentComplete"
                type="button"
                :disabled="wizard.isSubmittingAssessment"
                class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm text-white shadow transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click.prevent="submitStudentAnswer"
            >
                <span>{{ wizard.isSubmittingAssessment ? 'Assessing...' : 'Submit Answer' }}</span>
                <ArrowRightIcon class="h-4 w-4" />
            </button>
            <button
                v-if="wizard.currentStep === 4 && wizard.assessmentComplete"
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm text-white shadow transition hover:bg-indigo-700"
                @click.prevent="wizard.nextStep"
            >
                <span>Next Step</span>
                <ArrowRightIcon class="h-4 w-4" />
            </button>
            <button
                v-if="wizard.currentStep === 5"
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                @click.prevent="startOver"
            >
                <span>Start Over</span>
            </button>
            <button
                v-if="wizard.currentStep < 3"
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm text-white shadow transition hover:bg-indigo-700"
                @click.prevent="wizard.nextStep"
            >
                <span>Continue</span>
                <ArrowRightIcon class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';
import axios from 'axios';

const wizard = useWizardStore();

const emit = defineEmits<{
    'save-draft': [];
    publish: [];
}>();

const submitStudentAnswer = async () => {
    if (!wizard.validateStep(4)) return;

    if (!wizard.assignment_id) {
        wizard.showModal('Error', 'No assignment found. Please publish the rubric first.', 'error');
        return;
    }

    wizard.isSubmittingAssessment = true;

    try {
        const assignmentId =
            typeof wizard.assignment_id === 'number'
                ? wizard.assignment_id
                : (wizard.assignment_id?.value ?? null);
        console.debug(
            'Submitting student answer; assignment_id resolved as:',
            assignmentId,
            'wizard.assignment_id raw:',
            wizard.assignment_id,
        );

        const submissionRes = await axios.post('/submissions', {
            assignment_id: assignmentId,
            student_response: wizard.studentAnswer,
            answers: wizard.questions.map((question) => ({
                question_id: Number(question.id),
                response: (wizard.studentAnswers[question.id] ?? '').trim(),
            })),
            demo_id: wizard.demo_id,
        });

        if (!submissionRes.data.success) {
            wizard.showModal('Error', 'Failed to save submission', 'error');
            wizard.isSubmittingAssessment = false;
            return;
        }

        const submissionId = submissionRes.data.submission_id;
        wizard.submission_id = submissionId;

        const assessmentRes = await axios.post(`/submissions/${submissionId}/assess`, {
            submission_id: submissionId,
        });

        if (!assessmentRes.data.success) {
            wizard.showModal('Error', 'Failed to process assessment', 'error');
            wizard.isSubmittingAssessment = false;
            return;
        }

        wizard.setAssessmentResults(
            assessmentRes.data.scores,
            assessmentRes.data.max_score,
            assessmentRes.data.question_grades ?? [],
        );

        wizard.showModal('Assessed', 'Student answer assessed successfully!', 'success');
        setTimeout(() => {
            wizard.nextStep();
            wizard.isSubmittingAssessment = false;
        }, 1000);
    } catch (error: any) {
        const message = error.response?.data?.message || 'An error occurred during assessment';
        wizard.showModal('Error', message, 'error');
        wizard.isSubmittingAssessment = false;
    }
};

const publishAndProceed = () => {
    emit('publish');
    setTimeout(() => {
        wizard.nextStep();
    }, 1000);
};

const startOver = () => {
    wizard.showModal('Ready', 'Assessment complete! Starting over...', 'success');
    setTimeout(() => {
        wizard.resetForm();
    }, 1500);
};
</script>
