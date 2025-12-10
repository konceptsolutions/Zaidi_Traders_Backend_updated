<?php

namespace App\Http\Controllers;

use App\Models\CoaAccount;
use App\Models\CoaGroup;
use App\Models\CoaSubGroup;
use App\Models\Land;
use App\Models\VoucherTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class BusinessReportsController extends Controller
{
    /**
     * Balance sheet
     *
     * @param \Illuminate\Http\request date
     * @return \Illuminate\Http\Response
     */
    public function getBalanceSheet(Request $req)
    {
        $rules = array(
            'date' => 'required'
        );

        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $date = Land::changeDateFormat($req->date);
        $assets = CoaGroup::with(['nonDepreciationSubGroups.coaAccounts.balance' => function ($query) use ($date) {
            $query->whereDate('date', '<=', $date);
        }])->where('parent', 'Assets')->select('id', 'name', 'parent', 'code')->get();

        $depreciation = CoaGroup::with(['depreciationSubGroups.coaAccounts.balance' => function ($query) use ($date) {
            $query->whereDate('date', '<=', $date);
        }])->where('id', 2)->select('id', 'name', 'parent', 'code')->get();

        $count =  count($assets[1]->nonDepreciationSubGroups);
        $assets[1]->nonDepreciationSubGroups[$count] = count($depreciation[0]->depreciationSubGroups) > 0 ?  $depreciation[0]->depreciationSubGroups[0] : $depreciation[0];
        $liabilities = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($date) {
            $query->whereDate('date', '<=', $date);
        }])->where('parent', 'Liabilities')->select('id', 'name', 'parent', 'code')->get();

        $capital = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($date) {
            $query->whereDate('date', '<=', $date);
        }])->where('parent', 'Capital')->select('id', 'name', 'parent', 'code')->get();

        $revenue = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($date) {
            $query->whereDate('date', '<=', $date);
        }])->where('parent', 'Revenues')->select('id', 'name', 'parent', 'code')->get();

        $expense = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($date) {
            $query->whereDate('date', '<=', $date);
        }])->where('parent', 'Expenses')->select('id', 'name', 'parent', 'code')->get();

        $cost = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($date) {
            $query->whereDate('date', '<=', $date);
        }])->where('parent', 'Cost')->select('id', 'name', 'parent', 'code')->get();

        $revenueSum = 0;
        if ($revenue[0]->coaSubGroups != null) {
            foreach ($revenue[0]->coaSubGroups as $revenueSubGroup) {
                if ($revenueSubGroup->coaAccounts != null) {
                    foreach ($revenueSubGroup->coaAccounts as $revenueAccount) {
                        $revenueSum = $revenueSum + optional($revenueAccount->balance)->balance;
                    }
                }
            }
        }

        $expenseSum = 0;
        if ($expense[0]->coaSubGroups != null) {
            foreach ($expense[0]->coaSubGroups as $expenseSubGroup) {
                if ($expenseSubGroup->coaAccounts != null) {
                    foreach ($expenseSubGroup->coaAccounts as $expenseAccount) {
                        $expenseSum = $expenseSum + optional($expenseAccount->balance)->balance;
                    }
                }
            }
        }

        $costSum = 0;
        if ($cost[0]->coaSubGroups != null) {
            foreach ($cost[0]->coaSubGroups as $costSubGroup) {
                if ($costSubGroup->coaAccounts != null) {
                    foreach ($costSubGroup->coaAccounts as $costAccount) {
                        $costSum = $costSum + optional($costAccount->balance)->balance;
                    }
                }
            }
        }
        $revExp = $revenueSum + $expenseSum + $costSum;

        return ['data' => array(
            'assets' => $assets,
            'liabilities' => $liabilities,
            'capital' => $capital,
            'revExp' => $revExp,
        )];
    }


    /**
     * Trail Balance
     *
     * @param \Illuminate\Http\request date
     * @return \Illuminate\Http\Response
     */
    public function getTrailBalance(Request $req)
    {
        $rules = array(
            'from' => 'required',
            'to' => 'required'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $from = Land::changeDateFormat($req->from);
        $to = Land::changeDateFormat($req->to);
        $assets = CoaGroup::with(['nonDepreciationSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Assets')->select('id', 'name', 'parent', 'code')->get();

        $depreciation = CoaGroup::with(['depreciationSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('id', 2)->select('id', 'name', 'parent', 'code')->get();

        $count =  count($assets[1]->nonDepreciationSubGroups);
        $assets[1]->nonDepreciationSubGroups[$count] = count($depreciation[0]->depreciationSubGroups) > 0 ?  $depreciation[0]->depreciationSubGroups[0] : $depreciation[0];

        $liabilities = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Liabilities')->select('id', 'name', 'parent', 'code')->get();

        $capital = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Capital')->select('id', 'name', 'parent', 'code')->get();

        $revenues = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Revenues')->select('id', 'name', 'parent', 'code')->get();

        $expenses = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Expenses')->select('id', 'name', 'parent', 'code')->get();


        $cost = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Cost')->select('id', 'name', 'parent', 'code')->get();

        return ['data' => array(
            'assets' => $assets,
            'liabilities' => $liabilities,
            'capital' => $capital,
            'revenues' => $revenues,
            'expenses' => $expenses,
            'cost' => $cost,
        )];
    }

    public function getChartOfAccounts(Request $req)
    {
        // $rules = array(
        //     'from' => 'required',
        //     'to' => 'required'
        // );
        // $validator = Validator::make($req->all(), $rules);
        // if ($validator->fails()) {
        //     return ['status' => 'error', 'message' => $validator->errors()->first()];
        // }
        $from = Land::changeDateFormat($req->from);
        $to = Land::changeDateFormat($req->to);
        $assets = CoaGroup::with('nonDepreciationSubGroups')
            ->where('parent', 'Assets')->select('id', 'name', 'parent', 'code')->get();

        $depreciation = CoaGroup::with(['depreciationSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('id', 2)->select('id', 'name', 'parent', 'code')->get();

        $count =  count($assets[1]->nonDepreciationSubGroups);
        $assets[1]->nonDepreciationSubGroups[$count] = count($depreciation[0]->depreciationSubGroups) > 0 ?  $depreciation[0]->depreciationSubGroups[0] : $depreciation[0];

        $liabilities = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Liabilities')->select('id', 'name', 'parent', 'code')->get();

        $capital = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Capital')->select('id', 'name', 'parent', 'code')->get();

        $revenues = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Revenues')->select('id', 'name', 'parent', 'code')->get();

        $expenses = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Expenses')->select('id', 'name', 'parent', 'code')->get();


        $cost = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
            $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
        }])->where('parent', 'Cost')->select('id', 'name', 'parent', 'code')->get();

        return ['data' => array(
            'assets' => $assets,
            // 'liabilities' => $liabilities,
            // 'capital' => $capital,
            // 'revenues' => $revenues,
            // 'expenses' => $expenses,
            // 'cost' => $cost,
        )];
    }

    /**
     * Displaying GeneralJournal
     *
     * @return \Illuminate\Http\Response
     */
    public function getGeneralJournal(Request $req)
    {
        if (isset($req->to) && isset($req->from)) {
            $to = Land::changeDateFormat($req->to);
            $from = Land::changeDateFormat($req->from);
            $data = VoucherTransaction::with('voucherNumber', 'coaAccount')
                ->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"])
                ->whereHas('voucher', function ($qu) {
                    return $qu->where([['isApproved', 1], ['is_post_dated', 0]]);
                })
                ->get();
        } else {
            $data = VoucherTransaction::with('voucherNumber', 'coaAccount')
                ->whereHas('voucher', function ($qu) {
                    return $qu->where([['isApproved', 1], ['is_post_dated', 0]]);
                })
                ->get();
        }
        return ['data' => $data];
    }

    /**
     * Displaying daily closing report
     *
     * @param \Illuminate\Http\date
     * @return \Illuminate\Http\Response
     */
    public function getDailyClosingReport(Request $req)
    {
        $rules = [
            'date' => 'required'
        ];
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        $coaAccounts = CoaAccount::whereHas('coaSubGroup', function ($qu) {
            return $qu->where('type', 'cash');
        })
            ->select('id', 'name', 'coa_sub_group_id')
            ->get();
        $openingBalances = [];
        $date = Land::changeDateFormat($req->date);
        $i = 0;
        $debitTransactions = [];
        $creditTransactions = [];
        if ($req->coaAccounts) {
            $coaAccounts = json_decode(json_encode($req->coaAccounts), FALSE);
        }
        $count = count($coaAccounts);
        foreach ($coaAccounts as $coaAccount) {

            //--------------------getting opening balances-----------------
            $getOpeningBal = VoucherTransaction::whereDate('date', '<', $date)
                ->where('coa_account_id', $coaAccount->id)
                ->whereHas('voucher', function ($qu) {
                    return $qu->where([['isApproved', 1], ['is_post_dated', 0]]);
                })->select(DB::raw('SUM(debit) - SUM(credit) as balance'))
                ->groupBy('coa_account_id') // Add the GROUP BY clause
                ->orderBy('id', 'desc')->first();
            $openingBal = 0;
            if ($getOpeningBal) {
                $openingBal = $getOpeningBal->balance;
            }

            $openingBalances[] = array(
                "account_id" => $coaAccount->id,
                "account_name" => $coaAccount->name,
                "opening_bal" => $openingBal
            );

            //-------------------------getting credit transaactions---------------------

            $credit = VoucherTransaction::with('voucher')->whereDate('date', '=', $date)
                ->where([['coa_account_id', $coaAccount->id], ['credit', '>', 0]])
                ->whereHas('voucherNumber', function ($qu) {
                    return $qu->where([['isApproved', 1], ['is_post_dated', 0]]);
                })->select("id", "voucher_id", "coa_account_id", "credit", 'description')
                ->get();
            $array = [];
            $descriptionArray = array();
            foreach ($credit as $credit) {
                for ($j = 0; $j < $count; $j++) {
                    $amount = 0;
                    if ($j == $i) {
                        $amount = $credit->credit;
                        $descriptionArray  = array(
                            'description' => $credit->description,
                            'voucher_no' => $credit->voucher->voucher_no,
                        );
                    }
                    $array[$j] = array(
                        "amount" => $amount
                    );
                }
                $creditTransactions[] = array(
                    'transactions' => $array,
                    'descriptionArray' => $descriptionArray,
                );
            }

            //-------------------------getting debit transaactions---------------------


            $debit = VoucherTransaction::whereDate('date', '=', $date)
                ->where([['coa_account_id', $coaAccount->id], ['debit', '>', 0]])
                ->whereHas('voucherNumber', function ($qu) {
                    return $qu->where([['isApproved', 1], ['is_post_dated', 0]]);
                })->select("id", "voucher_id", "coa_account_id", "debit", 'description')
                ->get();
            $array = [];
            $descriptionArray = array();
            foreach ($debit as $debit) {
                for ($j = 0; $j < $count; $j++) {
                    $amount = 0;
                    if ($j == $i) {
                        $amount = $debit->debit;
                        $descriptionArray  = array(
                            'description' => $debit->description,
                            'voucher_no' => $debit->voucher->voucher_no,
                        );
                    }
                    $array[$j] = array(
                        "amount" => $amount,
                    );
                }
                $debitTransactions[] = array(
                    'transactions' => $array,
                    'descriptionArray' => $descriptionArray,
                );
            }

            $i++;
        }

        return ['status' => 'ok', 'coaAccounts' => $coaAccounts, 'openingBalances' => $openingBalances, 'debitTransactions' => $debitTransactions, 'creditTransactions' => $creditTransactions];
    }

public function getCustomerReceivebleBalance(Request $request)
{
    $rules = [
        'date' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
        return ['status' => 'error', 'message' => $validator->errors()->first()];
    }

    $date = Carbon::parse($request->date)->format('Y-m-d');
    $customer_id = $request->customer_id;

    \DB::enableQueryLog();

    $receivables = CoaAccount::query()
    ->with(['balance' => function ($query) use ($date) {
        $query->where('date', '<=', $date);
    }, 'person'])
    ->when($customer_id, function ($q, $customer_id) {
        $q->where('person_id', '=', $customer_id);
    })
    ->where('coa_sub_group_id', '9')
    ->get()
    ->filter(function ($account) {
        // Treat any balance with absolute value less than 0.01 as zero
        return $account->balance && abs($account->balance->balance) >= 0.9;
    })
    ->values();


    return ['Receivables' => $receivables];
}


    public function getSupplierPayableBalance(Request $request)
{
    $rules = array(
        'date' => 'required',
    );
    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
        return ['status' => 'error', 'message' => $validator->errors()->first()];
    }

    // Parse the original date
    $carbonDate = Carbon::parse($request->date);
    $date = $carbonDate->format('Y-m-d');
    $supplier_id = $request->supplier_id;

    $receivables = CoaAccount::query()
        ->has('balance')
        ->with([
            'balance' => function ($query) use ($date) {
                $query->where('date', '<=', $date);
            },
            'person'
        ])
        ->when($supplier_id, function ($query, $supplier_id) {
            $query->where('person_id', '=', $supplier_id);
        })
        ->where('coa_sub_group_id', '2')
        ->get()
        ->filter(function ($account) {
            return $account->balance && abs($account->balance->balance) >= 0.9;
        })
        ->values();

    return ['Receivables' => $receivables];
}

}
