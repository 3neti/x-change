<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { RefreshCw } from "lucide-vue-next";
import { computed } from "vue";
import { update as updateFundingQrMerchantProfile } from "@/routes/x-change/cockpit/accounts/funding-qr-merchant-profile";
import { renderFundingQrMerchantLabel } from "../fundingQrMerchantLabel";
import type {
  CockpitFundingQrMerchantProfile,
  CockpitStandingFundingAddress,
} from "../types";

const props = defineProps<{
  address: CockpitStandingFundingAddress;
  profile: CockpitFundingQrMerchantProfile;
  notice?: string | null;
}>();

const emit = defineEmits<{
  updated: [];
}>();

const merchantProfileForm = useForm({
  name: props.profile.name,
  city: props.profile.city,
  merchant_category_code: props.profile.merchant_category_code,
  merchant_name_template: props.profile.merchant_name_template,
});

const labelPreview = computed(() =>
  renderFundingQrMerchantLabel({
    name: merchantProfileForm.name,
    city: merchantProfileForm.city,
    applicationName: props.profile.application_name,
    template: merchantProfileForm.merchant_name_template,
    maximumLength: props.profile.maximum_label_length,
    uppercase: props.profile.uppercase,
  }),
);
const hasUnsavedLabel = computed(() => merchantProfileForm.isDirty);
const displayedLabel = computed(() =>
  hasUnsavedLabel.value
    ? labelPreview.value.label
    : props.address.merchant_name || props.profile.rendered_label,
);
const labelStatus = computed(() => {
  if (!hasUnsavedLabel.value) {
    return "Applied to this QR";
  }

  if (!labelPreview.value.fits) {
    return `${labelPreview.value.overflow} characters too long`;
  }

  return "Preview · Update QR to apply";
});

function updateQr(): void {
  if (!labelPreview.value.fits) {
    return;
  }

  merchantProfileForm.patch(updateFundingQrMerchantProfile(), {
    preserveScroll: true,
    onSuccess: () => emit("updated"),
  });
}
</script>

<template>
  <div class="bg-sky-50/50 px-4 py-6 sm:px-6 dark:bg-sky-950/10">
    <div class="mx-auto flex max-w-2xl flex-col items-center">
      <div class="text-center" aria-live="polite">
        <p
          class="text-[0.65rem] font-semibold tracking-[0.18em] text-sky-700 uppercase dark:text-sky-300"
        >
          Shown in the payer’s app
        </p>
        <p
          class="mt-1 min-h-7 max-w-xl text-lg font-semibold tracking-tight text-slate-950 sm:text-xl dark:text-white"
          data-testid="funding-qr-merchant-label-preview"
        >
          {{ displayedLabel }}
        </p>
        <p
          class="mt-1 text-xs font-medium"
          :class="
            !labelPreview.fits
              ? 'text-rose-700 dark:text-rose-300'
              : hasUnsavedLabel
                ? 'text-amber-700 dark:text-amber-300'
                : 'text-slate-500 dark:text-slate-400'
          "
          data-testid="funding-qr-merchant-label-state"
        >
          {{ labelStatus }}
        </p>
      </div>

      <div
        class="mt-4 rounded-3xl border border-sky-200 bg-white p-3 shadow-[0_18px_50px_-24px_rgba(14,116,144,0.55)] sm:p-4 dark:border-sky-900"
      >
        <img
          :src="address.qr_code"
          :alt="`QR Ph Account Funding code for ${displayedLabel}`"
          class="size-64 object-contain sm:size-72"
          data-testid="standing-funding-address-qr"
        />
      </div>

      <form
        class="mt-6 w-full rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950"
        data-testid="funding-qr-merchant-profile"
        @submit.prevent="updateQr"
      >
        <div
          class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
        >
          <div>
            <h3 class="text-sm font-semibold text-slate-950 dark:text-white">
              Merchant label
            </h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
              Choose how your name appears after scanning.
            </p>
          </div>
          <span
            class="text-xs font-medium tabular-nums"
            :class="
              labelPreview.fits
                ? 'text-slate-500 dark:text-slate-400'
                : 'text-rose-700 dark:text-rose-300'
            "
            data-testid="funding-qr-merchant-label-count"
          >
            {{ labelPreview.characterCount }} /
            {{ labelPreview.maximumLength }}
          </span>
        </div>

        <fieldset class="mt-4">
          <legend class="sr-only">Merchant label format</legend>
          <div
            class="grid grid-cols-3 rounded-xl bg-slate-100 p-1 dark:bg-slate-900"
          >
            <label
              v-for="option in profile.template_options"
              :key="option.value"
              class="cursor-pointer"
            >
              <input
                v-model="merchantProfileForm.merchant_name_template"
                type="radio"
                name="merchant_name_template"
                :value="option.value"
                class="peer sr-only"
              />
              <span
                class="flex min-h-9 items-center justify-center rounded-lg px-2 text-center text-xs font-semibold text-slate-500 transition peer-checked:bg-white peer-checked:text-sky-800 peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-sky-500 dark:text-slate-400 dark:peer-checked:bg-slate-800 dark:peer-checked:text-sky-200"
              >
                {{ option.label }}
              </span>
            </label>
          </div>
        </fieldset>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <label class="grid gap-1.5 text-xs font-medium">
            <span>Name</span>
            <input
              v-model="merchantProfileForm.name"
              type="text"
              maxlength="25"
              autocomplete="organization"
              class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 transition outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:focus:ring-sky-950"
            />
            <span
              v-if="merchantProfileForm.errors.name"
              class="text-rose-700 dark:text-rose-300"
            >
              {{ merchantProfileForm.errors.name }}
            </span>
          </label>
          <label class="grid gap-1.5 text-xs font-medium">
            <span>City</span>
            <input
              v-model="merchantProfileForm.city"
              type="text"
              maxlength="15"
              autocomplete="address-level2"
              class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 transition outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:focus:ring-sky-950"
            />
            <span
              v-if="merchantProfileForm.errors.city"
              class="text-rose-700 dark:text-rose-300"
            >
              {{ merchantProfileForm.errors.city }}
            </span>
          </label>
        </div>

        <p
          v-if="merchantProfileForm.errors.merchant_name_template"
          class="mt-2 text-xs text-rose-700 dark:text-rose-300"
        >
          {{ merchantProfileForm.errors.merchant_name_template }}
        </p>

        <button
          type="submit"
          class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-semibold whitespace-nowrap text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50 sm:ml-auto sm:flex sm:w-auto"
          :disabled="merchantProfileForm.processing || !labelPreview.fits"
          data-testid="funding-qr-update"
        >
          <RefreshCw
            class="size-4 shrink-0"
            :class="{
              'animate-spin': merchantProfileForm.processing,
            }"
            aria-hidden="true"
            data-testid="funding-qr-update-icon"
          />
          {{ merchantProfileForm.processing ? "Updating…" : "Update QR" }}
        </button>
      </form>

      <div
        v-if="notice"
        class="mt-4 w-full rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
        role="status"
      >
        {{ notice }}
      </div>
    </div>
  </div>
</template>
