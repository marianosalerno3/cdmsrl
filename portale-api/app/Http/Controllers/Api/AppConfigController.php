<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(AppConfig::payload());
    }
}
