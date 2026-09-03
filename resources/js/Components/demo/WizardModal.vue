<template>
    <Transition
        enterActiveClass="transition ease-out duration-200"
        enterFromClass="opacity-0"
        enterToClass="opacity-100"
        leaveActiveClass="transition ease-in duration-150"
        leaveFromClass="opacity-100"
        leaveToClass="opacity-0"
    >
        <div
            v-if="wizard.isModalOpen"
            class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center"
            @click="wizard.closeModal"
        >
            <!-- Modal Content -->
            <Transition
                enterActiveClass="transition ease-out duration-200"
                enterFromClass="opacity-0 scale-95"
                enterToClass="opacity-100 scale-100"
                leaveActiveClass="transition ease-in duration-150"
                leaveFromClass="opacity-100 scale-100"
                leaveToClass="opacity-0 scale-95"
            >
                <div
                    v-if="wizard.isModalOpen"
                    class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4"
                    @click.stop
                >
                    <!-- Icon -->
                    <div class="flex justify-center mb-4">
                        <div
                            :class="{
                                'bg-red-100 text-red-600': wizard.modalType === 'error',
                                'bg-green-100 text-green-600': wizard.modalType === 'success',
                                'bg-blue-100 text-blue-600': wizard.modalType === 'info',
                            }"
                            class="w-12 h-12 rounded-full flex items-center justify-center"
                        >
                            <CheckIcon v-if="wizard.modalType === 'success'" class="w-6 h-6" />
                            <XMarkIcon v-else-if="wizard.modalType === 'error'" class="w-6 h-6" />
                            <template v-else>
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </template>
                        </div>
                    </div>

                    <!-- Title and Message -->
                    <h3 class="text-lg font-semibold text-slate-900 text-center mb-2">
                        {{ wizard.modalTitle }}
                    </h3>
                    <p class="text-slate-600 text-center text-sm mb-6">{{ wizard.modalMessage }}</p>

                    <!-- Close Button -->
                    <div class="flex justify-center">
                        <button
                            type="button"
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium text-sm"
                            @click="wizard.closeModal"
                        >
                            Got it
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<script setup lang="ts">
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';

const wizard = useWizardStore();
</script>
