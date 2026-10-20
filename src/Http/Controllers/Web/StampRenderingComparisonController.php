<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\Stamp\StampCatalog;

final class StampRenderingComparisonController
{
    public function __invoke(StampCatalog $catalog): Response
    {
        abort_unless(app()->environment('local') && config('x-change.experience.stamp_rendering.comparison_enabled', false), 404);

        return Inertia::render('x-change/stamp/RenderingComparison', ['designs' => $catalog->options()]);
    }
}
