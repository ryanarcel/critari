<template>
    <div class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow">
        <div
            class="flex flex-col gap-3 border-b bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6"
        >
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-sm font-semibold text-slate-700">Rubric Matrix</div>
                <span class="text-xs text-slate-500">Manage levels and criteria</span>
            </div>
            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-200 active:scale-95"
                @click.prevent="wizard.addLevel"
            >
                <PlusIcon class="h-4 w-4 text-slate-600" />
                <span class="font-medium">Add Level</span>
            </button>
        </div>

        <div class="p-4 sm:p-6">
            <p class="mb-3 text-xs text-slate-500 lg:hidden">
                Swipe sideways to see every level.
            </p>
            <div class="overflow-x-auto">
                <table
                    :class="
                        wizard.levels.length >= 6
                            ? 'min-w-[1200px] table-fixed border-collapse'
                            : 'min-w-[640px] w-full table-fixed border-collapse sm:min-w-0'
                    "
                    class="bg-white"
                >
                    <thead>
                        <tr class="bg-slate-100">
                            <th
                                class="w-1/4 p-4 border border-slate-200 text-left text-sm font-semibold text-slate-700"
                            >
                                CRITERIA \ LEVELS
                            </th>
                            <th
                                v-for="(lvl, li) in wizard.levels"
                                :key="lvl.id"
                                class="p-3 border text-center"
                            >
                                <div class="flex flex-col items-center gap-1">
                                    <input
                                        v-model="lvl.name"
                                        class="w-full text-center rounded-lg border border-slate-200 bg-slate-50 text-sm font-semibold p-2 transition hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                                    />
                                    <input
                                        v-model="lvl.range"
                                        class="w-full text-center text-xs text-slate-500 bg-transparent p-1"
                                    />
                                    <div class="mt-2">
                                        <button
                                            type="button"
                                            class="text-slate-500 p-1 hover:bg-red-50 rounded-md"
                                            @click.prevent="wizard.deleteLevel(li)"
                                        >
                                            <XMarkIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(c, ci) in wizard.criteria"
                            :key="c.id"
                            class="align-top odd:bg-white even:bg-slate-50 border-b hover:bg-slate-50 transition-colors duration-150"
                        >
                            <td class="p-4 align-top">
                                <div class="flex items-start justify-between">
                                    <div class="w-full">
                                        <input
                                            v-model="c.name"
                                            class="w-full font-medium rounded-lg border border-slate-200 bg-slate-50 p-2 text-sm transition hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <div class="ml-3">
                                        <button
                                            type="button"
                                            class="text-slate-500 p-1 hover:bg-red-50 rounded-md"
                                            @click.prevent="wizard.deleteCriteria(ci)"
                                        >
                                            <XMarkIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td
                                v-for="(cell, k) in c.cells"
                                :key="k"
                                class="p-3 align-top border-l border-slate-200"
                            >
                                <textarea
                                    v-autogrow
                                    v-model="c.cells[k]"
                                    @input="autoGrowTextarea"
                                    class="w-full border border-slate-200 rounded-lg bg-slate-50 p-3 text-sm resize-none transition hover:border-slate-300 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 scrollbar-hide hover:scrollbar-show"
                                    rows="1"
                                    placeholder="Describe performance at this level..."
                                ></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    @click.prevent="wizard.addCriteria"
                >
                    <PlusIcon class="h-4 w-4" />
                    <span>Add Criteria (Row)</span>
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-indigo-500 bg-neutral-100 px-4 py-2 text-sm font-medium text-indigo-600 transition duration-150 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="wizard.isLoadingAI"
                    @click.prevent="emit('ai-suggest')"
                >
                    <div
                        v-if="wizard.isLoadingAI"
                        class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"
                    ></div>
                    <LightBulbIcon v-else class="w-4 h-4" />
                    <span>{{ wizard.isLoadingAI ? 'Generating...' : 'AI Suggest Criteria' }}</span>
                </button>
            </div>
        </div>

        <!-- Summary stats -->
        <div class="border-t bg-slate-50 p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 text-sm text-slate-700">
                    <div class="bg-white p-2 rounded-full border border-slate-200">
                        <CheckIcon class="w-4 h-4 text-indigo-600" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-slate-600">Total Criteria</div>
                        <div class="text-sm font-semibold text-slate-800">
                            {{ wizard.criteria.length }} rows
                        </div>
                    </div>
                </div>
                <div class="text-sm text-slate-700">
                    <div class="text-xs font-medium text-slate-600">Max Rubric Score</div>
                    <div class="text-sm font-semibold text-slate-800">
                        {{ getMaxScorePerCriterion() * wizard.criteria.length }} pts
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { CheckIcon, XMarkIcon, PlusIcon, LightBulbIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';
import { Directive } from 'vue';

const wizard = useWizardStore();

const emit = defineEmits<{
    'ai-suggest': [];
}>();

// Auto-grow directive for textareas
const vAutogrow: Directive = {
    mounted(el: HTMLTextAreaElement) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    },
    updated(el: HTMLTextAreaElement) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    },
};

// Auto-grow event handler
const autoGrowTextarea = (event: Event) => {
    const textarea = event.target as HTMLTextAreaElement;
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
};

// Calculate max score per criterion from the highest level's range
const getMaxScorePerCriterion = () => {
    if (wizard.levels.length === 0) return 10;
    const lastLevel = wizard.levels[wizard.levels.length - 1];
    const range = lastLevel.range || '0-10';
    const parts = range.split('-');
    return parseInt(parts[1] || '10');
};
</script>

<style scoped>
/* Scrollbar styling - hidden by default, visible on hover */
.scrollbar-hide::-webkit-scrollbar {
    width: 8px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.scrollbar-hide::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-hide::-webkit-scrollbar-thumb {
    background-color: rgba(148, 163, 184, 0.5);
    border-radius: 4px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.scrollbar-hide:hover::-webkit-scrollbar-thumb {
    opacity: 1;
}

.scrollbar-hide::-webkit-scrollbar-thumb:hover {
    background-color: rgba(100, 116, 139, 0.8);
}

/* Firefox support */
.scrollbar-hide {
    scrollbar-color: transparent transparent;
}

.scrollbar-hide:hover {
    scrollbar-color: rgba(148, 163, 184, 0.5) transparent;
}
</style>
