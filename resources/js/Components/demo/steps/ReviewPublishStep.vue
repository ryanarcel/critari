<template>
    <div class="bg-white border border-slate-100 rounded-xl p-12 shadow-sm text-center">
        <div class="space-y-4">
            <div
                class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto"
            >
                <CheckCircleIcon class="w-10 h-10 text-green-600" />
            </div>
            <h2 class="text-2xl font-bold text-slate-900">Rubric Published</h2>
            <p class="text-slate-600">
                Your rubric has been successfully published and is ready for student submissions.
            </p>
            <div class="pt-4">
                <p class="text-sm text-slate-500">
                    Proceed to the next step to collect student responses.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { CheckCircleIcon } from '@heroicons/vue/24/outline';
</script>
