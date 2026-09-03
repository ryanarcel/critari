<template>
    <div class="bg-white border border-slate-100 rounded-xl p-8 shadow-sm">
        <div class="space-y-6">
            <div>
                <label for="question" class="block text-sm font-semibold text-slate-700 mb-3"
                    >Assignment Question</label
                >
                <div class="relative">
                    <textarea
                        id="question"
                        v-model="wizard.question"
                        rows="8"
                        placeholder="Write the essay question or assignment prompt here..."
                        class="block w-full rounded-lg border border-slate-200 bg-slate-50 shadow-sm sm:text-sm p-4 text-slate-700 transition-colors duration-150 hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                    ></textarea>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    Enter the full question or prompt that students will be responding to.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useWizardStore } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();
</script>
