<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppearanceUpdateRequest;
use Illuminate\Http\RedirectResponse;

class AppearanceController extends Controller
{
    /**
     * Update the user's appearance preference.
     */
    public function update(AppearanceUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'appearance' => $request->validated()['appearance'],
        ]);

        return back();
    }
}
