<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    status?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Merchant settings',
        href: '/settings/merchant',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    merchant_code: user.merchant.code,
    merchant_name: user.merchant.name,
    merchant_city: user.merchant.city,
});

const submit = () => {
    form.patch(route('merchant.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Merchant settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Merchant information" description="Update your merchant code, name and city" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="merchant_code">Merchant Code</Label>
                        <Input id="merchant_code" class="mt-1 block w-full" v-model="form.merchant_code" required autocomplete="merchant_code" placeholder="e.g., JD" />
                        <InputError class="mt-2" :message="form.errors.merchant_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="merchant_name">Merchant Name</Label>
                        <Input id="merchant_name" class="mt-1 block w-full" v-model="form.merchant_name" required autocomplete="merchant_name" placeholder="e.g., Account of John Doe" />
                        <InputError class="mt-2" :message="form.errors.merchant_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="merchant_city">Merchant City</Label>
                        <Input id="merchant_city" class="mt-1 block w-full" v-model="form.merchant_city" required autocomplete="merchant_city" placeholder="e.g., Manila" />
                        <InputError class="mt-2" :message="form.errors.merchant_city" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">Saved.</p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
