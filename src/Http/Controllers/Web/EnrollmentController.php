<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Models\EnrollmentConsent;
use LBHurtado\XChange\Models\EnrollmentOffer;

final class EnrollmentController extends Controller
{
    public function show(Request $request, string $reference): Response
    {
        $offer = $this->activeOffer($reference);

        return Inertia::render('x-change/enrollment/Enroll', [
            'offer' => [
                'title' => $offer->title,
                'consent_statement' => $offer->consent_statement,
                'consent_version' => $offer->consent_version,
            ],
            'store_url' => route('x-change.enrollment.store', ['reference' => $offer->reference]),
            'receipt' => $request->session()->get('enrollment_receipt'),
        ]);
    }

    public function store(Request $request, string $reference, AuditLoggerContract $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'mobile' => ['required', 'string', 'regex:/^(?:\+63|0)9[0-9]{9}$/'],
            'accepted' => ['accepted'],
        ]);
        $mobile = str_starts_with($validated['mobile'], '0')
            ? '+63'.substr($validated['mobile'], 1)
            : $validated['mobile'];
        $mobileHash = hash_hmac('sha256', $mobile, (string) config('app.key'));

        [$consent, $created] = DB::transaction(function () use ($reference, $validated, $mobile, $mobileHash): array {
            $offer = EnrollmentOffer::query()
                ->where('reference', $reference)
                ->where('active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = $offer->consents()
                ->where('consent_version', $offer->consent_version)
                ->where('mobile_hash', $mobileHash)
                ->first();
            if ($existing instanceof EnrollmentConsent) {
                return [$existing, false];
            }

            return [$offer->consents()->create([
                'name_ciphertext' => trim($validated['name']),
                'mobile_ciphertext' => $mobile,
                'mobile_hash' => $mobileHash,
                'consent_version' => $offer->consent_version,
                'consent_statement' => $offer->consent_statement,
                'statement_hash' => hash('sha256', $offer->consent_statement),
                'consented_at' => now(),
            ]), true];
        });

        if ($created) {
            $audit->log('enrollment.consent.recorded', [
                'offer_reference' => $consent->offer->reference,
                'consent_reference' => $consent->reference,
                'consent_version' => $consent->consent_version,
                'statement_hash' => $consent->statement_hash,
            ]);
        }

        return to_route('x-change.enrollment.show', ['reference' => $reference])
            ->with('enrollment_receipt', $consent->reference);
    }

    private function activeOffer(string $reference): EnrollmentOffer
    {
        return EnrollmentOffer::query()->where('reference', $reference)->where('active', true)->firstOrFail();
    }
}
