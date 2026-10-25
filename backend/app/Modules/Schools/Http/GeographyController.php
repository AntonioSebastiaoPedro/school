<?php

namespace App\Modules\Schools\Http;

use App\Http\Controllers\Controller;
use App\Modules\Schools\Models\Country;
use App\Modules\Schools\Models\Municipality;
use App\Modules\Schools\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeographyController extends Controller
{
    public function countries(): JsonResponse
    {
        return response()->json(['data' => Country::query()->orderBy('name')->get()]);
    }

    public function provinces(Request $request): JsonResponse
    {
        $data = $request->validate(['country_id' => ['required', 'integer']]);

        return response()->json(['data' => Province::query()->where('country_id', $data['country_id'])->orderBy('name')->get()]);
    }

    public function municipalities(Request $request): JsonResponse
    {
        $data = $request->validate(['province_id' => ['required', 'integer']]);

        return response()->json(['data' => Municipality::query()->where('province_id', $data['province_id'])->orderBy('name')->get()]);
    }
}
