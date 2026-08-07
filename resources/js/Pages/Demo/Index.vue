<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useWizardStore } from '@/stores/demo/wizardStore';
import WizardSidebar from '@/Components/demo/WizardSidebar.vue';
import WizardModal from '@/Components/demo/WizardModal.vue';
import WizardNavigation from '@/Components/demo/WizardNavigation.vue';
import RubricSetupStep from '@/Components/demo/steps/RubricSetupStep.vue';
import QuestionPromptStep from '@/Components/demo/steps/QuestionPromptStep.vue';
import ReviewPublishStep from '@/Components/demo/steps/ReviewPublishStep.vue';
import StudentResponseStep from '@/Components/demo/steps/StudentResponseStep.vue';
import AssessmentViewStep from '@/Components/demo/steps/AssessmentViewStep.vue';

const page = usePage();
const user = computed(() => (page.props.auth as { user: { name: string } | null })?.user ?? null);
const axios = (window as any).axios;
const wizard = useWizardStore();

// Get sessionId from route params
const sessionId = computed(() => (page.props as { sessionId?: string }).sessionId);

// Load session state on mount
onMounted(() => {
	if (sessionId.value) {
		wizard.loadState(sessionId.value);
	}
});

const saveRubric = (publish = false) => {
	// Validate all steps before proceeding
	if (!wizard.validateStep(1) || !wizard.validateStep(2) || !wizard.validateStep(3)) {
		return;
	}

	const payload = {
		title: wizard.title,
		question: wizard.question,
		levels: wizard.levels.slice(),
		criteria: wizard.criteria.map(c => ({ id: c.id, name: c.name, cells: c.cells.slice() })),
		session_id: sessionId.value || null,
		publish,
	};

	axios.post(route('assignments.store'), payload)
		.then((res) => {
			// Store assignment_id and demo_id in wizard store for later use in assessment
			if (res.data.data) {
				wizard.assignment_id = res.data.data.assignment_id;
				wizard.demo_id = res.data.data.demo_id;
			}
			
			wizard.showModal('Success', `Your rubric has been ${publish ? 'published' : 'saved as draft'} successfully!`, 'success');
			// Only reset form if saving as draft, not on publish
			if (!publish) {
				setTimeout(() => {
					wizard.resetForm();
				}, 1500);
			}
		})
		.catch(err => {
			console.error(err);
			wizard.showModal('Save Failed', err.response?.data?.message || err.message || 'An error occurred while saving.', 'error');
		});
};

const getAIRubricSuggestion = () => {
	// Fall back on title if question is not yet present
	const context = wizard.question.trim() || wizard.title;
	
	if (!context.trim()) {
		wizard.showModal('Missing Context', 'Please enter a rubric title to generate criteria.', 'error');
		return;
	}

	wizard.isLoadingAI = true;

	const payload = {
		question: wizard.question.trim() ? wizard.question : null,
		title: !wizard.question.trim() ? wizard.title : null,
		levels: wizard.levels.slice(),
		num_criteria: wizard.criteria.length,
	};

	axios.post(route('assignments.ai-rubric-suggestion'), payload).then((res) => {
		try {
			const data = res.data || {};
			if (data.success && data.rubric && Array.isArray(data.rubric.criteria)) {
				// Update level names and ranges if provided
				if (data.levels && Array.isArray(data.levels)) {
					data.levels.forEach((suggestedLevel, idx) => {
						if (idx < wizard.levels.length) {
							wizard.levels[idx].name = suggestedLevel.name;
							wizard.levels[idx].range = suggestedLevel.range;
						}
					});
				}

				// Map returned criteria into local shape
				wizard.criteria = data.rubric.criteria.map((c, idx) => ({
					id: `c-ai-${Date.now()}-${idx}`,
					name: c.name || `Criterion ${idx + 1}`,
					cells: Array.isArray(c.cells) ? c.cells.slice() : Array(wizard.levels.length).fill(''),
				}));
				
				wizard.showModal('Rubric Generated', 'AI has suggested level names and criteria for your rubric. Review and edit as needed.', 'success');
			}
		} catch (e) {
			console.error('Failed to apply AI rubric suggestion', e);
			wizard.showModal('Generation Failed', 'Could not process the AI response. Please try again.', 'error');
		}
	}).catch(err => {
		console.error(err);
		wizard.showModal('AI Request Failed', err.response?.data?.message || 'Failed to generate criteria. Please try again.', 'error');
	}).finally(() => {
		wizard.isLoadingAI = false;
	});
};</script>

