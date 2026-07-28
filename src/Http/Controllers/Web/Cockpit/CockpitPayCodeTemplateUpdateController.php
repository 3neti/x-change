<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Cockpit\SavePayCodeTemplate;
use LBHurtado\XChange\Http\Requests\Cockpit\UpdatePayCodeTemplateRequest;
use LBHurtado\XChange\Models\PayCodeTemplate;

class CockpitPayCodeTemplateUpdateController extends Controller
{
    public function __invoke(
        UpdatePayCodeTemplateRequest $request,
        PayCodeTemplate $template,
        SavePayCodeTemplate $save,
    ): RedirectResponse {
        $owner = $request->user();

        abort_unless($owner instanceof Model, 403);

        $save->handle($owner, $request->validated(), $template);

        return back()->with('success', 'Pay Code template updated.');
    }
}
