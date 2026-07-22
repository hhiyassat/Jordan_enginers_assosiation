<?php

namespace Tests\Unit\Http\Responses;

use App\Http\Responses\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_ok_wraps_payload_under_data_with_200(): void
    {
        $r = ApiResponse::ok(['foo' => 'bar']);

        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame(
            ['data' => ['foo' => 'bar']],
            $r->getData(true),
        );
    }

    public function test_ok_accepts_custom_status(): void
    {
        $r = ApiResponse::ok(['x' => 1], 202);
        $this->assertSame(202, $r->getStatusCode());
    }

    public function test_created_returns_201_with_data_envelope(): void
    {
        $r = ApiResponse::created(['id' => 42]);

        $this->assertSame(201, $r->getStatusCode());
        $this->assertSame(['data' => ['id' => 42]], $r->getData(true));
    }

    public function test_paginated_splits_items_and_meta(): void
    {
        $items = new Collection([['id' => 1], ['id' => 2], ['id' => 3]]);
        $p = new LengthAwarePaginator($items, 47, 15, 2);

        $r = ApiResponse::paginated($p);

        $this->assertSame(200, $r->getStatusCode());
        $body = $r->getData(true);
        $this->assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $body['data']);
        $this->assertSame(
            ['current_page' => 2, 'per_page' => 15, 'total' => 47, 'last_page' => 4],
            $body['meta'],
        );
        // Meta must NOT leak Laravel's paginator internals.
        $this->assertArrayNotHasKey('links', $body);
        $this->assertArrayNotHasKey('path', $body);
    }

    public function test_error_wraps_code_and_message_under_error_key(): void
    {
        $r = ApiResponse::error('SERVICE_LOCKED', 'This service is locked.', 423);

        $this->assertSame(423, $r->getStatusCode());
        $this->assertSame(
            ['error' => ['code' => 'SERVICE_LOCKED', 'message' => 'This service is locked.']],
            $r->getData(true),
        );
    }

    public function test_error_includes_details_when_provided(): void
    {
        $r = ApiResponse::error(
            'VALIDATION_FAILED',
            'The given data was invalid.',
            422,
            ['field_errors' => ['email' => ['required']]],
        );

        $body = $r->getData(true);
        $this->assertSame('VALIDATION_FAILED', $body['error']['code']);
        $this->assertSame(['field_errors' => ['email' => ['required']]], $body['details']);
    }

    public function test_error_defaults_to_400_when_status_omitted(): void
    {
        $r = ApiResponse::error('SOME_CODE', 'msg');
        $this->assertSame(400, $r->getStatusCode());
    }
}
