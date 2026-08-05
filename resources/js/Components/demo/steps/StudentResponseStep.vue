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
	</div>
</template>

<script setup lang="ts">
import { useWizardStore } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();
</script>
