<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { KeyRound, ShieldCheck } from 'lucide-vue-next';
import InitialPinSetupController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Onboarding/InitialPinSetupController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    mobile: string;
}>();
</script>

<template>
    <Head title="Create your PIN" />

    <main
        class="min-h-screen bg-slate-100 px-4 py-10 text-slate-950 dark:bg-slate-950 dark:text-white"
    >
        <section
            class="mx-auto max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            data-testid="initial-pin-setup"
        >
            <header class="bg-slate-950 px-6 py-6 text-white">
                <div
                    class="flex size-11 items-center justify-center rounded-xl bg-sky-400/15 text-sky-300"
                >
                    <KeyRound class="size-5" />
                </div>
                <p
                    class="mt-5 text-xs font-semibold tracking-[0.18em] text-sky-300 uppercase"
                >
                    Account Security
                </p>
                <h1 class="mt-2 text-2xl font-semibold">Create your PIN</h1>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    Use this PIN with {{ mobile }} when you return to x-change.
                </p>
            </header>

            <Form
                v-bind="InitialPinSetupController.store.form()"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="space-y-5 p-6"
            >
                <div class="grid gap-2">
                    <Label for="password">New PIN</Label>
                    <Input
                        id="password"
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        name="password"
                        required
                        autofocus
                        autocomplete="new-password"
                        placeholder="At least 4 digits"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm PIN</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Enter the same PIN"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div
                    class="flex gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm leading-5 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100"
                >
                    <ShieldCheck class="mt-0.5 size-4 shrink-0" />
                    <p>
                        You will not be asked for a current PIN during this
                        one-time setup.
                    </p>
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full"
                    :disabled="processing"
                    data-testid="save-initial-pin"
                >
                    <Spinner v-if="processing" />
                    Save PIN and Continue
                </Button>
            </Form>
        </section>
    </main>
</template>
