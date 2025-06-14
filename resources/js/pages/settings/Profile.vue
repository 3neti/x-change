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
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    mobile: user.mobile,
    email: user.email,
    merchant_code: user.merchant.code,
    merchant_name: user.merchant.name,
    merchant_city: user.merchant.city,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Profile information" description="Update your name and email address" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Full name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="mobile">Mobile</Label>
                        <Input id="mobile" class="mt-1 block w-full" v-model="form.mobile" required autocomplete="mobile" placeholder="Mobile Number" />
                        <InputError class="mt-2" :message="form.errors.mobile" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input id="email" type="email" class="mt-1 block w-full" v-model="form.email" required autocomplete="username" disabled />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="merchant_code">Merchant Code</Label>
                        <Input id="merchant_code" class="mt-1 block w-full" v-model="form.merchant_code" required autocomplete="merchant_code" disabled />
                        <InputError class="mt-2" :message="form.errors.merchant_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="merchant_name">Merchant Name</Label>
                        <Input id="merchant_name" class="mt-1 block w-full" v-model="form.merchant_name" required autocomplete="merchant_name" placeholder="Merchant Name" />
                        <InputError class="mt-2" :message="form.errors.merchant_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="merchant_city">Merchant City</Label>
                        <Input id="merchant_city" class="mt-1 block w-full" v-model="form.merchant_city" required autocomplete="merchant_city" placeholder="Merchant City" />
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

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
