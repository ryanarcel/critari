<template>
    <div class="grid grid-cols-3 gap-6 h-auto">
        <!-- Left: Student Essay -->
        <div class="col-span-2 space-y-4">
            <div class="bg-white border border-slate-100 rounded-xl p-8 shadow-sm">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-700 mb-4">
                            Student Submission
                        </h3>
                        <div
                            class="bg-slate-50 p-6 rounded-lg border border-slate-200 min-h-96 text-slate-700 whitespace-pre-wrap text-sm leading-relaxed"
                        >
                            {{ wizard.studentAnswer }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Rubric Scores -->
        <div class="col-span-1">
            <div
                class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm sticky top-20 space-y-4"
            >
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 mb-4">Assessment Scores</h3>
                </div>

                <!-- Score Cards -->
                <div class="space-y-3">
                    <div
                        v-for="crit in wizard.criteria"
                        :key="crit.id"
                        class="border border-slate-200 rounded-lg p-4 bg-gradient-to-br from-slate-50 to-white"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-slate-700">
                                    {{ crit.name }}
                                </h4>
                                <div class="flex items-center gap-2">
                                    <span class="text-lg font-bold text-indigo-600">{{
                                        wizard.aiScores[crit.id] || 0
                                    }}</span>
                                    <span class="text-xs text-slate-500"
                                        >/ {{ getMaxScorePerCriterion() }}</span
                                    >
                                </div>
                            </div>
                            <div class="flex gap-1">
                                <div
                                    v-for="(level, idx) in wizard.levels"
                                    :key="level.id"
                                    :class="{
                                        'bg-indigo-600':
                                            (wizard.aiScores[crit.id] || 0) >=
                                            getLevelMinScore(idx),
                                        'bg-slate-200':
                                            (wizard.aiScores[crit.id] || 0) < getLevelMinScore(idx),
                                    }"
                                    class="h-2 flex-1 rounded-full transition-colors"
                                ></div>
                            </div>
                            <p
                                v-if="wizard.aiFeedback[crit.id]"
                                class="text-xs text-slate-600 italic border-l-2 border-indigo-300 pl-3 mt-3"
                            >
                                {{ wizard.aiFeedback[crit.id] }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total Score -->
                <div class="border-t border-slate-200 pt-4 mt-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-700">Total Score</span>
                        <span class="text-2xl font-bold text-indigo-600"
                            >{{ Object.values(wizard.aiScores).reduce((a, b) => a + b, 0) }} /
                            {{ wizard.maxScore }}</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useWizardStore } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();

// Calculate max score per criterion from the highest level's range
const getMaxScorePerCriterion = () => {
    if (wizard.levels.length === 0) return 10;
    const lastLevel = wizard.levels[wizard.levels.length - 1];
    const range = lastLevel.range || '0-10';
    const parts = range.split('-');
    return parseInt(parts[1] || '10');
};

// Get the minimum score for a level (used for progress bar fill)
const getLevelMinScore = (levelIndex: number) => {
    if (levelIndex >= wizard.levels.length) return 0;
    const level = wizard.levels[levelIndex];
    const range = level.range || '0-0';
    const parts = range.split('-');
    return parseInt(parts[0] || '0');
};
</script>
