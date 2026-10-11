<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): CurrentUserResource
    {
        return new CurrentUserResource($request->user());
    }

    public function updateLocale(UpdateLocaleRequest $request): CurrentUserResource
    {
        $user = $request->user();
        $user->locale = $request->validated('locale');
        $user->save();

        return new CurrentUserResource($user);
    }
}
