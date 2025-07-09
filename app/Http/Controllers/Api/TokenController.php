<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** @deprecated  */
class TokenController extends Controller
{
    public function store(Request $request)
    {
        // (Optional) revoke any previous “api-token” so you don’t accumulate stale tokens
        $request->user()->tokens()->where('name', 'api-token')->delete();

        // create a new one
        $token = $request->user()
            ->createToken('api-token')  // name it however you like
            ->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }
}
