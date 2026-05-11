<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollTimeTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TimeTemplateService
{
    public function paginate(int $page, int $perPage): LengthAwarePaginator
    {
        return PayrollTimeTemplate::orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(int $id): ?PayrollTimeTemplate
    {
        return PayrollTimeTemplate::find($id);
    }

    public function save(array $data, ?int $id = null): PayrollTimeTemplate
    {
        if ($id) {
            $template = PayrollTimeTemplate::findOrFail($id);
        } else {
            $template = new PayrollTimeTemplate();
        }

        $template->fill($data);
        $template->save();

        return $template;
    }
}
