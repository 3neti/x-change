<script setup lang="ts">
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ArrowRight, CheckCircle2, Loader2, RefreshCcw, ShieldAlert } from 'lucide-vue-next';
import {
    formatProvisioningLabel,
    type ProvisioningRequirement,
} from './provisioningRequirement';

const props = defineProps<{
    requirement?: ProvisioningRequirement | null;
    resumeLabel?: string;
    retryLabel?: string;
}>();

const emit = defineEmits<{
    resume: [];
}>();

const checkingStatus = ref(false);
const statusMessage = ref<string | null>(null);
const statusVariant = ref<'default' | 'destructive'>('default');

const descriptor = computed(() => props.requirement?.descriptor ?? null);
const steps = computed(() => descriptor.value?.steps ?? []);
const fields = computed(() => descriptor.value?.fields ?? []);
const actions = computed(() => descriptor.value?.actions ?? []);
const missing = computed(() => props.requirement?.missing ?? []);
const onboardingReference = computed(() => {
    const reference = props.requirement?.onboarding?.reference;

    return typeof reference === 'string' && reference.trim() !== '' ? reference : null;
});
const onboardingStatusUrl = computed(() => {
    const url = props.requirement?.onboarding?.links?.status_url;

    return typeof url === 'string' && url.trim() !== '' ? url : null;
});
const onboardingResumeUrl = computed(() => {
    const url = props.requirement?.onboarding?.links?.resume_url;

    return typeof url === 'string' && url.trim() !== '' ? url : null;
});
const onboardingResumeHref = computed(() => {
    if (! onboardingResumeUrl.value) {
        return null;
    }

    if (typeof window === 'undefined') {
        return onboardingResumeUrl.value;
    }

    const currentUrl = `${window.location.pathname}${window.location.search}`;
    const url = new URL(onboardingResumeUrl.value, window.location.origin);

    if (currentUrl !== '') {
        url.searchParams.set('return_url', currentUrl);
    }

    return `${url.pathname}${url.search}${url.hash}`;
});

const primaryActionLabel = computed(() => {
    if (props.resumeLabel) {
        return props.resumeLabel;
    }

    return onboardingReference.value ? 'Resume flow' : 'Retry provisioning';
});

const secondaryActionLabel = computed(() => {
    if (onboardingStatusUrl.value) {
        return 'Check setup status';
    }

    return props.retryLabel ?? 'Retry now';
});

function continueSetup(): void {
    statusMessage.value = null;
    emit('resume');
}

