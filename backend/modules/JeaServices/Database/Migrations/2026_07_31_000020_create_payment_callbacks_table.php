<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CS-03: gateway-callback receipt table.
 *
 * One row per verified gateway callback. Backs the callback endpoint's
 * idempotency: `unique(reference)` means a replayed webhook — even if
 * its signature verifies — cannot flip `payment_status` a second time
 * or generate a duplicate audit trail. The controller upserts under
 * `insertOrIgnore` semantics and returns the same 200 either way.
 *
 * The table is deliberately provider-neutral (no `provider` column):
 * the whole point of the PaymentGateway abstraction is that
 * PaymentsController + PaymentCallbackController don't know which
 * gateway they're wired to. `gateway_meta` carries whatever provider
 * extras were returned in the receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')
                  ->nullable()
                  ->constrained('applications')
                  ->cascadeOnDelete();
            $table->string('reference', 191)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8);
            $table->timestamp('settled_at')->nullable();
            $table->json('gateway_meta')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['application_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
    }
};
