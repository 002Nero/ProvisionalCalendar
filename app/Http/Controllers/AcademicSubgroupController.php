<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use App\Models\Subgroup;

class SubgroupController extends Controller
{
    public function index()
    {
        $subgroups = Subgroup::with(['Group.Promotion'])->get();

        if (request()->wantsJson()) {
            return new JsonResponse($subgroups);
        }

        return Inertia::render('GroupPage', [
            'subgroups' => $subgroups
        ]);

    }
}
