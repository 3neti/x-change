<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import CockpitLayout from '@/cockpit/layouts/CockpitLayout.vue';
import accountLifecycle from '@/routes/x-change/cockpit/account-lifecycle';

type Event = { previous_status: string; new_status: string; actor_type: string; actor_id: string; reason: string; created_at: string };
type Account = { id: string; name: string; status: string; events: Event[] };
const props = defineProps<{ account: Account | null }>();
const accountId = ref(props.account?.id ?? '');
const form = useForm({ status: props.account?.status === 'suspended' ? 'active' : 'suspended', reason: '' });
function findAccount() { router.get(accountLifecycle.index(), { account_id: accountId.value }); }
function changeStatus() {
    if (props.account) {
        form.status = props.account.status === 'suspended' ? 'active' : 'suspended';
        form.post(accountLifecycle.update.url(props.account.id), { onSuccess: () => form.reset('reason') });
    }
}
</script>

<template>
    <Head title="Account lifecycle" />
    <CockpitLayout>
        <main class="mx-auto max-w-3xl space-y-6 p-4">
            <h1 class="text-2xl font-semibold">Account lifecycle</h1>
            <p>Suspension blocks new issuance and credentials. Existing payments and recovery continue.</p>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="findAccount">
                <label class="block">Account ID <input v-model="accountId" required class="block rounded border p-2" /></label>
                <button class="rounded bg-slate-700 px-4 py-2 text-white" type="submit">Find account</button>
            </form>
            <section v-if="account" class="space-y-4 rounded-xl border p-4">
                <h2 class="text-xl font-semibold">{{ account.name }} · {{ account.id }}</h2>
                <p>Account status: <strong>{{ account.status }}</strong></p>
                <form class="space-y-3" @submit.prevent="changeStatus">
                    <label class="block">Reason <textarea v-model="form.reason" required maxlength="1000" class="block w-full rounded border p-2" /></label>
                    <p v-if="form.errors.reason" role="alert">{{ form.errors.reason }}</p>
                    <button class="rounded bg-slate-700 px-4 py-2 text-white disabled:opacity-50" :disabled="form.processing" type="submit">{{ account.status === 'suspended' ? 'Reactivate account' : 'Suspend account' }}</button>
                </form>
                <h3 class="font-semibold">Activity</h3>
                <p v-if="account.events.length === 0">No status changes recorded.</p>
                <ul v-else class="space-y-2"><li v-for="(event, index) in account.events" :key="index" class="rounded border p-2">{{ event.previous_status }} → {{ event.new_status }} · {{ event.created_at }}<br />{{ event.reason }} · {{ event.actor_type }} {{ event.actor_id }}</li></ul>
            </section>
        </main>
    </CockpitLayout>
</template>
