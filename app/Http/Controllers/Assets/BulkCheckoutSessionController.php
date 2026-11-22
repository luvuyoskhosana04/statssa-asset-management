<?php

namespace App\Http\Controllers\Assets;

use App\Actions\BulkCheckout\BuildBulkCheckoutInventoryAction;
use App\Actions\BulkCheckout\CommitBulkCheckoutAction;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkCheckoutCatalogRequest;
use App\Http\Requests\BulkCheckoutSessionRequest;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Project;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BulkCheckoutSessionController extends Controller
{
    public function index(): View
    {
        abort_unless(Gate::allows('checkout', Asset::class) || Gate::allows('checkout', Accessory::class), 403);

        return view('hardware.bulk-checkout', [
            'locations' => Location::orderBy('name')->pluck('name', 'id'),
            'projects' => Gate::allows('view', Project::class) ? Project::whereIn('status', ['active', 'planned'])->whereDate('end_date', '>=', today())->orderBy('name')->get() : collect(),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'statuses' => ['' => trans('general.do_not_change')] + Statuslabel::where('deployable', 1)->where('archived', 0)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function catalog(BulkCheckoutCatalogRequest $request): JsonResponse
    {
        $input = $request->validated();
        $rows = BuildBulkCheckoutInventoryAction::run($request->user(), $input);
        if (! empty($input['scan']) && (count($rows) !== 1 || ! $rows[0]['remaining'])) {
            throw ValidationException::withMessages(['scan' => trans('bulk_checkout.scan_unavailable')]);
        }

        return response()->json(Helper::formatStandardApiResponse('success', ['rows' => $rows], null));
    }

    public function context(BulkCheckoutCatalogRequest $request): JsonResponse
    {
        $input = $request->validated();
        $class = ['user' => User::class, 'location' => Location::class, 'project' => Project::class][$input['mode'] ?? 'user'];
        $target = $class::findOrFail($input['target_id'] ?? 0);
        Gate::authorize('view', $target);
        $rows = [];
        if (Gate::allows('view', Asset::class)) {
            $assets = $target instanceof Project ? $target->assets()->with('model')->limit(100)->get() : Asset::where('assigned_type', $class)->where('assigned_to', $target->id)->with('model')->limit(100)->get();
            foreach ($assets as $asset) {
                if (Gate::allows('view', $asset)) {
                    $rows[] = ['kind' => 'asset', 'name' => $asset->name ?: $asset->model?->name, 'tag' => $asset->asset_tag, 'quantity' => 1];
                }
            }
        }
        if (Gate::allows('view', Accessory::class)) {
            $checkouts = AccessoryCheckout::where('assigned_type', $class)->where('assigned_to', $target->id)->select('accessory_id')->selectRaw('COUNT(*) as units')->groupBy('accessory_id')->with('accessory')->limit(100)->get();
            foreach ($checkouts as $checkout) {
                if ($checkout->accessory && Gate::allows('view', $checkout->accessory)) {
                    $rows[] = ['kind' => 'accessory', 'name' => $checkout->accessory->name, 'tag' => null, 'quantity' => (int) $checkout->units];
                }
            }
        }

        return response()->json(Helper::formatStandardApiResponse('success', ['rows' => $rows], null));
    }

    public function store(BulkCheckoutSessionRequest $request): JsonResponse
    {
        $batch = CommitBulkCheckoutAction::run($request->user(), $request->validated());

        return response()->json(Helper::formatStandardApiResponse('success', [
            'id' => $batch->id, 'mode' => $batch->mode, 'target' => $batch->target_name,
            'items' => json_decode($batch->items, true), 'receipt_url' => route('hardware.bulkcheckout.receipt', $batch->id),
        ], trans('bulk_checkout.saved.'.$batch->mode)));
    }

    public function receipt(string $batch): View
    {
        $record = DB::table('bulk_checkout_batches')->where('id', $batch)->first();
        abort_unless($record && ((int) $record->created_by === (int) auth()->id() || auth()->user()->isSuperUser()), 404);
        abort_unless(Gate::allows('checkout', Asset::class) || Gate::allows('checkout', Accessory::class), 403);

        return view('hardware.bulk-checkout-receipt', ['batch' => $record, 'items' => json_decode($record->items, true)]);
    }
}
