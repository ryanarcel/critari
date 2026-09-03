<template>
    <div class="space-y-6">
        <!-- Title Section -->
        <div class="bg-white border border-slate-100 rounded-xl p-8 shadow-sm">
            <div class="space-y-6">
                <div>
                    <label for="title" class="block text-sm font-semibold text-slate-700 mb-3"
                        >Rubric Title</label
                    >
                    <div class="relative">
                        <input
                            id="title"
                            v-model="wizard.title"
                            placeholder="e.g. Narrative Essay - Midterm 2026"
                            class="block w-full rounded-lg border border-slate-200 bg-slate-50 shadow-sm sm:text-sm p-4 pr-10 text-slate-700 transition-colors duration-150 hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                        />
                        <div
                            class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400"
                        >
                            <PencilIcon class="w-5 h-5" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Give your rubric a descriptive title that clearly identifies the assignment.
                        (e.g., Narrative Essay, Evidence-based Essay)
                    </p>
                </div>
            </div>
        </div>

        <!-- Rubric Matrix -->
        <RubricMatrix @ai-suggest="emit('ai-suggest')" />
    </div>
</template>

<script setup lang="ts">
import { PencilIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';
import RubricMatrix from '../RubricMatrix.vue';

const wizard = useWizardStore();

const emit = defineEmits<{
    'ai-suggest': [];
}>();
</script>
