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
			<button 
				v-if="wizard.currentStep === 3"
				type="button"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 transition" 
				@click.prevent="emit('save-draft')"
			>
				<span>Save Draft</span>
			</button>
			<button 
				v-if="wizard.currentStep === 4"
				type="button"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition shadow" 
				@click.prevent="submitStudentAnswer"
			>
				<span>Submit Answer</span>
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
			<button 
				v-if="wizard.currentStep === 3"
				type="button"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition shadow" 
				@click.prevent="emit('publish')"
			>
				<ArrowDownOnSquareIcon class="w-4 h-4" />
				<span>Publish</span>
			</button>
			<button 
				v-if="wizard.currentStep >= 3 && wizard.currentStep < 5"
				type="button"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition shadow" 
				@click.prevent="wizard.nextStep"
			>
				<span>Next Step</span>
				<ArrowRightIcon class="w-4 h-4" />
			</button>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ArrowLeftIcon, ArrowRightIcon, ArrowDownOnSquareIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();

const emit = defineEmits<{
	'save-draft': [];
	'publish': [];
}>();

const submitStudentAnswer = () => {
	if (wizard.validateStep(4)) {
		wizard.showModal('Submitted', 'Student answer submitted for assessment!', 'success');
		setTimeout(() => {
			wizard.nextStep();
		}, 1000);
	}
};

const startOver = () => {
	wizard.showModal('Ready', 'Assessment complete! Starting over...', 'success');
	setTimeout(() => {
		wizard.resetForm();
	}, 1500);
};
</script>
