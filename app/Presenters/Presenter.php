<?php

namespace App\Presenters;

use App\Models\Asset;
use App\Models\Setting;
use App\Models\SnipeModel;

abstract class Presenter
{
    /**
     * @var SnipeModel
     */
    protected $model;

    /**
     * Presenter constructor.
     */
    public function __construct(SnipeModel $model)
    {
        $this->model = $model;
    }

    public function displayAddress()
    {
        $address = '';
        if ($this->model->address) {
            $address .= e($this->model->address)."\n";
        }

        if ($this->model->address2) {
            $address .= e($this->model->address2)."\n";
        }

        if ($this->model->city) {
            $address .= e($this->model->city).', ';
        }

        if ($this->model->state) {
            $address .= e($this->model->state).' ';
        }

        if ($this->model->zip) {
            $address .= e($this->model->zip).' ';
        }

        if ($this->model->country) {
            $address .= e($this->model->country).' ';
        }

        return $address;
    }

    // Convenience functions for datatables stuff
    public function categoryUrl()
    {
        $model = $this->model;
        // Category of Asset belongs to model.
        if ($model->model) {
            $model = $this->model->model;
        }

        if ($model->category) {
            return $model->category->present()->nameUrl();
        }

        return '';
    }

    public function locationUrl()
    {
        if ($this->model->location) {
            return $this->model->location->present()->nameUrl();
        }

        return '';
    }

    public function companyUrl()
    {
        if ($this->model->company) {
            return $this->model->company->present()->nameUrl();
        }

        return '';
    }

    public function manufacturerUrl()
    {
        $model = $this->model;
        // Category of Asset belongs to model.
        if ($model->model) {
            $model = $this->model->model;
        }

        if ($model->manufacturer) {
            return $model->manufacturer->present()->nameUrl();
        }

        return '';
    }

    /**
     * Used to take user created URL and dynamically fill in the needed values per item
     *
     * @return string
     */
    public function dynamicUrl($dynamic_url)
    {
        $url = (str_replace('{LOCALE}', Setting::getSettings()->locale, $dynamic_url));

        if ($this->model instanceof Asset) {
            $url = (str_replace('{SERIAL}', urlencode($this->model->serial), $url));
            $url = (str_replace('{MODEL_NAME}', urlencode($this->model->model->name), $url));
            $url = (str_replace('{MODEL_NUMBER}', urlencode($this->model->model->model_number), $url));

            return $url;
        }

        $url = (str_replace('{SERIAL}', urlencode($this->serial), $url));
        $url = (str_replace('{MODEL_NAME}', urlencode($this->model_name), $url));
        $url = (str_replace('{MODEL_NUMBER}', urlencode($this->model_number), $url));

        return $url;

    }

    public function __get($property)
    {
        if (method_exists($this, $property)) {
            return $this->{$property}();
        }

        return $this->model->{$property};
    }

    public function __call($method, $args)
    {
        return $this->model->$method($args);
    }

