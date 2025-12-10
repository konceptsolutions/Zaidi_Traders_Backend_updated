<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Quotation extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'quotations';
    protected $fillable = ['person_id', 'sales_rep_id', 'walk_in_customer_name', 'walk_in_customer_phone', 'sale_type', 'tax_type', 'quotation_no', 'ref_no', 'date', 'total_amount', 'status', 'is_inv_generated', 'remarks', 'termcondition'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($Quotation) {
            $lastRec = static::withTrashed()->latest('quotation_no')->first();
            $lastRecNumber = $lastRec ? $lastRec->quotation_no : '1000';
            $newRecNumber = (int) $lastRecNumber + 1;
            $Quotation->quotation_no = (string) $newRecNumber;
        });
    }
    public function quotationchild()
    {
        return $this->hasMany(QuotationChild::class, 'parent_id', 'id')->with('item');
    }
    public function customer()
    {
        return $this->belongsTo(Person::class, 'person_id', 'id');
    }
    public function salesrep()
    {
        return $this->belongsTo(Person::class, 'sales_rep_id', 'id');
    }
}
