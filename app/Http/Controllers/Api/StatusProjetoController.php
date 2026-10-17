<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Statu;
use Illuminate\Http\JsonResponse;

class StatusProjetoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Statu::orderBy('ordem')->orderBy('id')->get());
    }
}
