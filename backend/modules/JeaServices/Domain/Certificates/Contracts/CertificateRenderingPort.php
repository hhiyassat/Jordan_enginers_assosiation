<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Certificates\Contracts;

use Modules\JeaServices\Domain\Certificates\ValueObjects\CertificateIssuanceRequest;

/**
 * TD-07 · Port for certificate rendering (PDF generation).
 *
 * NOT_WIRED. Production rendering blocked until publication +
 * production authorization + real adapter.
 */
interface CertificateRenderingPort
{
    /** Returns an opaque render handle (storage key or vendor id). */
    public function render(CertificateIssuanceRequest $request): string;

    /** Adapter-side self-test — MODELLED / CONFIGURED / VERIFIED. */
    public function operationalState(): string;
}
