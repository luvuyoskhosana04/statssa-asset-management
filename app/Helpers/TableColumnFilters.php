<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Column menus share the listing's authorized query, before pagination. */
class TableColumnFilters
{
    public static function columns(Builder $query): array
    {
        $table = $query->getModel()->getTable();
        $prefix = DB::getTablePrefix();
        $base = $prefix.$table;
        $columns = [];
        $add = function (string $field, string $type = 'text', ?string $expression = null) use (&$columns, $base): void {
            $columns[$field] = ['type' => $type, 'expression' => $expression ?? $base.'.'.$field];
        };
        $relation = function (string $field, string $target, string $foreign, string $label = 'name') use ($add, $base, $prefix): void {
            $related = $prefix.$target;
            $add($field, 'text', "(SELECT cf_related.$label FROM $related AS cf_related WHERE cf_related.id = $base.$foreign LIMIT 1)");
        };
        // Only explicitly listed, non-sensitive display columns can be queried.
        $standard = [
            'assets' => ['asset_tag', 'serial', 'order_number'],
            'licenses' => ['name', 'license_name', 'license_email', 'order_number'],
            'accessories' => ['name', 'model_number'],
            'consumables' => ['name', 'model_number', 'item_no'],
            'components' => ['name', 'serial', 'model_number'],
            'users' => ['first_name', 'last_name', 'username', 'email', 'employee_num', 'jobtitle'],
            'maintenances' => ['name', 'asset_maintenance_type'],
            'models' => ['name', 'model_number'],
            'locations' => ['name', 'address', 'city', 'state', 'country', 'zip'],
            'companies' => ['name'], 'departments' => ['name'], 'categories' => ['name', 'category_type'],
            'manufacturers' => ['name', 'url'], 'suppliers' => ['name', 'email', 'phone'],
            'status_labels' => ['name'], 'depreciations' => ['name'], 'permission_groups' => ['name'],
            'kits' => ['name'], 'action_logs' => ['action_type', 'note'],
        ];
        if (!isset($standard[$table])) {
            return [];
        }
        foreach ($standard[$table] as $field) {
            $add($field);
        }
        if ($table === 'assets') {
            $modelName = "(SELECT cf_model.name FROM {$prefix}models AS cf_model WHERE cf_model.id = $base.model_id LIMIT 1)";
            $add('name', 'text', "COALESCE(NULLIF($base.name, ''), $modelName)");
            $relation('model', 'models', 'model_id');
            $relation('location', 'locations', 'location_id');
            $relation('rtd_location', 'locations', 'rtd_location_id');
            $status = "(SELECT cf_status.name FROM {$prefix}status_labels AS cf_status WHERE cf_status.id = $base.status_id LIMIT 1)";
            $add('status', 'text', "CASE WHEN $base.assigned_to IS NOT NULL THEN 'Deployed' ELSE $status END");
            $add('assigned_to', 'text', "CASE $base.assigned_type WHEN 'App\\\\Models\\\\User' THEN (SELECT COALESCE(NULLIF(cf_user.display_name,''), CONCAT_WS(' ', cf_user.first_name, cf_user.last_name)) FROM {$prefix}users cf_user WHERE cf_user.id = $base.assigned_to) WHEN 'App\\\\Models\\\\Location' THEN (SELECT cf_loc.name FROM {$prefix}locations cf_loc WHERE cf_loc.id = $base.assigned_to) WHEN 'App\\\\Models\\\\Asset' THEN (SELECT COALESCE(NULLIF(cf_asset.name,''), cf_asset.asset_tag) FROM {$prefix}assets cf_asset WHERE cf_asset.id = $base.assigned_to) END");
            foreach (['purchase_date', 'expected_checkin', 'next_audit_date', 'last_audit_date', 'last_checkout', 'last_checkin', 'asset_eol_date'] as $field) {
                $add($field, 'date');
            }
            $add('purchase_cost', 'number');
        }
        if (in_array($table, ['assets', 'licenses', 'accessories', 'consumables', 'components', 'departments'], true)) {
            $relation('company', 'companies', 'company_id');
        }
        if (in_array($table, ['licenses', 'accessories', 'consumables', 'components', 'models'], true)) {
            $relation('category', 'categories', 'category_id');
            $relation('manufacturer', 'manufacturers', 'manufacturer_id');
        }
        if (in_array($table, ['accessories', 'consumables', 'components'], true)) {
            $relation('location', 'locations', 'location_id');
            $add('qty', 'number');
            $add('min_amt', 'number');
            $checkedOut = match ($table) {
                'accessories' => "(SELECT COUNT(*) FROM {$prefix}accessories_checkout cf_checkout WHERE cf_checkout.accessory_id = $base.id)",
                'consumables' => "(SELECT COUNT(*) FROM {$prefix}consumables_users cf_checkout WHERE cf_checkout.consumable_id = $base.id)",
                'components' => "(SELECT COALESCE(SUM(cf_checkout.assigned_qty),0) FROM {$prefix}components_assets cf_checkout WHERE cf_checkout.component_id = $base.id)",
            };
            $remaining = "$base.qty - $checkedOut";
            $add('remaining', 'number', $remaining);
            $out = DB::getPdo()->quote(trans('presentation.out_of_stock'));
            $low = DB::getPdo()->quote(trans('presentation.low_stock'));
            $in = DB::getPdo()->quote(trans('presentation.in_stock'));
            $add('presentation_stock_state', 'text', "CASE WHEN ($remaining) <= 0 THEN $out WHEN $base.min_amt IS NOT NULL AND ($remaining) <= $base.min_amt THEN $low ELSE $in END");
        }
        if ($table === 'licenses') {
            $add('seats', 'number');
            $free = "(SELECT COUNT(*) FROM {$prefix}license_seats cf_seat WHERE cf_seat.license_id = $base.id AND cf_seat.assigned_to IS NULL AND cf_seat.asset_id IS NULL AND cf_seat.deleted_at IS NULL)";
            $unreassignable = "(CASE WHEN $base.reassignable = 1 THEN 0 ELSE (SELECT COUNT(*) FROM {$prefix}license_seats cf_locked WHERE cf_locked.license_id = $base.id AND cf_locked.unreassignable_seat = 1 AND cf_locked.deleted_at IS NULL) END)";
            $available = "($free - $unreassignable)";
            $add('free_seats_count', 'number', $available);
            $add('presentation_assigned_seats', 'number', "GREATEST(0, $base.seats - $available)");
            $add('expiration_date', 'date');
            $add('purchase_date', 'date');
            $add('purchase_cost', 'number');
        }
        if ($table === 'users') {
            $add('name', 'text', "CONCAT_WS(' ', $base.first_name, $base.last_name)");
            $relation('department', 'departments', 'department_id');
            $relation('location', 'locations', 'location_id');
            $add('activated', 'number');
        }
        if ($table === 'maintenances') {
            $relation('asset_tag', 'assets', 'item_id', 'asset_tag');
            $relation('asset_name', 'assets', 'item_id');
            $add('responsible_party', 'text', "(SELECT CONCAT_WS(' ', cf_person.first_name, cf_person.last_name) FROM {$prefix}users cf_person WHERE cf_person.id = $base.responsible_party_id LIMIT 1)");
            $relation('supplier', 'suppliers', 'supplier_id');
            foreach (['start_date', 'expected_completion_date', 'completed_at'] as $field) {
                $add($field, 'date');
            }
            $add('cost', 'number');
            $complete = DB::getPdo()->quote(trans('presentation.completed'));
            $unscheduled = DB::getPdo()->quote(trans('presentation.unscheduled'));
            $overdue = DB::getPdo()->quote(trans('presentation.overdue'));
            $scheduled = DB::getPdo()->quote(trans('presentation.scheduled'));
            $today = DB::getPdo()->quote(today()->toDateString());
            $add('presentation_job_state', 'text', "CASE WHEN $base.completed_at IS NOT NULL THEN $complete WHEN $base.expected_completion_date IS NULL THEN $unscheduled WHEN $base.expected_completion_date < $today THEN $overdue ELSE $scheduled END");
        }
        if ($table === 'action_logs') {
            $add('created_at', 'date');
            $add('created_by', 'text', "(SELECT COALESCE(NULLIF(cf_person.display_name,''), CONCAT_WS(' ', cf_person.first_name, cf_person.last_name)) FROM {$prefix}users cf_person WHERE cf_person.id = $base.created_by LIMIT 1)");
            $relation('location', 'locations', 'location_id');
        }
        if ($table === 'depreciations') {
            $add('months', 'number');
        }
        return $columns;
    }

