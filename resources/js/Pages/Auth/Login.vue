<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import GoogleRoleButtons from '@/Components/Auth/GoogleRoleButtons.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Sign in" />

        <h1 class="text-xl font-bold tracking-tight text-slate-900">Sign in</h1>
        <p class="mt-1 text-sm text-slate-500">
            Continue as a teacher or student, or use email.
        </p>

        <div v-if="status" class="mt-4 rounded-lg bg-green-50 px-3 py-2 text-sm font-medium text-green-700">
            {{ status }}
        </div>

        <GoogleRoleButtons class="mt-6" />

        <div class="my-6 flex items-center">
            <div class="flex-1 border-t border-slate-200"></div>
            <span class="px-3 text-xs font-medium uppercase tracking-wide text-slate-400">Or with email</span>
            <div class="flex-1 border-t border-slate-200"></div>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center">
                    <Checkbox v-model:checked="form.remember" name="remember" />
                    <span class="ms-2 text-sm text-slate-600">Remember me</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                >
                    Forgot password?
                </Link>
            </div>

            <button
                type="submit"
                class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Signing in...' : 'Sign in' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Don't have an account?
            <Link
                :href="route('register')"
                class="font-medium text-indigo-600 hover:text-indigo-700"
            >
                Register
            </Link>
        </p>
    </GuestLayout>
</template>