    /** Named working views keep technical fields available without crowding daily tasks. */
    public static function tableViews(): array
    {
        return [
            'assets' => [
                'working' => ['checkbox', 'name', 'asset_tag', 'model', 'status', 'assigned_to', 'location', 'checkincheckout', 'actions'],
                'finance' => ['checkbox', 'name', 'asset_tag', 'purchase_cost', 'book_value', 'purchase_date', 'supplier', 'order_number', 'actions'],
                'lifecycle' => ['checkbox', 'name', 'asset_tag', 'status', 'warranty_expires', 'expected_checkin', 'next_audit_date', 'asset_eol_date', 'actions'],
                'technical' => ['checkbox', 'name', 'asset_tag', 'serial', 'model', 'model_number', 'manufacturer', 'primary_mac', 'primary_ip', 'external_os', 'actions'],
            ],
            'licenses' => [
                'working' => ['checkbox', 'name', 'free_seats_count', 'presentation_assigned_seats', 'seats', 'expiration_date', 'checkincheckout', 'actions'],
                'finance' => ['checkbox', 'name', 'purchase_cost', 'purchase_date', 'purchase_order', 'order_number', 'supplier', 'actions'],
                'technical' => ['checkbox', 'name', 'product_key', 'license_name', 'license_email', 'maintained', 'reassignable', 'actions'],
            ],
            'accessories' => [
                'working' => ['checkbox', 'name', 'remaining', 'min_amt', 'presentation_stock_state', 'location', 'change', 'available_actions'],
                'finance' => ['name', 'qty', 'purchase_cost', 'total_cost', 'orders', 'available_actions'],
                'technical' => ['name', 'category', 'model_number', 'manufacturer', 'supplier', 'notes', 'available_actions'],
            ],
            'consumables' => [
                'working' => ['name', 'remaining', 'min_amt', 'presentation_stock_state', 'location', 'change', 'actions'],
                'finance' => ['name', 'qty', 'purchase_cost', 'total_cost', 'orders', 'actions'],
                'technical' => ['name', 'category', 'model_number', 'item_no', 'manufacturer', 'supplier', 'notes', 'actions'],
            ],
            'components' => [
                'working' => ['name', 'remaining', 'min_amt', 'presentation_stock_state', 'location', 'checkincheckout', 'actions'],
                'finance' => ['name', 'qty', 'purchase_cost', 'total_cost', 'orders', 'actions'],
                'technical' => ['name', 'serial', 'category', 'model_number', 'manufacturer', 'supplier', 'notes', 'actions'],
            ],
            'users' => [
                'working' => ['checkbox', 'name', 'username', 'email', 'department', 'location', 'activated', 'actions'],
                'technical' => ['checkbox', 'name', 'employee_num', 'jobtitle', 'manager', 'groups', 'activated', 'last_login', 'actions'],
                'lifecycle' => ['checkbox', 'name', 'assets_count', 'licenses_count', 'accessories_count', 'consumables_count', 'manages_users_count', 'manages_locations_count', 'actions'],
            ],
            'maintenance' => [
                'working' => ['checkbox', 'name', 'asset_name', 'asset_tag', 'responsible_party', 'expected_completion_date', 'presentation_job_state', 'actions'],
                'finance' => ['checkbox', 'name', 'asset_tag', 'supplier', 'cost', 'is_warranty', 'actions'],
                'lifecycle' => ['checkbox', 'name', 'asset_tag', 'start_date', 'expected_completion_date', 'completed_at', 'completed_by', 'presentation_job_state', 'actions'],
                'technical' => ['name', 'asset_tag', 'serial', 'model', 'status_label', 'assigned_to', 'location', 'maintenance_type_details', 'notes', 'actions'],
            ],
            'requests' => [
                'working' => ['checkbox', 'user', 'requestable.item_name', 'quantity', 'presentation_request_age', 'presentation_availability', 'actions'],
                'lifecycle' => ['checkbox', 'user', 'requestable.item_name', 'requested_at', 'start_date', 'end_date', 'requestable.expected_checkin', 'actions'],
                'technical' => ['checkbox', 'requestable.item_name', 'requestable.asset_tag', 'requestable.serial', 'requestable.location.name', 'requestable.remaining', 'notes', 'actions'],
            ],
        ];
    }

    protected static function tablePresentation(array $layout, string $profile): array
    {
        $views = static::tableViews()[$profile] ?? null;
        if (! $views) {
            return $layout;
        }
        $extra = match ($profile) {
            'licenses' => ['presentation_assigned_seats', 'assigned_seats', 'presentationAssignedSeatsFormatter'],
            'accessories', 'consumables', 'components' => ['presentation_stock_state', 'stock_state', 'presentationStockStateFormatter'],
            'maintenance' => ['presentation_job_state', 'job_state', 'presentationJobStateFormatter'],
            'requests' => ['presentation_request_age', 'request_age', 'presentationRequestAgeFormatter'],
            default => null,
        };
        if ($extra) {
            $layout[] = ['field' => $extra[0], 'title' => trans('presentation.'.$extra[1]), 'formatter' => $extra[2], 'sortable' => false, 'switchable' => true];
        }
        if ($profile === 'requests') {
            $layout[] = ['field' => 'presentation_availability', 'title' => trans('presentation.availability'), 'formatter' => 'presentationAvailabilityFormatter', 'sortable' => false, 'switchable' => true];
        }
        $order = array_flip($views['working']);
        foreach ($layout as &$column) {
            $column['visible'] = isset($order[$column['field']]);
            if ($profile === 'licenses' && $column['field'] === 'free_seats_count') {
                $column['title'] = trans('presentation.available_seats');
            }
        }
        unset($column);
        foreach (['checkincheckout', 'change', 'available_actions', 'actions'] as $index => $field) {
            if (isset($order[$field])) {
                $order[$field] = 2000 + $index;
            }
        }
        usort($layout, fn (array $a, array $b): int => ($order[$a['field']] ?? 1000) <=> ($order[$b['field']] ?? 1000));

        return $layout;
    }
}
