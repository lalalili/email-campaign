<?php

namespace Lalalili\EmailCampaign\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lalalili\EmailCampaign\Models\EmailCampaign;

/**
 * Imports campaign recipients from already-parsed spreadsheet rows (CSV / XLSX / XLS).
 *
 * Each row is a heading-keyed associative array. The reserved headers
 * `email` / `user_name` / `external_id` (matched case-insensitively) map to the
 * dedicated recipient columns; every other column is preserved verbatim into
 * `payload_json` so personalization keywords keep their original (often Chinese)
 * header text — see {@see BuildVariableMapAction}.
 *
 * Rows are upserted by `(email_campaign_id, email)` so re-importing updates rather
 * than violating the unique index; duplicate emails within one file collapse to a
 * single record (last row wins).
 */
class ImportCampaignRecipientsFromRowsAction
{
    /** @var list<string> */
    private const RESERVED = ['email', 'user_name', 'external_id'];

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @return array{imported: int, updated: int, skipped: int, errors: list<array{row: int, email: string, message: string}>}
     */
    public function execute(EmailCampaign $campaign, iterable $rows): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        /** @var array<string, array{email: string, attributes: array<string, mixed>}> $deduped */
        $deduped = [];

        $rowNumber = 1; // header occupies row 1; data begins at row 2

        foreach ($rows as $row) {
            $rowNumber++;

            $email = $this->reservedValue($row, 'email');

            if ($email === '') {
                $skipped++;

                continue;
            }

            if (Validator::make(['email' => $email], ['email' => ['email']])->fails()) {
                $errors[] = ['row' => $rowNumber, 'email' => $email, 'message' => 'Email 格式不正確，已略過。'];
                $skipped++;

                continue;
            }

            $deduped[mb_strtolower($email)] = [
                'email' => $email,
                'attributes' => [
                    'user_name' => $this->nullableReservedValue($row, 'user_name'),
                    'external_id' => $this->nullableReservedValue($row, 'external_id'),
                    'payload_json' => $this->foldPayload($row) ?: null,
                ],
            ];
        }

        DB::transaction(function () use ($campaign, $deduped, &$imported, &$updated): void {
            foreach ($deduped as $entry) {
                $recipient = $campaign->recipients()->updateOrCreate(
                    ['email' => $entry['email']],
                    $entry['attributes'],
                );

                $recipient->wasRecentlyCreated ? $imported++ : $updated++;
            }
        });

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function reservedValue(array $row, string $reserved): string
    {
        foreach ($row as $key => $value) {
            if (mb_strtolower(trim((string) $key)) === $reserved) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function nullableReservedValue(array $row, string $reserved): ?string
    {
        $value = $this->reservedValue($row, $reserved);

        return $value === '' ? null : $value;
    }

    /**
     * Every non-reserved, non-empty column, keyed by its original header text.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function foldPayload(array $row): array
    {
        $payload = [];

        foreach ($row as $key => $value) {
            $header = trim((string) $key);

            if ($header === '' || in_array(mb_strtolower($header), self::RESERVED, true)) {
                continue;
            }

            $normalized = is_string($value) ? trim($value) : $value;

            if ($normalized === null || $normalized === '') {
                continue;
            }

            $payload[$header] = $normalized;
        }

        return $payload;
    }
}
