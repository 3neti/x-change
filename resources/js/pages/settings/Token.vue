<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    token_name?: string;
    token?: string;
    generated_at?: string;
    status?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Token settings', href: '/settings/token' },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    token_name: page.props.token_name,
    token: page.props.token, // one-time display
});

const generatedAt = page.props.generated_at;

const timeAgo = computed(() => {
    if (!generatedAt) return null;
    const now = new Date();
    const generated = new Date(generatedAt);
    const diff = Math.floor((now.getTime() - generated.getTime()) / 1000);
    if (diff < 60) return `${diff} second(s) ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)} minute(s) ago`;
    return `${Math.floor(diff / 3600)} hour(s) ago`;
});

const copyToClipboard = () => {
    if (!form.token || !navigator.clipboard) return;
    navigator.clipboard.writeText(form.token).then(() => {
        alert('Token copied to clipboard.');
    }).catch(() => {
        alert('Failed to copy token.');
    });
};

const submit = () => {
    form.patch(route('token.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.token = usePage().props.token; // ✅ update manually
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Generate token" />
        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Generate token" description="Generate a personal access token" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="token_name">Token Name</Label>
                        <Input
                            id="token_name"
                            class="w-full"
                            v-model="form.token_name"
                            required
                            placeholder="e.g., api-token"
                        />
                        <InputError class="mt-2" :message="form.errors.token_name" />
                    </div>

                    <div v-if="form.token" class="grid gap-2">
                        <Label for="token">Token</Label>
                        <div class="flex gap-2">
                            <Input
                                id="token"
                                class="w-full"
                                v-model="form.token"
                                readonly
                            />
                            <Button type="button" @click="copyToClipboard" variant="secondary">
                                Copy
                            </Button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.token" />
                        <p v-if="timeAgo" class="text-sm text-neutral-600 dark:text-neutral-400">
                            Token generated {{ timeAgo }}
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Generate</Button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">Generated.</p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
