<?php

namespace App\Http\Controllers;

use App\Support\ApiUptime;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function index(ApiUptime $apiUptime): View
    {
        return view('welcome', [
            'status' => $apiUptime->snapshot(),
        ]);
    }

    public function uptime(ApiUptime $apiUptime): JsonResponse
    {
        return response()->json($apiUptime->snapshot());
    }
}
