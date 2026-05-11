<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class SafeDate implements CastsAttributes
{
    // Adda motherfucking rows nga 0000-00-00 ti value na isu kylangan i-cast. hahahah.
    // Just to be safe. amin nga model nga ada start_date kn end_date na nga column ket in-cast ko ltn.
    // Query below will prove it:

    // SELECT 'education' as tbl, COUNT(*) FROM tbl_employee_education WHERE start_date = '0000-00-00' OR end_date = '0000-00-00'
    // UNION ALL
    // SELECT 'training', COUNT(*) FROM tbl_employee_training WHERE start_date = '0000-00-00' OR end_date = '0000-00-00'
    // UNION ALL
    // SELECT 'work_exp', COUNT(*) FROM tbl_employee_work_exp WHERE start_date = '0000-00-00' OR end_date = '0000-00-00'
    // UNION ALL
    // SELECT 'volwork', COUNT(*) FROM tbl_employee_volwork WHERE start_date = '0000-00-00' OR end_date = '0000-00-00';


    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (!$value || str_starts_with($value, '0000-00-00')) return null;
        return \Carbon\Carbon::parse($value)->format('Y-m-d');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
