<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

const props = defineProps<{
    token: string;
    email: string;
}>();
</script>

<template>
    <Head title="Reset PIN" />

    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold">Choose a New PIN</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                This PIN will be used with your mobile number when you log in.
            </p>
        </div>

        <Form
            v-bind="update.form()"
            :transform="
                (data) => ({
                    ...data,
                    token: props.token,
                    email: props.email,
                })
            "
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="space-y-5"
        >
            <input type="hidden" name="email" :value="props.email" />

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

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-testid="reset-pin"
            >
                <Spinner v-if="processing" />
                Save New PIN
            </Button>
        </Form>
    </div>
</template>