async function checkSetupStatus(): Promise<void> {
    if (! onboardingStatusUrl.value) {
        emit('resume');
        return;
    }

    checkingStatus.value = true;

    try {
        const response = await fetch(onboardingStatusUrl.value, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await response.json().catch(() => ({}));
        const status = String(payload?.data?.status ?? '').toLowerCase();

        if (status === 'completed') {
            statusVariant.value = 'default';
            statusMessage.value = 'Setup complete. Resuming the guarded flow now.';
            emit('resume');

            return;
        }

        statusVariant.value = status === 'cancelled' ? 'destructive' : 'default';
        statusMessage.value = status === 'cancelled'
            ? 'Setup was cancelled. Restart the setup or retry the guarded flow.'
            : 'Setup is still in progress. Complete the onboarding steps, then continue here.';
    } catch {
        statusVariant.value = 'destructive';
        statusMessage.value = 'Unable to check setup status right now. Retry the guarded flow when ready.';
    } finally {
        checkingStatus.value = false;
    }
}

function retryNow(): void {
    statusMessage.value = null;
    void checkSetupStatus();
}
</script>

<template>
    <Card
        v-if="requirement"
        class="border-primary/20 bg-gradient-to-br from-primary/8 via-background to-background shadow-sm"
        data-testid="provisioning-setup"
    >
        <CardHeader class="space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-primary">
                        <ShieldAlert class="h-5 w-5" />
                        <span class="text-sm font-semibold uppercase tracking-[0.18em]">
                            Provider Setup Required
                        </span>
                    </div>

                    <div class="space-y-1">
                        <CardTitle class="text-xl">
                            {{ descriptor?.title || 'Provider setup required' }}
                        </CardTitle>
                        <CardDescription class="max-w-2xl text-sm leading-6">
                            {{ descriptor?.description || requirement.reason || 'Complete the required provider setup before continuing.' }}
                        </CardDescription>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Badge v-if="requirement.provider" variant="secondary">
                        {{ requirement.provider }}
                    </Badge>
                    <Badge v-if="requirement.mode" variant="outline">
                        {{ formatProvisioningLabel(requirement.mode) }}
                    </Badge>
                    <Badge v-if="onboardingReference" variant="outline">
                        Ref {{ onboardingReference }}
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-[1.35fr_0.9fr]">
                <section class="space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <CheckCircle2 class="h-4 w-4 text-primary" />
                            <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                Setup Steps
                            </h3>
                        </div>

                        <ol
                            v-if="steps.length > 0"
                            class="grid gap-3 sm:grid-cols-2"
                        >
                            <li
                                v-for="(step, index) in steps"
                                :key="step"
                                class="rounded-2xl border bg-background/80 px-4 py-3"
                            >
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                    Step {{ index + 1 }}
                                </p>
                                <p class="mt-1 text-sm font-medium capitalize text-foreground">
                                    {{ formatProvisioningLabel(step) }}
                                </p>
                            </li>
                        </ol>
                        <p v-else class="text-sm text-muted-foreground">
                            The provider did not publish step details for this flow yet.
                        </p>
                    </div>

                    <Separator />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <section class="space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                Required Inputs
                            </h3>
                            <div v-if="fields.length > 0" class="flex flex-wrap gap-2">
                                <Badge
                                    v-for="field in fields"
                                    :key="field"
                                    variant="outline"
                                >
                                    {{ formatProvisioningLabel(field) }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No additional fields were projected for this setup.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                Provider Actions
                            </h3>
                            <div v-if="actions.length > 0" class="flex flex-wrap gap-2">
                                <Badge
                                    v-for="action in actions"
                                    :key="action"
                                    variant="secondary"
                                >
                                    {{ formatProvisioningLabel(action) }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No provider actions were projected for this setup yet.
                            </p>
                        </section>
                    </div>
                </section>

                <aside class="space-y-4 rounded-2xl border bg-background/90 p-4">
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Readiness Gaps
                        </h3>

                        <div v-if="missing.length > 0" class="flex flex-wrap gap-2">
                            <Badge
                                v-for="item in missing"
                                :key="item"
                                variant="outline"
                            >
                                {{ formatProvisioningLabel(item) }}
                            </Badge>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            Readiness details are not available yet.
                        </p>
                    </div>

                    <Separator />

                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Next Move
                        </h3>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Complete the provider setup with reference
                            <span class="font-medium text-foreground">
                                {{ onboardingReference || 'pending' }}
                            </span>
                            and then resume the guarded flow here.
                        </p>
                    </div>

                    <Alert v-if="statusMessage" :variant="statusVariant">
                        <AlertDescription>
                            {{ statusMessage }}
                        </AlertDescription>
                    </Alert>
                </aside>
            </div>
        </CardContent>

        <CardFooter class="flex flex-col items-stretch gap-3 border-t bg-background/70 md:flex-row md:justify-end">
            <Button
                variant="outline"
                class="w-full md:w-auto"
                @click="retryNow"
            >
                <RefreshCcw class="mr-2 h-4 w-4" />
                {{ secondaryActionLabel }}
            </Button>

            <Button
                v-if="onboardingResumeHref"
                as="a"
                class="w-full md:w-auto"
                :disabled="checkingStatus"
                :href="onboardingResumeHref"
            >
                <ArrowRight class="mr-2 h-4 w-4" />
                {{ primaryActionLabel }}
            </Button>

            <Button
                v-else
                class="w-full md:w-auto"
                :disabled="checkingStatus"
                @click="continueSetup"
            >
                <ArrowRight class="mr-2 h-4 w-4" />
                {{ primaryActionLabel }}
            </Button>
        </CardFooter>
    </Card>
</template>