    public static function handle(Builder $query, Request $request): ?array
    {
        if (!$request->hasAny(['column_filters', 'column_filter_meta', 'column_filter_field'])) {
            return null;
        }
        $columns = self::columns($query);
        if ($request->boolean('column_filter_meta')) {
            return ['columns' => array_map(fn ($column) => ['type' => $column['type']], $columns)];
        }
        $filters = $request->input('column_filters', '{}');
        $filters = is_string($filters) ? json_decode($filters, true) : $filters;
        if (!is_array($filters) || count($filters) > 30) {
            self::invalid();
        }
        $facet = $request->input('column_filter_field');
        if ($facet !== null && (!is_string($facet) || !isset($columns[$facet]))) {
            self::invalid();
        }
        foreach ($filters as $field => $filter) {
            if (!isset($columns[$field]) || !is_array($filter)) {
                self::invalid();
            }
            // A menu retains all of its alternatives while other columns narrow them.
            if ($field !== $facet) {
                self::apply($query, $columns[$field], $filter);
            }
        }
        if ($facet === null) {
            return null;
        }
        $expression = $columns[$facet]['expression'];
        $source = (clone $query)->withoutEagerLoads()->reorder()->selectRaw("$expression AS column_value");
        // Replace existing select/withCount columns, never load listing records or keys.
        $source->getQuery()->columns = [DB::raw("$expression AS column_value")];
        $source->getQuery()->setBindings([], 'select');
        $values = DB::query()->fromSub($source, 'column_source')
            ->selectRaw("COALESCE(CAST(column_value AS CHAR), '') AS value")->distinct();
        $search = $request->input('column_filter_search') ?? '';
        if (!is_string($search) || mb_strlen($search) > 200) {
            self::invalid();
        }
        if ($search !== '') {
            $values->whereRaw("COALESCE(CAST(column_value AS CHAR), '') LIKE ?", ['%'.self::escapeLike($search).'%']);
        }
        $offset = max(0, min(100000, (int) $request->input('column_filter_offset', 0)));
        if ($columns[$facet]['type'] === 'number') {
            $values->orderByRaw('CAST(value AS DECIMAL(30,10))');
        }
        $rows = $values->orderBy('value')->offset($offset)->limit(101)->pluck('value');
        return ['values' => $rows->take(100)->values()->all(), 'more' => $rows->count() > 100];
    }

