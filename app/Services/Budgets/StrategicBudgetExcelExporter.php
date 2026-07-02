<?php

declare(strict_types=1);

namespace App\Services\Budgets;

use App\Models\StrategicBudget;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class StrategicBudgetExcelExporter
{
    public function __construct(private readonly StrategicBudgetService $budgets) {}

    public function filename(StrategicBudget $budget): string
    {
        $client = $budget->client;
        $name = $client?->trading_name ?: $client?->legal_name ?: 'client';

        return Str::slug($name.' '.$budget->label.' export').'.xlsx';
    }

    public function export(StrategicBudget $budget): string
    {
        $analytics = $this->budgets->analyticsPayload($budget);

        return $this->workbook([
            'Summary' => $this->summaryRows($budget, $analytics),
            'Descriptive' => $this->descriptiveRows($analytics),
            'Diagnostic' => $this->diagnosticRows($analytics),
            'Predictive' => $this->predictiveRows($analytics),
            'Prescriptive' => $this->prescriptiveRows($analytics),
            'Monthly Forecast' => $this->monthlyForecastRows($analytics),
            'Annual Forecast' => $this->annualForecastRows($analytics),
            'Scenarios' => $this->scenarioRows($analytics),
            'Inputs' => $this->inputRows($budget),
        ]);
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, array<int, mixed>>
     */
    private function summaryRows(StrategicBudget $budget, array $analytics): array
    {
        $client = $budget->client;

        return [
            ['Future Shift Advisory', $budget->label],
            ['Client', $client?->trading_name ?: $client?->legal_name ?: $budget->client_id],
            ['Status', $budget->status],
            ['Pathway', $budget->pathway],
            ['Horizon months', $budget->horizon_months],
            ['Readiness score', (int) data_get($budget->confidence, 'score', 0)],
            ['Exported at', now()->toIso8601String()],
            [],
            ['Framework', 'Summary'],
            ['Descriptive', data_get($analytics, 'descriptive.summary')],
            ['Diagnostic', data_get($analytics, 'diagnostic.summary')],
            ['Predictive', data_get($analytics, 'predictive.summary')],
            ['Prescriptive', data_get($analytics, 'prescriptive.summary')],
            [],
            ['Framework', 'Hover explanation'],
            ['Descriptive', data_get($analytics, 'descriptive.explanation')],
            ['Diagnostic', data_get($analytics, 'diagnostic.explanation')],
            ['Predictive', data_get($analytics, 'predictive.explanation')],
            ['Prescriptive', data_get($analytics, 'prescriptive.explanation')],
        ];
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, array<int, mixed>>
     */
    private function descriptiveRows(array $analytics): array
    {
        $rows = [
            ['Descriptions'],
            ...collect((array) data_get($analytics, 'descriptive.findings', []))
                ->map(fn (mixed $finding): array => [(string) $finding])
                ->all(),
            [],
            ['Metric', 'Value', 'Format'],
            ...collect((array) data_get($analytics, 'descriptive.metrics', []))
                ->map(fn (array $metric): array => [
                    $metric['label'] ?? '',
                    $metric['value'] ?? '',
                    $metric['format'] ?? '',
                ])
                ->all(),
            [],
            ['Source financials'],
            ['Unlocked', data_get($analytics, 'descriptive.source_financials.unlocked') ? 'Yes' : 'No'],
            ['Count', (int) data_get($analytics, 'descriptive.source_financials.count', 0)],
            [],
            ['Filename', 'Detected as', 'Uploaded at'],
        ];

        return [
            ...$rows,
            ...collect((array) data_get($analytics, 'descriptive.source_financials.items', []))
                ->map(fn (array $item): array => [
                    $item['filename'] ?? '',
                    $item['detected_as'] ?? '',
                    $item['uploaded_at'] ?? '',
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, array<int, mixed>>
     */
    private function diagnosticRows(array $analytics): array
    {
        return [
            ['Diagnoses'],
            ...collect((array) data_get($analytics, 'diagnostic.findings', []))
                ->map(fn (mixed $finding): array => [(string) $finding])
                ->all(),
            [],
            ['Flags'],
            ['Key', 'Title', 'Severity', 'Message'],
            ...collect((array) data_get($analytics, 'diagnostic.flags', []))
                ->map(fn (array $flag): array => [
                    $flag['key'] ?? '',
                    $flag['title'] ?? '',
                    $flag['severity'] ?? '',
                    $flag['message'] ?? '',
                ])
                ->all(),
            [],
            ['Cost drivers'],
            ['Driver', 'Value'],
            ...collect((array) data_get($analytics, 'diagnostic.cost_drivers', []))
                ->map(fn (array $driver): array => [
                    $driver['label'] ?? '',
                    $driver['value'] ?? 0,
                ])
                ->all(),
            [],
            ['Missing assumptions'],
            ['Key', 'Label'],
            ...collect((array) data_get($analytics, 'diagnostic.missing_assumptions', []))
                ->map(fn (array $assumption): array => [
                    $assumption['key'] ?? '',
                    $assumption['label'] ?? '',
                ])
                ->all(),
            [],
            ['Confidence mix'],
            ['Known', data_get($analytics, 'diagnostic.confidence_mix.known', 0)],
            ['Estimate', data_get($analytics, 'diagnostic.confidence_mix.estimate', 0)],
            ['Guess', data_get($analytics, 'diagnostic.confidence_mix.guess', 0)],
        ];
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, array<int, mixed>>
     */
    private function predictiveRows(array $analytics): array
    {
        return [
            ['Predictions'],
            ...collect((array) data_get($analytics, 'predictive.findings', []))
                ->map(fn (mixed $finding): array => [(string) $finding])
                ->all(),
            [],
            ['Key events'],
            ['Metric', 'Value', 'Format'],
            ...collect((array) data_get($analytics, 'predictive.key_events', []))
                ->map(fn (array $metric): array => [
                    $metric['label'] ?? '',
                    $metric['value'] ?? '',
                    $metric['format'] ?? '',
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, array<int, mixed>>
     */
    private function prescriptiveRows(array $analytics): array
    {
        return [
            ['Prescriptions'],
            ...collect((array) data_get($analytics, 'prescriptive.findings', []))
                ->map(fn (mixed $finding): array => [(string) $finding])
                ->all(),
            [],
            ['Priority', 'Action', 'Reason'],
            ...collect((array) data_get($analytics, 'prescriptive.actions', []))
                ->map(fn (array $action): array => [
                    $action['priority'] ?? '',
                    $action['action'] ?? '',
                    $action['reason'] ?? '',
                ])
                ->all(),
            [],
            ['Advisor decision points'],
            ...collect((array) data_get($analytics, 'prescriptive.advisor_decision_points', []))
                ->map(fn (mixed $point): array => [(string) $point])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, array<int, mixed>>
     */
    private function monthlyForecastRows(array $analytics): array
    {
        return [
            ['Month', 'Year', 'Revenue', 'Variable costs', 'Fixed costs', 'Interest', 'Tax', 'Loan principal', 'Funding inflow', 'Launch costs', 'Net profit after tax', 'Net cash flow', 'Cumulative cash'],
            ...collect((array) data_get($analytics, 'predictive.monthly_forecast', []))
                ->map(fn (array $row): array => [
                    $row['month'] ?? 0,
                    $row['year'] ?? 0,
                    $row['revenue'] ?? 0,
                    $row['variable_costs'] ?? 0,
                    $row['fixed_costs'] ?? 0,
                    $row['interest'] ?? 0,
                    $row['tax'] ?? 0,
                    $row['loan_principal'] ?? 0,
                    $row['funding_inflow'] ?? 0,
                    $row['launch_costs'] ?? 0,
                    $row['net_profit_after_tax'] ?? 0,
                    $row['net_cash_flow'] ?? 0,
                    $row['cumulative_cash'] ?? 0,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, array<int, mixed>>
     */
    private function annualForecastRows(array $analytics): array
    {
        return [
            ['Year', 'Revenue', 'Variable costs', 'Fixed costs', 'Interest', 'Tax', 'Loan principal', 'Funding inflow', 'Launch costs', 'Gross profit', 'GP %', 'NPBT', 'NPBT %', 'NPAT', 'NPAT %', 'Net cash flow', 'Ending cash'],
            ...collect((array) data_get($analytics, 'predictive.annual_forecast', []))
                ->map(fn (array $row): array => [
                    $row['year'] ?? 0,
                    $row['revenue'] ?? 0,
                    $row['variable_costs'] ?? 0,
                    $row['fixed_costs'] ?? 0,
                    $row['interest'] ?? 0,
                    $row['tax'] ?? 0,
                    $row['loan_principal'] ?? 0,
                    $row['funding_inflow'] ?? 0,
                    $row['launch_costs'] ?? 0,
                    $row['gross_profit'] ?? 0,
                    $this->marginPercent((float) ($row['gross_profit'] ?? 0), (float) ($row['revenue'] ?? 0)),
                    $row['net_profit_before_tax'] ?? 0,
                    $this->marginPercent((float) ($row['net_profit_before_tax'] ?? 0), (float) ($row['revenue'] ?? 0)),
                    $row['net_profit_after_tax'] ?? 0,
                    $this->marginPercent((float) ($row['net_profit_after_tax'] ?? 0), (float) ($row['revenue'] ?? 0)),
                    $row['net_cash_flow'] ?? 0,
                    $row['ending_cash'] ?? 0,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, array<int, mixed>>
     */
    private function scenarioRows(array $analytics): array
    {
        return [
            ['Scenario', 'Type', 'Runway months', 'Open ended runway', 'Break-even year', 'Cash-flow positive year', 'Total funding', 'Ending cash'],
            ...collect((array) data_get($analytics, 'predictive.scenarios', []))
                ->map(fn (array $row): array => [
                    $row['name'] ?? '',
                    $row['type'] ?? '',
                    $row['runway_months'] ?? '',
                    ! empty($row['runway_open_ended']) ? 'Yes' : 'No',
                    $row['break_even_year'] ?? '',
                    $row['cash_flow_positive_year'] ?? '',
                    $row['total_funding'] ?? 0,
                    $row['ending_cash'] ?? 0,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function inputRows(StrategicBudget $budget): array
    {
        return [
            ['Assumptions'],
            ['Key', 'Value'],
            ...collect((array) ($budget->assumptions ?? []))
                ->map(fn (mixed $value, string $key): array => [$key, $value])
                ->values()
                ->all(),
            [],
            ...$this->inputGroupRows('Implementation costs', (array) ($budget->implementation_costs ?? [])),
            [],
            ...$this->inputGroupRows('Monthly fixed costs', (array) ($budget->monthly_fixed_costs ?? [])),
            [],
            ...$this->inputGroupRows('Revenue forecast', (array) ($budget->revenue_forecast ?? [])),
            [],
            ...$this->inputGroupRows('Funding sources', (array) ($budget->funding_sources ?? [])),
            [],
            ...$this->inputGroupRows('Future costs', (array) ($budget->future_costs ?? [])),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    private function inputGroupRows(string $title, array $rows): array
    {
        return [
            [$title],
            ['Label', 'Amount', 'Quantity', 'Month', 'Year', 'Confidence'],
            ...collect($rows)
                ->map(fn (array $row): array => [
                    $row['label'] ?? '',
                    $row['amount'] ?? 0,
                    $row['quantity'] ?? 1,
                    $row['month'] ?? '',
                    $row['year'] ?? '',
                    $row['confidence'] ?? '',
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, array<int, array<int, mixed>>>  $sheets
     */
    private function workbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fsa-budget-export-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary workbook.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);

            throw new RuntimeException('Unable to create the workbook archive.');
        }

        $sheetNames = array_keys($sheets);
        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetNames));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml(count($sheets)));

        foreach (array_values($sheets) as $index => $rows) {
            $zip->addFromString(
                'xl/worksheets/sheet'.($index + 1).'.xml',
                $this->worksheetXml($rows),
            );
        }

        $zip->close();
        $contents = file_get_contents($path);
        @unlink($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the generated workbook.');
        }

        return $contents;
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $overrides = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  array<int, string>  $sheetNames
     */
    private function workbookXml(array $sheetNames): string
    {
        $sheets = collect($sheetNames)
            ->values()
            ->map(fn (string $name, int $index): string => '<sheet name="'.$this->escapeAttribute($this->sheetName($name)).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(int $sheetCount): string
    {
        $relationships = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relationships
            .'</Relationships>';
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function worksheetXml(array $rows): string
    {
        $body = collect($rows)
            ->values()
            ->map(function (array $row, int $rowIndex): string {
                $rowNumber = $rowIndex + 1;
                $cells = collect(array_values($row))
                    ->map(fn (mixed $value, int $columnIndex): string => $this->cell($value, $columnIndex + 1, $rowNumber))
                    ->implode('');

                return '<row r="'.$rowNumber.'">'.$cells.'</row>';
            })
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$body.'</sheetData>'
            .'</worksheet>';
    }

    private function cell(mixed $value, int $column, int $row): string
    {
        $reference = $this->columnName($column).$row;

        if ($value === null) {
            $value = '';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"><v>'.(string) $value.'</v></c>';
        }

        $text = (string) $value;
        $space = trim($text) !== $text ? ' xml:space="preserve"' : '';

        return '<c r="'.$reference.'" t="inlineStr"><is><t'.$space.'>'.$this->escapeText($text).'</t></is></c>';
    }

    private function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function sheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name) ?: 'Sheet';

        return Str::limit(trim($name) ?: 'Sheet', 31, '');
    }

    private function marginPercent(float $profit, float $revenue): float
    {
        if ($revenue === 0.0) {
            return 0.0;
        }

        return round(($profit / $revenue) * 100, 1);
    }

    private function escapeText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
