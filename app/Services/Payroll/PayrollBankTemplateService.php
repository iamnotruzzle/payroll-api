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
    //  Get all saved templates (id + created_at only), newest first.
    //   Used to populate the history/switcher list.
    public function getAll(): Collection
    {
        return PayrollBankTemplate::orderByDesc('id')
            ->get(['id', 'created_at']);
    }

    // Get a single template with its columns.
    public function getById(int $id): PayrollBankTemplate
    {
        return PayrollBankTemplate::with('columns')->findOrFail($id);
    }

    // Get the latest saved template with its columns.
    public function getLatest(): ?PayrollBankTemplate
    {
        return PayrollBankTemplate::with('columns')
            ->latest('id')
            ->first();
    }

    //  Always creates a NEW template snapshot — never overwrites.
    //   Every save = a new history entry.
    public function save(array $columns): PayrollBankTemplate
    {
        $template = PayrollBankTemplate::create([
            'created_at' => Carbon::now(),
        ]);

        $this->syncColumns($template, $columns);

        return $template->load('columns');
    }

    // Delete a template and its columns.
    public function delete(int $id): void
    {
        PayrollBankTemplate::findOrFail($id)->delete();
    }

    // Build a blank Excel file from a template's columns. Returns temp file path.
    public function buildExcel(PayrollBankTemplate $template): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1565C0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBBBBBB']]],
        ];

        foreach ($template->columns as $index => $col) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$letter}1", $col->label);
            $sheet->getStyle("{$letter}1")->applyFromArray($headerStyle);
            $sheet->getColumnDimension($letter)->setWidth($col->width / 7);
        }

        $sheet->freezePane('A2');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $path = sys_get_temp_dir() . '/bank_template_' . time() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

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
