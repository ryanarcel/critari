<template>
	<div class="space-y-6">
		<!-- Question Display -->
		<div class="bg-white border border-slate-100 rounded-xl p-8 shadow-sm">
			<div class="space-y-4">
				<div>
					<h3 class="text-sm font-semibold text-slate-700 mb-3">Assignment Question</h3>
					<p class="text-slate-700 bg-slate-50 p-4 rounded-lg border border-slate-200 whitespace-pre-wrap">{{ wizard.question }}</p>
				</div>
			</div>
		</div>

		<!-- Student Answer Input -->
		<div class="bg-white border border-slate-100 rounded-xl p-8 shadow-sm">
			<div class="space-y-4">
				<div>
					<label for="studentAnswer" class="block text-sm font-semibold text-slate-700 mb-3">Your Answer</label>
					<textarea 
						id="studentAnswer" 
						v-model="wizard.studentAnswer" 
						rows="12" 
						placeholder="Write your essay response here..." 
						class="block w-full rounded-lg border border-slate-200 bg-slate-50 shadow-sm sm:text-sm p-4 text-slate-700 transition-colors duration-150 hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
					></textarea>
					<p class="mt-2 text-xs text-slate-500">Enter your complete essay response or written answer to the prompt above.</p>
				</div>
			</div>
		</div>

		<!-- Helper Actions -->
		<div class="bg-slate-50 border border-slate-200 rounded-xl p-6 flex gap-3">
			<button 
				type="button"
				class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-white border border-slate-200 text-sm text-slate-700 hover:bg-slate-100 transition" 
				@click.prevent="loadSampleAnswer"
			>
				<LightBulbIcon class="w-4 h-4" />
				<span>Load Sample Answer</span>
			</button>
			<p class="text-xs text-slate-600 flex items-center">Use this to preview how the AI assessment works with pre-written content.</p>
		</div>
	</div>
</template>

<script setup lang="ts">
import { LightBulbIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();

const loadSampleAnswer = () => {
	const sampleKey = 'narrative-essay';
	if (wizard.sampleAnswers[sampleKey as keyof typeof wizard.sampleAnswers]) {
		wizard.studentAnswer = wizard.sampleAnswers[sampleKey as keyof typeof wizard.sampleAnswers];
		wizard.showModal('Sample Loaded', 'Sample answer loaded. You can edit it as needed.', 'success');
	}
};
</script>