<template>
	<Head title="Rubric Editor — Critari" />

	<div class="flex flex-col min-h-screen text-slate-900">
		<!-- Nav -->
		<nav class="sticky top-0 z-50 border-b border-indigo-700 bg-indigo-600 px-6 py-4 backdrop-blur-xl">
			<div class="mx-auto flex max-w-full items-center justify-between">
				<Link :href="route('demos.index')" class="text-xl font-black tracking-tight text-white">
					critari<span class="text-indigo-200">.</span>
				</Link>

				<div class="hidden items-center space-x-8 text-xs font-semibold uppercase tracking-wider text-indigo-100 md:flex">
					<a href="#pipeline" class="transition-colors hover:text-white">Pipeline</a>
					<a href="#isolation" class="transition-colors hover:text-white">Data Security</a>
				</div>

				<div class="flex items-center space-x-4">
					<template v-if="user">
						<Link :href="route('dashboard')" class="text-xs font-bold uppercase tracking-wider text-indigo-100 transition-colors hover:text-white">
							Dashboard
						</Link>
					</template>
					<template v-else>
						<Link :href="route('login')" class="text-xs font-bold uppercase tracking-wider text-indigo-100 transition-colors hover:text-white">
							Sign In
						</Link>
						<Link :href="route('register')" class="rounded-lg border border-indigo-400 bg-indigo-700 px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-white transition-all hover:bg-indigo-800">
							Get Started
						</Link>
					</template>
				</div>
			</div>
		</nav>

		<!-- Main Content + Sidebar Container -->
		<div class="flex flex-1">
			<!-- Left: Main Content Area -->
			<div class="flex-1 overflow-y-auto">
				<div class="w-full px-6 py-10">
					<!-- Step Header -->
					<header class="mb-8 max-w-6xl mx-auto">
						<div class="mb-4">
							<h1 class="text-3xl font-bold text-slate-900">{{ wizard.currentStepData.name }}</h1>
							<p class="text-slate-500 mt-2">{{ wizard.currentStepData.description }}</p>
						</div>
						<div class="w-full bg-slate-200 rounded-full h-2">
							<div 
								class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
								:style="{ width: `${(wizard.currentStep / wizard.totalSteps) * 100}%` }"
							></div>
						</div>
					</header>

					<div class="max-w-6xl mx-auto">
						<form @submit.prevent="saveRubric(true)">
							<!-- Step 1: Rubric Setup -->
							<div v-show="wizard.currentStep === 1" class="animate-fade-in">
								<RubricSetupStep @ai-suggest="getAIRubricSuggestion" />
							</div>

							<!-- Step 2: Question Prompt -->
							<div v-show="wizard.currentStep === 2" class="animate-fade-in">
								<QuestionPromptStep />
							</div>

							<!-- Step 3: Review & Publish -->
							<div v-show="wizard.currentStep === 3" class="animate-fade-in">
								<ReviewPublishStep />
							</div>

							<!-- Step 4: Student Response -->
							<div v-show="wizard.currentStep === 4" class="animate-fade-in">
								<StudentResponseStep />
							</div>

							<!-- Step 5: AI Assessment -->
							<div v-show="wizard.currentStep === 5" class="animate-fade-in">
								<AssessmentViewStep />
							</div>

							<!-- Navigation -->
							<WizardNavigation 
								@save-draft="saveRubric(false)"
								@publish="saveRubric(true)"
							/>
						</form>
					</div>
				</div>
			</div>

			<!-- Right: Sidebar -->
			<WizardSidebar />
		</div>

		<!-- Modal -->
		<WizardModal />
	</div>
</template>


<style scoped>
@keyframes fade-in {
	from {
		opacity: 0;
		transform: translateY(10px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

@keyframes spin {
	from {
		transform: rotate(0deg);
	}
	to {
		transform: rotate(360deg);
	}
}

.animate-fade-in {
	animation: fade-in 0.3s ease-out;
}

.animate-spin {
	animation: spin 1s linear infinite;
}
</style>
