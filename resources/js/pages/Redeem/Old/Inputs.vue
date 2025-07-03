<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { Head, useForm }         from '@inertiajs/vue3'
import { Label }                 from '@/components/ui/label'
import { Input }                 from '@/components/ui/input'
import { Button }                from '@/components/ui/button'
import InputError                from '@/components/InputError.vue'
import { computed }              from 'vue'

/**
 * We expect Laravel to flash the “old input” (voucher_code, mobile, country, etc.)
 * into the session when we redirected with ->withInput($request->all()).
 * Inertia’s useForm will pick those up automatically.
 */
const form = useForm({
    // original redeem payload
    voucher_code:           '' as string,
    mobile:                 '' as string,
    country:                '' as string,

    // new “inputs” we want to collect
    name:                   '' as string,
    address:                '' as string,
    birthdate:              '' as string,
    email:                  '' as string,
    gross_monthly_income:   '' as string,
})

/**
 * If you need defaults, you can pull from page.props via
 * const props = usePage().props.value; etc.
 * But by default useForm() will bootstrap from any flashed oldInput.
 */

function submit() {
    // trim voucher code just in case
    form.voucher_code = form.voucher_code.trim()

    form.post(route('inputs.store'), {
        preserveState: true,
        onFinish: () => {
            // next middleware will pick up signature or final redeem
        },
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Additional Details" />

        <form @submit.prevent="submit" class="space-y-6 max-w-md mx-auto p-4">

            <!-- keep your original redeem fields hidden so they get carried forward -->
            <input type="hidden" name="voucher_code" v-model="form.voucher_code" />
            <input type="hidden" name="mobile"       v-model="form.mobile" />
            <input type="hidden" name="country"      v-model="form.country" />

            <!-- 1) Full Name -->
            <div class="flex flex-col gap-1">
                <Label for="name">Full Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    placeholder="Your full name"
                />
                <InputError :message="form.errors.name" class="mt-1" />
            </div>

            <!-- 2) Address -->
            <div class="flex flex-col gap-1">
                <Label for="address">Address</Label>
                <Input
                    id="address"
                    v-model="form.address"
                    required
                    placeholder="Your address"
                />
                <InputError :message="form.errors.address" class="mt-1" />
            </div>

            <!-- 3) Birthdate -->
            <div class="flex flex-col gap-1">
                <Label for="birthdate">Birthdate</Label>
                <Input
                    id="birthdate"
                    v-model="form.birthdate"
                    type="date"
                    required
                />
                <InputError :message="form.errors.birthdate" class="mt-1" />
            </div>

            <!-- 4) Email -->
            <div class="flex flex-col gap-1">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    placeholder="you@example.com"
                />
                <InputError :message="form.errors.email" class="mt-1" />
            </div>

            <!-- 5) Gross Monthly Income -->
            <div class="flex flex-col gap-1">
                <Label for="gross_monthly_income">Gross Monthly Income</Label>
                <Input
                    id="gross_monthly_income"
                    v-model="form.gross_monthly_income"
                    type="number"
                    step="0.01"
                    required
                    placeholder="e.g. 50000.00"
                />
                <InputError :message="form.errors.gross_monthly_income" class="mt-1" />
            </div>

            <!-- Submit -->
            <div class="pt-4">
                <Button :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Continue' }}
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
.bevel-line {
    border: 0;
    height: 1px;
    margin: 1rem 0;
    background: linear-gradient(to right,#d1d5db,#f9fafb,#d1d5db);
}
/* add a bit more breathing room under each label+input */
.flex > .flex-col > label + * {
    margin-top: .25rem;
}
</style>
