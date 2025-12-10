<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherTransaction extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['voucher_id', 'coa_account_id', 'debit', 'credit', 'balance', 'description', 'date'];

    public function coaAccount()
    {
        return $this->belongsTo(CoaAccount::class)->select('id', 'name', 'code', 'type');
    }

    public function voucherNumber()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id')->select('id', 'voucher_no', 'isApproved', 'type', 'cheque_no', 'cheque_date', 'cleared_date','generated_at');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

}
