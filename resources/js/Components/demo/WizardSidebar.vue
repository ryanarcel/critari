<template>
	<div class="w-80 bg-gradient-to-b from-indigo-700 to-indigo-900 text-white flex flex-col shadow-xl border-l border-indigo-600 sticky top-0 h-screen overflow-y-auto">
		<!-- Sidebar Header -->
		<div class="p-6 border-b border-indigo-600">
			<h2 class="text-sm font-semibold uppercase tracking-wider text-indigo-200">Workflow Progress</h2>
			<p class="text-xs text-indigo-300 mt-1">Step {{ wizard.currentStep }} of {{ wizard.totalSteps }}</p>
		</div>

		<!-- Steps List with Section Grouping -->
		<div class="flex-1 overflow-y-auto p-4">
			<div class="space-y-4">
				<!-- Teacher Section -->
				<StepSection
					emoji="👨‍🏫"
					title="Teacher Setup"
					:steps="wizard.steps.filter(s => s.section === 'Teacher')"
				/>

				<!-- Student Section -->
				<StepSection
					emoji="👤"
					title="Student Response"
					:steps="wizard.steps.filter(s => s.section === 'Student')"
				/>

				<!-- Assessment Section -->
				<StepSection
					emoji="🤖"
					title="AI Assessment"
					:steps="wizard.steps.filter(s => s.section === 'Assessment')"
				/>
			</div>
		</div>

		<!-- Sidebar Footer -->
		<div class="border-t border-indigo-600 p-4 space-y-2 text-xs">
			<a href="#" class="block text-indigo-300 hover:text-white transition">Help & Support</a>
			<a href="#" class="block text-indigo-300 hover:text-white transition">Terms of Service</a>
			<a href="#" class="block text-indigo-300 hover:text-white transition">Privacy Policy</a>
		</div>
	</div>
</template>

<script setup lang="ts">
import { useWizardStore } from '@/stores/demo/wizardStore';
import StepSection from './StepSection.vue';

const wizard = useWizardStore();
</script>
