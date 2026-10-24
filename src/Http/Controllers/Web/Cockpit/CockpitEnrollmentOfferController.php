<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Models\EnrollmentOffer;

final class CockpitEnrollmentOfferController extends Controller
{
    public function index(Request $request): Response
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model && $owner->exists, 403);

        $offers = EnrollmentOffer::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey())
            ->withCount('consents')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (EnrollmentOffer $offer): array => [
                'reference' => $offer->reference,
                'title' => $offer->title,
                'consent_version' => $offer->consent_version,
                'active' => $offer->active,
                'consents_count' => $offer->consents_count,
                'public_url' => route('x-change.enrollment.show', ['reference' => $offer->reference]),
            ])->all();

        return Inertia::render('x-change/cockpit/EnrollmentOffers', [
            'offers' => $offers,
            'store_url' => route('x-change.cockpit.enrollments.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model && $owner->exists, 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'consent_statement' => ['required', 'string', 'min:20', 'max:2000'],
            'consent_version' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]{1,40}$/'],
        ]);

        EnrollmentOffer::query()->create([
            ...$validated,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'active' => true,
        ]);

        return to_route('x-change.cockpit.enrollments.index')->with('enrollment_notice', 'Enrollment link is ready to share.');
    }
}
