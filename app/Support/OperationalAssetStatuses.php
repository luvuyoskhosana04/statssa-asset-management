<?php

namespace App\Support;

class OperationalAssetStatuses
{
    // Verified against the legacy Status Labels catalogue on 22 November 2026.
    public const DEFINITIONS = [
        'Awaiting Receipt' => ['deployable' => 0, 'pending' => 1, 'archived' => 0],
        'Awaiting Inspection' => ['deployable' => 0, 'pending' => 1, 'archived' => 0],
        'Preparing for Deployment' => ['deployable' => 0, 'pending' => 1, 'archived' => 0],
        'Available' => ['deployable' => 1, 'pending' => 0, 'archived' => 0],
        'In Transit' => ['deployable' => 1, 'pending' => 0, 'archived' => 0],
        'Awaiting User Handover' => ['deployable' => 0, 'pending' => 1, 'archived' => 0],
        'Assigned / In Use' => ['deployable' => 1, 'pending' => 0, 'archived' => 0],
        'On Hold' => ['deployable' => 0, 'pending' => 1, 'archived' => 0],
        'Unavailable - Damaged' => ['deployable' => 0, 'pending' => 0, 'archived' => 0],
        'Reserved - Project' => ['deployable' => 1, 'pending' => 0, 'archived' => 0],
    ];

    public static function names(): array
    {
        return array_keys(self::DEFINITIONS);
    }
}
