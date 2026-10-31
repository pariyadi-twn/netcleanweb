<?php

namespace App\Modules\Reporting\Actions;

use App\Models\User;
use App\Modules\Catalog\Actions\CreateService;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Customer\Actions\CreateCustomer;
use App\Modules\Customer\Actions\NormalizePhoneNumber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Reporting\Enums\ImportBatchStatus;
use App\Modules\Reporting\Enums\ImportRowStatus;
use App\Modules\Reporting\Models\ImportBatch;
use App\Modules\Reporting\Models\ImportRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RunImport
{
    private const CUSTOMER_FIELDS = ['name', 'phone', 'email', 'notes', 'address_label', 'address'];

    private const CATALOG_FIELDS = [
        'code', 'name', 'unit', 'minimum_quantity', 'quantity_step', 'workflow_template_id',
        'sla_mode', 'regular_turnaround', 'express_turnaround',
    ];

    public function __construct(
        private readonly CreateCustomer $customers,
        private readonly CreateService $services,
        private readonly NormalizePhoneNumber $phones,
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(
        int $tenantId,
        int $membershipId,
        string $type,
        UploadedFile $file,
        bool $dryRun,
        array $mapping,
        ?User $actor = null,
    ): ImportBatch {
        $content = file_get_contents($file->getRealPath());
        if ($content === false) {
            throw ValidationException::withMessages(['file' => ['The uploaded file could not be read.']]);
        }

        [$headers, $parsedRows] = $this->parseCsv($content);
        $normalizedMapping = $this->mapping($type, $headers, $mapping);

        if (count($parsedRows) > (int) config('imports.max_rows', 5000)) {
            throw ValidationException::withMessages(['file' => ['The import contains too many rows.']]);
        }

        $checksum = hash('sha256', $content);
        $key = 'imports/'.$tenantId.'/'.Str::uuid().'.csv';
        Storage::disk((string) config('uploads.disk'))->put($key, $content, ['visibility' => 'private']);

        $batch = new ImportBatch([
            'requested_by_membership_id' => $membershipId,
            'type' => $type,
            'source' => 'csv',
            'file_name' => basename($file->getClientOriginalName()),
            'checksum' => $checksum,
            'raw_object_key' => $key,
            'raw_expires_at' => now()->addDays((int) config('imports.raw_retention_days', 7)),
            'dry_run' => $dryRun,
            'status' => ImportBatchStatus::Processing,
            'total_rows' => count($parsedRows),
        ]);
        $batch->tenant_id = $tenantId;
        $batch->save();

        $seen = [];
        $counts = ['valid' => 0, 'invalid' => 0, 'duplicate' => 0, 'imported' => 0];

        try {
            foreach ($parsedRows as $index => $row) {
                $result = $this->processRow(
                    $batch,
                    $type,
                    $index + 2,
                    $row['original'],
                    $row['mapped'],
                    $normalizedMapping,
                    $dryRun,
                    $seen,
                    $actor,
                );

                $counts[$result]++;
            }
        } catch (\Throwable $exception) {
            $batch->forceFill([
                'status' => ImportBatchStatus::Failed,
                'error_code' => 'IMPORT_FAILED',
                'completed_at' => now(),
                'version' => $batch->version + 1,
            ])->save();

            throw $exception;
        }

        $batch->forceFill([
            'status' => ImportBatchStatus::Completed,
            'valid_rows' => $counts['valid'] + $counts['duplicate'] + $counts['imported'],
            'invalid_rows' => $counts['invalid'],
            'duplicate_rows' => $counts['duplicate'],
            'imported_rows' => $counts['imported'],
            'completed_at' => now(),
            'version' => $batch->version + 1,
        ])->save();

        $this->auditor->record(
            tenantId: $tenantId,
            action: AuditAction::ImportCompleted,
            subjectType: AuditRecorder::SUBJECT_IMPORT_BATCH,
            subjectPublicId: $batch->public_id,
            changes: [
                'type' => $type,
                'dry_run' => $dryRun,
                'total_rows' => $batch->total_rows,
                'invalid_rows' => $batch->invalid_rows,
                'duplicate_rows' => $batch->duplicate_rows,
                'imported_rows' => $batch->imported_rows,
            ],
            actor: $actor,
        );
        $this->outbox->record(
            tenantId: $tenantId,
            eventType: 'import.completed',
            aggregatePublicId: $batch->public_id,
            payload: [
                'import_batch_id' => $batch->public_id,
                'type' => $type,
                'dry_run' => $dryRun,
                'imported_rows' => $batch->imported_rows,
            ],
        );

        return $batch->refresh();
    }

    /**
     * @param  array<string, string>  $original
     * @param  array<string, mixed>  $mapped
     * @param  array<string, string>  $mapping
     * @param  array<string, int>  $seen
     */
    private function processRow(
        ImportBatch $batch,
        string $type,
        int $rowNumber,
        array $original,
        array $mapped,
        array $mapping,
        bool $dryRun,
        array &$seen,
        ?User $actor,
    ): string {
        $normalized = $this->normalized($mapped, $mapping);
        $row = new ImportRow([
            'import_batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'status' => ImportRowStatus::Invalid,
            'raw_payload' => $original,
            'normalized_payload' => $normalized,
        ]);
        $row->tenant_id = $batch->tenant_id;
        $result = $type === 'customers'
            ? $this->customerRow($batch, $normalized, $seen, $dryRun, $actor, $row)
            : $this->catalogRow($batch, $normalized, $seen, $dryRun, $row);

        $row->status = $result['status'];
        $row->error_code = $result['error_code'] ?? null;
        $row->error_message = $result['error_message'] ?? null;
        $row->target_public_id = ($result['target_public_id'] ?? null) ?: null;
        $row->save();

        return $result['count'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $seen
     * @return array{status: ImportRowStatus, count: string, error_code?: string, error_message?: string, target_public_id?: string}
     */
    private function customerRow(
        ImportBatch $batch,
        array $data,
        array &$seen,
        bool $dryRun,
        ?User $actor,
        ImportRow $row,
    ): array {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'address_label' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->addAddressValidation($validator, $data);

        if ($validator->fails()) {
            return $this->invalid($row, $validator->errors()->all());
        }

        try {
            $phone = $this->phones->optional($this->stringValue($data['phone'] ?? null));
        } catch (ValidationException $exception) {
            return $this->invalid($row, $exception->errors()['phone'] ?? ['Invalid phone number.']);
        }

        $email = $this->stringValue($data['email'] ?? null);
        $dedup = $phone !== null ? 'phone:'.$phone : ($email !== null ? 'email:'.mb_strtolower($email) : null);

        if ($dedup !== null && array_key_exists($dedup, $seen)) {
            return ['status' => ImportRowStatus::Duplicate, 'count' => 'duplicate', 'target_public_id' => $seen[$dedup]];
        }

        $existing = $this->existingCustomer($batch->tenant_id, $phone, $email);
        if ($existing !== null) {
            if ($dedup !== null) {
                $seen[$dedup] = $existing->public_id;
            }

            return ['status' => ImportRowStatus::Duplicate, 'count' => 'duplicate', 'target_public_id' => $existing->public_id];
        }

        if ($dedup !== null) {
            $seen[$dedup] = null;
        }

        if ($dryRun) {
            return ['status' => ImportRowStatus::Validated, 'count' => 'valid'];
        }

        $attributes = [
            'name' => $this->stringValue($data['name']),
            'phone' => $phone,
            'email' => $email,
            'notes' => $this->stringValue($data['notes'] ?? null),
        ];
        if ($this->stringValue($data['address'] ?? null) !== null) {
            $attributes['addresses'] = [[
                'label' => $this->stringValue($data['address_label'] ?? null) ?? 'Utama',
                'address' => $this->stringValue($data['address']),
            ]];
        }

        try {
            $customer = $this->customers->handle($batch->tenant_id, $attributes, $actor);
        } catch (ValidationException $exception) {
            if ($dedup !== null) {
                unset($seen[$dedup]);
            }

            return $this->invalid($row, $exception->errors());
        }

        if ($dedup !== null) {
            $seen[$dedup] = $customer->public_id;
        }

        return ['status' => ImportRowStatus::Imported, 'count' => 'imported', 'target_public_id' => $customer->public_id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $seen
     * @return array{status: ImportRowStatus, count: string, error_code?: string, error_message?: string, target_public_id?: string}
     */
    private function catalogRow(ImportBatch $batch, array $data, array &$seen, bool $dryRun, ImportRow $row): array
    {
        $validator = Validator::make($data, [
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', Rule::in(['kg', 'pcs', 'pair', 'meter', 'package'])],
            'minimum_quantity' => ['required', 'numeric', 'gt:0'],
            'quantity_step' => ['required', 'numeric', 'gt:0'],
            'workflow_template_id' => ['required', 'uuid'],
            'sla_mode' => ['nullable', Rule::in(['working_days', 'elapsed_hours'])],
            'regular_turnaround' => ['nullable', 'integer', 'min:1'],
            'express_turnaround' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->invalid($row, $validator->errors()->all());
        }

        $code = $this->stringValue($data['code']);
        if (array_key_exists('code:'.$code, $seen)) {
            return ['status' => ImportRowStatus::Duplicate, 'count' => 'duplicate', 'target_public_id' => $seen['code:'.$code]];
        }
        $existing = Service::query()->where('tenant_id', $batch->tenant_id)->where('code', $code)->first();
        if ($existing !== null) {
            $seen['code:'.$code] = $existing->public_id;

            return ['status' => ImportRowStatus::Duplicate, 'count' => 'duplicate', 'target_public_id' => $existing->public_id];
        }

        $workflow = WorkflowTemplate::query()
            ->where('tenant_id', $batch->tenant_id)
            ->where('public_id', $this->stringValue($data['workflow_template_id']))
            ->where('is_active', true)
            ->first();
        if ($workflow === null) {
            return $this->invalid($row, ['workflow_template_id' => ['The workflow template must be active in this tenant.']]);
        }

        $seen['code:'.$code] = null;
        if ($dryRun) {
            return ['status' => ImportRowStatus::Validated, 'count' => 'valid'];
        }

        $attributes = [
            'code' => $code,
            'name' => $this->stringValue($data['name']),
            'unit' => $this->stringValue($data['unit']),
            'minimum_quantity' => (string) $data['minimum_quantity'],
            'quantity_step' => (string) $data['quantity_step'],
            'workflow_template_id' => $workflow->public_id,
            'sla_mode' => $this->stringValue($data['sla_mode'] ?? null) ?? 'working_days',
            'regular_turnaround' => (int) ($data['regular_turnaround'] ?? 2),
            'express_turnaround' => (int) ($data['express_turnaround'] ?? 1),
        ];

        try {
            $service = $this->services->handle($batch->tenant_id, $attributes);
        } catch (ValidationException $exception) {
            unset($seen['code:'.$code]);

            return $this->invalid($row, $exception->errors());
        }

        $seen['code:'.$code] = $service->public_id;

        return ['status' => ImportRowStatus::Imported, 'count' => 'imported', 'target_public_id' => $service->public_id];
    }

    private function existingCustomer(int $tenantId, ?string $phone, ?string $email): ?Customer
    {
        if ($phone !== null) {
            $customer = Customer::query()->where('tenant_id', $tenantId)->where('normalized_phone', $phone)->first();
            if ($customer !== null) {
                return $customer;
            }
        }

        if ($email !== null) {
            return Customer::query()->where('tenant_id', $tenantId)->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function addAddressValidation(\Illuminate\Validation\Validator $validator, array $data): void
    {
        if ($this->stringValue($data['address'] ?? null) !== null && $this->stringValue($data['address_label'] ?? null) === null) {
            $validator->errors()->add('address_label', 'An address label is required when an address is provided.');
        }
    }

    /**
     * @param  array<int, string>  $errors
     * @return array{status: ImportRowStatus, count: string, error_code: string, error_message: string}
     */
    private function invalid(ImportRow $row, array $errors): array
    {
        $message = collect($errors)->flatten()->filter()->implode(' ');

        return [
            'status' => ImportRowStatus::Invalid,
            'count' => 'invalid',
            'error_code' => 'VALIDATION_ERROR',
            'error_message' => $message !== '' ? $message : 'The row is invalid.',
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{array<int, string>, array<int, array{original: array<string, string>, mapped: array<string, mixed>}>}
     */
    private function parseCsv(string $content): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $headers = fgetcsv($stream);
        if ($headers === false) {
            throw ValidationException::withMessages(['file' => ['The CSV file is empty.']]);
        }

        $headers = array_map(fn ($value): string => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)), $headers);
        if ($headers === []) {
            throw ValidationException::withMessages(['file' => ['The CSV file has no header row.']]);
        }

        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            if (collect($values)->every(fn ($value): bool => trim((string) $value) === '')) {
                continue;
            }
            $original = [];
            $mapped = [];
            foreach ($headers as $index => $header) {
                $value = $values[$index] ?? '';
                $original[$header] = $value;
                $mapped[$header] = trim((string) $value);
            }
            $rows[] = ['original' => $original, 'mapped' => $mapped];
        }
        fclose($stream);

        return [$headers, $rows];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, string>  $mapping
     * @return array<string, string>
     */
    private function mapping(string $type, array $headers, array $mapping): array
    {
        $allowed = $type === 'customers' ? self::CUSTOMER_FIELDS : self::CATALOG_FIELDS;
        $required = $type === 'customers'
            ? ['name']
            : ['code', 'name', 'unit', 'minimum_quantity', 'quantity_step', 'workflow_template_id'];

        $mapping = $mapping === [] ? array_combine($headers, $headers) : $mapping;
        $result = [];
        foreach ($mapping as $header => $target) {
            if (! is_string($header) || ! is_string($target) || ! in_array($target, $allowed, true)) {
                throw ValidationException::withMessages(['mapping' => ['The mapping contains an unsupported target field.']]);
            }
            if (! in_array($header, $headers, true)) {
                throw ValidationException::withMessages(['mapping' => ['The mapping references a missing CSV header.']]);
            }
            $result[$header] = $target;
        }

        $targets = array_values($result);
        if (count($targets) !== count(array_unique($targets))) {
            throw ValidationException::withMessages(['mapping' => ['The mapping must not map two headers to one field.']]);
        }
        foreach ($required as $field) {
            if (! in_array($field, $targets, true)) {
                throw ValidationException::withMessages(['mapping' => ['The mapping is missing required field: '.$field.'.']]);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(array $data, array $mapping): array
    {
        $normalized = [];
        foreach ($mapping as $header => $target) {
            $normalized[$target] = $data[$header] ?? null;
        }

        return $normalized;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }
}
