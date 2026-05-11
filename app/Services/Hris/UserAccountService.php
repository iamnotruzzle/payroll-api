<?php

namespace App\Services\Hris;

use App\Models\Hris\UserAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserAccountService
{
    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        string $sort = 'username',
        string $direction = 'asc'
    ): LengthAwarePaginator {

        $query = UserAccount::with('employee')
            ->when($search, function ($q) use ($search) {

                $tokens = preg_split('/\s+/', trim($search));

                $q->where(function ($q2) use ($search, $tokens) {

                    $q2->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")

                        ->orWhere(function ($q3) use ($tokens) {

                            foreach ($tokens as $token) {

                                $q3->whereHas('employee', function ($q4) use ($token) {

                                    $q4->where(function ($q5) use ($token) {

                                        $q5->where('firstname', 'like', "%{$token}%")
                                            ->orWhere('lastname', 'like', "%{$token}%")
                                            ->orWhere('middlename', 'like', "%{$token}%");
                                    });
                                });
                            }
                        });
                });
            });

        $query->orderBy(match ($sort) {
            'userid' => 'userid',
            'emp_id' => 'emp_id',
            default  => 'username',
        }, $direction === 'descending' ? 'desc' : 'asc');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
