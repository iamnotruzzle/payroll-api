<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollBankTemplate extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_bank_templates';

    public $timestamps = false;

    protected $fillable = [
        'bank_name',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function columns(): HasMany
    {
        return $this->hasMany(PayrollBankTemplateColumn::class, 'template_id')
            ->orderBy('position');
    }
}
