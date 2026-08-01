<template>
	<div class="bg-white border border-slate-100 rounded-xl p-12 shadow-sm text-center">
		<div class="space-y-4">
			<CheckIcon class="w-16 h-16 text-indigo-600 mx-auto" />
			<h2 class="text-2xl font-bold text-slate-900">Ready to Publish</h2>
			<p class="text-slate-600">Your rubric is complete and ready to be published or saved as a draft.</p>
			<div class="pt-4">
				<p class="text-sm text-slate-500">Review your rubric details before finalizing.</p>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { CheckIcon } from '@heroicons/vue/24/outline';
</script>
