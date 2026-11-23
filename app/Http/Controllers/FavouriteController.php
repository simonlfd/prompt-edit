<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavouriteController extends Controller
{
    public function update(Request $request, Generation $generation): RedirectResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);

        $data = $request->validate(['favourite' => ['required', 'boolean']]);

        // Set rather than toggle, so a double click or a retried request can't flip it back.
        $generation->forceFill(['is_favourite' => $data['favourite']])->save();

        return back();
    }
}
