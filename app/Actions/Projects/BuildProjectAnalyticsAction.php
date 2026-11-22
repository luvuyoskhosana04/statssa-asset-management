<?php

namespace App\Actions\Projects;

use App\Models\Asset;
use App\Models\OrderItem;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class BuildProjectAnalyticsAction
{
    public static function run(?string $requestedCurrency = null): array
    {
        $projects = Project::with(['assets.location', 'assets.status'])->orderBy('name')->get();
        $assets = $projects->flatMap->assets->unique('id');
        $purchases = OrderItem::with('order')->where('item_type', Asset::class)->whereIn('item_id', $assets->pluck('id')->all())
            ->where('qty', '>', 0)->whereHas('order')->orderByDesc('id')->get()->unique('item_id')->keyBy('item_id');
        $pending = DB::table('project_issues as i')->join('project_issue_sessions as s', 's.id', '=', 'i.session_id')
            ->join('checkout_acceptances as a', 'a.id', '=', 'i.acceptance_id')
            ->whereIn('s.project_id', $projects->modelKeys())->whereIn('i.asset_id', $assets->pluck('id')->all())
            ->whereNull('i.cancelled_at')->whereNull('a.accepted_at')->whereNull('a.declined_at')
            ->select('s.project_id', 'i.asset_id')->get()->mapWithKeys(fn ($row) => [$row->project_id.':'.$row->asset_id => true]);
        $defaultCurrency = Setting::getSettings()->default_currency ?: trans('presentation.chart_currency_unspecified');
        $currencies = [];
        $stages = ['stock' => 0, 'awaiting' => 0, 'issued' => 0, 'other' => 0];
        $rows = [];
        $pendingReturns = 0;
        $overdue = 0;
        $missingCosts = 0;
        foreach ($projects as $project) {
            $row = ['name' => $project->name, 'url' => route('projects.show', $project), 'amounts' => [], 'risk' => [], 'assets' => $project->assets->count()];
            foreach ($project->assets as $asset) {
                $purchase = $purchases->get($asset->id);
                $currency = $purchase?->order?->currency ?: ($asset->location?->currency ?: $defaultCurrency);
                $currencies[$currency] = true;
                $price = $purchase?->price ?? $asset->purchase_cost;
                $cents = $price === null ? 0 : (int) round((float) $price * 100);
                $missingCosts += $price === null ? 1 : 0;
                $row['amounts'][$currency] = ($row['amounts'][$currency] ?? 0) + $cents;
                $awaiting = $pending->has($project->id.':'.$asset->id);
                $issued = (bool) $asset->assigned_to && ! $awaiting;
                $stage = $awaiting ? 'awaiting' : ($issued ? 'issued' : ((int) $asset->status_id === (int) $project->reservation_status_id ? 'stock' : 'other'));
                $stages[$stage]++;
                if ($issued) {
                    $pendingReturns++;
                    if ($project->end_date->lt(today())) {
                        $overdue++;
                        $row['risk'][$currency] = ($row['risk'][$currency] ?? 0) + $cents;
                    }
                }
            }
            $rows[] = $row;
        }
        if (! $currencies) {
            $currencies[$defaultCurrency] = true;
        }
        $currencies = array_keys($currencies);
        sort($currencies);
        $currency = in_array($requestedCurrency, $currencies, true) ? $requestedCurrency : (in_array($defaultCurrency, $currencies, true) ? $defaultCurrency : $currencies[0]);
        foreach ($rows as &$row) {
            $row['value'] = $row['amounts'][$currency] ?? 0;
            $row['at_risk'] = $row['risk'][$currency] ?? 0;
            unset($row['amounts'], $row['risk']);
        }
        unset($row);
        usort($rows, fn ($a, $b) => $b['value'] <=> $a['value']);

        return ['currency' => $currency, 'currencies' => $currencies, 'projects' => count($rows), 'assets' => $assets->count(),
            'value' => array_sum(array_column($rows, 'value')), 'at_risk' => array_sum(array_column($rows, 'at_risk')),
            'pending_returns' => $pendingReturns, 'overdue' => $overdue, 'missing_costs' => $missingCosts,
            'stages' => $stages, 'rows' => $rows];
    }
}
