<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Persist the requested locale and redirect back.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supportedLocales = config('app.supported_locales', []);

        abort_unless(in_array($locale, $supportedLocales, true), 404);

        session(['locale' => $locale]);
        app()->setLocale($locale);

        return redirect()->back();
    }
}
