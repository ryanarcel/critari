<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useWizardStore } from '@/stores/demo/wizardStore';
import WizardSidebar from '@/Components/demo/WizardSidebar.vue';
import WizardModal from '@/Components/demo/WizardModal.vue';
import WizardNavigation from '@/Components/demo/WizardNavigation.vue';
import RubricSetupStep from '@/Components/demo/steps/RubricSetupStep.vue';
import QuestionPromptStep from '@/Components/demo/steps/QuestionPromptStep.vue';
import ReviewPublishStep from '@/Components/demo/steps/ReviewPublishStep.vue';

const page = usePage();
const user = computed(() => (page.props.auth as { user: { name: string } | null })?.user ?? null);
const axios = (window as any).axios;
const wizard = useWizardStore();

wizard.setMode('assignment');
wizard.resetForm();

const saveAssignment = () => {
    if (!wizard.validateStep(1) || !wizard.validateStep(2) || !wizard.validateStep(3)) {
        return;
    }

    wizard.isSaving = true;

    const payload = {
        title: wizard.title,
        question: wizard.question,
        questions: wizard.questions.map((item) => ({ id: item.id, prompt: item.prompt })),
        levels: wizard.levels.slice(),
        criteria: wizard.criteria.map((c) => ({ id: c.id, name: c.name, cells: c.cells.slice() })),
    };

    axios
        .post('/assignments', payload)
        .then((res) => {
            if (res.data.data) {
                wizard.assignment_id = res.data.data.assignment_id;
                wizard.demo_id = res.data.data.demo_id;
            }

            wizard.showModal(
                'Assignment created',
                'Your assignment is on the dashboard. You can add student papers next.',
                'success',
            );

            setTimeout(() => {
                router.visit(route('dashboard'));
            }, 800);
        })
        .catch((err) => {
            wizard.showModal(
                'Save Failed',
                err.response?.data?.message || err.message || 'An error occurred while saving.',
                'error',
            );
        })
        .finally(() => {
            wizard.isSaving = false;
        });
};

const getAIRubricSuggestion = () => {
    const context = wizard.question.trim() || wizard.title;

    if (!context.trim()) {
        wizard.showModal(
            'Missing Context',
            'Please enter a rubric title to generate criteria.',
            'error',
        );
        return;
    }

    wizard.isLoadingAI = true;

    const payload = {
        question: wizard.question.trim() ? wizard.question : null,
        title: !wizard.question.trim() ? wizard.title : null,
        levels: wizard.levels.slice(),
        num_criteria: wizard.criteria.length,
    };

    axios
        .post('/assignments/ai-rubric-suggestion', payload)
        .then((res) => {
            try {
                const data = res.data || {};
                if (data.success && data.rubric && Array.isArray(data.rubric.criteria)) {
                    if (data.levels && Array.isArray(data.levels)) {
                        data.levels.forEach((suggestedLevel, idx) => {
                            if (idx < wizard.levels.length) {
                                wizard.levels[idx].name = suggestedLevel.name;
                                wizard.levels[idx].range = suggestedLevel.range;
                            }
                        });
                    }

                    wizard.criteria = data.rubric.criteria.map((c, idx) => ({
                        id: `c-ai-${Date.now()}-${idx}`,
                        name: c.name || `Criterion ${idx + 1}`,
                        cells: Array.isArray(c.cells)
                            ? c.cells.slice()
                            : Array(wizard.levels.length).fill(''),
                    }));

                    wizard.showModal(
                        'Rubric Generated',
                        'AI has suggested level names and criteria for your rubric. Review and edit as needed.',
                        'success',
                    );
                }
            } catch (e) {
                console.error('Failed to apply AI rubric suggestion', e);
                wizard.showModal(
                    'Generation Failed',
                    'Could not process the AI response. Please try again.',
                    'error',
                );
            }
        })
        .catch((err) => {
            console.error(err);
            wizard.showModal(
                'AI Request Failed',
                err.response?.data?.message || 'Failed to generate criteria. Please try again.',
                'error',
            );
        })
        .finally(() => {
            wizard.isLoadingAI = false;
        });
};
</script>

<template>
    <Head title="Create Assignment" />

    <div class="flex min-h-screen flex-col text-slate-900">
        <nav
            class="sticky top-0 z-50 border-b border-indigo-700 bg-indigo-600 px-6 py-4 backdrop-blur-xl"
        >
            <div class="mx-auto flex max-w-full items-center justify-between">
                <Link
                    :href="route('dashboard')"
                    class="text-xl font-black tracking-tight text-white"
                >
                    critari<span class="text-indigo-200">.</span>
                </Link>

                <div class="flex items-center space-x-4">
                    <Link
                        :href="route('dashboard')"
                        class="text-xs font-bold uppercase tracking-wider text-indigo-100 transition-colors hover:text-white"
                    >
                        Cancel
                    </Link>
                    <span
                        v-if="user"
                        class="text-xs font-bold uppercase tracking-wider text-white"
                    >
                        {{ user.name }}
                    </span>
                </div>
            </div>
        </nav>

        <div class="flex flex-1">
            <div class="flex-1 overflow-y-auto">
                <div class="w-full px-6 py-10">
                    <header class="mx-auto mb-8 max-w-6xl">
                        <div class="mb-4">
                            <h1 class="text-3xl font-bold text-slate-900">
                                {{ wizard.currentStepData.name }}
                            </h1>
                            <p class="mt-2 text-slate-500">
                                {{ wizard.currentStepData.description }}
                            </p>
                        </div>
                        <div class="h-2 w-full rounded-full bg-slate-200">
                            <div
                                class="h-2 rounded-full bg-indigo-600 transition-all duration-300"
                                :style="{
                                    width: `${(wizard.currentStep / wizard.totalSteps) * 100}%`,
                                }"
                            ></div>
                        </div>
                    </header>

                    <div class="mx-auto max-w-6xl">
                        <form @submit.prevent="saveAssignment">
                            <div v-show="wizard.currentStep === 1" class="animate-fade-in">
                                <RubricSetupStep @ai-suggest="getAIRubricSuggestion" />
                            </div>

                            <div v-show="wizard.currentStep === 2" class="animate-fade-in">
                                <QuestionPromptStep />
                            </div>

                            <div v-show="wizard.currentStep === 3" class="animate-fade-in">
                                <ReviewPublishStep
                                    title="Ready to create"
                                    message="Confirm the rubric and prompt, then create this assignment. It will show up on your dashboard."
                                    hint="You can add student papers after this."
                                    :show-summary="true"
                                />
                            </div>

                            <WizardNavigation @publish="saveAssignment" />
                        </form>
                    </div>
                </div>
            </div>

            <WizardSidebar />
        </div>

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

.animate-fade-in {
    animation: fade-in 0.3s ease-out;
}
</style>
