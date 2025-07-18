<!-- resources/js/Pages/Redeem/Inputs.vue -->
<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'
import { computed, onMounted, ref, nextTick } from 'vue'

// ⬅️ Accept key-value input props
const props = defineProps<{
    context: {
        voucherCode: string
        mobile: string
    }
    inputs: Record<string, string | null> // 👈 now an object with field defaults
}>()

// Hydrate form with default values
const form = useForm({
    name: props.inputs.name ?? '',
    address: props.inputs.address ?? '',
    birth_date: props.inputs.birth_date ?? '',
    email: props.inputs.email ?? '',
    gross_monthly_income: props.inputs.gross_monthly_income ?? '',
    country: props.inputs.country ?? '',
})

// 👁️ Get only the visible fields from keys
const visibleFields = computed(() => Object.keys(props.inputs))

function submit() {
    form.post(route('redeem.inputs', {
        voucher: props.context.voucherCode,
        plugin: 'inputs',
    }), {
        preserveScroll: true,
    })
}

const inputGroupRef = ref<HTMLFieldSetElement | null>(null)

onMounted(async () => {
    await nextTick()
    // Focus the first input inside the group box
    const firstInput = inputGroupRef.value?.querySelector('input, select, textarea') as HTMLElement | null
    firstInput?.focus()
})
</script>

<template>
    <GuestLayout>
        <Head title="Additional Information" />

        <form @submit.prevent="submit" class="space-y-6 relative">
            <!-- Mobile (Read-only) -->
            <div class="flex flex-col gap-1">
                <Label for="mobile">Mobile Handle</Label>
                <input
                    id="mobile"
                    type="text"
                    :value="props.context.mobile"
                    readonly
                    tabindex="-1"
                    inert
                    class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                />
            </div>

            <!-- Inputs Group -->
            <fieldset ref="inputGroupRef" class="border border-gray-300 dark:border-gray-600 rounded-lg p-4">
                <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-2">Requested Inputs</legend>

                <div v-if="visibleFields.includes('name')" class="mt-2 flex flex-col gap-1">
                    <Label for="name">Full Name</Label>
                    <Input id="name" v-model="form.name"/>
                    <InputError :message="form.errors.name" />
                </div>

                <div v-if="visibleFields.includes('address')" class="mt-4 flex flex-col gap-1">
                    <Label for="address">Address</Label>
                    <Input id="address" v-model="form.address"/>
                    <InputError :message="form.errors.address" />
                </div>

                <div v-if="visibleFields.includes('birth_date')" class="mt-4 flex flex-col gap-1">
                    <Label for="birthdate">Birth Date</Label>
                    <Input id="birthdate" v-model="form.birth_date" type="date"/>
                    <InputError :message="form.errors.birth_date" />
                </div>

                <div v-if="visibleFields.includes('email')" class="mt-4 flex flex-col gap-1">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email"/>
                    <InputError :message="form.errors.email" />
                </div>

                <div v-if="visibleFields.includes('gross_monthly_income')" class="mt-4 flex flex-col gap-1">
                    <Label for="gross_monthly_income">Gross Monthly Income</Label>
                    <Input
                        id="gross_monthly_income"
                        v-model="form.gross_monthly_income"
                        type="number"
                        step="any"
                        min="0"
                        required
                    />
                    <InputError :message="form.errors.gross_monthly_income" />
                </div>

                <div v-if="visibleFields.includes('country')" class="mt-4 flex flex-col gap-1">
                    <Label for="country">Country</Label>
                    <Input id="country" v-model="form.country"/>
                    <InputError :message="form.errors.country" />
                </div>
            </fieldset>

            <!-- Footer Section -->
            <div class="flex justify-between items-center pt-4">
                <div class="text-xs text-right text-gray-500 dark:text-gray-400 italic">
                    Redeeming cash code: <span class="font-semibold">{{ props.context.voucherCode }}</span>
                </div>

                <Button :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Next' }}
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
/* Clean layout, consistent spacing */
</style>
