<?php

namespace App\Http\Controllers\Assets;

use App\Actions\Assets\CreateReceivedAssetsAction;
use App\Actions\Assets\SaveReceivingBatchAction;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReceivedAssetsRequest;
use App\Http\Requests\LookupReceivingAssetRequest;
use App\Http\Requests\ReceiptHistoryRequest;
use App\Http\Requests\ReceiveAssetRequest;
use App\Http\Requests\SaveReceivingBatchRequest;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Statuslabel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetReceivingController extends Controller
{
    private function awaitingReceipt(): Builder
    {
        return Asset::pending()->whereNull('assigned_to')->whereNotIn('assets.id', DB::table('asset_receipts')->select('asset_id'));
    }

    private function visibleReceipts(): QueryBuilder
    {
        $query = DB::table('asset_receipts as ri')->join('asset_receipt_batches as rb', 'rb.id', '=', 'ri.batch_id');
        if (! auth()->user()->hasAccess('superuser')) {
            $query->whereIn('ri.asset_id', Asset::withTrashed()->select('assets.id'));
        }

        return $query;
    }

    public function index(ReceiptHistoryRequest $request): View
    {
        $search = trim((string) $request->validated('q', ''));
        $query = $this->visibleReceipts();
        if ($search !== '') {
            $matches = $this->visibleReceipts()->where(function ($q) use ($search) {
                foreach (['ri.serial', 'ri.asset_tag', 'ri.previous_tag', 'ri.model_name', 'ri.order_number', 'rb.receiver_name'] as $column) {
                    $q->orWhere($column, 'like', '%'.addcslashes($search, '%_\\').'%');
                }
                if (preg_match('/^(?:RCV-)?0*(\d+)$/i', $search, $match)) {
                    $q->orWhere('rb.id', (int) $match[1]);
                }
            })->select('ri.batch_id');
            $query->whereIn('rb.id', $matches);
        }
        $columns = ['rb.id', 'rb.receiver_name', 'rb.received_by', 'rb.created_at', 'rb.status_name', 'rb.location_name'];
        $batches = $query->select($columns)->selectRaw('COUNT(ri.id) AS item_count')->groupBy($columns)
            ->orderByDesc('rb.created_at')->orderByDesc('rb.id')->paginate(20)->withQueryString();

        return view('hardware.receive-tag', compact('batches', 'search'));
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);
        $this->authorize('view', Asset::class);

        return view('hardware.receive-batch-create', [
            'models' => AssetModel::whereHas('category', fn ($q) => $q->where('category_type', 'asset'))->orderBy('name')->get(['id', 'name', 'model_number']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'statuses' => Statuslabel::where('archived', 0)->orderBy('name')->get(),
            'defaultStatus' => Statuslabel::where('deployable', 1)->where('pending', 0)->where('archived', 0)->orderBy('id')->value('id'),
        ]);
    }

    public function storeNewBatch(CreateReceivedAssetsRequest $request): JsonResponse
    {
        $payload = CreateReceivedAssetsAction::run($request->user(), $request->validated());
        $payload['history_url'] = route('hardware.receive.index').'#receipt-batch-'.$payload['batch_id'];
        $payload['view_url'] = route('hardware.receive.show', ['batchId' => $payload['batch_id']]);
        $request->session()->flash('success', trans('presentation.receive_batch_saved', ['count' => $payload['item_count']]));

        return response()->json(Helper::formatStandardApiResponse('success', $payload, trans('presentation.receive_batch_saved', ['count' => $payload['item_count']])));
    }

    public function show(int $batchId): View
    {
        abort_unless(auth()->user()->can('update', Asset::class) || (auth()->user()->can('create', Asset::class) && auth()->user()->can('view', Asset::class)), 403);
        $items = $this->visibleReceipts()->where('rb.id', $batchId)->orderBy('ri.id')->get(['ri.*']);
        abort_if($items->isEmpty(), 404);
        $batch = DB::table('asset_receipt_batches')->where('id', $batchId)->first();
        $assetLinks = Asset::whereIn('id', $items->pluck('asset_id'))->pluck('id')->all();

        return view('hardware.receive-batch-show', compact('batch', 'items', 'assetLinks'));
    }

    public function lookup(LookupReceivingAssetRequest $request): JsonResponse
    {
        $matches = Asset::where('serial', trim($request->validated('serial')))->limit(2)->get();
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['serial' => trans('presentation.receive_serial_unmatched')]);
        }
        $asset = $matches->first();
        $this->authorize('update', $asset);
        if (! $this->awaitingReceipt()->whereKey($asset->id)->exists() || ($request->filled('order_number') && $asset->order_number !== $request->input('order_number'))) {
            throw ValidationException::withMessages(['serial' => trans('presentation.receive_not_waiting')]);
        }
        $token = Crypt::encryptString(json_encode([
            'id' => $asset->id, 'user' => $request->user()->id,
            'version' => hash('sha256', json_encode($asset->getRawOriginal())),
            'expires' => $request->boolean('for_batch') ? now()->addHours(4)->timestamp : now()->addMinutes(15)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return response()->json(Helper::formatStandardApiResponse('success', [
            'id' => $asset->id, 'serial' => $asset->serial, 'asset_tag' => $asset->asset_tag,
            'name' => $asset->name, 'model' => $asset->model?->name, 'receipt_token' => $token,
        ], trans('presentation.receive_matched')));
    }

    public function storeBatch(SaveReceivingBatchRequest $request): JsonResponse
    {
        $payload = SaveReceivingBatchAction::run($request->user(), $request->validated());
        $payload['history_url'] = route('hardware.receive.index').'#receipt-batch-'.$payload['batch_id'];
        $payload['view_url'] = route('hardware.receive.show', ['batchId' => $payload['batch_id']]);
        $request->session()->flash('success', trans('presentation.receive_batch_saved', ['count' => $payload['item_count']]));

        return response()->json(Helper::formatStandardApiResponse('success', $payload, trans('presentation.receive_batch_saved', ['count' => $payload['item_count']])));
    }

    public function store(ReceiveAssetRequest $request, int $assetId): JsonResponse
    {
        $data = $request->validated();
        $payload = SaveReceivingBatchAction::run($request->user(), [
            'request_key' => (string) Str::uuid(), 'status_id' => $data['status_id'], 'location_id' => $data['location_id'] ?? null,
            'items' => [array_merge($data, ['asset_id' => $assetId])],
        ]);
        $item = $payload['items'][0];
        $item['waiting'] = $this->awaitingReceipt()->count();
        $item['batch_id'] = $payload['batch_id'];

        return response()->json(Helper::formatStandardApiResponse('success', $item, trans('presentation.receive_saved')));
    }
}
