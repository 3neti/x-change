<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

final class CampaignDemonstrationProductPresentation
{
    /** @return array<string, string>|null */
    public function forDriver(string $driverId, string $driverVersion): ?array
    {
        return match ($driverId.'@'.$driverVersion) {
            AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@'.AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION => [
                'result_code' => 'policy_issued_demo',
                'decision' => 'issued_demo',
                'reference_prefix' => 'AUI-DEMO-',
                'product' => 'Personal Accident — demonstration',
                'eyebrow' => 'Demonstration only',
                'title' => 'Demo policy summary',
                'description' => 'Your details were submitted and the demonstration response was recorded.',
                'notice' => 'Demonstration only. This is not an issued insurance policy and does not establish insurance coverage.',
                'action_label' => 'View demo policy',
                'action_description' => 'View your demonstration policy summary. This is not actual insurance coverage.',
                'processing_title' => 'Details submitted',
                'processing_body' => 'Your payment has been received. Your policy result is being prepared. You will be notified when it is ready.',
                'ready_title' => 'Policy result ready',
                'ready_body' => 'Your payment has been received and your details have been submitted. Your policy result is ready. Use the link provided by the campaign. A demonstration result is not actual insurance coverage.',
                'first_sms_title' => 'Campaign payment received',
                'first_sms_body' => 'Payment received. AUI demonstration only, not an issued insurance policy. Complete your personal details: :url',
                'summary_sms_title' => 'Demonstration policy summary',
                'summary_sms_body' => 'Your demo policy summary is ready. DEMONSTRATION ONLY, not an issued insurance policy or proof of coverage. View: :url',
            ],
            MedicardDemoBenefitPolicyCompletionDriver::DRIVER_ID.'@'.MedicardDemoBenefitPolicyCompletionDriver::DRIVER_VERSION => [
                'result_code' => 'benefit_ready_demo',
                'decision' => 'ready_demo',
                'reference_prefix' => 'MEDICARD-DEMO-',
                'product' => 'MediCard Demo Benefit Pass',
                'eyebrow' => 'Demonstration only',
                'title' => 'Demo benefit summary',
                'description' => 'Your details were submitted and the healthcare-access demonstration response was recorded.',
                'notice' => 'DEMONSTRATION ONLY. This does not create MediCard membership, healthcare coverage, a policy, a letter of authorization, or a right to treatment or reimbursement.',
                'action_label' => 'View demo benefit',
                'action_description' => 'View your private MediCard Demo Benefit Summary. No membership or healthcare coverage was created.',
                'processing_title' => 'Details submitted',
                'processing_body' => 'Your demonstration details were received. Your Demo Benefit Summary is being prepared. No membership or healthcare coverage has been created.',
                'ready_title' => 'Demo benefit ready',
                'ready_body' => 'Your MediCard Demo Benefit Summary is ready. This demonstration does not create membership, healthcare coverage, treatment authorization, or reimbursement rights.',
                'first_sms_title' => 'MediCard demonstration payment received',
                'first_sms_body' => 'We received your PHP 50.00 MediCard demonstration payment. Complete your details: :url. DEMONSTRATION ONLY—no membership or healthcare coverage is created.',
                'summary_sms_title' => 'MediCard Demo Benefit Summary',
                'summary_sms_body' => 'Your MediCard Demo Benefit Summary is ready: :url. DEMONSTRATION ONLY—not membership, coverage, treatment authorization, or reimbursement.',
            ],
            default => null,
        };
    }
}
