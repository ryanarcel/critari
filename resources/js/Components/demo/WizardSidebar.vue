<template>
    <div
        v-if="wizard.sidebarOpen"
        class="fixed inset-0 z-[55] bg-slate-900/50 lg:hidden"
        aria-hidden="true"
        @click="wizard.closeSidebar"
    />

    <aside
        id="workflow-sidebar"
        class="fixed inset-y-0 right-0 z-[60] flex w-[min(20rem,calc(100vw-2.5rem))] flex-col border-l border-indigo-600 bg-gradient-to-b from-indigo-700 to-indigo-900 text-white shadow-xl transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:max-h-[calc(100dvh-4.5rem)] lg:w-80"
        :class="
            wizard.sidebarOpen
                ? 'translate-x-0'
                : 'pointer-events-none translate-x-full lg:hidden lg:translate-x-0 lg:pointer-events-auto'
        "
        :aria-hidden="!wizard.sidebarOpen"
    >
        <div class="flex items-start justify-between border-b border-indigo-600 p-4 sm:p-6">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-indigo-200">
                    Workflow Progress
                </h2>
                <p class="mt-1 text-xs text-indigo-300">
                    Step {{ wizard.currentStep }} of {{ wizard.totalSteps }}
                </p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-indigo-100 hover:bg-indigo-800 hover:text-white"
                aria-label="Hide steps"
                @click="wizard.closeSidebar"
            >
                <XMarkIcon class="h-5 w-5" />
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-4">
            <div class="space-y-4">
                <StepSection
                    emoji="👨‍🏫"
                    title="Teacher Setup"
                    :steps="wizard.steps.filter((s) => s.section === 'Teacher')"
                />

                <StepSection
                    v-if="wizard.steps.some((s) => s.section === 'Student')"
                    emoji="👤"
                    title="Student Response"
                    :steps="wizard.steps.filter((s) => s.section === 'Student')"
                />

                <StepSection
                    v-if="wizard.steps.some((s) => s.section === 'Assessment')"
                    emoji="🤖"
                    title="AI Assessment"
                    :steps="wizard.steps.filter((s) => s.section === 'Assessment')"
                />
            </div>
        </div>

        <div class="space-y-2 border-t border-indigo-600 p-4 text-xs">
            <a href="#" class="block py-1 text-indigo-300 transition hover:text-white"
                >Help & Support</a
            >
            <a href="#" class="block py-1 text-indigo-300 transition hover:text-white"
                >Terms of Service</a
            >
            <a href="#" class="block py-1 text-indigo-300 transition hover:text-white"
                >Privacy Policy</a
            >
        </div>
    </aside>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { useWizardStore } from '@/stores/demo/wizardStore';
import StepSection from './StepSection.vue';

const wizard = useWizardStore();

const syncBodyScroll = (open: boolean) => {
    if (typeof document === 'undefined') {
        return;
    }

    const isMobileDrawer = window.matchMedia('(max-width: 1023px)').matches;
    document.body.style.overflow = open && isMobileDrawer ? 'hidden' : '';
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && wizard.sidebarOpen) {
        wizard.closeSidebar();
    }
};

let wideLayout: MediaQueryList | null = null;

const onLayoutChange = (event: MediaQueryListEvent) => {
    wizard.sidebarOpen = event.matches;
    syncBodyScroll(wizard.sidebarOpen);
};

onMounted(() => {
    wideLayout = window.matchMedia('(min-width: 1024px)');
    wizard.sidebarOpen = wideLayout.matches;
    wideLayout.addEventListener('change', onLayoutChange);
    window.addEventListener('keydown', onKeydown);
    syncBodyScroll(wizard.sidebarOpen);
});

watch(
    () => wizard.sidebarOpen,
    (open) => {
        syncBodyScroll(open);
    },
);

onBeforeUnmount(() => {
    wideLayout?.removeEventListener('change', onLayoutChange);
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>
