<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\EconomicIndicator;
use App\Models\IndustryWaccData;
use App\Models\LearningUpdate;
use App\Models\ReferenceDataEntry;
use App\Models\User;
use App\Models\ValuationMultiple;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\ReferenceData\ReferenceDataSubmission;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\Exceptions\SecureFileStorageException;
use App\Services\Storage\SecureFileWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use ZipArchive;

final class ReferenceDataController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/reference-data/Index', [
            'datasets' => ReferenceDataEntry::datasets(),
            'currentValues' => $this->currentValues(),
            'entries' => $this->entryRows(),
        ]);
    }

    public function store(Request $request, ReferenceDataSubmission $submission, SecureFileWriter $files): RedirectResponse
    {
        abort_unless(Schema::hasTable('reference_data_entries'), 503, 'Reference data store is not migrated.');

        $validated = $request->validate([
            'dataset' => ['required', 'string', Rule::in(ReferenceDataEntry::datasets())],
            'source' => ['required', 'string', 'max:255'],
            'as_at' => ['required', 'date'],
            'payload_json' => ['nullable', 'string', 'required_without:upload'],
            'upload' => ['nullable', 'file', 'mimes:csv,txt,xlsx', 'max:10240', 'required_without:payload_json'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $dataset = (string) $validated['dataset'];
        $source = (string) $validated['source'];
        $asAt = Carbon::parse((string) $validated['as_at']);
        $fromUpload = $request->hasFile('upload');

        try {
            $payloads = $fromUpload
                ? $this->payloadsFromUpload($request->file('upload'), $dataset, $files, $user)
                : $this->payloadsFromJson((string) $validated['payload_json'], $dataset);

            foreach ($payloads as $payload) {
                $submission->submit($dataset, $payload, $asAt, $source, $user);
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                $fromUpload ? 'upload' : 'payload_json' => $exception->getMessage(),
            ]);
        }

        return to_route('admin.reference-data.index')->with('status', 'reference-data-submitted');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payloadsFromJson(string $json, string $dataset): array
    {
        try {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['payload_json' => 'Payload must be valid JSON.']);
        }

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['payload_json' => 'Payload must be a JSON object or array of objects.']);
        }

        if ($dataset === ReferenceDataEntry::DATASET_CPB_BENCHMARK) {
            return [$payload];
        }

        if (! array_is_list($payload)) {
            return [$payload];
        }

        foreach ($payload as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['payload_json' => 'Payload array must contain JSON objects.']);
            }
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payloadsFromUpload(?UploadedFile $upload, string $dataset, SecureFileWriter $files, User $user): array
    {
        if (! $upload instanceof UploadedFile) {
            throw ValidationException::withMessages(['upload' => 'Upload is required.']);
        }

        try {
            $document = $files->write(
                uploadedFile: $upload,
                owner: $user,
                category: Document::CATEGORY_OTHER,
            );
        } catch (InfectedFileException) {
            throw ValidationException::withMessages(['upload' => 'Upload failed malware scanning.']);
        } catch (SecureFileStorageException) {
            throw ValidationException::withMessages(['upload' => 'Upload could not be securely stored for scanning.']);
        }

        if ($document->scanner_result !== Document::SCANNER_CLEAN) {
            throw ValidationException::withMessages(['upload' => 'Upload is quarantined until malware scanning is available.']);
        }

        $contents = Storage::disk('secure_local')->get($document->stored_path);
        $extension = strtolower($upload->getClientOriginalExtension());
        $rows = $extension === 'xlsx'
            ? $this->rowsFromXlsx($contents)
            : $this->rowsFromCsv($contents);

        return $dataset === ReferenceDataEntry::DATASET_CPB_BENCHMARK ? [$rows] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromCsv(string $csv): array
    {
        $lines = preg_split('/\R/', trim($csv)) ?: [];
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['upload' => 'CSV upload requires a header row and at least one data row.']);
        }

        $headers = array_map('trim', str_getcsv((string) array_shift($lines)));
        $payloads = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            $payload = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $payload[$header] = $values[$index] ?? null;
                }
            }

            if ($payload !== []) {
                $payloads[] = $payload;
            }
        }

        if ($payloads === []) {
            throw ValidationException::withMessages(['upload' => 'CSV upload did not contain any data rows.']);
        }

        return $payloads;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromXlsx(string $bytes): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['upload' => 'XLSX parsing requires the PHP zip extension.']);
        }

        $path = tempnam(sys_get_temp_dir(), 'fsa-reference-data-');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            throw ValidationException::withMessages(['upload' => 'XLSX upload could not be opened.']);
        }

        $zip = new ZipArchive;
        $opened = false;

        try {
            if ($zip->open($path) !== true) {
                throw ValidationException::withMessages(['upload' => 'XLSX upload is not a readable workbook.']);
            }
            $opened = true;

            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (! is_string($sheet)) {
                throw ValidationException::withMessages(['upload' => 'XLSX upload must include a first worksheet.']);
            }

            $rows = $this->matrixRowsFromXlsxSheet($sheet, $this->sharedStringsFromXlsx($zip));

            return $this->payloadsFromMatrix($rows, 'upload');
        } finally {
            if ($opened) {
                $zip->close();
            }

            @unlink($path);
        }
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function matrixRowsFromXlsxSheet(string $xml, array $sharedStrings): array
    {
        $sheet = $this->xml($xml, 'XLSX worksheet could not be parsed.');
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $cells[$this->xlsxColumnIndex($reference)] = $this->xlsxCellValue($cell, $sharedStrings);
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $max = max(array_keys($cells));
            $rows[] = array_map(
                fn (int $index): string => $cells[$index] ?? '',
                range(0, $max),
            );
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStringsFromXlsx(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! is_string($xml)) {
            return [];
        }

        $strings = [];
        $shared = $this->xml($xml, 'XLSX shared strings could not be parsed.');
        foreach ($shared->si as $item) {
            $parts = $item->xpath('.//t') ?: [];
            $strings[] = implode('', array_map(fn ($part): string => (string) $part, $parts));
        }

        return $strings;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function payloadsFromMatrix(array $rows, string $field): array
    {
        $rows = array_values(array_filter($rows, fn (array $row): bool => array_filter($row, fn (string $value): bool => trim($value) !== '') !== []));
        if (count($rows) < 2) {
            throw ValidationException::withMessages([$field => 'Upload requires a header row and at least one data row.']);
        }

        $headers = array_map('trim', array_shift($rows));
        $payloads = [];

        foreach ($rows as $row) {
            $payload = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $payload[$header] = $row[$index] ?? null;
                }
            }

            if ($payload !== []) {
                $payloads[] = $payload;
            }
        }

        if ($payloads === []) {
            throw ValidationException::withMessages([$field => 'Upload did not contain any data rows.']);
        }

        return $payloads;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            return $sharedStrings[(int) ((string) $cell->v)] ?? '';
        }

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        return (string) ($cell->v ?? '');
    }

    private function xlsxColumnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function xml(string $xml, string $message): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $parsed instanceof \SimpleXMLElement) {
            throw ValidationException::withMessages(['upload' => $message]);
        }

        return $parsed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entryRows(): array
    {
        if (! Schema::hasTable('reference_data_entries')) {
            return [];
        }

        return ReferenceDataEntry::query()
            ->with('learningUpdate')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ReferenceDataEntry $entry): array => [
                'id' => $entry->id,
                'dataset' => $entry->dataset,
                'as_at' => $entry->as_at?->toDateString(),
                'source' => $entry->source,
                'learning_update_id' => $entry->learning_update_id,
                'learning_update_status' => $entry->learningUpdate?->status,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function currentValues(): array
    {
        $economic = $this->whenTableExists('economic_indicators', fn (): Collection => EconomicIndicator::query()
            ->latest('period_date')
            ->latest('fetched_at')
            ->limit(8)
            ->get()
            ->map(fn (EconomicIndicator $indicator): array => [
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'label' => $indicator->label,
                'value' => $indicator->value.' '.$indicator->unit,
                'as_at' => $indicator->period_date?->toDateString(),
                'source' => $indicator->source,
            ]));

        $valuation = $this->whenTableExists('valuation_multiples', fn (): Collection => ValuationMultiple::query()
            ->whereNull('superseded_at')
            ->latest('fetched_at')
            ->limit(8)
            ->get()
            ->map(fn (ValuationMultiple $multiple): array => [
                'dataset' => ReferenceDataEntry::DATASET_VALUATION_MULTIPLE,
                'label' => $multiple->industry_label.' / '.$multiple->metric,
                'value' => $multiple->multiple_low.'-'.$multiple->multiple_mid.'-'.$multiple->multiple_high,
                'as_at' => $multiple->quarter,
                'source' => $multiple->source,
            ]));

        $wacc = $this->whenTableExists('industry_wacc_data', fn (): Collection => IndustryWaccData::query()
            ->whereNull('superseded_at')
            ->latest('fetched_at')
            ->limit(8)
            ->get()
            ->map(fn (IndustryWaccData $row): array => [
                'dataset' => ReferenceDataEntry::DATASET_INDUSTRY_WACC,
                'label' => $row->industry_label,
                'value' => round($row->wacc_rate * 100, 2).'%',
                'as_at' => $row->quarter,
                'source' => $row->source,
            ]));

        $cpb = collect();
        $update = Schema::hasTable('learning_updates')
            ? LearningUpdate::query()
                ->where('layer_id', LayerCadenceRegistry::LAYER_NPO_COST_PER_BENEFICIARY_BENCHMARKS)
                ->where('status', LearningUpdate::STATUS_IMPLEMENTED)
                ->latest('updated_at')
                ->latest('created_at')
                ->first()
            : null;

        if ($update instanceof LearningUpdate) {
            $cpb = collect((array) data_get($update->proposed_change, 'benchmarks', []))
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $row): array => [
                    'dataset' => ReferenceDataEntry::DATASET_CPB_BENCHMARK,
                    'label' => str_replace('_', ' ', (string) ($row['programme_type'] ?? '')).' / '.str_replace('_', ' ', (string) ($row['size_band'] ?? '')),
                    'value' => (string) ($row['cost_per_beneficiary'] ?? ''),
                    'as_at' => (string) data_get($update->evidence, 'as_at', ''),
                    'source' => (string) data_get($update->source, 'source', 'learning_update'),
                ])
                ->values();
        }

        return $economic
            ->concat($valuation)
            ->concat($wacc)
            ->concat($cpb)
            ->values()
            ->all();
    }

    /**
     * @param  callable(): Collection<int, array<string, mixed>>  $callback
     * @return Collection<int, array<string, mixed>>
     */
    private function whenTableExists(string $table, callable $callback): Collection
    {
        return Schema::hasTable($table) ? $callback() : collect();
    }
}
