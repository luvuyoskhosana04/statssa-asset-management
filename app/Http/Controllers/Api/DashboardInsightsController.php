<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\BuildDashboardInsightsAction;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardInsightsController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(Gate::allows('view', Asset::class) || Gate::allows('view', User::class), 403);

        return response()->json(Helper::formatStandardApiResponse('success', BuildDashboardInsightsAction::run(), null));
    }
}
