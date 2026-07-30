<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request as requestPin } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Log in with mobile',
        description: 'Enter your mobile number and PIN',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    auth_intent?: {
        type: 'campaign_authorization' | 'onboarding_claimant_handoff';
        authentication_mode: 'authenticated_officer' | 'claimant_handoff';
        code: string;
        title: string;
        description: string;
        intended_url: string;
    } | null;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div
        v-if="auth_intent"
        class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900"
        data-testid="login-auth-intent"
    >
        <p class="font-semibold">{{ auth_intent.title }}</p>
        <p>{{ auth_intent.description }}</p>
        <p class="mt-1 font-mono font-semibold">{{ auth_intent.code }}</p>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="mobile">Mobile number</Label>
                <Input
                    id="mobile"
                    type="tel"
                    inputmode="tel"
                    name="mobile"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="tel"
                    placeholder="0917 123 4567"
                />
                <InputError :message="errors.mobile" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <Label for="password">PIN</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="requestPin()"
                        :tabindex="5"
                        class="text-xs"
                    >
                        Forgot PIN?
                    </TextLink>
                </div>
                <Input
                    id="password"
                    type="password"
                    inputmode="numeric"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Enter PIN"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div
            class="text-center text-sm text-muted-foreground"
            v-if="canRegister"
        >
            New to X-Change?
            <TextLink :href="register()" :tabindex="6">Create account</TextLink>
        </div>
    </Form>
</template>
