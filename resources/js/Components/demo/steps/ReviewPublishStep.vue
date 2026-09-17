<template>
    <div class="bg-white border border-slate-100 rounded-xl p-12 shadow-sm text-center">
        <div class="space-y-4">
            <div
                class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto"
            >
                <CheckCircleIcon class="w-10 h-10 text-green-600" />
            </div>
            <h2 class="text-2xl font-bold text-slate-900">{{ title }}</h2>
            <p class="text-slate-600">
                {{ message }}
            </p>
            <div v-if="showSummary" class="mx-auto max-w-lg rounded-lg bg-slate-50 p-4 text-left">
                <p class="text-sm font-semibold text-slate-900">{{ wizard.title }}</p>
                <ol class="mt-3 list-decimal space-y-2 pl-4 text-sm text-slate-600">
                    <li
                        v-for="item in wizard.questions.filter((question) => question.prompt.trim())"
                        :key="item.id"
                    >
                        {{ item.prompt }}
                    </li>
                </ol>
                <p class="mt-3 text-xs text-slate-500">
                    {{ wizard.criteria.length }} criteria · {{ wizard.levels.length }} levels
                </p>
            </div>
            <div class="pt-4">
                <p class="text-sm text-slate-500">
                    {{ hint }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { CheckCircleIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';

withDefaults(
    defineProps<{
        title?: string;
        message?: string;
        hint?: string;
        showSummary?: boolean;
    }>(),
    {
        title: 'Rubric Published',
        message:
            'Your rubric has been successfully published and is ready for student submissions.',
        hint: 'Proceed to the next step to collect student responses.',
        showSummary: false,
    },
);

const wizard = useWizardStore();
</script>
