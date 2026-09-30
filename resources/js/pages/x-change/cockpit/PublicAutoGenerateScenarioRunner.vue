<script setup lang="ts">
import PublicAutoGenerateScenarioRunner from '../../../cockpit/pages/PublicAutoGenerateScenarioRunner.vue';
import type { CockpitHeaderPageProps } from '../../../cockpit/types';

type ScenarioFact = { label: string; value: string };
type ScenarioStep = { key: string; label: string; outcome: string; facts: ScenarioFact[] };

const props = defineProps<
    CockpitHeaderPageProps & {
        run: {
            success: boolean;
            message: string;
            rollback_completed: boolean;
            simulation: {
                rollback_only: boolean;
                provider_calls: number;
                monetary_value: boolean;
                persisted: boolean;
            };
            steps: ScenarioStep[];
            artifacts: {
                commercial_principal_reference: string;
                order_reference: string;
                pay_code: string;
                claim_qr_present: boolean;
                share_result_present: boolean;
                expected_payment_minor: number;
            };
        } | null;
    }
>();
</script>

<template>
    <PublicAutoGenerateScenarioRunner v-bind="props" />
</template>
