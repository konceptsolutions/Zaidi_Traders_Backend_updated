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

use App\Services\CustomErrorMessages;

class CoaAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $req)
    {
        if ($req->is_active == 0 && $req->is_active != null) {
            $is_active = '00';
        } else if ($req->is_active == 1) {
            $is_active = 1;
        } else {
            $is_active = $req->is_active;
        }
        $coaAccounts = CoaAccount::where('isActive', 1)->orderBy('code')->with('coaGroup')->with('coaSubGroup')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where('isActive', 1);
            })
            ->when($is_active, function ($q, $is_active) {
                return $q->where('isActive', $is_active);
            })
            ->get();
        return ['coaAccounts' => $coaAccounts];
    }

    //------------------------------------------------------------------------------
    /**
     * Store a newly created CoaAccount in storage.
     *
     * @param \Illuminate\Http\Request name
     * @param \Illuminate\Http\Request coa_group_id
     * @param \Illuminate\Http\Request person_id (optional)
     * @param \Illuminate\Http\Request coa_sub_group_id (optional)
     * @param \Illuminate\Http\Request description (optional)
     * @param \Illuminate\Http\Request code
     * @return \Illuminate\Http\Response message
     * @return \Illuminate\Http\Response status
     */
    public function store(Request $req)
    {
        $rules = array(
            'name' => 'required',
            'coa_group_id' => 'required|int',
            'coa_sub_group_id' => 'required|int',
            'description' => 'max:255',
            // 'person_id' => 'unique:coa_accounts',
            'coa_sub_group_id_person_id' => 'unique:coa_accounts,coa_sub_group_id,person_id'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($req) {
                $subgroup = CoaSubGroup::find($req->coa_sub_group_id);
                $lastCode = CoaAccount::where('coa_sub_group_id', $subgroup->id)->orderBy('id', 'desc')->first();
                $coaAccount = CoaAccount::create($req->all());
                if (!$lastCode) {
                    $newCode = $subgroup->code . '001';
                } else {
                    $newCode = $lastCode->code + 1;
                }
                CoaAccount::where('id', $coaAccount->id)->update(['code' => $newCode]);
            });
            return ['status' => "ok", 'message' => 'CoaAccount stored successfully'];

            // code that may throw an exception
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }




    /**
     * Getting coaAccounts by coaGroup.
     ** @param \Illuminate\Http\Request coa_group_id
     * @return \Illuminate\Http\Response
     */
    public function getAccountsByGroup(Request $req)
    {
        $rules = array(
            'coa_group_id' => 'required|int',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $coaAccounts = CoaAccount::where([['coa_group_id', $req->coa_group_id], ['isActive', 1]])->with('coaGroup')->with('coaSubGroup')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where('isActive', 1);
            })
            ->get();
        return ['coaAccounts' => $coaAccounts];
    }

    /**
     * Getting coaAccounts by coaSubGroup.
     ** @param \Illuminate\Http\Request coa_sub_group_id
     * @return \Illuminate\Http\Response
     */
    public function getAccountsBySubGroup(Request $req)
    {
        $rules = array(
            'coa_sub_group_id' => 'required|int',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $coaAccounts = CoaAccount::where([['coa_sub_group_id', $req->coa_sub_group_id], ['isActive', 1]])->with('coaGroup')->with('coaSubGroup')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where('isActive', 1);
            })
            ->get();
        return ['coaAccounts' => $coaAccounts];
    }
    public function getBankAccountsBySubGroup(Request $req)
    {
        $rules = array(
            'coa_sub_group_id' => 'required|int',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $coaAccounts = CoaAccount::where([['coa_sub_group_id', $req->coa_sub_group_id], ['isActive', 1]])->with('coaGroup')->with('coaSubGroup')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where('isActive', 1);
            })
            ->get();
        return ['coaAccounts' => $coaAccounts];
    }
    /**
     * Getting coaAccount ledger
     ** @param \Illuminate\Http\Request account_id
     ** @param \Illuminate\Http\Request from
     ** @param \Illuminate\Http\Request to
     * @return \Illuminate\Http\Response
     */
    public function getAccountLedger(Request $req)
    {

        $land_id = $req->land_id;
        $land_payment_head_id = $req->land_payment_head_id;
        if (isset($req->from) && isset($req->to)) {
            $from = Land::changeDateFormat($req->from);
            $to = Land::changeDateFormat($req->to);
            $getOpeningBal = VoucherTransaction::where('coa_account_id', $req->account_id)->whereDate('date', '<', $from)

                ->whereHas('voucher', function ($qu) {
                    return $qu->where([['isApproved', 1], ['is_post_dated', 0]]);
                })->groupBy('coa_account_id')
               ->select(DB::raw('SUM(debit) - SUM(credit) as balance, MAX(id) as max_id')) // ✅ FIXED
    ->orderByDesc('max_id')->first();
            $openingBal = 0;
            if ($getOpeningBal) {
                $openingBal = $getOpeningBal->balance;
            }

            $ledger = VoucherTransaction::where('coa_account_id', $req->account_id)
                ->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"])
                ->with('voucherNumber')
                // ->when($land_payment_head_id, function ($query, $land_payment_head_id) {
                //     return $query->where('land_payment_head_id', '=', $land_payment_head_id);
                // })
                // ->when($land_id, function ($query, $land_id) {
                //     return $query->where('land_id', '=', $land_id);
                // })
                ->whereHas('voucherNumber', function ($qu) {
                    return $qu->where([['isApproved', 1], ['is_post_dated', 0]]);
                })->select("id", "voucher_id", "coa_account_id", "debit", "credit", DB::raw('debit - credit as balance'), "description", "created_at", "updated_at", 'date')
                ->orderBy('date')
                ->get();
        } else {
            $openingBal = 0;
            $ledger = VoucherTransaction::where('coa_account_id', $req->account_id)
                ->with('voucherNumber')
                // ->when($land_payment_head_id, function ($query, $land_payment_head_id) {
                //     return $query->where('land_payment_head_id', '=', $land_payment_head_id);
                // })
                // ->when($land_id, function ($query, $land_id) {
                //     return $query->where('land_id', '=', $land_id);
                // })
                ->whereHas('voucherNumber', function ($qu) {
                    return $qu->where([['isApproved', 1], ['is_post_dated', 0]]);
                })->select("id", "voucher_id", "coa_account_id", "debit", "credit", DB::raw('debit - credit as balance'), "description", "created_at", "updated_at", 'date')
                ->orderBy('date')
                ->get();
        }
        return ['opening_balance' => $openingBal, 'ledger' => $ledger];
    }

    /**
     * Getting accounts related to cash and bank .
     *
     * @return \Illuminate\Http\Response
     */
    public function getCashAccounts(Request $request)
    {
        $isActive = $request->isActive;
        $coaAccount = CoaAccount::orderBy('code')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where([['type', 'cash'], ['isActive', 1]]);
            })
            ->when($isActive !== null, function ($q) use ($isActive) {
                return $q->where('isActive', '=', $isActive);
            })
            ->where('isActive', 1)
            ->get();
        return ['coaAccounts' => $coaAccount];
    }


    /**
     * Getting accounts except cash and bank .
     *
     * @return \Illuminate\Http\Response
     */
    public function getAccountsExceptCash()
    {
        $coaAccount = CoaAccount::orderBy('code')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where([['type', '=', null], ['isActive', 1]]);
            })
            ->where('isActive', 1)
            ->get();
        return ['coaAccounts' => $coaAccount];
    }

    /**
     * Getting accounts.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAccounts()
    {
        $coaAccount = CoaAccount::orderBy('code')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where([['type', '=', null], ['isActive', 1]]);
            })
            ->where('isActive', 1)
            ->get();
        return ['coaAccounts' => $coaAccount];
    }

    /**
     * Getting mouza accounts.
     *
     * @return \Illuminate\Http\Response
     */
    public function getMouzaAccounts()
    {
        $coaAccounts = CoaAccount::orderBy('code')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where([['isActive', 1]]);
            })
            ->where([['type', 'mouza'], ['isActive', 1]])->get();
        return ['coaAccounts' => $coaAccounts];
    }

    /**
     * Getting person accounts
     *
     * @return \Illuminate\Http\Response
     */
    public function getPersonCoaAccounts()
    {
        $coaAccounts = CoaAccount::orderBy('code')
            ->where([['person_id', '!=', null], ['isActive', 1]])->with('coaSubGroup')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where([['isActive', 1]]);
            })
            ->get();
        return ['coaAccounts' => $coaAccounts];
    }

    /**
     * Getting person and mouza accounts
     *
     * @return \Illuminate\Http\Response
     */
    public function getPersonAndMouzaAccounts()
    {
        $coaAccounts = CoaAccount::orderBy('code')
            ->where([['person_id', '!=', null], ['isActive', 1]])
            ->orwhere([['type', 'mouza'], ['isActive', 1]])
            ->with('coaSubGroup')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where([['isActive', 1]]);
            })
            ->get();
        return ['coaAccounts' => $coaAccounts];
    }

    /**
     * Making account active or incactive
     *
     * @param \Illuminate\Http\Request account_id
     * @return \Illuminate\Http\Response
     */
    public function makeAccountActiveOrInactive(Request $req)
    {
        $rules = array(
            'account_id' => 'required|int'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        $coaAccount = CoaAccount::find($req->account_id);
        if (!$coaAccount) {
            return ['status' => "error", 'message' => 'Account Not found'];
        }
        if ($coaAccount->isDefault == 1) {
            return ['status' => 'error', 'message' => "This is a default account you can't update it"];
        }

        $message = $coaAccount->isActive == 1 ? 'Deactivated' : 'Activated';
        $coaAccount->isActive = $coaAccount->isActive == 1 ? 0 : 1;
        $coaAccount->save();
        return ['status' => "ok", 'message' => 'CoaAccount ' . $message . ' successfully'];
    }

    /**
     * Display a listing of the resource.
     * @param \Illuminate\Http\Request group_id(optional)
     * @param \Illuminate\Http\Request sub_group_id(optional)
     * @param \Illuminate\Http\Request type(optional)
     * @return \Illuminate\Http\Response
     */
    public function getRequiredAccounts(Request $req)
    {
        $type = $req->type;
        $group_id = $req->group_id;
        $sub_group_id = $req->sub_group_id;
        if (isset($req->type)) {
            $coaAccounts = CoaAccount::orderBy('code')->with('coaGroup')->with('coaSubGroup')
                ->where('isActive', $req->type)
                ->when($type, function ($query, $type) {
                    return $query->whereHas('coaSubGroup', function ($qu) use ($type) {
                        $qu->where('isActive', $type);
                    });
                })
                ->when($group_id, function ($query, $group_id) {
                    return $query->where('coa_group_id', '=', $group_id);
                })
                ->when($sub_group_id, function ($query, $sub_group_id) {
                    return $query->where('coa_sub_group_id', '=', $sub_group_id);
                })
                ->get();
        } else {
            $coaAccounts = CoaAccount::orderBy('code')->with('coaGroup')->with('coaSubGroup')
                ->when($group_id, function ($query, $group_id) {
                    return $query->where('coa_group_id', '=', $group_id);
                })
                ->when($sub_group_id, function ($query, $sub_group_id) {
                    return $query->where('coa_sub_group_id', '=', $sub_group_id);
                })
                ->get();
        }
        return ['coaAccounts' => $coaAccounts];
    }

    /**
     * editing coa account
     *
     * @param \Illuminate\Http\Request account_id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $req)
    {
        $coaAccount = CoaAccount::with('coaGroup', 'coaSubGroup')->find($req->account_id);
        if (!$coaAccount) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }
        return ['coaAccount' => $coaAccount];
    }

    /**
     * Updating coa account.
     *
     * @param \Illuminate\Http\Request id
     * @param \Illuminate\Http\Request name
     * @param \Illuminate\Http\Request coa_group_id
     * @param \Illuminate\Http\Request person_id (optional)
     * @param \Illuminate\Http\Request coa_sub_group_id (optional)
     * @param \Illuminate\Http\Request description (optional)
     * @return \Illuminate\Http\Response message
     * @return \Illuminate\Http\Response status
     */
    public function update(Request $req)
    {
        $coaAccount = CoaAccount::find($req->id);
        if (!$coaAccount) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }
        if ($coaAccount->isDefault == 1) {
            return ['status' => 'error', 'message' => "This is a default account you can't update it"];
        }
        $transaction = VoucherTransaction::where('coa_account_id', $req->id)->first();
        $message = "You can't update group and subgroup of this account";
        $coaAccount->description = $req->description;
        if (!$transaction) {
            $coaAccount->name = $req->name;
            $message = 'Account updated Successfully';
            $coaAccount->coa_group_id = $req->coa_group_id;
            $coaAccount->coa_sub_group_id = $req->coa_sub_group_id;
            $coaAccount->person_id = $req->person_id;
        }
        $coaAccount->save();
        return ['status' => 'ok', 'message' => $message];
    }

    /**
     * Deleting coa account
     *
     * @param \Illuminate\Http\Request account_id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $req)
    {
        $coaAccount = CoaAccount::find($req->account_id);
        if (!$coaAccount) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }
        if ($coaAccount->isDefault == 1) {
            return ['status' => 'error', 'message' => "This is a default account you can't delete it"];
        }
        $transaction = VoucherTransaction::where('coa_account_id', $req->account_id)->first();
        if ($transaction) {
            return ['status' => 'error', 'message' => "Transaction done through this account you can't delete it"];
        } else {
            CoaAccount::find($req->account_id)->delete();
            return ['status' => 'ok', 'message' => 'Account Deleted Successfully'];
        }
    }

    /**
     * Getting files and payment heads by coaaccount
     *
     * @param \Illuminate\Http\Request account_id
     * @return \Illuminate\Http\Response
     */
    public function getFilesByAccount(Request $req)
    {
        $rules = array(
            'account_id' => 'required|int'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $files = VoucherTransaction::where([['coa_account_id', $req->account_id], ['land_id', '!=', null]])->groupBy('land_id')->select('id', 'land_id')->with('land')
            ->get();

        return ['files' => $files];
    }

    /**
     * Getting files and payment heads by coaaccount
     *
     * @param \Illuminate\Http\Request account_id
     * @param \Illuminate\Http\Request file_id
     * @return \Illuminate\Http\Response
     */
    public function getPaymentHeads(Request $req)
    {
        $rules = array(
            'account_id' => 'required|int',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $land_id = $req->land_id;
        $paymentHeads = VoucherTransaction::where([['coa_account_id', $req->account_id], ['land_payment_head_id', '!=', null]])
            ->when($land_id, function ($query, $land_id) {
                return $query->where('land_id', '=', $land_id);
            })
            ->groupBy('land_payment_head_id')->select('id', 'land_payment_head_id')->with('landPaymentHead')
            ->get();

        return ['paymentHeads' => $paymentHeads];
    }


    /**
     * Getting accounts except cash and bank .
     *
     * @return \Illuminate\Http\Response
     */
    public function getAccountsExceptCashAndBank()
    {
        $coaAccount = CoaAccount::orderBy('code')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where([['type', '!=', 'cash'], ['isActive', 1]])->orWhere([['type', '=', null], ['isActive', 1]]);
            })
            ->where('isActive', 1)
            ->get();
        return ['coaAccounts' => $coaAccount];
    }

    public function getAccountsExceptCashAndBanks()
    {
        $coaAccounts = [];
        $chunkSize = 100; // Specify the batch size

        CoaAccount::orderBy('code')
            ->select('id','name','code')
            ->whereHas('coaSubGroup', function ($qu) {
                return $qu->where([['type', '=', null], ['isActive', 1]]);
            })
            ->where('isActive', 1)
            ->chunk($chunkSize, function ($chunk) use (&$coaAccounts) {
                foreach ($chunk as $coaAccount) {
                    // Process each $coaAccount here if needed
                    // You can add conditions to filter specific records
                    // Example: if ($coaAccount->someCondition()) { ... }

                    // Add the $coaAccount to the result array
                    $coaAccounts[] = $coaAccount->toArray();
                }
            });
        return ['coaAccounts' => $coaAccounts];
    }

    public function getCapitalAccounts()
    {
        try {
            $capitalAccounts = CoaAccount::where('coa_group_id', 5)->where('coa_sub_group_id', 17)->where('code', 504005)->get();

            return ['capitalAccounts' => $capitalAccounts];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getInventoryAccounts()
    {
        try {
            $inventoryAccounts = CoaAccount::where('coa_group_id', 1)->where('coa_sub_group_id', 1)->where('code', 101001)->get();

            return ['inventoryAccounts' => $inventoryAccounts];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getDisposeAccounts()
    {
        try {
            $disposeAccounts = CoaAccount::where('coa_group_id', 8)->where('coa_sub_group_id', 16)->where('code', 802042)->get();

            return ['disposeAccounts' => $disposeAccounts];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
