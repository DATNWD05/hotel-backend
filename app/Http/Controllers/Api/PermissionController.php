<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Permission;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::all();
        return response()->json($permissions);
    }
}
