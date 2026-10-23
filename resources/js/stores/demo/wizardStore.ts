import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';

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

export type WizardMode = 'demo' | 'assignment';

export interface WizardStep {
    id: number;
    name: string;
    description: string;
    section: 'Teacher' | 'Student' | 'Assessment';
}

export interface AssignmentQuestion {
    id: string;
    prompt: string;
}

export interface QuestionGradeScore {
    criterion_name: string | null;
    score: number | string | null;
    feedback: string | null;
}

export interface QuestionGrade {
    question_id: number | null;
    prompt: string | null;
    response: string;
    score: number | string | null;
    max_score: number | null;
    overall_feedback: string | null;
    scores: QuestionGradeScore[];
}

export interface WizardState {
    currentStep: number;
    title: string;
    question: string;
    questions?: AssignmentQuestion[];
    studentAnswer?: string;
    studentAnswers?: { [key: string]: string };
    questionGrades?: QuestionGrade[];
    levels: Level[];
    criteria: CriterionCell[];
    aiScores: { [key: string]: number };
    aiFeedback: { [key: string]: string };
    assignment_id: number | null;
    demo_id: number | null;
    submission_id: number | null;
    maxScore: number | null;
    assessmentComplete: boolean;
}

export const useWizardStore = defineStore('wizard', () => {
    // Session ID for persistence
    const sessionId = ref<string | null>(null);

    // Database references
    const assignment_id = ref<number | null>(null);
    const demo_id = ref<number | null>(null);
    const submission_id = ref<number | null>(null);
    const maxScore = ref<number | null>(null);

    // Assessment tracking - once true, all steps remain navigatable
    const assessmentComplete = ref(false);

    // Wizard state
    const mode = ref<WizardMode>('demo');
    const currentStep = ref(1);
    const isSaving = ref(false);

    const demoSteps: WizardStep[] = [
        {
            id: 1,
            name: 'Rubric Setup',
            description: 'Create title and rubric matrix',
            section: 'Teacher',
        },
        {
            id: 2,
            name: 'Question Prompt',
            description: 'Enter one or more assignment questions',
            section: 'Teacher',
        },
        {
            id: 3,
            name: 'Review & Publish',
            description: 'Finalize and publish your rubric',
            section: 'Teacher',
        },
        {
            id: 4,
            name: 'Student Response',
            description: 'Simulate a student submitting an answer',
            section: 'Student',
        },
        {
            id: 5,
            name: 'AI Assessment',
            description: 'Review AI-generated scoring and feedback',
            section: 'Assessment',
        },
    ];

    const assignmentSteps: WizardStep[] = [
        {
            id: 1,
            name: 'Rubric Setup',
            description: 'Create title and rubric matrix',
            section: 'Teacher',
        },
        {
            id: 2,
            name: 'Question Prompt',
            description: 'Enter one or more assignment questions',
            section: 'Teacher',
        },
        {
            id: 3,
            name: 'Review & Create',
            description: 'Confirm your rubric and create the assignment',
            section: 'Teacher',
        },
    ];

    const steps = computed(() => (mode.value === 'assignment' ? assignmentSteps : demoSteps));
    const totalSteps = computed(() => steps.value.length);

    const setMode = (newMode: WizardMode) => {
        mode.value = newMode;
        currentStep.value = 1;
    };

    // Modal state
    const isModalOpen = ref(false);
    const modalTitle = ref('');
    const modalMessage = ref('');
    const modalType = ref<'error' | 'success' | 'info'>('info');

    // Rubric editor state
    const title = ref('');
    const questions = ref<AssignmentQuestion[]>([{ id: 'q-0', prompt: '' }]);
    const question = computed(() =>
        questions.value
            .map((item) => item.prompt.trim())
            .filter(Boolean)
            .join('\n\n'),
    );
    const levels = ref<Level[]>([
        { id: 'lvl-0', name: 'Needs Imp.', range: '0-4' },
        { id: 'lvl-1', name: 'Satisfactory', range: '5-6' },
        { id: 'lvl-2', name: 'Good', range: '7-8' },
        { id: 'lvl-3', name: 'Excellent', range: '9-10' },
    ]);

    const criteria = ref<CriterionCell[]>([
        {
            id: 'c-idea',
            name: 'Idea explanation',
            cells: [
                'Little or no explanation',
                'Ideas somewhat explained',
                'Ideas explained',
                'Thoroughly explained ideas',
            ],
        },
        {
            id: 'c-coherency',
            name: 'Coherency',
            cells: [
                'Lacks coherency',
                'Somewhat coherent',
                'Coherent writing',
                'Extremely coherent writing',
            ],
        },
        {
            id: 'c-grammar',
            name: 'Grammar',
            cells: [
                'Many errors that hurt understanding',
                'Many errors',
                'Some errors',
                'Few errors',
            ],
        },
    ]);

    // Step 4: Student Response state
    const studentAnswers = ref<{ [key: string]: string }>({});
    const questionGrades = ref<QuestionGrade[]>([]);
    const studentAnswer = computed(() =>
        questions.value
            .map((item) => (studentAnswers.value[item.id] ?? '').trim())
            .filter(Boolean)
            .join('\n\n'),
    );
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
        'c-idea':
            'The essay demonstrates clear and insightful ideas with good supporting evidence. The student effectively uses personal narrative to explore the theme.',
        'c-coherency':
            'The writing flows smoothly from one paragraph to the next. Ideas are well-connected and the central theme remains consistent throughout.',
        'c-grammar':
            'While generally well-written, there are a few minor grammatical inconsistencies that could be refined with proofreading.',
    });

    // AI Suggestion loading state
    const isLoadingAI = ref(false);

    // Assessment submission loading state
    const isSubmittingAssessment = ref(false);

    // Modal methods
    const showModal = (
        modalTitleArg: string,
        message: string,
        type: 'error' | 'success' | 'info' = 'info',
    ) => {
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
        criteria.value.push({
            id,
            name: 'New Criteria',
            cells: Array(levels.value.length).fill('Description...'),
        });
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

    const addQuestion = () => {
        questions.value.push({
            id: `q-${Date.now()}`,
            prompt: '',
        });
    };

    const deleteQuestion = (idx: number) => {
        if (questions.value.length <= 1) {
            return;
        }

        const [removed] = questions.value.splice(idx, 1);
        if (removed) {
            const nextAnswers = { ...studentAnswers.value };
            delete nextAnswers[removed.id];
            studentAnswers.value = nextAnswers;
        }
    };

    const syncPersistedQuestions = (persisted: { id: number; prompt: string }[]) => {
        const previous = questions.value.map((question) => ({
            id: question.id,
            answer: studentAnswers.value[question.id] ?? '',
        }));

        questions.value = persisted.map((question) => ({
            id: String(question.id),
            prompt: question.prompt,
        }));

        const nextAnswers: { [key: string]: string } = {};
        questions.value.forEach((question, index) => {
            nextAnswers[question.id] = previous[index]?.answer ?? '';
        });
        studentAnswers.value = nextAnswers;
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
                    showModal(
                        'Incomplete Criteria',
                        `Criteria ${i + 1}: Please enter a name.`,
                        'error',
                    );
                    return false;
                }
                for (let j = 0; j < c.cells.length; j++) {
                    if (!c.cells[j].trim()) {
                        showModal(
                            'Empty Cell',
                            `Criteria "${c.name}", Level ${j + 1}: Please fill in the cell description.`,
                            'error',
                        );
                        return false;
                    }
                }
            }
            return true;
        } else if (step === 2) {
            if (questions.value.length === 0) {
                showModal(
                    'Missing Question',
                    'Please add at least one assignment question or prompt.',
                    'error',
                );
                return false;
            }

            for (let i = 0; i < questions.value.length; i++) {
                if (!questions.value[i].prompt.trim()) {
                    showModal(
                        'Incomplete Question',
                        `Question ${i + 1}: Please enter a question or prompt.`,
                        'error',
                    );
                    return false;
                }
            }

            return true;
        } else if (step === 4) {
            for (let i = 0; i < questions.value.length; i++) {
                const question = questions.value[i];
                if (!(studentAnswers.value[question.id] ?? '').trim()) {
                    showModal(
                        'Missing Answer',
                        `Please enter a response for question ${i + 1}.`,
                        'error',
                    );
                    return false;
                }
            }
            return true;
        }
        return true;
    };

    // Navigation methods
    const nextStep = () => {
        if (validateStep(currentStep.value)) {
            if (currentStep.value < totalSteps.value) {
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
        // If assessment is complete, allow full navigation between all steps
        // Otherwise, only allow backward navigation (to steps already visited)
        if (assessmentComplete.value || step <= currentStep.value) {
            currentStep.value = step;
        }
    };

    // Reset form
    const resetForm = () => {
        title.value = '';
        questions.value = [{ id: 'q-0', prompt: '' }];
        studentAnswers.value = {};
        questionGrades.value = [];
        levels.value = [
            { id: 'lvl-0', name: 'Needs Imp.', range: '0-4' },
            { id: 'lvl-1', name: 'Satisfactory', range: '5-6' },
            { id: 'lvl-2', name: 'Good', range: '7-8' },
            { id: 'lvl-3', name: 'Excellent', range: '9-10' },
        ];
        criteria.value = [
            {
                id: 'c-idea',
                name: 'Idea explanation',
                cells: [
                    'Little or no explanation',
                    'Ideas somewhat explained',
                    'Ideas explained',
                    'Thoroughly explained ideas',
                ],
            },
            {
                id: 'c-coherency',
                name: 'Coherency',
                cells: [
                    'Lacks coherency',
                    'Somewhat coherent',
                    'Coherent writing',
                    'Extremely coherent writing',
                ],
            },
            {
                id: 'c-grammar',
                name: 'Grammar',
                cells: [
                    'Many errors that hurt understanding',
                    'Many errors',
                    'Some errors',
                    'Few errors',
                ],
            },
        ];
        assignment_id.value = null;
        demo_id.value = null;
        submission_id.value = null;
        maxScore.value = null;
        assessmentComplete.value = false;
        currentStep.value = 1;
    };

    const currentStepData = computed(() => steps.value[currentStep.value - 1]);

    // Set assessment results from AI grading
    const setAssessmentResults = (
        scores: Array<{
            criterion_name: string;
            score: number;
            feedback: string;
            question_id?: number | null;
        }>,
        maxScoreFromAPI?: number,
        grades: QuestionGrade[] = [],
    ) => {
        const scoresMap: { [key: string]: number } = {};
        const feedbackMap: { [key: string]: string } = {};

        scores.forEach((item) => {
            const criterion = criteria.value.find((c) => c.name === item.criterion_name);
            if (criterion) {
                scoresMap[criterion.id] = (scoresMap[criterion.id] || 0) + Number(item.score);
                feedbackMap[criterion.id] = [feedbackMap[criterion.id], item.feedback]
                    .filter(Boolean)
                    .join('\n\n');
            }
        });

        aiScores.value = scoresMap;
        aiFeedback.value = feedbackMap;
        questionGrades.value = grades;
        if (maxScoreFromAPI !== undefined) {
            maxScore.value = maxScoreFromAPI;
        }

        assessmentComplete.value = true;
        saveState();
    };

    // Persistence methods
    const saveState = () => {
        if (!sessionId.value) return;

        const state: WizardState = {
            currentStep: currentStep.value,
            title: title.value,
            question: question.value,
            questions: questions.value,
            studentAnswer: studentAnswer.value,
            studentAnswers: studentAnswers.value,
            questionGrades: questionGrades.value,
            levels: levels.value,
            criteria: criteria.value,
            aiScores: aiScores.value,
            aiFeedback: aiFeedback.value,
            assignment_id: assignment_id.value,
            demo_id: demo_id.value,
            submission_id: submission_id.value,
            maxScore: maxScore.value,
            assessmentComplete: assessmentComplete.value,
        };

        try {
            localStorage.setItem(`wizard-session-${sessionId.value}`, JSON.stringify(state));
        } catch (e) {
            console.error('Failed to save wizard state:', e);
        }
    };

    const loadState = (newSessionId: string) => {
        sessionId.value = newSessionId;

        try {
            const saved = localStorage.getItem(`wizard-session-${newSessionId}`);
            if (saved) {
                const state = JSON.parse(saved) as WizardState;
                currentStep.value = state.currentStep;
                title.value = state.title;
                if (Array.isArray(state.questions) && state.questions.length > 0) {
                    questions.value = state.questions;
                } else {
                    questions.value = [
                        {
                            id: 'q-0',
                            prompt: state.question || '',
                        },
                    ];
                }
                if (state.studentAnswers && Object.keys(state.studentAnswers).length > 0) {
                    studentAnswers.value = state.studentAnswers;
                } else if (state.studentAnswer) {
                    const firstQuestion = questions.value[0];
                    studentAnswers.value = firstQuestion
                        ? { [firstQuestion.id]: state.studentAnswer }
                        : {};
                } else {
                    studentAnswers.value = {};
                }
                questionGrades.value = state.questionGrades ?? [];
                levels.value = state.levels;
                criteria.value = state.criteria;
                aiScores.value = state.aiScores;
                aiFeedback.value = state.aiFeedback;
                assignment_id.value = state.assignment_id || null;
                demo_id.value = state.demo_id || null;
                submission_id.value = state.submission_id || null;
                maxScore.value = state.maxScore || null;
                assessmentComplete.value = state.assessmentComplete || false;
            }
        } catch (e) {
            console.error('Failed to load wizard state:', e);
        }
    };

    // Watch for state changes and auto-save
    watch(
        () => [
            currentStep.value,
            title.value,
            questions.value,
            studentAnswers.value,
            levels.value,
            criteria.value,
        ],
        () => {
            saveState();
        },
        { deep: true },
    );

    return {
        // State
        sessionId,
        mode,
        currentStep,
        totalSteps,
        steps,
        isSaving,
        isModalOpen,
        modalTitle,
        maxScore,
        modalMessage,
        modalType,
        title,
        question,
        questions,
        levels,
        criteria,
        studentAnswer,
        studentAnswers,
        questionGrades,
        sampleAnswers,
        aiScores,
        aiFeedback,
        isLoadingAI,
        isSubmittingAssessment,
        currentStepData,
        assignment_id,
        demo_id,
        submission_id,
        assessmentComplete,
        // Methods
        showModal,
        closeModal,
        addCriteria,
        addLevel,
        deleteCriteria,
        deleteLevel,
        addQuestion,
        deleteQuestion,
        syncPersistedQuestions,
        validateStep,
        nextStep,
        prevStep,
        goToStep,
        setMode,
        resetForm,
        setAssessmentResults,
        saveState,
        loadState,
    };
});
