<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CoaAccount extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'code', 'coa_group_id', 'coa_sub_group_id', 'person_id', 'description', 'is_active', 'is_default'];

    public function coaGroup()
    {
        return $this->belongsTo(CoaGroup::class);
    }

    public function coaSubGroup()
    {
        return $this->belongsTo(CoaSubGroup::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function balance()
    {
        return $this->hasOne(VoucherTransaction::class)
            ->groupBy('coa_account_id')
            ->select(DB::raw('SUM(debit)-SUM(credit) as balance'), 'coa_account_id')
            ->whereHas('voucher', function ($qu) {
                return $qu->where([['isApproved', 1], ['is_post_dated', 0]]);
            });
    }

    public static function getCoaAccountBal($account_id)
    {
        $balance = VoucherTransaction::where('coa_account_id', $account_id)
            ->groupBy('coa_account_id')
            ->select(DB::raw('SUM(debit)-SUM(credit) as balance'), 'coa_account_id')
            ->whereHas('voucher', function ($qu) {
                return $qu->where([['is_approved', 1], ['is_post_dated', 0]]);
            })->first();

        return $balance->balance;
    }
}
