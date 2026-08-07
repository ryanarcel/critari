<template>
	<div class="mt-8 flex items-center justify-between">
		<button 
			v-if="wizard.currentStep > 1"
			type="button"
			class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 transition" 
			@click.prevent="wizard.prevStep"
		>
			<ArrowLeftIcon class="w-4 h-4" />
			<span>Back</span>
		</button>
		<div v-else></div>

		<div class="flex items-center gap-3">
			<!-- Step 3: Show only Next Step since rubric is published -->
			<button 
				v-if="wizard.currentStep === 3"
				type="button"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition shadow" 
				@click.prevent="wizard.nextStep"
			>
				<span>Next Step</span>
				<ArrowRightIcon class="w-4 h-4" />
			</button>
			<!-- Step 4: Show Submit Answer if assessment not done, Next Step if done -->
			<button 
				v-if="wizard.currentStep === 4 && !wizard.assessmentComplete"
				type="button"
				:disabled="wizard.isSubmittingAssessment"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition shadow disabled:opacity-50 disabled:cursor-not-allowed" 
				@click.prevent="submitStudentAnswer"
			>
				<span>{{ wizard.isSubmittingAssessment ? 'Assessing...' : 'Submit Answer' }}</span>
				<ArrowRightIcon class="w-4 h-4" />
			</button>
			<button 
				v-if="wizard.currentStep === 4 && wizard.assessmentComplete"
				type="button"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition shadow" 
				@click.prevent="wizard.nextStep"
			>
				<span>Next Step</span>
				<ArrowRightIcon class="w-4 h-4" />
			</button>
			<button 
				v-if="wizard.currentStep === 5"
				type="button"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 transition" 
				@click.prevent="startOver"
			>
				<span>Start Over</span>
			</button>
			<button 
				v-if="wizard.currentStep < 3"
				type="button"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition shadow" 
				@click.prevent="wizard.nextStep"
			>
				<span>Continue</span>
				<ArrowRightIcon class="w-4 h-4" />
			</button>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ArrowLeftIcon, ArrowRightIcon, ArrowDownOnSquareIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';
import axios from 'axios';

const wizard = useWizardStore();

const emit = defineEmits<{
	'save-draft': [];
	'publish': [];
}>();

const submitStudentAnswer = async () => {
	if (!wizard.validateStep(4)) return;
	
	if (!wizard.assignment_id) {
		wizard.showModal('Error', 'No assignment found. Please publish the rubric first.', 'error');
		return;
	}
	
	wizard.isSubmittingAssessment = true;
	
	try {
		// Step 1: Save student submission
		const submissionRes = await axios.post('/submissions', {
			assignment_id: wizard.assignment_id,
			student_response: wizard.studentAnswer,
			demo_id: wizard.demo_id,
		});
		
		if (!submissionRes.data.success) {
			wizard.showModal('Error', 'Failed to save submission', 'error');
			wizard.isSubmittingAssessment = false;
			return;
		}
		
		const submissionId = submissionRes.data.submission_id;
		wizard.submission_id = submissionId;
		
		// Step 2: Process AI assessment
		const assessmentRes = await axios.post(`/submissions/${submissionId}/assess`, {
			submission_id: submissionId,
		});
		
		if (!assessmentRes.data.success) {
			wizard.showModal('Error', 'Failed to process assessment', 'error');
			wizard.isSubmittingAssessment = false;
			return;
		}
		
		// Step 3: Store assessment results in wizard store
		wizard.setAssessmentResults(assessmentRes.data.scores, assessmentRes.data.max_score);
		
		// Step 4: Show success and advance
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
