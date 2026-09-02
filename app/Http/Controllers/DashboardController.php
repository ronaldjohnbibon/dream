<?php

namespace App\Http\Controllers;

use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $summary = null;

        if ($request->user()->is_admin) {
            $summary = [
                'users' => User::query()->count(),
                'administrators' => User::query()->where('is_admin', true)->count(),
            ];
        }

        return Inertia::render('Dashboard', [
            'summary' => $summary,
        ]);
    }
}
