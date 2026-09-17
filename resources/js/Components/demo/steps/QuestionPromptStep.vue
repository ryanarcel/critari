<template>
    <div class="space-y-4">
        <div
            v-for="(item, index) in wizard.questions"
            :key="item.id"
            class="bg-white border border-slate-100 rounded-xl p-8 shadow-sm"
        >
            <div class="mb-3 flex items-center justify-between gap-3">
                <label
                    :for="`question-${item.id}`"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Question {{ index + 1 }}
                </label>
                <button
                    v-if="wizard.questions.length > 1"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md p-1 text-slate-500 transition hover:bg-red-50 hover:text-red-600"
                    @click.prevent="wizard.deleteQuestion(index)"
                >
                    <XMarkIcon class="h-4 w-4" />
                    <span class="text-xs font-medium">Remove</span>
                </button>
            </div>
            <textarea
                :id="`question-${item.id}`"
                v-model="item.prompt"
                rows="6"
                placeholder="Write the essay question or assignment prompt here..."
                class="block w-full rounded-lg border border-slate-200 bg-slate-50 p-4 text-slate-700 shadow-sm transition-colors duration-150 hover:border-slate-300 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:text-sm"
            ></textarea>
        </div>

        <div class="flex items-center justify-between">
            <p class="text-xs text-slate-500">
                Students will see every question on this assignment.
            </p>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200"
                @click.prevent="wizard.addQuestion"
            >
                <PlusIcon class="h-4 w-4 text-slate-600" />
                Add question
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();
</script>
