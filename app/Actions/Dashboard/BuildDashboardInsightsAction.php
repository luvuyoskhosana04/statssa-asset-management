<?php

namespace App\Actions\Dashboard;

use App\Models\Asset;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BuildDashboardInsightsAction
{
    public static function run(): array
    {
        $start = today();
        $end = $start->copy()->addDays(14);
        $data = ['start' => $start->toDateString(), 'end' => $end->copy()->subDay()->toDateString(), 'assets' => null, 'offboarding' => null];
        if (Gate::allows('view', User::class)) {
            $data['offboarding'] = User::where('end_date', '>=', $start)->where('end_date', '<', $end)->count();
        }
        if (! Gate::allows('view', Asset::class)) {
            return $data;
        }

        $active = Asset::notArchived();
        $due = (clone $active)->where('assigned_to', '>', 0)->where('expected_checkin', '>=', $start)->where('expected_checkin', '<', $end)->count();
        $overdue = (clone $active)->where('assigned_to', '>', 0)->where('expected_checkin', '<', $start)->count();
        $warranties = 0;
        foreach ((clone $active)->whereNotNull('purchase_date')->where('warranty_months', '>', 0)->select(['id', 'purchase_date', 'warranty_months'])->cursor() as $asset) {
            if ($asset->warranty_expires?->gte($start) && $asset->warranty_expires->lt($end)) {
                $warranties++;
            }
        }
        $total = (clone $active)->count();
        $deployed = (clone $active)->where('assigned_to', '>', 0)->count();
        $statusExpression = 'COALESCE(chart_status.name, ?)';
        $statusBindings = [trans('presentation.chart_unspecified')];
        if (Setting::getSettings()->dash_chart_type !== 'name') {
            $statusExpression = 'CASE WHEN chart_status.pending = 1 THEN ? WHEN chart_status.deployable = 1 THEN ? ELSE ? END';
            $statusBindings = [trans('general.pending'), trans('general.ready_to_deploy'), trans('general.undeployable')];
        }
        $statuses = (clone $active)->leftJoin('status_labels as chart_status', 'chart_status.id', '=', 'assets.status_id')
            ->selectRaw('CASE WHEN assets.assigned_to > 0 THEN ? WHEN EXISTS (SELECT 1 FROM project_asset WHERE project_asset.asset_id = assets.id) THEN ? ELSE '.$statusExpression.' END AS label, COUNT(*) AS count', array_merge([trans('general.deployed'), trans('projects.reserved_stock')], $statusBindings))
            ->groupBy('label')->orderByDesc('count')->get()->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])->all();

        $byCategory = (clone $active)->leftJoin('models as chart_model', 'chart_model.id', '=', 'assets.model_id')
            ->leftJoin('categories as chart_category', 'chart_category.id', '=', 'chart_model.category_id')
            ->selectRaw('chart_category.id AS group_id, chart_category.name AS label, COUNT(*) AS count, SUM(CASE WHEN assets.assigned_to IS NULL AND NOT EXISTS (SELECT 1 FROM project_asset WHERE project_asset.asset_id = assets.id) AND assets.status_id IN (SELECT id FROM status_labels WHERE deployable = 1 AND pending = 0 AND archived = 0 AND deleted_at IS NULL) THEN 1 ELSE 0 END) AS available')
            ->groupBy('chart_category.id', 'chart_category.name')->orderByDesc('count')->get()
            ->map(fn ($row) => ['id' => $row->group_id, 'label' => $row->label ?? trans('presentation.chart_unspecified'), 'count' => (int) $row->count, 'available' => (int) $row->available])->all();
        $locations = (clone $active)->leftJoin('locations as chart_location', 'chart_location.id', '=', 'assets.location_id')
            ->selectRaw('chart_location.id AS group_id, chart_location.name AS label, COUNT(*) AS count')
            ->groupBy('chart_location.id', 'chart_location.name')->orderByDesc('count')->get()
            ->map(fn ($row) => ['id' => $row->group_id, 'label' => $row->label ?? trans('presentation.chart_unspecified'), 'count' => (int) $row->count])->all();

        $data['assets'] = [
            'due' => $due, 'overdue' => $overdue, 'warranties' => $warranties,
            'total' => $total, 'deployed' => $deployed, 'statuses' => $statuses,
            'categories' => $byCategory, 'locations' => $locations,
            'costs' => self::purchaseCosts(),
        ];

        return $data;
    }

    private static function purchaseCosts(): array
    {
        $lastPurchase = OrderItem::where('item_type', Asset::class)->where('qty', '>', 0)
            ->whereHas('order')->selectRaw('item_id, MAX(id) AS line_id')->groupBy('item_id');
        $query = Asset::notArchived()->leftJoinSub($lastPurchase, 'chart_purchase', 'chart_purchase.item_id', '=', 'assets.id')
            ->leftJoin('order_items as chart_line', 'chart_line.id', '=', 'chart_purchase.line_id')
            ->leftJoin('orders as chart_order', 'chart_order.id', '=', 'chart_line.order_id')
            ->leftJoin('locations as cost_location', 'cost_location.id', '=', 'assets.location_id')
            ->leftJoin('models as cost_model', 'cost_model.id', '=', 'assets.model_id')
            ->leftJoin('manufacturers as cost_maker', 'cost_maker.id', '=', 'cost_model.manufacturer_id');
        $currency = 'COALESCE(NULLIF(chart_order.currency, ?), NULLIF(cost_location.currency, ?), ?)';
        $bindings = ['', '', Setting::getSettings()->default_currency ?: trans('presentation.chart_currency_unspecified')];
        $amount = 'COALESCE(chart_line.price, assets.purchase_cost)';
        $totals = (clone $query)->whereRaw($amount.' IS NOT NULL')->selectRaw($currency.' AS currency, SUM('.$amount.') AS amount, COUNT(*) AS priced', $bindings)
            ->groupBy('currency')->orderBy('currency')->get()->map(fn ($row) => ['currency' => $row->currency, 'amount' => (float) $row->amount, 'priced' => (int) $row->priced])->all();
        $groups = [
            'location' => ['cost_location.id', 'cost_location.name'],
            'manufacturer' => ['cost_maker.id', 'cost_maker.name'],
        ];
        if (Gate::allows('view', User::class)) {
            $query->leftJoin('users as cost_user', function ($join) {
                $join->on('cost_user.id', '=', 'assets.assigned_to')->where('assets.assigned_type', '=', User::class);
            })->leftJoin('departments as cost_department', 'cost_department.id', '=', 'cost_user.department_id');
            $groups['department'] = ['cost_department.id', 'cost_department.name'];
        }
        $purchaseDate = 'COALESCE(chart_order.purchase_date, assets.purchase_date)';
        $period = "CASE WHEN YEAR($purchaseDate) > 0 AND MONTH($purchaseDate) BETWEEN 1 AND 12 THEN DATE_FORMAT($purchaseDate, '%Y-%m-01') ELSE NULL END";
        $timeline = (clone $query)->whereRaw($amount.' IS NOT NULL')
            ->selectRaw($currency.' AS currency, '.$period.' AS period, SUM('.$amount.') AS amount, COUNT(*) AS priced', $bindings)
            ->groupBy('currency', 'period')->orderBy('period')->get()
            ->map(fn ($row) => ['currency' => $row->currency, 'period' => $row->period, 'amount' => (float) $row->amount, 'priced' => (int) $row->priced])->all();
        $result = ['totals' => $totals, 'timeline' => $timeline];
        foreach ($groups as $key => [$id, $label]) {
            $result[$key] = (clone $query)->whereRaw($amount.' IS NOT NULL')
                ->selectRaw($currency.' AS currency, '.$label.' AS label, '.$id.' AS group_id, SUM('.$amount.') AS amount', $bindings)
                ->groupBy('currency', $id, $label)->orderByDesc('amount')->get()
                ->map(fn ($row) => ['currency' => $row->currency, 'id' => $row->group_id, 'label' => $row->label ?? trans('presentation.chart_unspecified'), 'amount' => (float) $row->amount])->all();
        }

        return $result;
    }
}
