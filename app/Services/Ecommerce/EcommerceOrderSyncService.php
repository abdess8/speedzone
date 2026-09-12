<?php

namespace App\Services\Ecommerce;

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Enums\EcommerceSyncRowStatus;
use App\Enums\EcommerceSyncStatus;
use App\Enums\EcommerceSyncTrigger;
use App\Enums\OrderCreationSource;
use App\Models\EcommerceIntegration;
use App\Models\EcommerceIntegrationSync;
use App\Models\EcommerceIntegrationSyncRow;
use App\Models\User;
use App\Services\Ecommerce\YouCan\YouCanFieldCatalog;
use App\Services\Ecommerce\YouCan\YouCanOrderMapper;
use App\Services\Ecommerce\YouCan\YouCanOrderSyncDriver;
use App\Services\OrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EcommerceOrderSyncService
{
    public function __construct(
        private readonly YouCanOrderSyncDriver $youCan,
        private readonly YouCanOrderMapper $mapper,
        private readonly OrderService $orders,
    ) {}

    public function run(EcommerceIntegration $integration, EcommerceSyncTrigger $trigger): EcommerceIntegrationSync
    {
        $sync = EcommerceIntegrationSync::query()->create([
            'ecommerce_integration_id' => $integration->id,
            'status' => EcommerceSyncStatus::Running,
            'trigger' => $trigger,
            'started_at' => now(),
        ]);

        try {
            $this->process($integration, $sync);
        } catch (Throwable $e) {
            $this->fail($integration, $sync, $e);
        }

        return $sync->refresh();
    }

    private function process(EcommerceIntegration $integration, EcommerceIntegrationSync $sync): void
    {
        if ($integration->status !== EcommerceIntegrationStatus::Connected) {
            throw new RuntimeException(__('integrations.sync.errors.not_connected'));
        }

        if ($integration->platform !== EcommercePlatform::YouCan) {
            throw new RuntimeException(__('integrations.sync.errors.unsupported_platform'));
        }

        $seller = User::query()->find($integration->seller_id);

        if ($seller === null) {
            throw new RuntimeException(__('integrations.sync.errors.missing_seller'));
        }

        $since = $this->syncSince($integration, $sync);
        $payloads = iterator_to_array($this->youCan->fetchOrders($integration, $since), false);
        $sources = YouCanFieldCatalog::discover($payloads);

        if ($payloads !== []) {
            $integration->source_fields = $sources;
            $integration->field_mapping = YouCanFieldCatalog::mergeMapping(
                $integration->field_mapping,
                YouCanFieldCatalog::autoMap($sources),
            );
            $integration->save();
        }

        $stageOnly = $sync->trigger === EcommerceSyncTrigger::Manual;
        $skipped = [];
        $fetched = 0;
        $created = 0;
        $skippedCount = 0;
        $errors = 0;
        $pending = 0;
        $seenExternalIds = [];
        $seenRefs = [];
        $handled = $this->mapper->handledCatalogKeys($integration);

        foreach ($payloads as $payload) {
            $fetched++;
            $externalId = $this->mapper->externalId($payload);
            $shopRef = $this->mapper->orderRef($payload);
            $ref = $shopRef ?? (string) ($externalId ?? '');

            if (! $this->youCan->matchesImportStatus($payload, $integration)) {
                $skippedCount++;
                $this->rememberSkip($skipped, $externalId, $ref, 'status_mismatch', $this->mapper->orderStatusSlug($payload));

                continue;
            }

            $duplicateInBatch = ($externalId !== null && isset($seenExternalIds[$externalId]))
                || ($shopRef !== null && isset($seenRefs[$shopRef]));

            if ($duplicateInBatch || $this->mapper->isHandled($handled, $externalId, $shopRef)) {
                $skippedCount++;
                $this->rememberSkip($skipped, $externalId, $ref, 'already_imported', $shopRef ?? $externalId);

                continue;
            }

            if ($externalId !== null) {
                $seenExternalIds[$externalId] = true;
                $handled['ids'][$externalId] = true;
            }

            if ($shopRef !== null) {
                $seenRefs[$shopRef] = true;
                $handled['refs'][$shopRef] = true;
            }

            $raw = $this->mapper->extractRaw($payload, $integration->resolvedFieldMapping());
            $values = $this->mapper->resolveValues($raw);

            if ($stageOnly) {
                $this->stageRow(
                    $integration,
                    $sync,
                    $externalId,
                    $ref,
                    EcommerceSyncRowStatus::Pending,
                    $values,
                    $raw,
                );
                $pending++;

                continue;
            }

            $mapped = $this->mapper->toOrderPayload($payload, $integration, $sync);

            if (! ($mapped['ok'] ?? false)) {
                $skippedCount++;
                $reason = (string) ($mapped['reason'] ?? 'skipped');

                if ($reason !== 'already_imported') {
                    $errors++;
                    $this->stageRow(
                        $integration,
                        $sync,
                        $externalId,
                        $ref,
                        EcommerceSyncRowStatus::Failed,
                        $values,
                        $raw,
                        [$reason => $mapped['detail'] ?? $reason],
                    );
                }

                $this->rememberSkip($skipped, $externalId, $ref, $reason, $mapped['detail'] ?? null);

                continue;
            }

            try {
                $this->orders->create($mapped['data'], $seller);
                $created++;
                $this->stageRow(
                    $integration,
                    $sync,
                    $externalId,
                    $ref,
                    EcommerceSyncRowStatus::Imported,
                    $values,
                    $raw,
                );
            } catch (Throwable $e) {
                $errors++;
                $skippedCount++;
                $this->stageRow(
                    $integration,
                    $sync,
                    $externalId,
                    $ref,
                    EcommerceSyncRowStatus::Failed,
                    $values,
                    $raw,
                    ['create_failed' => $e->getMessage()],
                );
                $this->rememberSkip($skipped, $externalId, $ref, 'create_failed', $e->getMessage());
                Log::warning('ecommerce.sync.order_failed', [
                    'integration_id' => $integration->id,
                    'external_order_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($stageOnly) {
            $status = $pending > 0
                ? EcommerceSyncStatus::Review
                : ($fetched > 0 ? EcommerceSyncStatus::Succeeded : EcommerceSyncStatus::Succeeded);
            $message = $pending > 0 ? null : ($fetched === 0 ? null : __('integrations.sync.nothing_to_review'));
        } else {
            $status = $errors > 0 && $created === 0 && $fetched > 0
                ? EcommerceSyncStatus::Failed
                : ($errors > 0 ? EcommerceSyncStatus::Partial : EcommerceSyncStatus::Succeeded);
            $message = $status === EcommerceSyncStatus::Failed
                ? __('integrations.sync.errors.all_failed')
                : null;
        }

        $sync->fill([
            'status' => $status,
            'fetched_count' => $fetched,
            'created_count' => $created,
            'updated_count' => 0,
            'skipped_count' => $skippedCount,
            'error_count' => $errors,
            'finished_at' => now(),
            'error_message' => $message,
            'skipped_reasons' => $skipped,
        ])->save();

        $integration->last_synced_at = now();
        $integration->last_error = null;
        $integration->scheduleNextSync();
        $integration->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     */
    public function commit(
        EcommerceIntegrationSync $sync,
        array $orders,
        User $seller,
    ): int {
        return DB::transaction(function () use ($sync, $orders, $seller): int {
            $integration = $sync->integration;
            $created = 0;
            $submitted = [];

            foreach ($orders as $order) {
                $rowId = (int) ($order['id'] ?? 0);
                $row = $sync->rows()
                    ->where('id', $rowId)
                    ->whereIn('status', [
                        EcommerceSyncRowStatus::Pending->value,
                        EcommerceSyncRowStatus::Failed->value,
                    ])
                    ->first();

                if (! $row instanceof EcommerceIntegrationSyncRow) {
                    continue;
                }

                $submitted[] = $row->id;
                $externalId = (string) $row->external_order_id;
                $shopRef = filled($row->ref) && $row->ref !== $externalId
                    ? mb_substr((string) $row->ref, 0, 100)
                    : null;

                if ($this->mapper->alreadyImported($integration, $externalId, $shopRef)) {
                    $row->status = EcommerceSyncRowStatus::Skipped;
                    $row->save();

                    continue;
                }

                unset($order['id']);
                $order['store_id'] = $integration->store_id;
                $order['creation_source'] = OrderCreationSource::Integration->value;
                $order['ecommerce_integration_id'] = $integration->id;
                $order['external_order_id'] = $externalId;
                $order['ecommerce_order_ref'] = $shopRef;
                $order['ecommerce_sync_id'] = $sync->id;

                $this->orders->create($order, $seller);
                $row->status = EcommerceSyncRowStatus::Imported;
                $row->values = array_merge($row->values ?? [], $order);
                $row->errors = null;
                $row->save();
                $created++;
            }

            $sync->rows()
                ->whereIn('status', [
                    EcommerceSyncRowStatus::Pending->value,
                    EcommerceSyncRowStatus::Failed->value,
                ])
                ->whereNotIn('id', $submitted)
                ->update(['status' => EcommerceSyncRowStatus::Skipped->value]);

            $remaining = $sync->rows()
                ->whereIn('status', [
                    EcommerceSyncRowStatus::Pending->value,
                    EcommerceSyncRowStatus::Failed->value,
                ])
                ->count();

            $sync->created_count = (int) $sync->rows()->where('status', EcommerceSyncRowStatus::Imported->value)->count();
            $sync->error_count = $remaining;
            $sync->status = $remaining > 0
                ? ($sync->created_count > 0 ? EcommerceSyncStatus::Partial : EcommerceSyncStatus::Review)
                : EcommerceSyncStatus::Succeeded;
            $sync->error_message = null;
            $sync->save();

            return $created;
        });
    }

    private function stageRow(
        EcommerceIntegration $integration,
        EcommerceIntegrationSync $sync,
        ?string $externalId,
        string $ref,
        EcommerceSyncRowStatus $status,
        array $values,
        array $raw,
        ?array $errors = null,
    ): void {
        if ($externalId === null || $externalId === '') {
            return;
        }

        EcommerceIntegrationSyncRow::query()->updateOrCreate(
            [
                'ecommerce_integration_sync_id' => $sync->id,
                'external_order_id' => $externalId,
            ],
            [
                'ecommerce_integration_id' => $integration->id,
                'ref' => $ref !== '' ? $ref : $externalId,
                'status' => $status,
                'values' => $values,
                'raw' => $raw,
                'errors' => $errors,
            ],
        );
    }

    private function fail(
        EcommerceIntegration $integration,
        EcommerceIntegrationSync $sync,
        Throwable $e,
    ): void {
        $message = $e->getMessage();
        $twoFactor = str_contains($message, __('integrations.youcan.errors.two_factor'))
            || str_contains(strtolower($message), '2fa');

        if ($twoFactor) {
            $integration->auto_sync_enabled = false;
            $integration->next_sync_at = null;
        }

        $sync->fill([
            'status' => EcommerceSyncStatus::Failed,
            'finished_at' => now(),
            'error_message' => $twoFactor
                ? __('integrations.sync.errors.two_factor')
                : $message,
        ])->save();

        $integration->last_error = $sync->error_message;
        $integration->scheduleNextSync();
        $integration->save();
    }

    private function syncSince(EcommerceIntegration $integration, EcommerceIntegrationSync $sync): ?Carbon
    {
        $alreadyScanned = EcommerceIntegrationSync::query()
            ->where('ecommerce_integration_id', $integration->id)
            ->where('id', '!=', $sync->id)
            ->where('fetched_count', '>', 0)
            ->exists();

        if ($alreadyScanned && $integration->last_synced_at) {
            return Carbon::parse($integration->last_synced_at)->subMinutes(5);
        }

        $lookbackDays = (int) config('youcan.first_sync_lookback_days', 0);

        return $lookbackDays > 0 ? now()->subDays($lookbackDays) : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $skipped
     */
    private function rememberSkip(
        array &$skipped,
        ?string $externalId,
        string $ref,
        string $reason,
        ?string $detail,
    ): void {
        if (count($skipped) >= 100) {
            return;
        }

        $skipped[] = [
            'external_order_id' => $externalId,
            'ref' => $ref !== '' ? $ref : $externalId,
            'reason' => $reason,
            'detail' => $detail,
        ];
    }
}
