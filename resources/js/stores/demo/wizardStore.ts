import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export interface Level {
	id: string;
	name: string;
	range: string;
}

export interface CriterionCell {
	id: string;
	name: string;
	cells: string[];
}

export const useWizardStore = defineStore('wizard', () => {
	// Wizard state
	const currentStep = ref(1);
	const totalSteps = 5;

	const steps = [
		{ id: 1, name: 'Rubric Setup', description: 'Create title and rubric matrix', section: 'Teacher' },
		{ id: 2, name: 'Question Prompt', description: 'Enter the assignment question', section: 'Teacher' },
		{ id: 3, name: 'Review & Publish', description: 'Finalize and publish your rubric', section: 'Teacher' },
		{ id: 4, name: 'Student Response', description: 'Simulate a student submitting an answer', section: 'Student' },
		{ id: 5, name: 'AI Assessment', description: 'Review AI-generated scoring and feedback', section: 'Assessment' },
	];

	// Modal state
	const isModalOpen = ref(false);
	const modalTitle = ref('');
	const modalMessage = ref('');
	const modalType = ref<'error' | 'success' | 'info'>('info');

	// Rubric editor state
	const title = ref('');
	const question = ref('');
	const levels = ref<Level[]>([
		{ id: 'lvl-0', name: 'Needs Imp.', range: '0-4' },
		{ id: 'lvl-1', name: 'Satisfactory', range: '5-6' },
		{ id: 'lvl-2', name: 'Good', range: '7-8' },
		{ id: 'lvl-3', name: 'Excellent', range: '9-10' },
	]);

	const criteria = ref<CriterionCell[]>([
		{ id: 'c-idea', name: 'Idea explanation', cells: ['Little or no explanation', 'Ideas somewhat explained', 'Ideas explained', 'Thoroughly explained ideas'] },
		{ id: 'c-coherency', name: 'Coherency', cells: ['Lacks coherency', 'Somewhat coherent', 'Coherent writing', 'Extremely coherent writing'] },
		{ id: 'c-grammar', name: 'Grammar', cells: ['Many errors that hurt understanding', 'Many errors', 'Some errors', 'Few errors'] },
	]);

	// Step 4: Student Response state
	const studentAnswer = ref('');
	const sampleAnswers = {
		'narrative-essay': `The old oak tree in our backyard has stood for over fifty years, witnessing countless moments of our family's history. I remember climbing its branches as a child, feeling invincible among the rustling leaves. My grandmother would sit on the porch, watching me with a gentle smile, occasionally calling out warnings about being too adventurous. That tree represented freedom and security in equal measure—a place where I could escape the complexities of the world while remaining safely within sight of home.

As I grew older, the tree became a symbol of permanence in an ever-changing world. Through high school stress and college anxieties, the tree remained, its roots deep and unshakeable. When I returned home after my first year away, the tree was the first thing I sought out. Sitting beneath its canopy, I felt the weight of change and growth settle into perspective. The tree had aged, just as I had, and yet there was a quiet wisdom in its stillness.

Today, when I stand before that tree, I see not just wood and leaves, but a repository of memories and a monument to the passage of time. It stands as a testament to what endures—not despite change, but alongside it. The essay of that oak tree is my own story written in the language of seasons and growth.`,
	};

	// Step 5: AI Assessment state
	const aiScores = ref<{ [key: string]: number }>({
		'c-idea': 3,
		'c-coherency': 3,
		'c-grammar': 2,
	});

	const aiFeedback = ref<{ [key: string]: string }>({
		'c-idea': 'The essay demonstrates clear and insightful ideas with good supporting evidence. The student effectively uses personal narrative to explore the theme.',
		'c-coherency': 'The writing flows smoothly from one paragraph to the next. Ideas are well-connected and the central theme remains consistent throughout.',
		'c-grammar': 'While generally well-written, there are a few minor grammatical inconsistencies that could be refined with proofreading.',
	});

	// AI Suggestion loading state
	const isLoadingAI = ref(false);

	// Modal methods
	const showModal = (modalTitleArg: string, message: string, type: 'error' | 'success' | 'info' = 'info') => {
		modalTitle.value = modalTitleArg;
		modalMessage.value = message;
		modalType.value = type;
		isModalOpen.value = true;
	};

	const closeModal = () => {
		isModalOpen.value = false;
	};

	// Rubric management methods
	const addCriteria = () => {
		const id = `c-${Date.now()}`;
		criteria.value.push({ id, name: 'New Criteria', cells: Array(levels.value.length).fill('Description...') });
	};

	const addLevel = () => {
		const id = `lvl-${Date.now()}`;
		levels.value.push({ id, name: 'New Level', range: '0' });
		for (const c of criteria.value) c.cells.push('Description...');
	};

	const deleteCriteria = (idx: number) => {
		criteria.value.splice(idx, 1);
	};

	const deleteLevel = (idx: number) => {
		levels.value.splice(idx, 1);
		for (const c of criteria.value) c.cells.splice(idx, 1);
	};

	// Validation methods
	const validateStep = (step: number): boolean => {
		if (step === 1) {
			if (!title.value.trim()) {
				showModal('Missing Title', 'Please enter a title for your rubric.', 'error');
				return false;
			}
			if (levels.value.length === 0) {
				showModal('No Levels', 'Please add at least one performance level.', 'error');
				return false;
			}
			if (criteria.value.length === 0) {
				showModal('No Criteria', 'Please add at least one rubric criterion.', 'error');
				return false;
			}
			for (let i = 0; i < criteria.value.length; i++) {
				const c = criteria.value[i];
				if (!c.name.trim()) {
					showModal('Incomplete Criteria', `Criteria ${i + 1}: Please enter a name.`, 'error');
					return false;
				}
				for (let j = 0; j < c.cells.length; j++) {
					if (!c.cells[j].trim()) {
						showModal('Empty Cell', `Criteria "${c.name}", Level ${j + 1}: Please fill in the cell description.`, 'error');
						return false;
					}
				}
			}
			return true;
		} else if (step === 2) {
			if (!question.value.trim()) {
				showModal('Missing Question', 'Please enter an assignment question or prompt.', 'error');
				return false;
			}
			return true;
		} else if (step === 4) {
			if (!studentAnswer.value.trim()) {
				showModal('Missing Answer', 'Please enter a student response.', 'error');
				return false;
			}
			return true;
		}
		return true;
	};

	// Navigation methods
	const nextStep = () => {
		if (validateStep(currentStep.value)) {
			if (currentStep.value < totalSteps) {
				currentStep.value++;
			}
		}
	};

	const prevStep = () => {
		if (currentStep.value > 1) {
			currentStep.value--;
		}
	};

	const goToStep = (step: number) => {
		if (step <= currentStep.value) {
			currentStep.value = step;
		}
	};

	// Reset form
	const resetForm = () => {
		title.value = '';
		question.value = '';
		studentAnswer.value = '';
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
		currentStep.value = 1;
	};

	const currentStepData = computed(() => steps[currentStep.value - 1]);

	return {
		// State
		currentStep,
		totalSteps,
		steps,
		isModalOpen,
		modalTitle,
		modalMessage,
		modalType,
		title,
		question,
		levels,
		criteria,
		studentAnswer,
		sampleAnswers,
		aiScores,
		aiFeedback,
		isLoadingAI,
		currentStepData,
		// Methods
		showModal,
		closeModal,
		addCriteria,
		addLevel,
		deleteCriteria,
		deleteLevel,
		validateStep,
		nextStep,
		prevStep,
		goToStep,
		resetForm,
	};
});
