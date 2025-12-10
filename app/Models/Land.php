<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Land extends Model
{
    use HasFactory;
    protected $fillable = [
        'file_no', 'location_id', 'khewat', 'khatoni', 'mouza_id', 'size_id', 'rate_per_marla', 'total_price', 'dc_value', 'purchase_date', 'land_category_id', 'status', 'description', 'date', 'inteqal_bezubani', 'isApproved','jv_person','is_returned'
    ];

    public function landCategory()
    {
        return $this->belongsTo(landCategory::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }


    public function mouza()
    {
        return $this->belongsTo(Mouza::class);
    }

    public function purchasers()
    {
        return $this->hasMany(LandPerson::class)->with('purchaser')->where('person_type', 1);
    }

    public function jvPerson()
    {
        return $this->belongsTo(Person::class,'jv_person')->select('id','name','father_name');
    }

    public function sellers()
    {
        return $this->hasMany(LandPerson::class)->with('seller')->where('person_type', 2);
    }

    public function agents()
    {
        return $this->hasMany(LandPerson::class)->with('agent')->where('person_type', 3);
    }

    public function stagesRecords()
    {
        return $this->hasMany(StagesRecord::class)->with('stage');
    }
    public function registries()
    {
        return $this->hasMany(Registry::class)->with('registryPersons');
    }

    public function landPayments()
    {
        return $this->hasMany(LandPayment::class)->with('transactionType', 'payer', 'payeeSeller', 'payeeAgent')->orderBy('date');
    }

    public function landPaymentsSum()
    {
        return $this->hasOne(LandPayment::class)->groupBy('land_id')->select(DB::raw('SUM(amount) as simplePayments'), 'land_id');
    }

    public function taxesSum()
    {
        return $this->hasOne(Tax::class)->groupBy('land_id')->select(DB::raw('SUM(amount) as taxesSum'), 'land_id');
    }

    public function taxes()
    {
        return $this->hasMany(Tax::class)->with('taxType', 'transactionType')->orderBy('date');
    }

    public function expensesSum()
    {
        return $this->hasOne(Expense::class)->groupBy('land_id')->select(DB::raw('SUM(amount) as expensesSum'), 'land_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class)->with('expenseType', 'transactionType')->orderBy('date');
    }

    public function khasraRecords()
    {
        return $this->hasMany(KhasraRecord::class)->with('khasra', 'clearedLands', 'size', 'registryRecords');
    }

    public function khasras()
    {
        return $this->hasManyThrough(Khasra::class, KhasraRecord::class);
    }

    public function mutationNo()
    {
        return $this->hasMany(StagesRecord::class)->where('stage_id', 3);
    }

    public function registryRecords()
    {
        return $this->hasMany(RegistryRecord::class)->with('totalLand')->select('id', 'total_size_id', 'land_id', 'khasra_id')->orderBy('date');
    }

    public function purchaseInstallments()
    {
        return $this->hasMany(PurchaseInstallment::class);
    }

    public static function changeDateFormat($reqDate, $backend = 1)
    {
        if (!isset($reqDate)) {
            return null;
        }
        if ($backend == 1) {
            if (strlen($reqDate) > 8) {
                return $reqDate;
            }
            $date = str_split(str_replace('/', '-', $reqDate), 3);
            return $date = '20' . $date[2] . '-' . $date[1] . str_replace('-', '', $date[0]);
        } else {
            $getDate = explode(" ", $reqDate);
            $getDate = explode("-", $getDate[0]);
            return $date = $getDate[2] . "/" . $getDate[1] . "/" . substr($getDate[0], 2);
        }
    }

    public static function getPurchasedLand($land_id = "", $khasra_id = "")
    {
        if ($land_id == "" && $khasra_id == "") {
            return 'Please provide khasra_id or land_id or both';
        }
        $khasraRecord = KhasraRecord::with('size')
            ->when($land_id, function ($query, $land_id) {
                return $query->where('land_id', '=', $land_id);
            })
            ->when($khasra_id, function ($query, $khasra_id) {
                return $query->where('khasra_id', '=', $khasra_id);
            })
            ->get();
        $sizeInSqft = 0;
        foreach ($khasraRecord as $khasraRecord) {
            $size = Size::convertToSqFeet($khasraRecord->size);
            $sizeInSqft += $size;
        }
        return Size::convertFromSqFeet($sizeInSqft);
    }


    public static function getClearedLand($land_id = "", $khasra_id = "")
    {
        if ($land_id == "" && $khasra_id == "") {
            return 'Please provide khasra_id or land_id or both';
        }
        $registryRecord = RegistryRecord::with('totalLand')
            ->when($land_id, function ($query, $land_id) {
                return $query->where('land_id', '=', $land_id);
            })
            ->when($khasra_id, function ($query, $khasra_id) {
                return $query->where('khasra_id', '=', $khasra_id);
            })
            ->get();
        $sizeInSqft = 0;
        foreach ($registryRecord as $registryRecord) {
            $size = Size::convertToSqFeet($registryRecord->totalLand);
            $sizeInSqft += $size;
        }

        $khasraRecord = KhasraRecord::with('size')
            ->whereHas('land', function ($q) {
                $q->where('inteqal_bezubani', 1);
            })
            ->when($land_id, function ($query, $land_id) {
                return $query->where('land_id', '=', $land_id);
            })
            ->when($khasra_id, function ($query, $khasra_id) {
                return $query->where('khasra_id', '=', $khasra_id);
            })
            ->get();
        foreach ($khasraRecord as $khasraRecord) {
            $size = Size::convertToSqFeet($khasraRecord->size);
            $sizeInSqft += $size;
        }
        return Size::convertFromSqFeet($sizeInSqft);
    }

    public static function getLandTotalSize($land_id)
    {
        return self::getPurchasedLand($land_id);
    }

    public function installmentsSum()
    {
        return $this->hasOne(PurchaseInstallment::class)->groupBy('land_id')->select(DB::raw('SUM(amount) as totalAmount'), 'land_id');
    }

    public function expensesTransactionsSum()
    {
        return $this->hasOne(VoucherTransaction::class)->groupBy('land_id')
        ->select(DB::raw('SUM(debit) as totalAmount'), 'land_id')
        ->whereHas('voucher', function ($qu) {
            return $qu->where([['isApproved', 1], ['is_post_dated', 0], ['type', 5]])
            ->orWhere([['isApproved', 1], ['is_post_dated', 0], ['type', 7]]);
        })
        ->whereHas('coaAccount', function ($qu) {
            return $qu->where([['type', '=', 'mouza']]);
        })
        ->where([['credit', 0], ['land_payment_head_id', '!=', 10]]);
    }

    public function paymentsTransactionsSum()
    {
        return $this->hasOne(VoucherTransaction::class)->groupBy('land_id')
        ->select(DB::raw('SUM(debit) as totalAmount'), 'land_id')
        ->whereHas('voucher', function ($qu) {
            return $qu->where([['isApproved', 1], ['is_post_dated', 0], ['type', 5]])
            ->orWhere([['isApproved', 1], ['is_post_dated', 0], ['type', 7]])
            ;
        })
        ->whereHas('coaAccount', function ($qu) {
            return $qu->where([['type', '!=', 'mouza']])->orWhereNull('type');
        })
        ->where([['credit', 0], ['land_payment_head_id', '!=', 10]]);
    }

    public function totalDebit()
    {
        return $this->hasOne(VoucherTransaction::class)->groupBy('land_id')->select(DB::raw('SUM(debit) as total_payment'), 'land_id','date')->whereHas('voucher', function ($qu) {
            return $qu->where([['isApproved', 1], ['is_post_dated', 0], ['type', 5]]);
        })->whereHas('coaAccount', function ($qu) {
            return $qu->where([['type', '!=', 'mouza']])->orWhereNull('type');
        });
    }


    public function totalInstallmentSum()
    {
        return $this->hasOne(PurchaseInstallment::class)
        ->groupBy('land_id')
        ->select(DB::raw('SUM(amount) as total_amount'),'land_id','date');
    }
}
