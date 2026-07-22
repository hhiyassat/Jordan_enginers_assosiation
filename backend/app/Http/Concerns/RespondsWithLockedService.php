<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Models\ServiceDefinition;
use Illuminate\Http\JsonResponse;

/**
 * Shared 423 Locked response for controllers that mutate a
 * ServiceDefinition. Extracted so ServiceCatalogController and
 * ServiceFeesController (Workstream 5 split) emit identical envelopes
 * without duplicating the string — a divergence risk once the two
 * controllers live in different modules.
 */
trait RespondsWithLockedService
{
    protected function lockedResponse(ServiceDefinition $service): JsonResponse
    {
        return response()->json([
            'error'        => 'service_locked',
            'message'      => 'الخدمة مقفلة للتعديل — يجب فتح قفلها أولاً من قبل مسؤول.',
            'service_code' => $service->code,
        ], 423);
    }
}
