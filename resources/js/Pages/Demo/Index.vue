<script setup lang="ts">
import { ref } from 'vue';
import { ArrowDownOnSquareIcon, XMarkIcon, PlusIcon, PencilIcon, CheckIcon, LightBulbIcon } from '@heroicons/vue/24/outline';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

// Rubric editor state following the attachment layout
const title = ref('');
const question = ref('');

const levels = ref([
	{ id: 'lvl-0', name: 'Needs Imp.', range: '0-4' },
	{ id: 'lvl-1', name: 'Satisfactory', range: '5-6' },
	{ id: 'lvl-2', name: 'Good', range: '7-8' },
	{ id: 'lvl-3', name: 'Excellent', range: '9-10' },
]);

const criteria = ref([
	{ id: 'c-idea', name: 'Idea explanation', cells: ['Little or no explanation', 'Ideas somewhat explained', 'Ideas explained', 'Thoroughly explained ideas'] },
	{ id: 'c-coherency', name: 'Coherency', cells: ['Lacks coherency', 'Somewhat coherent', 'Coherent writing', 'Extremely coherent writing'] },
	{ id: 'c-grammar', name: 'Grammar', cells: ['Many errors that hurt understanding', 'Many errors', 'Some errors', 'Few errors'] },
]);

const addCriteria = () => {
	const id = `c-${Date.now()}`;
	criteria.value.push({ id, name: 'New Criteria', cells: Array(levels.value.length).fill('Description...') });
};

const addLevel = () => {
	const id = `lvl-${Date.now()}`;
	levels.value.push({ id, name: 'New Level', range: '0' });
	// add empty cell for each criterion
	for (const c of criteria.value) c.cells.push('Description...');
};

const deleteCriteria = (idx: number) => {
	criteria.value.splice(idx, 1);
};

const deleteLevel = (idx: number) => {
	levels.value.splice(idx, 1);
	for (const c of criteria.value) c.cells.splice(idx, 1);
};

const saveRubric = (publish = false) => {
	const payload = { title: title.value, description: question.value, levels: levels.value.slice(), criteria: criteria.value.map(c => ({ id: c.id, name: c.name, cells: c.cells.slice() })), publish };
	console.log('Save rubric payload', payload);
	alert(`${publish ? 'Publish' : 'Save Draft'} — check console for payload.`);
};

const form = ref({
	// kept for UI reset only; actual submission uses Inertia.post
	reset: () => {
				title.value = '';
				question.value = '';
				levels.value = [
						{ id: 'lvl-0', name: 'Needs Imp.', range: '0-4' },
						{ id: 'lvl-1', name: 'Satisfactory', range: '5-6' },
						{ id: 'lvl-2', name: 'Good', range: '7-8' },
						{ id: 'lvl-3', name: 'Excellent', range: '9-10' },
				];
				criteria.value = [
						{ id: 'c-idea', name: 'Idea explanation', cells: ['Little or no explanation', 'Ideas somewhat explained', 'Ideas explained', 'Thoroughly explained ideas'] },
						{ id: 'c-coherency', name: 'Coherency', cells: ['Lacks coherency', 'Somewhat coherent', 'Coherent writing', 'Extremely coherent writing'] },
						{ id: 'c-grammar', name: 'Grammar', cells: ['Many errors that hurt understanding', 'Many errors', 'Some errors', 'Few errors'] },
				];
		}
	});

	const submit = () => {
		const payload = {
			title: title.value,
			question: question.value,
			levels: levels.value.slice(),
			criteria: criteria.value.map(c => ({ id: c.id, name: c.name, cells: c.cells.slice() })),
		};

		axios.post(route('assignments.store'), payload, {

		}).then(() => form.value.reset()).catch(err => console.error(err));
	};


	const getAIRubricSuggestion = () => {
		const payload = {
			question: question.value,
			levels: levels.value.slice(),
		};

		axios.post(route('assignments.ai-rubric-suggestion'), payload, {

		}).then((res) => {
			try {
				const data = res.data || {};
				if (data.success && data.rubric && Array.isArray(data.rubric.criteria)) {
					// Map returned criteria into local shape
					criteria.value = data.rubric.criteria.map((c, idx) => ({
						id: `c-ai-${Date.now()}-${idx}`,
						name: c.name || `Criterion ${idx + 1}`,
						cells: Array.isArray(c.cells) ? c.cells.slice() : Array(levels.value.length).fill(''),
					}));
				}
			} catch (e) {
				console.error('Failed to apply AI rubric suggestion', e);
			}
		}).catch(err => console.error(err));
	};
