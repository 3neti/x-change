<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot PIN',
        description: 'Receive a secure PIN reset link at your Account email',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot PIN" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-emerald-600"
        role="status"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Account Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    autofocus
                    placeholder="you@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                class="mt-6 w-full"
                :disabled="processing"
                data-testid="send-pin-reset-link"
            >
                <Spinner v-if="processing" />
                Send PIN Reset Link
            </Button>
        </Form>

        <p class="text-center text-sm text-muted-foreground">
            Remembered it?
            <TextLink :href="login()">Return to Login</TextLink>
        </p>
    </div>
</template>
