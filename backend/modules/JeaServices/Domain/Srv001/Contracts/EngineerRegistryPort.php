<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-01 · Port for engineer-registry lookups (JEA membership database).
 *
 * Used by TargetSrv001SubmissionPolicy for:
 *   - Head-of-specialization signature verification (SRS §7, Srv001PilotSeeder L508)
 *   - Engineer-number auto-population (Srv001PilotSeeder L351,
 *     semantic_status=NEEDS_JEA_API)
 *   - Category A / B loss-of-specialist detection (FR-SS-082)
 *
 * IMPLEMENTATION STATUS: interface-only in TD-01. No production adapter.
 * Test doubles simulate lookups. Real JEA API adapter is BLOCKED_UNTIL_OD-30
 * (external contracts).
 *
 * NAMING: Port (not "Repository" or "Service") to reinforce hexagonal
 * boundary intent — the target domain owns the interface; adapters live
 * outside the domain layer.
 */
interface EngineerRegistryPort
{
    /**
     * Look up an engineer's registered specialty by their JEA number.
     *
     * Return null when the number is unknown OR when the port is
     * unavailable (network down, OD-30 not yet closed). Callers must
     * treat null as "cannot verify" not "unregistered" — the
     * distinction shapes the ServiceSubmissionDecision.
     */
    public function findSpecialtyByEngineerNumber(string $engineerNumber): ?EngineerSpecialty;

    /**
     * Is this engineer currently active (not deregistered, not
     * suspended)? Returns null when the port cannot answer.
     */
    public function isActive(string $engineerNumber): ?bool;
}
