<script setup lang="ts">
import CommercialPayCodeScenarioRunner from '../../../cockpit/pages/CommercialPayCodeScenarioRunner.vue';
import type { CockpitHeaderPageProps } from '../../../cockpit/types';

type Actor = { id: string; name: string; mobile: string };
type ScenarioRun = {
    reference: string;
    phase: 'awaiting_checker' | 'issued' | 'cancelled';
    amount: string;
    worksheet_reference: string;
    approval_pay_code: string | null;
    authorization_reference: string | null;
    funding_source: string;
    pay_code: string | null;
    steps: Array<{ label: string; complete: boolean }>;
};

const props = defineProps<
    CockpitHeaderPageProps & {
        commercial_principal: {
            reference: string | null;
            legal_name: string | null;
            active: boolean;
        funding_source: string;
        revenue_account_excluded: boolean;
        balances: {
            client_funds_minor: number;
            pay_code_reserve_minor: number;
            revenue_minor: number;
        } | null;
        };
        maker: Actor;
        maker_ready: boolean;
        checkers: Actor[];
        run: ScenarioRun | null;
    }
>();
</script>

<template>
    <CommercialPayCodeScenarioRunner v-bind="props" />
</template>
