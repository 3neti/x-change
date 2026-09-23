export type CampaignPolicyLifecycle = {
  schema: "x-change.campaign-policy-lifecycle.v1";
  stage: string;
  attention_required: boolean;
  campaign: {
    reference: string | null;
    name: string | null;
    revision_id: string | null;
  };
  payment: {
    recognition_reference: string | null;
    gross_amount_minor: number | null;
    currency: string | null;
    settled_at: string | null;
  };
  coverage: {
    reference: string;
    type: string;
    status: string;
    amount_minor: number | null;
    currency: string;
    effective_at: string | null;
    expires_at: string | null;
  };
  completion: {
    issuance_reference: string;
    pay_code: string | null;
    issued_at: string | null;
    claim_number: number | null;
    claim_completed_at: string | null;
    projection_reference: string | null;
    projected_at: string | null;
  } | null;
  policy: {
    request_reference: string;
    status: string;
    requested_at: string | null;
    approved_at: string | null;
    outcome_reference: string | null;
    outcome_status: string | null;
    result_code: string | null;
    recorded_at: string | null;
  } | null;
  updated_at: string | null;
};
