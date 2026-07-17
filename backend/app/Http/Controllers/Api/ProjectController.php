<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProjectController
 *
 * User's engineering projects. Scoped to the authenticated user's ownership.
 * Applicants list/create/view their own projects; each project is a container
 * for service applications (e.g. drawing approvals).
 */
class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::where('owner_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['projects' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar'     => ['required', 'string', 'max:255'],
            'name_en'     => ['nullable', 'string', 'max:255'],
            'type'        => ['nullable', 'string', 'max:50'],
            'area_m2'     => ['nullable', 'integer', 'min:1'],
            'city'        => ['nullable', 'string', 'max:100'],
            'contract_no' => ['nullable', 'string', 'max:50'],
        ]);

        $project = Project::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'owner_user_id'   => $request->user()->id,
            'status'          => 'pending',
        ]);

        return response()->json(['project' => $project], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = Project::where('owner_user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['project' => $project]);
    }
}
