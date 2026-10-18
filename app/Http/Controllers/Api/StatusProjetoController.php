<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StatusProjeto;
use Illuminate\Http\JsonResponse;

class StatusProjetoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(StatusProjeto::orderBy('ordem')->orderBy('id')->get());
    }
}
