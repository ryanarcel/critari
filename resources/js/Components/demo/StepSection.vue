<template>
	<div>
		<div class="text-xs font-bold uppercase tracking-widest text-indigo-400 px-4 py-2 mb-2">{{ emoji }} {{ title }}</div>
		<div class="space-y-3">
			<button
				v-for="(step, idx) in steps"
				:key="step.id"
				type="button"
				:class="{
					'bg-indigo-600 border border-indigo-500 shadow-lg': wizard.currentStep === step.id,
					'bg-indigo-700/50 hover:bg-indigo-700/70 border border-indigo-600': wizard.currentStep !== step.id,
					'opacity-50 cursor-not-allowed': step.id > wizard.currentStep,
					'cursor-pointer': step.id <= wizard.currentStep
				}"
				:disabled="step.id > wizard.currentStep"
				class="w-full text-left p-4 rounded-lg transition-all duration-200 relative"
				@click="wizard.goToStep(step.id)"
			>
				<div class="flex items-start gap-3">
					<div 
						class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition-all"
						:class="{
							'bg-white text-indigo-700': wizard.currentStep === step.id,
							'bg-indigo-500 text-white': wizard.currentStep !== step.id,
						}"
					>
						{{ wizard.steps.findIndex(s => s.id === step.id) + 1 }}
					</div>
					<div class="flex-1 min-w-0">
						<h3 class="font-semibold text-sm">{{ step.name }}</h3>
						<p class="text-xs text-indigo-200 mt-1">{{ step.description }}</p>
					</div>
				</div>
				<div 
					v-if="wizard.currentStep > step.id"
					class="absolute right-4 top-1/2 -translate-y-1/2"
				>
					<CheckIcon class="w-5 h-5 text-indigo-200" />
				</div>
			</button>
		</div>
	</div>
</template>

<script setup lang="ts">
import { CheckIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';

interface Step {
	id: number;
	name: string;
	description: string;
	section: string;
}

defineProps<{
	emoji: string;
	title: string;
	steps: Step[];
}>();

const wizard = useWizardStore();
</script>
