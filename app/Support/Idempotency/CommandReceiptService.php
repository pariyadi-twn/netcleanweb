<?php

namespace App\Support\Idempotency;

use App\Exceptions\ApiException;
use App\Modules\Operations\Enums\CommandReceiptStatus;
use App\Modules\Operations\Models\CommandReceipt;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommandReceiptService
{
    public function __construct(private readonly CanonicalPayload $canonical) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(): bool  $authorize
     * @param  Closure(): CommandResult  $execute
     * @param  Closure(CommandReceipt): CommandResult  $resolve
     */
    public function run(
        int $tenantId,
        string $clientCommandId,
        string $commandType,
        array $payload,
        int $actorMembershipId,
        Closure $authorize,
        Closure $execute,
        Closure $resolve,
    ): CommandResult {
        $requestHash = $this->canonical->hash($commandType, $payload);

        $this->assertAuthorized($authorize);
        $receipt = $this->reserve($tenantId, $clientCommandId, $commandType, $requestHash, $actorMembershipId);

        if ($receipt->request_hash !== $requestHash) {
            throw $this->payloadMismatch();
        }

        if ($receipt->status === CommandReceiptStatus::Completed) {
            $this->assertAuthorized($authorize);

            return $resolve($receipt)->asReplay();
        }

        if ($receipt->status === CommandReceiptStatus::Rejected) {
            throw new ApiException($receipt->error_code ?? 'COMMAND_REJECTED', 'The command was rejected.', 422);
        }

        try {
            return DB::transaction(function () use ($receipt, $requestHash, $authorize, $execute, $resolve): CommandResult {
                $locked = CommandReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

                if ($locked->request_hash !== $requestHash) {
                    throw $this->payloadMismatch();
                }

                if ($locked->status === CommandReceiptStatus::Completed) {
                    $this->assertAuthorized($authorize);

                    return $resolve($locked)->asReplay();
                }

                if ($locked->status === CommandReceiptStatus::Rejected) {
                    throw new ApiException($locked->error_code ?? 'COMMAND_REJECTED', 'The command was rejected.', 422);
                }

                $result = $execute();

                $locked->forceFill([
                    'status' => CommandReceiptStatus::Completed,
                    'result_resource_type' => $result->resourceType,
                    'result_public_id' => $result->resourcePublicId,
                    'completed_at' => now(),
                    'error_code' => null,
                ])->save();

                return $result;
            });
        } catch (ApiException|ValidationException $exception) {
            $this->markRejected($receipt, $exception);

            throw $exception;
        }
    }

    private function reserve(
        int $tenantId,
        string $clientCommandId,
        string $commandType,
        string $requestHash,
        int $actorMembershipId,
    ): CommandReceipt {
        try {
            return DB::transaction(fn (): CommandReceipt => CommandReceipt::query()->create([
                'tenant_id' => $tenantId,
                'client_command_id' => $clientCommandId,
                'command_type' => $commandType,
                'request_hash' => $requestHash,
                'actor_membership_id' => $actorMembershipId,
                'status' => CommandReceiptStatus::Processing,
            ]));
        } catch (UniqueConstraintViolationException) {
            return CommandReceipt::query()
                ->where('tenant_id', $tenantId)
                ->where('client_command_id', $clientCommandId)
                ->firstOrFail();
        }
    }

    private function assertAuthorized(Closure $authorize): void
    {
        if (! $authorize()) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function markRejected(CommandReceipt $receipt, ApiException|ValidationException $exception): void
    {
        $errorCode = $exception instanceof ApiException ? $exception->errorCode : 'VALIDATION_FAILED';

        try {
            DB::transaction(function () use ($receipt, $errorCode): void {
                $locked = CommandReceipt::query()->lockForUpdate()->find($receipt->id);

                if ($locked !== null && $locked->status === CommandReceiptStatus::Processing) {
                    $locked->forceFill([
                        'status' => CommandReceiptStatus::Rejected,
                        'error_code' => $errorCode,
                    ])->save();
                }
            });
        } catch (\Throwable) {
            // The original command failure remains the response.
        }
    }

    private function payloadMismatch(): ApiException
    {
        return new ApiException(
            'IDEMPOTENCY_PAYLOAD_MISMATCH',
            'The idempotency key was already used with a different payload.',
            409,
        );
    }
}
