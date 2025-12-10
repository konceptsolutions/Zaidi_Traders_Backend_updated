<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VoucherTransactionInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['voucher_id' , 'invoice_id'];
}
