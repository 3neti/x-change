<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Tighten\Ziggy\Ziggy;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => function () use ($user) {
                    if (!$user) {
                        return null;
                    }

                    // Eager load necessary relationships
//                    $user->load(['currentCampaign']);

                    return array_merge($user->toArray(), [
                        'balanceFloat' => (float) $user->balanceFloat,
                        'balanceUpdatedAt' => $user->updated_at,
                        'mobile' => $user->mobile
                            ? phone($user->mobile, 'PH')->formatForMobileDialingInCountry('PH')
                            : null,
                        'webhook' => $user->webhook,
                        'merchant' => $user->merchant?->toArray(),
                        'wallet' => (fn() => $user->wallet ? [
                            'id' => $user->wallet->getKey(),
                            'type' => $user->wallet->type,
                        ] : null)(),
                    ]);
                },
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'warning' => fn () => $request->session()->get('warning'),
                'data' => fn () => $request->session()->get('data'),
                'event' => fn () => $request->session()->get('event'),
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
