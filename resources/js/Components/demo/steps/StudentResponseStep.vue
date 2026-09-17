<template>
    <div class="space-y-6">
        <!-- Question Display -->
        <div class="bg-white border border-slate-100 rounded-xl p-8 shadow-sm">
            <div class="space-y-4">
                <div>
                    <h3 class="mb-3 text-sm font-semibold text-slate-700">
                        Assignment Question{{ wizard.questions.length > 1 ? 's' : '' }}
                    </h3>
                    <ol class="list-decimal space-y-3 pl-5">
                        <li
                            v-for="item in wizard.questions.filter((question) =>
                                question.prompt.trim(),
                            )"
                            :key="item.id"
                            class="text-slate-700"
                        >
                            <p
                                class="whitespace-pre-wrap rounded-lg border border-slate-200 bg-slate-50 p-4"
                            >
                                {{ item.prompt }}
                            </p>
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Student Answer Input -->
        <div class="bg-white border border-slate-100 rounded-xl p-8 shadow-sm">
            <div class="space-y-4">
                <div>
                    <label
                        for="studentAnswer"
                        class="block text-sm font-semibold text-slate-700 mb-3"
                        >Your Answer</label
                    >
                    <textarea
                        id="studentAnswer"
                        v-model="wizard.studentAnswer"
                        rows="12"
                        placeholder="Write your essay response here..."
                        class="block w-full rounded-lg border border-slate-200 bg-slate-50 shadow-sm sm:text-sm p-4 text-slate-700 transition-colors duration-150 hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                    ></textarea>
                    <p class="mt-2 text-xs text-slate-500">
                        Enter your complete essay response or written answer to the prompt{{
                            wizard.questions.length > 1 ? 's' : ''
                        }}
                        above.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useWizardStore } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();
</script>
