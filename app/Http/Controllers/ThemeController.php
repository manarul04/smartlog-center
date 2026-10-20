<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(['dark', 'light'])],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ]);

        $request->user()->forceFill(['theme' => $data['theme']])->save();

        $target = $data['redirect'] ?? url()->previous();

        // Only allow same-host redirects so the form cannot be used to bounce
        // authenticated users to an external site.
        $host = parse_url($target, PHP_URL_HOST);
        if ($host !== null && $host !== $request->getHost()) {
            $target = route('overview');
        }

        return redirect($target);
    }
}
