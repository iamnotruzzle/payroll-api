<?php

namespace App\Console\Commands;

use App\Services\Payroll\HrisApiClient;
use Illuminate\Console\Command;
use RuntimeException;

class CheckHrisApi extends Command
{
    protected $signature = 'payroll:api-check {resource=basic-pay}
        {--employee= : Limit basic-pay to one employee ID}
        {--period= : YYYY-MM period for timekeeping}
        {--from= : YYYY-MM-DD leave range start}
        {--to= : YYYY-MM-DD leave range end}';

    protected $description = 'Check HRIS API connectivity and field mappings without changing payroll data';

    public function handle(HrisApiClient $client): int
    {
        foreach (['period' => 'Y-m', 'from' => 'Y-m-d', 'to' => 'Y-m-d'] as $option => $format) {
            $value = $this->option($option);
            if ($value !== null) {
                $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
                if (! $date || $date->format($format) !== $value) {
                    $this->error("Invalid --{$option}; expected {$format}.");

                    return self::FAILURE;
                }
            }
        }
        if ($this->option('from') && $this->option('to') && $this->option('from') > $this->option('to')) {
            $this->error('The leave range end must be on or after its start.');

            return self::FAILURE;
        }
        try {
            $result = $client->fetch($this->argument('resource'), [
                'employee_id' => $this->option('employee'), 'period' => $this->option('period'),
                'from' => $this->option('from'), 'to' => $this->option('to'),
            ]);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Connection and required mappings checked. No payroll data changed.');
        $this->table(['Metric', 'Count'], [
            ['Mapped records', count($result['rows'])], ['Pages', $result['pages']],
            ['Source warnings', $result['warning_count']],
        ]);
        if ($result['rows'] === []) {
            $this->warn('No records returned; field mappings could not be verified.');
        }
        if ($result['missing_fields'] !== []) {
            $this->table(['Missing or null optional field', 'Records'], collect($result['missing_fields'])->map(fn ($count, $field) => [$field, $count])->values()->all());
        }
        if ($result['warning_count'] > 0) {
            $this->warn('HRIS reported warnings; resolve their meaning before importing. Raw source content is not printed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