</script>

<template>
	<Head title="Rubric Editor — Critari" />

	<div class="min-h-screen text-slate-900 relative overflow-hidden">

		<div class="max-w-6xl mx-auto px-6 py-10 relative">
			<header class="mb-6">
				<nav class="flex items-center justify-between mb-4">
					<a href="#" class="text-sm text-slate-500 hover:underline">&lt;-- Back to Rubrics</a>
					<div class="flex items-center gap-3">
						<button class="inline-flex items-center gap-2 px-3 py-1 rounded-md border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50">Preview</button>
						<button class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-white border border-slate-200 text-sm text-slate-700 hover:bg-slate-50" @click.prevent="saveRubric(false)">Save Draft</button>
						<button class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-primary text-white text-sm hover:bg-primary/90 shadow" @click.prevent="saveRubric(true)">Publish</button>
					</div>
				</nav>
			</header>

			<form @submit.prevent="submit">
				<!-- Details -->
				<div class="mb-6 bg-white border border-slate-100 rounded-xl p-6 pb-8 md:pb-6 shadow-sm transition-shadow duration-150 min-h-[120px]">
					<div class="grid grid-cols-1 gap-y-6 sm:grid-cols-3 sm:gap-x-6">
						<div class="sm:col-span-2">
							<label for="title" class="block text-sm font-medium text-slate-700">Title</label>
							<div class="mt-1">
								<div class="relative">
									<input id="title" v-model="title" placeholder="e.g. Narrative Essay - Midterm 2026" class="block w-full rounded-lg border border-slate-200 bg-slate-50 shadow-sm sm:text-sm p-3 pr-10 text-slate-700 transition-colors duration-150 hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" />
									<div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
										<PencilIcon class="w-5 h-5" />
									</div>
								</div>
							</div>
						</div>
						<div class="sm:col-span-3">
							<label for="question" class="block text-sm font-medium text-slate-700">Question</label>
							<div class="mt-1">
								<div class="relative pb-12">
										<textarea id="question" v-model="question" rows="3" placeholder="Write essay question here." class="block w-full rounded-lg border border-slate-200 bg-slate-50 shadow-sm sm:text-sm p-3 text-slate-700 transition-colors duration-150 hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"></textarea>

										<!-- AI Suggestion button was here; moved to Rubric Matrix section -->
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Rubric Matrix Wrapper -->
					<div class="bg-white border border-slate-100 rounded-xl shadow overflow-auto transition-shadow duration-150">
						<div class="flex items-center justify-between p-4 border-b">
							<div class="flex items-center gap-3">
								<div class="text-sm font-medium text-slate-700">Rubric Matrix</div>
								<span class="text-xs text-slate-500">Manage levels and criteria</span>
							</div>
							<div>
								<button class="inline-flex items-center gap-2 px-3 py-2 rounded-md bg-slate-100 border border-slate-200 text-sm text-slate-700 hover:bg-slate-200 transition transform active:scale-95" @click.prevent="addLevel">
									<PlusIcon class="w-4 h-4 text-slate-600" />
									<span class="font-medium">Add Level</span>
								</button>
							</div>
						</div>

					<div class="p-4">
							<div class="overflow-x-auto">
								<table :class="levels.length >= 6 ? 'min-w-[1200px] table-fixed border-collapse' : 'w-full table-fixed border-collapse'" class="bg-white">
								<thead>
									<tr class="bg-slate-100">
										<th class="w-1/4 p-4 border border-slate-200 text-left text-sm font-semibold text-slate-700">CRITERIA \ LEVELS</th>
										<th v-for="(lvl, li) in levels" :key="lvl.id" class="p-3 border text-center">
											<div class="flex flex-col items-center gap-1">
												<input v-model="lvl.name" class="w-full text-center rounded-lg border border-slate-200 bg-slate-50 text-sm font-semibold p-2 transition hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" />
												<input v-model="lvl.range" class="w-full text-center text-xs text-slate-500 bg-transparent p-1" />
												<div class="mt-2">
													<button class="text-slate-500 p-1 hover:bg-red-50 rounded-md" @click.prevent="deleteLevel(li)">
														<XMarkIcon class="w-4 h-4" />
													</button>
												</div>
											</div>
										</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="(c, ci) in criteria" :key="c.id" class="align-top odd:bg-white even:bg-slate-50 border-b hover:bg-slate-50 transition-colors duration-150">
										<td class="p-4 align-top">
											<div class="flex items-start justify-between">
												<div class="w-full">
													<input v-model="c.name" class="w-full font-medium rounded-lg border border-slate-200 bg-slate-50 p-2 text-sm transition hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" />
												</div>
												<div class="ml-3">
													<button class="text-slate-500 p-1 hover:bg-red-50 rounded-md" @click.prevent="deleteCriteria(ci)">
														<XMarkIcon class="w-4 h-4" />
													</button>
												</div>
											</div>
										</td>
										<td v-for="(cell, k) in c.cells" :key="k" class="p-3 align-top border-l border-slate-200">
											<textarea v-model="c.cells[k]" class="w-full border border-slate-200 rounded-lg bg-slate-50 p-3 text-sm min-h-[80px] resize-none transition hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" rows="3" placeholder="Describe performance at this level..."></textarea>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

							<div class="mt-4">
								<button class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-white border border-slate-200 text-sm text-slate-700 hover:bg-slate-50" @click.prevent="addCriteria">+ Add Criteria (Row)</button>
							</div>
					</div>
				</div>

				<!-- AI Suggestion (Rubric Matrix) -->
				<div class="mt-4 flex justify-end">
					<button class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-neutral-100 border border-primary text-primary hover:bg-primary-50 transition duration-150" @click.prevent="getAIRubricSuggestion()">
						<LightBulbIcon class="w-4 h-4 text-primary" />
						<span class="text-sm font-medium">AI Suggest</span>
					</button>
				</div>

				<!-- Summary stats -->
				<div class="mt-4 p-4 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-between">
					<div class="flex items-center gap-3 text-sm text-slate-700">
						<div class="bg-white p-2 rounded-full border border-slate-200">
							<CheckIcon class="w-4 h-4 text-indigo-600" />
						</div>
						<div>
							<div class="text-xs font-medium text-slate-600">Total Criteria</div>
							<div class="text-sm font-semibold text-slate-800">{{ criteria.length }} rows</div>
						</div>
					</div>
					<div class="text-sm text-slate-700">
						<div class="text-xs font-medium text-slate-600">Max Rubric Score</div>
						<div class="text-sm font-semibold text-slate-800">{{ levels.length * 10 }} pts</div>
					</div>
				</div>

				<!-- Save actions -->
				<div class="mt-6 flex items-center justify-start gap-3">
					<button class="px-4 py-2 rounded-md bg-white border border-slate-200 text-sm text-slate-700 hover:bg-slate-50" @click.prevent="saveRubric(false)">Save Draft</button>
					<button class="px-4 py-2 rounded-md bg-primary text-white flex items-center gap-2 shadow hover:bg-slate-800" @click.prevent="submit()">
						<ArrowDownOnSquareIcon class="w-4 h-4" />
						<span class="text-sm font-medium">Save & Publish</span>
					</button>
				</div>
			</form>
		</div>
	</div>
</template>

