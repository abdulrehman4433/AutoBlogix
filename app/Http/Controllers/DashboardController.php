<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    /**
     * Show the dashboard with real, user-scoped metrics.
     */
    public function index(Request $request): View
    {
        return view('dashboard.index', [
            'metrics' => $this->dashboardService->metricsFor($request->user()),
        ]);
    }
}
