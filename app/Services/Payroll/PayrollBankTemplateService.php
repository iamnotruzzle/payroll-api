<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollBankTemplate;
use App\Models\Payroll\PayrollBankTemplateColumn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollBankTemplateService
{
    /**
     * Get the latest saved template with its columns.
     * Returns null if none has been saved yet.
     */
    public function getLatest(): ?PayrollBankTemplate
    {
        return PayrollBankTemplate::with('columns')
            ->latest('id')
            ->first();
    }

    /**
     * Create a new template snapshot with the provided columns.
     * Each call creates a new record (timestamp = identifier).
     */
    public function save(array $columns): PayrollBankTemplate
    {
        $template = PayrollBankTemplate::create([
            'created_at' => Carbon::now(),
        ]);

        $this->syncColumns($template, $columns);

        return $template->load('columns');
    }

    /**
     * Update an existing template's columns in-place.
     */
    public function updateColumns(int $templateId, array $columns): PayrollBankTemplate
    {
        $template = PayrollBankTemplate::findOrFail($templateId);

        PayrollBankTemplateColumn::where('template_id', $template->id)->delete();
        $this->syncColumns($template, $columns);

        return $template->load('columns');
    }

    /**
     * Build a blank Excel file from a template's columns and return the file path.
     */
    public function buildExcel(PayrollBankTemplate $template): string
    {
        $columns = $template->columns;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        // Header style
        $headerStyle = [
            'font' => [
                'bold'  => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size'  => 10,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1565C0'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FFBBBBBB'],
                ],
            ],
        ];

        // Write headers
        foreach ($columns as $index => $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $cell = $colLetter . '1';

            $sheet->setCellValue($cell, $col->label);
            $sheet->getStyle($cell)->applyFromArray($headerStyle);
            $sheet->getColumnDimension($colLetter)->setWidth($col->width / 7); // pts → approx chars
        }

        // Freeze header row
        $sheet->freezePane('A2');

        // Row height for header
        $sheet->getRowDimension(1)->setRowHeight(24);

        $tempPath = sys_get_temp_dir() . '/bank_template_' . time() . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return $tempPath;
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function syncColumns(PayrollBankTemplate $template, array $columns): void
    {
        foreach ($columns as $position => $col) {
            PayrollBankTemplateColumn::create([
                'template_id' => $template->id,
                'column_key'  => $col['column_key'],
                'label'       => $col['label'],
                'position'    => $position,
                'width'       => $col['width'] ?? 150,
            ]);
        }
    }
}
