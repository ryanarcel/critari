<template>
    <div class="space-y-6">
        <section
            v-for="(item, index) in wizard.questions"
            :key="item.id"
            class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm sm:p-8"
        >
            <label
                :for="`student-answer-${item.id}`"
                class="block text-sm font-semibold text-slate-700"
            >
                {{ index + 1 }}. {{ item.prompt }}
            </label>
            <textarea
                :id="`student-answer-${item.id}`"
                v-model="wizard.studentAnswers[item.id]"
                rows="10"
                placeholder="Write your answer..."
                class="mt-4 block w-full rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 shadow-sm transition-colors duration-150 hover:border-slate-300 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            ></textarea>
            <p class="mt-2 text-xs text-slate-500">
                Enter a complete response for this question. Each answer is graded on its own.
            </p>
        </section>
    </div>
</template>

<script setup lang="ts">
import { useWizardStore } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();
</script>
