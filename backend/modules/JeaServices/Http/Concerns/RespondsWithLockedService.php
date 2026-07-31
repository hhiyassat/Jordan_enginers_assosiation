<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Modules\JeaServices\Models\ServiceDefinition;

/**
 * Shared 423 Locked response for controllers that mutate a
 * ServiceDefinition. Extracted so ServiceCatalogController and
 * ServiceFeesController emit identical envelopes without duplicating
 * the string.
 *
 * H-07 (2026-07-31): moved from `App\Http\Concerns` into this
 * JEA-owned namespace so Platform no longer imports
 * `Modules\JeaServices\Models\ServiceDefinition`. The "locked
 * service" concept IS jea-services; the trait lives with it.
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
