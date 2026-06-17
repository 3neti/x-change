<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import { edit } from '@/routes/security';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
            {
                title: 'Confirm PIN',
                href: '/settings/security/confirm',
            },
        ],
    },
});
</script>

<template>
    <Head title="Confirm PIN" />

    <h1 class="sr-only">Confirm PIN</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Confirm PIN"
            description="Enter your current PIN to manage security settings"
        />

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <div class="grid gap-2">
                <Label for="password">Current PIN</Label>
                <Input
                    id="password"
                    type="password"
                    inputmode="numeric"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                    placeholder="Enter PIN"
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                class="w-full"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                Confirm PIN
            </Button>
        </Form>
    </div>
</template>