    /** For queues whose authorization is applied to a collection before pagination. */
    public static function handleRows(array $rows, array $columns, Request $request): array
    {
        if ($request->boolean('column_filter_meta')) {
            return ['columns' => array_map(fn ($type) => ['type' => $type], $columns)];
        }
        $filters = $request->input('column_filters', '{}');
        $filters = is_string($filters) ? json_decode($filters, true) : $filters;
        $facet = $request->input('column_filter_field');
        if (!is_array($filters) || count($filters) > 30 || ($facet !== null && (!is_string($facet) || !isset($columns[$facet])))) {
            self::invalid();
        }
        foreach ($filters as $field => $filter) {
            if (!isset($columns[$field]) || !is_array($filter)) {
                self::invalid();
            }
            self::validateFilter($columns[$field], $filter);
        }
        $read = function ($row, $field): string {
            $value = data_get($row, $field);
            if (is_array($value)) {
                $value = $value['name'] ?? $value['datetime'] ?? $value['date'] ?? '';
            }
            return html_entity_decode((string) ($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };
        $result = collect($rows)->filter(function ($row) use ($filters, $facet, $columns, $read) {
            foreach ($filters as $field => $filter) {
                if ($field === $facet) {
                    continue;
                }
                $a = $read($row, $field);
                $op = $filter['op'] ?? 'in';
                $value = $filter['value'] ?? '';
                $matched = match ($op) {
                    'in' => in_array($a, $filter['values'] ?? [], true),
                    'not_in' => !in_array($a, $filter['values'] ?? [], true),
                    'blank' => $a === '', 'not_blank' => $a !== '',
                    'contains' => str_contains(mb_strtolower($a), mb_strtolower($value)),
                    'not_contains' => !str_contains(mb_strtolower($a), mb_strtolower($value)),
                    default => null,
                };
                if ($matched === null) {
                    if ($a === '') {
                        return false;
                    }
                    $a = $columns[$field] === 'number' ? (float) $a : ($columns[$field] === 'date' ? substr($a, 0, 10) : mb_strtolower($a));
                    $b = $columns[$field] === 'number' ? (float) $value : mb_strtolower($value);
                    $matched = match ($op) {
                        'eq' => $a === $b, 'neq' => $a !== $b, 'gt' => $a > $b,
                        'gte' => $a >= $b, 'lt' => $a < $b, 'lte' => $a <= $b,
                    };
                }
                if (!$matched) {
                    return false;
                }
            }
            return true;
        })->values();
        if ($facet !== null) {
            $search = $request->input('column_filter_search') ?? '';
            if (!is_string($search) || mb_strlen($search) > 200) {
                self::invalid();
            }
            $values = $result->map(fn ($row) => $read($row, $facet))->unique()->filter(fn ($value) => str_contains(mb_strtolower($value), mb_strtolower($search)))->values()->all();
            $columns[$facet] === 'number' ? sort($values, SORT_NUMERIC) : natcasesort($values);
            $offset = max(0, min(100000, (int) $request->input('column_filter_offset', 0)));
            return ['values' => array_slice(array_values($values), $offset, 100), 'more' => count($values) > $offset + 100];
        }
        $offset = max(0, (int) $request->input('offset', 0));
        $limit = max(1, min(500, (int) $request->input('limit', 50)));
        return ['total' => $result->count(), 'rows' => $result->slice($offset, $limit)->values()->all()];
    }

    private static function validateFilter(string $type, array $filter): void
    {
        $op = $filter['op'] ?? 'in';
        $values = $filter['values'] ?? [];
        if (!is_string($op) || !in_array($op, ['in', 'not_in', 'blank', 'not_blank', 'contains', 'not_contains', 'eq', 'neq', 'gt', 'gte', 'lt', 'lte'], true)
            || !is_array($values) || count($values) > 500 || array_filter($values, fn ($value) => !is_string($value) || mb_strlen($value) > 2000)) {
            self::invalid();
        }
        if (in_array($op, ['in', 'not_in', 'blank', 'not_blank'], true)) {
            return;
        }
        $value = $filter['value'] ?? null;
        if (!is_string($value) || $value === '' || mb_strlen($value) > 2000) {
            self::invalid();
        }
        if (in_array($op, ['contains', 'not_contains'], true)) {
            return;
        }
        if (($type === 'text' && !in_array($op, ['eq', 'neq'], true))
            || ($type === 'number' && !is_numeric($value))
            || ($type === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))) {
            self::invalid();
        }
    }

    private static function apply(Builder $query, array $column, array $filter): void
    {
        self::validateFilter($column['type'], $filter);
        $expression = $column['expression'];
        $op = $filter['op'] ?? 'in';
        $values = $filter['values'] ?? [];
        if (in_array($op, ['in', 'not_in'], true)) {
            $normalized = "COALESCE(CAST(($expression) AS CHAR), '')";
            $op === 'in' ? $query->whereIn(DB::raw($normalized), $values) : $query->whereNotIn(DB::raw($normalized), $values);
            return;
        }
        if (in_array($op, ['blank', 'not_blank'], true)) {
            $query->whereRaw("COALESCE(CAST(($expression) AS CHAR), '') ".($op === 'blank' ? '=' : '<>')." ''");
            return;
        }
        $value = $filter['value'] ?? null;
        if ($op === 'contains' || $op === 'not_contains') {
            $query->whereRaw("COALESCE(CAST(($expression) AS CHAR), '') ".($op === 'contains' ? 'LIKE' : 'NOT LIKE').' ?', ['%'.self::escapeLike($value).'%']);
            return;
        }
        $operators = ['eq' => '=', 'neq' => '<>', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];
        if ($column['type'] === 'date') {
            $expression = "DATE($expression)";
        }
        $query->whereRaw("($expression) {$operators[$op]} ?", [$value]);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['column_filters' => trans('presentation.column_filter_invalid')]);
    }
}
