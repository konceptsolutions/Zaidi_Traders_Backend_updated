<?php

namespace App\Http\Controllers;

use App\Models\Land;
use App\Models\Person;
use App\Models\Invoice;
use App\Models\Voucher;
use App\Models\CoaAccount;
use Illuminate\Http\Request;
use App\Models\EditedVoucher;
use App\Models\PurchaseOrder;
use App\Models\LandPaymentHead;
use App\Models\VoucherTransaction;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseInstallment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\EditedVoucherTransaction;
use App\Models\VoucherTransactionInvoice;
use Illuminate\Support\Facades\Validator;
use App\Models\VoucherTransactionPoNumber;

class VoucherController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function index(Request $req)
    {
        $post_dated = '';
        $canceled = '';
        $cleared = '';
        $allPostDated = '';
        $is_post_dated = $req->is_post_dated;
        if (isset($req->is_post_dated) && $is_post_dated == 0) {
            $cleared = 1;
        } elseif (isset($req->is_post_dated) && $is_post_dated == 1) {
            $post_dated = 1;
        } elseif (isset($req->is_post_dated) && $is_post_dated == 2) {
            $canceled = 1;
        } elseif (isset($req->is_post_dated) && $is_post_dated == 3) {
            $allPostDated = 1;
        }
        $voucher_no = $req->voucher_no;
        $group_id = $req->coa_group_id;
        $sub_group_id = $req->coa_sub_group_id;
        $account_id = $req->coa_account_id;
        $name =  strtoupper(preg_replace('/[^a-zA-Z]/', '', $req->voucher_no));
        if (isset($voucher_no)) {
            $voucher_no = preg_replace('/[^0-9]/', '', $req->voucher_no);
            if ($voucher_no[0] == 0 && $voucher_no[1] == 0) {
                $voucher_no = substr($voucher_no, 2);
            } elseif ($voucher_no[0] == 0) {
                $voucher_no = substr($voucher_no, 1);
            }
        }
        $isApproved = $req->is_approved;
        if ($req->isApproved == 0 && $req->isApproved != null) {
            $isApproved = '00';
        } else if ($req->isApproved == 1) {
            $isApproved = 1;
        } else {
            $isApproved = $req->isApproved;
        }
        $type = $req->type;
        $isDeleted = $req->isDeleted;
        $to = isset($req->to) ? Land::changeDateFormat($req->to) : null;
        $from = isset($req->from) ?  Land::changeDateFormat($req->from) : null;
        $cheque_date_to = isset($req->cheque_date_to) ? Land::changeDateFormat($req->cheque_date_to) : null;
        $cheque_date_from = isset($req->cheque_date_from) ?  Land::changeDateFormat($req->cheque_date_from) : null;
        $vouchers = Voucher::with('voucherType')->orderBy('date', 'desc')->orderBy('id', 'desc')
            ->when($name, function ($query, $name) {
                return $query->whereHas('voucherType', function ($query) use ($name) {
                    return $query->where('name', $name);
                });
            })
            ->when($voucher_no, function ($query, $voucher_no) {
                return $query->where('voucher_no',  $voucher_no);
            })
            ->when($from, function ($query, $from) {
                return $query->where('date', '>=', $from);
            })
            ->when($to, function ($query, $to) {
                return $query->where('date', '<=', $to);
            })
            ->when($cheque_date_from, function ($query, $cheque_date_from) {
                return $query->where('cheque_date', '>=', $cheque_date_from);
            })
            ->when($cheque_date_to, function ($query, $cheque_date_to) {
                return $query->where('cheque_date', '<=', $cheque_date_to);
            })
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->when($isApproved, function ($query, $isApproved) {
                return $query->where('isApproved', $isApproved);
            })
            ->when($post_dated, function ($query) {
                return $query->where('is_post_dated', 1);
            })
            ->when($canceled, function ($query) {
                return $query->where('is_post_dated', 2);
            })
            ->when($allPostDated, function ($query) {
                return $query->whereNotNull('cheque_no')->where('cheque_no', '!=', '');
            })
            ->when($cleared, function ($query) {
                return $query->whereNotNull('cheque_no')->where([['cheque_no', '!=', ''], ['is_post_dated', 0]]);
            })
            ->when($isDeleted, function ($query) {
                return $query->onlyTrashed();
            })
            ->when($group_id, function ($query) use ($group_id) {
                return $query->whereHas('voucherTransactions', function ($query) use ($group_id) {
                    return $query->whereHas('coaAccount', function ($query) use ($group_id) {
                        return $query->whereHas('coaGroup', function ($query) use ($group_id) {
                            return $query->where('coa_group_id', $group_id);
                        });
                    });
                });
            })
            ->when($sub_group_id, function ($query) use ($sub_group_id) {
                return $query->whereHas('voucherTransactions', function ($query) use ($sub_group_id) {
                    return $query->whereHas('coaAccount', function ($query) use ($sub_group_id) {
                        return $query->whereHas('coaSubGroup', function ($query) use ($sub_group_id) {
                            return $query->where('coa_sub_group_id', $sub_group_id);
                        });
                    });
                });
            })
            ->when($account_id, function ($query) use ($account_id) {
                return $query->whereHas('voucherTransactions', function ($query) use ($account_id) {
                    return $query->whereHas('coaAccount', function ($query) use ($account_id) {
                        return $query->where('id', $account_id);
                    });
                });
            })
            ->get();
        return ['vouchers' => $vouchers];
    }

    //------------------------------------------------------------------------------
    /**
     * Store a newly created voucher in storage.
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response message
     * @return \Illuminate\Http\Response status
     */
    public function store(Request $req)
    {
        // return response()->json($req->all());
        $id = 0;
        $rules = array(
            'type' => 'required|int',
            'date' => 'required',
            'total_amount' => 'required',
            'list' => 'required'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        DB::transaction(function () use ($req, &$id) {
            $is_post_dated = isset($req->cheque_no) ? 1 : 0;
            $getVoucherNo = DB::table('vouchers')->where('type', $req->type)->orderBy('id', 'desc')->first();
            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
            $voucher = new Voucher;
            $voucher->voucher_no = $newVoucherNo;
            $voucher->date = Land::changeDateFormat($req->date);
            $voucher->generated_at = now();
            $voucher->name = $req->name;
            $voucher->type = $req->type;
            $voucher->total_amount = $req->total_amount;
            $voucher->isApproved = 1;
            $voucher->cheque_no = $req->cheque_no;
            // $voucher->cheque_date = Land::changeDateFormat($req->cheque_date);
            $voucher->is_post_dated = $is_post_dated;
            $voucher->save();
            $voucher_id = $voucher->id;
            $id = $voucher_id;
            foreach ($req->list as $transaction) {
                $prevBal = 0;
                $getPrevBal = VoucherTransaction::where('coa_account_id', $transaction['account']['id'])->orderBy('id', 'desc')->first();
                if ($getPrevBal) {
                    $prevBal = $getPrevBal->balance;
                }
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = Land::changeDateFormat($req->date) . " " . date("H:i:s");
                $voucherTransaction->coa_account_id = $transaction['account']['id'];
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = $transaction['dr'];
                $voucherTransaction->credit = $transaction['cr'];
                $voucherTransaction->balance = $transaction['dr'] - $transaction['cr'] + $prevBal;
                $voucherTransaction->description = $transaction['description'];
                if (isset($transaction['file_id'])) {

                    $voucherTransaction->land_id = $transaction['file_id']['id'];
                }
                if (isset($transaction['land_payment_head_id']['id'])) {
                    $voucherTransaction->land_payment_head_id = $transaction['land_payment_head_id']['id'];
                }
                if ($req->type == 1 || $req->type == 5) {
                    $voucherTransaction->save();
                }
                if ($req->type == 1 || $req->type == 2 || $req->type == 5 || $req->type == 6) {
                    $prevBal2 = 0;
                    $getPrevBal2 = VoucherTransaction::where('coa_account_id', $req->account['id'])->orderBy('id', 'desc')->first();
                    if ($getPrevBal2) {
                        $prevBal2 = $getPrevBal2->balance;
                    }
                    $voucherTransaction2 = new VoucherTransaction();
                    $voucherTransaction2->voucher_id = $voucher_id;
                    $voucherTransaction2->is_approved = 1;
                    $voucherTransaction2->date = Land::changeDateFormat($req->date) . " " . date("H:i:s");
                    $voucherTransaction2->coa_account_id = $req->account['id'];
                    $voucherTransaction2->debit = $transaction['cr'];
                    $voucherTransaction2->credit = $transaction['dr'];
                    $voucherTransaction2->balance = $transaction['cr'] - $transaction['dr'] + $prevBal2;
                    $voucherTransaction2->description = $transaction['description'];
                    if (isset($transaction['file_id'])) {
                        $voucherTransaction2->land_id = $transaction['file_id']['id'];
                    }
                    if (isset($transaction['land_payment_head_id']['id'])) {
                        $voucherTransaction2->land_payment_head_id = $transaction['land_payment_head_id']['id'];
                    }
                    $voucherTransaction2->save();
                }
                if ($req->type == 2 || $req->type == 3 || $req->type == 4 || $req->type == 6 || $req->type == 7) {
                    $voucherTransaction->save();
                }
            }
        });
        return ['status' => "ok", 'message' => 'Voucher created successfully', 'id' => $id];
    }

    /**
     * Displaying voucher details
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function getVoucherDetails(Request $req)
    {
        $voucher = Voucher::where('id', $req->voucher_id)
            ->with('voucherTransactions')
            ->first();


        return ['voucher' => $voucher];
    }


    /**
     * Approve or unapprove voucher
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function approveOrUnapproveVoucher(Request $req)
    {
        $rules = array(
            'voucher_id' => 'required|int'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $Voucher = Voucher::find($req->voucher_id);
        if (!$Voucher) {
            return ['status' => "error", 'message' => 'Voucher Not found'];
        }
        $message = '';
        DB::transaction(function () use ($req, $Voucher, &$message) {
            $isApproved = $Voucher->isApproved == 1 ? 0 : 1;
            if ($Voucher->type == 7) {
                $VoucherTransaction = VoucherTransaction::where([['voucher_id', $Voucher->id], ['plot_id', '!=', null]])->first();
                if ($VoucherTransaction) {
                    // Plot::where('id', $VoucherTransaction->plot_id)->update(['is_approved' => $isApproved]);
                }
            }
            $message = $Voucher->isApproved == 1 ? 'Unapproved' : 'Approved';
            VoucherTransaction::where('voucher_id', $req->voucher_id)->update(['is_approved' => $isApproved]);
            $Voucher->isApproved = $isApproved;
            $Voucher->save();
        });
        return ['status' => "ok", 'message' => 'Voucher ' . $message . ' successfully'];
    }

    /**
     * Deleting voucher
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function update(Request $req)
    {
        $rules = array(
            'voucher_id' => 'required|int',
            'type' => 'required|int',
            'date' => 'required',
            'total_amount' => 'required',
            'list' => 'required',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $voucher = Voucher::find($req->voucher_id);
        if (!$voucher) {
            return ['status' => "error", 'message' => 'Voucher not found'];
        }
        if ($voucher->is_auto == 1) {
            return ['status' => 'error', 'message' => "This is auto voucher you can't update it"];
        }
        DB::transaction(function () use ($req) {
            $voucher_id = $req->voucher_id;
            $voucher = Voucher::find($req->voucher_id);
            //----------Recording old values of voucher
            // $editedVoucher = new EditedVoucher;
            // $editedVoucher->voucher_id = $voucher->id;
            // $editedVoucher->type = $voucher->type;
            // $editedVoucher->name = $voucher->name;
            // $editedVoucher->date = $voucher->date;
            // $editedVoucher->generated_at = $voucher->generated_at;
            // $editedVoucher->total_amount = $voucher->total_amount;
            // $editedVoucher->cheque_no = $voucher->cheque_no;
            // $editedVoucher->cheque_date = $voucher->cheque_date;
            // $editedVoucher->isApproved = $voucher->isApproved;
            // $editedVoucher->is_post_dated = $voucher->is_post_dated;
            // $editedVoucher->cleared_date = $voucher->cleared_date;
            // $editedVoucher->save();

            $oldTime = explode(' ', date_format($voucher->created_at, "Y-m-d H:i:s"));
            $transactionDate = $voucher->date == Land::changeDateFormat($req->date) ? $voucher->date . " " . $oldTime[1] : Land::changeDateFormat($req->date) . " " . date('H:i:s');

            //----------Recording new values of voucher
            $voucher->name = $req->name;
            $voucher->date = Land::changeDateFormat($req->date);
            // $voucher->generated_at = Land::changeDateFormat($req->date);
            $voucher->total_amount = $req->total_amount;
            // $voucher->cheque_no = $req->cheque_no;
            // $voucher->cheque_date = Land::changeDateFormat($req->cheque_date);
            // $voucher->isApproved = $req->isApproved;
            $voucher->save();
            $oldVoucherTransactions = VoucherTransaction::where('voucher_id', $voucher_id)->get();
            $old_loan_amortization_id = $oldVoucherTransactions[0]->loan_amortization_id;
            //----------Recording old values of voucher transactions
            // foreach ($oldVoucherTransactions as $oldVoucherTransaction) {
            //     $editedVoucherTransaction = new EditedVoucherTransaction;
            //     $editedVoucherTransaction->edited_voucher_id = $editedVoucher->id;
            //     $editedVoucherTransaction->date = $voucher->date;
            //     $editedVoucherTransaction->coa_account_id = $oldVoucherTransaction->coa_account_id;
            //     $editedVoucherTransaction->debit = $oldVoucherTransaction->debit;
            //     $editedVoucherTransaction->credit = $oldVoucherTransaction->credit;
            //     $editedVoucherTransaction->balance = $oldVoucherTransaction->balance;
            //     $editedVoucherTransaction->description = $oldVoucherTransaction->description;
            //     $editedVoucherTransaction->land_id = $oldVoucherTransaction->land_id;
            //     $editedVoucherTransaction->land_payment_head_id = $oldVoucherTransaction->land_payment_head_id;
            //     $editedVoucherTransaction->loan_amortization_id = $oldVoucherTransaction->loan_amortization_id;
            //     $editedVoucherTransaction->save();
            // }

            //----------Deleting old values of voucher transactions
            VoucherTransaction::where('voucher_id', $voucher_id)->forceDelete();

            //----------Recording new values of voucher transactions
            foreach ($req->list as $transaction) {
                $prevBal = 0;
                $getPrevBal = VoucherTransaction::where('coa_account_id', $transaction['account']['id'])->orderBy('id', 'desc')->first();
                if ($getPrevBal) {
                    $prevBal = $getPrevBal->balance;
                }
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = $transactionDate;
                $voucherTransaction->coa_account_id = $transaction['account']['id'];
                $voucherTransaction->debit = $transaction['dr'];
                $voucherTransaction->credit = $transaction['cr'];
                $voucherTransaction->balance = $transaction['dr'] - $transaction['cr'] + $prevBal;
                $voucherTransaction->description = $transaction['description'];
                if (isset($transaction['file_id'])) {
                    $coaAccount = CoaAccount::find($transaction['account']['id']);
                    if ($coaAccount->coa_sub_group_id == 1 || $coaAccount->coa_sub_group_id == 22) {
                        $voucherTransaction->land_id = $transaction['file_id']['id'];
                    } elseif ($coaAccount->coa_sub_group_id == 56 || $coaAccount->coa_sub_group_id == 59 || $coaAccount->coa_sub_group_id == 29) {
                        $voucherTransaction->loan_amortization_id = $transaction['file_id']['id'];
                    }
                }
                if (isset($transaction['land_payment_head_id']['id'])) {
                    $voucherTransaction->land_payment_head_id = $transaction['land_payment_head_id']['id'];
                }
                if ($req->type == 1 || $req->type == 5) {
                    $voucherTransaction->save();
                }
                if ($req->type == 1 || $req->type == 2 || $req->type == 5 || $req->type == 6) {
                    $prevBal2 = 0;
                    $getPrevBal2 = VoucherTransaction::where('coa_account_id', $req->account['id'])->orderBy('id', 'desc')->first();
                    if ($getPrevBal2) {
                        $prevBal2 = $getPrevBal2->balance;
                    }
                    $voucherTransaction2 = new VoucherTransaction();
                    $voucherTransaction2->voucher_id = $voucher_id;
                    $voucherTransaction2->date = $transactionDate;
                    $voucherTransaction2->coa_account_id = $req->account['id'];
                    $voucherTransaction2->debit = $transaction['cr'];
                    $voucherTransaction2->credit = $transaction['dr'];
                    $voucherTransaction2->balance = $transaction['cr'] - $transaction['dr'] + $prevBal2;
                    $voucherTransaction2->description = $transaction['description'];
                    if (isset($transaction['file_id'])) {
                        $coaAccount = CoaAccount::find($transaction['account']['id']);
                        if ($coaAccount->coa_sub_group_id == 1 || $coaAccount->coa_sub_group_id == 22) {
                            $voucherTransaction2->land_id = $transaction['file_id']['id'];
                        } elseif ($coaAccount->coa_sub_group_id == 56 || $coaAccount->coa_sub_group_id == 59 || $coaAccount->coa_sub_group_id == 29) {
                            $voucherTransaction2->loan_amortization_id = $transaction['file_id']['id'];
                        }
                    }
                    if (isset($transaction['land_payment_head_id']['id'])) {
                        $voucherTransaction2->land_payment_head_id = $transaction['land_payment_head_id']['id'];
                    }
                    $voucherTransaction2->save();
                }
                if ($req->type == 2 || $req->type == 3 || $req->type == 4 || $req->type == 6 || $req->type == 7) {
                    $voucherTransaction->save();
                }
            }
        });
        return ['status' => "ok", 'message' => 'Voucher updated successfully', 'id' => $req->voucher_id];
    }

    /**
     * Deleting voucher
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $req)
    {
        $rules = array(
            'voucher_id' => 'required|int'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $voucher = Voucher::find($req->voucher_id);
        if (!$voucher) {
            return ['status' => 'error', 'message' => 'Voucher not found'];
        }
        if ($voucher->is_auto == 1) {
            return ['status' => 'error', 'message' => "This is auto voucher you can't delete it"];
        }
        DB::transaction(function () use ($req) {
            Voucher::where('id', $req->voucher_id)->delete();
            VoucherTransaction::where('voucher_id', $req->voucher_id)->delete();
        });

        return ['status' => 'ok', 'message' => 'Voucher deleted successfully'];
    }

    /**
     * editing voucher
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $req)
    {
        $voucher = Voucher::where('id', $req->voucher_id)->with('voucherTransactions')->first();
        $list = [];
        if ($voucher->type == 1 || $voucher->type == 5) {
            $getAccount =  $voucher->voucherTransactions->where('debit', 0)->first();
            $getTransactions =  $voucher->voucherTransactions->where('debit', '!=', 0);
        } elseif ($voucher->type == 2 || $voucher->type == 6) {
            $getAccount =  $voucher->voucherTransactions->where('credit', 0)->first();
            $getTransactions =  $voucher->voucherTransactions->where('credit', '!=', 0);
        } else {
            $getAccount = null;
            $getTransactions =  $voucher->voucherTransactions;
        }

        if ($getAccount != null) {
            $account = array(
                "id" => $getAccount->coaAccount->id,
                "value" => $getAccount->coaAccount->id,
                "label" => $getAccount->coaAccount->name
            );
        } else {
            $account = null;
        }
        $i = 0;
        foreach ($getTransactions as $voucherTransaction) {
            $land_payment_head_id = null;
            if ($voucherTransaction->landPaymentHead != null) {
                $land_payment_head_id = array(
                    "id" => optional($voucherTransaction->landPaymentHead)->id,
                    "value" => optional($voucherTransaction->landPaymentHead)->id,
                    "label" => optional($voucherTransaction->landPaymentHead)->name,
                    "type" => optional($voucherTransaction->landPaymentHead)->type
                );
            }
            $file_id = null;
            if ($voucherTransaction->land_id != null) {
                $file_id = array(
                    "id" => optional($voucherTransaction->land)->id,
                    "value" => optional($voucherTransaction->land)->id,
                    "label" => optional($voucherTransaction->land)->file_no . '-' . optional($voucherTransaction->land)->file_name
                );
            } elseif ($voucherTransaction->customer != null) {
                $file_id = array(
                    "id" => $voucherTransaction->customer->id,
                    "value" => $voucherTransaction->customer->id,
                    "label" => $voucherTransaction->customer->file_no . '-' . $voucherTransaction->customer->name
                );
            }

            $coa_account = CoaAccount::find($voucherTransaction->coa_account_id);
            // $PersonController = new PersonController;
            // $personFilesOptions = $PersonController->getFilesByPersonOrMouza($req, $voucherTransaction->coa_account_id);
            $type = 0;
            if ($coa_account->coa_sub_group_id == 1 || $coa_account->coa_sub_group_id == 22) {
                $type = 1;
            } elseif ($coa_account->coa_sub_group_id == 53 || $coa_account->coa_sub_group_id == 58) {
                $type = 2;
            } elseif ($coa_account->coa_sub_group_id == 29 || $coa_account->coa_sub_group_id == 56) {
                $type = 3;
            }
            $land_payment_head_options = [];
            // $LandPaymentHeads = LandPaymentHead::where('type', $type)->orderBy('id')->get();


            // foreach ($LandPaymentHeads as $LandPaymentHead) {
            //     $isDisabled = true;
            //     $row_type = $voucherTransaction->debit == 0 ? 'cr' : 'dr';
            //     if ($type === 3) {
            //         if (
            //             $coa_account->coa_sub_group_id === 29 &&
            //             $row_type === 'dr' &&
            //             $LandPaymentHead->id === 21
            //         ) {
            //             $isDisabled = false;
            //         } else if (
            //             $coa_account->coa_sub_group_id === 29 &&
            //             $row_type === 'cr' &&
            //             $LandPaymentHead->id === 19
            //         ) {
            //             $isDisabled = false;
            //         } else if (
            //             $coa_account->coa_sub_group_id === 56 &&
            //             $row_type === 'dr' &&
            //             $LandPaymentHead->id === 20
            //         ) {
            //             $isDisabled = false;
            //         } else {
            //             $isDisabled = true;
            //         }
            //     } else if ($type === 2) {
            //         if (
            //             ($coa_account->coa_sub_group_id === 53 ||
            //                 $coa_account->coa_sub_group_id === 58) &&
            //             $row_type === 'cr'
            //         ) {
            //             $isDisabled = false;
            //         } else {
            //             $isDisabled = true;
            //         }
            //     } else if ($LandPaymentHead->id === 10) {
            //         $isDisabled = true;
            //     } else {
            //         $isDisabled = false;
            //     }
            //     $land_payment_head_options[] = array(
            //         'id' => $LandPaymentHead->id,
            //         'value' => $LandPaymentHead->id,
            //         'label' => $LandPaymentHead->name,
            //         'disabled' => $isDisabled,
            //     );
            // }
            $person_files_options = [];
            // foreach ($personFilesOptions['files'] as $personFilesOption) {
            //     $person_files_options[] = array(
            //         'id' => $personFilesOption->id,
            //         'value' => $personFilesOption->id,
            //         'label' => $personFilesOption->file_no . '-' . $personFilesOption->file_name,
            //     );
            // }
            $list[$i] = array(
                "account" => array(
                    "id" => $voucherTransaction->coa_account_id,
                    "value" => $voucherTransaction->coa_account_id,
                    "label" => $voucherTransaction->coaAccount->code . '-' . $voucherTransaction->coaAccount->name,
                    "coa_sub_group_id" => $coa_account->coa_sub_group_id
                ),
                "index" => $i,
                "land_payment_head_id" => $land_payment_head_id,
                "file_id" => $file_id,
                "description" => $voucherTransaction->description,
                "cr" => $voucherTransaction->credit,
                "dr" => $voucherTransaction->debit,
                "row_type" => $voucherTransaction->debit == 0 ? 'cr' : 'dr',
                "person_files_options" => $person_files_options,
                "land_payment_head_options" => $land_payment_head_options,
                "person_files_options_loading" => false,
                "land_payment_head_options_loading" => false
            );
            $i++;
        }
        $array = array(

            "list" => $list,
            "account" => $account,
            "name" => $voucher->name,
            "date" => Land::changeDateFormat($voucher->date, 2),
            "total_amount_dr" => $voucher->total_amount,
            "total_amount_cr" => $voucher->total_amount,
            "total_amount" => $voucher->total_amount,
            "voucher_no" => $voucher->voucher_no,
            "cheque_no" => $voucher->cheque_no,
            "cheque_date" => Land::changeDateFormat($voucher->cheque_date, 2),
            "type" => $voucher->type,
            "voucher_id" => $voucher->id,
        );
        return ['voucher' => $array];
    }

    /**
     * Getting land transactions
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function getLandTransactions(Request $req)
    {
        $rules = array(
            'land_id' => 'required|int'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $expenses = VoucherTransaction::with('voucherNumber', 'landPaymentHead', 'coaAccount', 'land')
            ->whereHas('voucher', function ($qu) {
                return $qu->where([['isApproved', 1], ['is_post_dated', 0], ['type', 5]])
                    ->orWhere([['isApproved', 1], ['is_post_dated', 0], ['type', 7]]);
            })
            ->whereHas('coaAccount', function ($qu) {
                return $qu->where([['type', 'mouza']]);
            })
            ->where([['land_id', $req->land_id], ['credit', 0], ['land_payment_head_id', '!=', 10]])
            ->orderBy('date')
            ->get();

        $payments = VoucherTransaction::with('voucherNumber', 'landPaymentHead', 'coaAccount', 'land')
            ->whereHas('voucher', function ($qu) {
                return $qu->where([['isApproved', 1], ['is_post_dated', 0], ['type', 5]])->orWhere([['isApproved', 1], ['is_post_dated', 0], ['type', 7]]);
            })
            ->whereHas('coaAccount', function ($qu) {
                return $qu->where([['type', '!=', 'mouza']])->orWhereNull('type');
            })
            ->where([['land_id', $req->land_id], ['credit', 0], ['land_payment_head_id', '!=', 10]])
            ->orderBy('date')
            ->get();
        $landValue = Land::select('id', 'total_price')->find($req->land_id);

        return ['expenses' => $expenses, 'payments' => $payments, 'land_value' => $landValue->total_price];
    }

    /**
     * Getting land transactions
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function getPaymentSchedule(Request $req)
    {
        $land_id = $req->land_id;
        $mouza_id = $req->mouza_id;
        $date = isset($req->date) ? Land::changeDateFormat($req->date) : date('Y-m-d');
        $lands = Land::with(['mouza', 'jvPerson', 'totalDebit' => function ($qu) use ($date, $req) {
            $qu->when($req->date, function ($qu) use ($date) {
                $qu->where('date', '<=', $date);
            });
        }, 'totalInstallmentSum' => function ($qu) use ($date) {
            $qu->where('date', '<=', $date);
        }])
            ->when($land_id, function ($qu) use ($land_id) {
                $qu->where('id',  $land_id);
            })
            ->when($mouza_id, function ($qu) use ($mouza_id) {
                $qu->where('mouza_id',  $mouza_id);
            })
            ->get();

        $array = [];
        $i = 0;
        foreach ($lands as $land) {
            $total_price = $land->total_price;
            $land_id = $land->id;
            $total_payment = $land->totalDebit == null ? 0 : $land->totalDebit->total_payment;
            $installments_to_be_paid = $land->totalInstallmentSum == null ? 0 : $land->totalInstallmentSum->total_amount;
            $outStanding = $installments_to_be_paid - $total_payment;
            if (isset($req->date)) {
                if ($outStanding > 0) {
                    $array[$i] = array(
                        'id' => $land_id,
                        'file_name' => $land->file_name,
                        'file_no' => $land->file_no,
                        'total_price' => $total_price,
                        'total_payment' => $total_payment,
                        'remaining' => $total_price - $total_payment,
                        'installments_to_be_paid' => $installments_to_be_paid,
                        'outstanding' => $installments_to_be_paid - $total_payment,
                        'mouza' => $land->mouza,
                        'jv_person' => $land->jvPerson,
                    );

                    $i++;
                }
            } else {
                $array[$i] = array(
                    'id' => $land_id,
                    'file_name' => $land->file_name,
                    'file_no' => $land->file_no,
                    'total_price' => $total_price,
                    'total_payment' => $total_payment,
                    'remaining' => $total_price - $total_payment,
                    'installments_to_be_paid' => $installments_to_be_paid,
                    'outstanding' => $installments_to_be_paid - $total_payment,
                    'mouza' => $land->mouza,
                    'jv_person' => $land->jvPerson,
                );

                $i++;
            }
        }

        return ['data' => $array];
    }

    /**
     * Getting land transactions
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function getReceivableSchedule(Request $req)
    {
        $rules = array(
            // 'date' => 'required'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        $date = isset($req->date) ? Land::changeDateFormat($req->date) : date('Y-m-d');

        // $bookings  = Booking::with([
        //     'bookingCustomers',
        //     'plot.coaAccount.plotTotalReceived' => function ($qu) use ($date, $req) {
        //         $qu->when($req->date, function ($qu) use ($date) {
        //             $qu->where('date', '<=', $date);
        //         });
        //     },
        //     'installmentsAmountSum' => function ($qu) use ($date) {
        //         $qu->where('due_date', '<=', $date);
        //     }
        // ])
        // ->orderBy('id')
        // ->select('id', 'plot_id', 'total_payable')
        // ->get();
        $data = [];
        // foreach ($bookings as $booking) {
        //     $totalReceived = $booking->plot->coaAccount->plotTotalReceived->balance ?? 0;
        //     $totalToBePaid = $booking->installmentsAmountSum->totalToBePaid ?? 0;
        //     $data[] = array(

        //         'id' => $booking->plot->id,
        //         'reg_no' => $booking->plot->reg_no,
        //         'total_price' => $booking->total_payable,
        //         'total_payment' => $totalReceived,
        //         'total_installments_to_be_paid' => $totalToBePaid,
        //         'outstanding' => $totalToBePaid - $totalReceived,
        //     );
        // }
        return ['data' => $data];

        // $plots = Plot::with([
        //     'totalCredit' => function ($qu) use ($date, $req) {
        //         $qu->when($req->date, function ($qu) use ($date) {
        //             $qu->where('date', '<=', $date);
        //         });
        //     }, 'totalInstallmentSum' => function ($qu) use ($date) {
        //         $qu->whereDate('due_date', '<', $date);
        //     }
        // ])->select('id', 'reg_no')->whereHas('totalCredit')
        //     ->get();

        // $array = [];
        // $i = 0;
        // foreach ($plots as $plot) {
        //     $total_price = Booking::select('plot_id', 'total_payable')->where('plot_id', $plot->id)->first();
        //     $total_price = $total_price->total_payable;
        //     $plot_id = $plot->id;
        //     $total_payment = $plot->totalCredit == null ? 0 : $plot->totalCredit->total_payment;
        //     $total_installments_to_be_paid = $plot->totalInstallmentSum == null ? 0 : $plot->totalInstallmentSum->total_amount;

        //     $array[$i] = array(
        //         'id' => $plot_id,
        //         'reg_no' => $plot->reg_no,
        //         'total_price' => $total_price,
        //         'total_payment' => $total_payment,
        //         'total_installments_to_be_paid' => $total_installments_to_be_paid,
        //         'outstanding' => $total_installments_to_be_paid - $total_payment,
        //     );

        //     $i++;
        // }
        // return $array;

        // return ['data' => $array];
    }

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function index2(Request $req)
    {
        $rules = array(
            'records' => 'required|int',
            'pageNo' => 'required|int',
            'colName' => 'required|string',
            'sort' => 'required|string',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $post_dated = '';
        $canceled = '';
        $cleared = '';
        $allPostDated = '';
        $is_post_dated = $req->is_post_dated;
        if (isset($req->is_post_dated) && $is_post_dated == 0) {
            $cleared = 1;
        } elseif (isset($req->is_post_dated) && $is_post_dated == 1) {
            $post_dated = 1;
        } elseif (isset($req->is_post_dated) && $is_post_dated == 2) {
            $canceled = 1;
        } elseif (isset($req->is_post_dated) && $is_post_dated == 3) {
            $allPostDated = 1;
        }
        $voucher_no = $req->voucher_no;
        $group_id = $req->coa_group_id;
        $sub_group_id = $req->coa_sub_group_id;
        $account_id = $req->coa_account_id;
        $name =  strtoupper(preg_replace('/[^a-zA-Z]/', '', $req->voucher_no));
        if (isset($voucher_no)) {
            $voucher_no = preg_replace('/[^0-9]/', '', $req->voucher_no);
            if ($voucher_no[0] == 0 && $voucher_no[1] == 0) {
                $voucher_no = substr($voucher_no, 2);
            } elseif ($voucher_no[0] == 0) {
                $voucher_no = substr($voucher_no, 1);
            }
        }
        $isApproved = $req->is_approved;
        if ($req->isApproved == 0 && $req->isApproved != null) {
            $isApproved = '00';
        } else if ($req->isApproved == 1) {
            $isApproved = 1;
        } else {
            $isApproved = $req->isApproved;
        }
        $type = $req->type;
        $isDeleted = $req->isDeleted;
        $to = isset($req->to) ? Land::changeDateFormat($req->to) : null;
        $from = isset($req->from) ?  Land::changeDateFormat($req->from) : null;
        $cheque_date_to = isset($req->cheque_date_to) ? Land::changeDateFormat($req->cheque_date_to) : null;
        $cheque_date_from = isset($req->cheque_date_from) ?  Land::changeDateFormat($req->cheque_date_from) : null;
        $vouchers = Voucher::with('voucherType')->orderBy('date', 'desc')->orderBy('id', 'desc')
            ->when($name, function ($query, $name) {
                return $query->whereHas('voucherType', function ($query) use ($name) {
                    return $query->where('name', $name);
                });
            })
            ->when($voucher_no, function ($query, $voucher_no) {
                return $query->where('voucher_no',  $voucher_no);
            })
            ->when($from, function ($query, $from) {
                return $query->where('date', '>=', $from);
            })
            ->when($to, function ($query, $to) {
                return $query->where('date', '<=', $to);
            })
            ->when($cheque_date_from, function ($query, $cheque_date_from) {
                return $query->where('cheque_date', '>=', $cheque_date_from);
            })
            ->when($cheque_date_to, function ($query, $cheque_date_to) {
                return $query->where('cheque_date', '<=', $cheque_date_to);
            })
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->when($isApproved, function ($query, $isApproved) {
                return $query->where('isApproved', $isApproved);
            })
            ->when($post_dated, function ($query) {
                return $query->where('is_post_dated', 1);
            })
            ->when($canceled, function ($query) {
                return $query->where('is_post_dated', 2);
            })
            ->when($allPostDated, function ($query) {
                return $query->whereNotNull('cheque_no')->where('cheque_no', '!=', '');
            })
            ->when($cleared, function ($query) {
                return $query->whereNotNull('cheque_no')->where([['cheque_no', '!=', ''], ['is_post_dated', 0]]);
            })
            ->when($isDeleted, function ($query) {
                return $query->onlyTrashed();
            })
            ->when($group_id, function ($query) use ($group_id) {
                return $query->whereHas('voucherTransactions', function ($query) use ($group_id) {
                    return $query->whereHas('coaAccount', function ($query) use ($group_id) {
                        return $query->whereHas('coaGroup', function ($query) use ($group_id) {
                            return $query->where('coa_group_id', $group_id);
                        });
                    });
                });
            })
            ->when($sub_group_id, function ($query) use ($sub_group_id) {
                return $query->whereHas('voucherTransactions', function ($query) use ($sub_group_id) {
                    return $query->whereHas('coaAccount', function ($query) use ($sub_group_id) {
                        return $query->whereHas('coaSubGroup', function ($query) use ($sub_group_id) {
                            return $query->where('coa_sub_group_id', $sub_group_id);
                        });
                    });
                });
            })
            ->when($account_id, function ($query) use ($account_id) {
                return $query->whereHas('voucherTransactions', function ($query) use ($account_id) {
                    return $query->whereHas('coaAccount', function ($query) use ($account_id) {
                        return $query->where('id', $account_id);
                    });
                });
            })
            ->orderBy($req->colName, $req->sort)
            ->paginate($req->records, ['*'], 'page', $req->pageNo);
        return ['vouchers' => $vouchers];
    }

    //------------------------------------------------------------------------------
    /**
     * Store a newly created voucher in storage.
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response message
     * @return \Illuminate\Http\Response status
     */
    public function storeExtendedJv(Request $req)
    {
        $rules = array(
            'type' => 'required|int',
            'date' => 'required',
            'total_amount' => 'required',
            'list' => 'required'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        DB::transaction(function () use ($req) {
            $is_post_dated = isset($req->cheque_no) ? 1 : 0;
            $getVoucherNo = DB::table('vouchers')->where('type', 7)->orderBy('id', 'desc')->first();
            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
            $voucher = new Voucher;
            $voucher->voucher_no = $newVoucherNo;
            $voucher->date = Land::changeDateFormat($req->date);
            $voucher->generated_at = Land::changeDateFormat($req->date);
            $voucher->name = $req->name;
            $voucher->type = 7;
            $voucher->total_amount = $req->total_amount;
            $voucher->cheque_no = $req->cheque_no;
            $voucher->cheque_date = Land::changeDateFormat($req->cheque_date);
            $voucher->is_post_dated = $is_post_dated;
            $voucher->save();
            $voucher_id = $voucher->id;
            foreach ($req->list as $transaction) {
                $prevBal = 0;
                $getPrevBal = VoucherTransaction::where('coa_account_id', $transaction['account']['id'])->orderBy('id', 'desc')->first();
                if ($getPrevBal) {
                    $prevBal = $getPrevBal->balance;
                }
                $coaAccount = CoaAccount::find($transaction['account']['id']);
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = Land::changeDateFormat($req->date) . " " . date("H:i:s");
                $voucherTransaction->coa_account_id = $transaction['account']['id'];
                $voucherTransaction->debit = $transaction['dr'];
                $voucherTransaction->credit = $transaction['cr'];
                $voucherTransaction->balance = $transaction['dr'] - $transaction['cr'] + $prevBal;
                $voucherTransaction->description = $transaction['description'];
                if (isset($transaction['file_id'])) {
                    if ($coaAccount->coa_sub_group_id == 29 || $coaAccount->coa_sub_group_id == 56) {
                        $voucherTransaction->loan_amortization_id = $transaction['file_id']['id'];
                    } elseif ($coaAccount->coa_sub_group_id == 20 || $coaAccount->coa_sub_group_id == 48 || $coaAccount->coa_sub_group_id == 49  || $coaAccount->coa_sub_group_id == 59) {
                        $voucherTransaction->plot_id = $transaction['file_id']['id'];
                    } elseif ($coaAccount->coa_sub_group_id == 1 || $coaAccount->coa_sub_group_id == 22) {
                        $voucherTransaction->land_id = $transaction['file_id']['id'];
                    }
                }
                if (isset($transaction['land_payment_head_id']['id'])) {
                    $voucherTransaction->land_payment_head_id = $transaction['land_payment_head_id']['id'];
                }
                $voucherTransaction->save();
            }
        });
        return ['status' => "ok", 'message' => 'Voucher created successfully'];
    }

    public function invoicePaymentVoucher(Request $req)
    {
        Log::info("invoicePaymentVoucher called", $req->all());
        $rules = array(
            'customer_id' => 'required',
            'amount' => 'required|numeric',
            'invoice_id' => 'required',
            'date' => 'required',
            'account_id' => 'required|numeric'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        
        // Normalize invoice_id to always be an array
        if (!is_array($req->invoice_id)) {
            $req->merge(['invoice_id' => [$req->invoice_id]]);
        }
        
        // To get the date Financial Year Closed
        $getFinYearClosing = DB::table('fin_year_closing')->select('to_date')->orderBy('to_date', 'desc')->first();

        // Compare request date and date of Financial Year Closed
        if (Land::changeDateFormat($req->date) <= $getFinYearClosing->to_date) {
            return ['status' => 'error', 'message' => "Financial Year Closed." . '' . '(' . $getFinYearClosing->to_date . ')'];
        }
        DB::transaction(function () use ($req) {
            $customer = Person::find($req->customer_id);
            $customer_coa_account_id = CoaAccount::where([['coa_sub_group_id', 9], ['person_id', $req->customer_id]])->value('id');
            $customer_name = $customer->name;

            $invoiceNumbers = [];
            foreach ($req->invoice_id as $invoice_id) {
                $invoice = Invoice::find($invoice_id);
                $invoiceNumbers[] = $invoice->invoice_no;
            }
            //------------------Payment or receipt Voucher-----------
            $date = Land::changeDateFormat($req->date);

            $is_post_dated = isset($req->cheque_no) ? 1 : 0;
            $voucherType = 2;

            $voucherName = '';

            $getVoucherNo = DB::table('vouchers')->where('type', $voucherType)->orderBy('id', 'desc')->first();
            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
            $user = Auth::guard('api')->user();
            // $userId = $user->id;
            $userId = 1;

            $invoiceNumbersString = implode(', ', $invoiceNumbers);
            $voucher = new Voucher();

            $voucher->voucher_no = $newVoucherNo;
            $voucher->date = $date;
            $voucher->generated_at = $date;
            $voucher->name = $voucherName . $customer_name . " Invoice Nos: " . $invoiceNumbersString;
            $voucher->type = $voucherType;
            $voucher->total_amount = $req->amount;
            $voucher->cheque_no = $req->cheque_no;
            $voucher->cheque_date = Land::changeDateFormat($req->cheque_date);
            $voucher->is_post_dated = $is_post_dated;
            $voucher->created_by = $userId;
            $voucher->isApproved = 1;
            $voucher->save();
            $voucher_id = $voucher->id;

            //--------------debiting cash account --------------------

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $date;
            $voucherTransaction->coa_account_id = $req->account_id;
            $voucherTransaction->debit = $req->amount;
            $voucherTransaction->credit = 0;
            // $voucherTransaction->invoice_id = implode(', ', $req->invoice_id); // Corrected this line
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = $voucherName . $customer_name . " Invoice Nos: " . $invoiceNumbersString;
            $voucherTransaction->save();

            //---------------------Crediting investor account ------------------
            $voucherTransaction2 = new VoucherTransaction();
            $voucherTransaction2->voucher_id = $voucher_id;
            $voucherTransaction2->date = $date;
            $voucherTransaction2->coa_account_id = $customer_coa_account_id;
            $voucherTransaction2->debit = 0;
            $voucherTransaction2->is_approved = 1;
            $voucherTransaction2->credit = $req->amount;
            // $voucherTransaction2->invoice_id = implode(', ', $req->invoice_id); // Corrected this line
            $voucherTransaction2->description = $voucherName . $customer_name . " Invoice Nos: " . $invoiceNumbersString;
            $voucherTransaction2->save();


            $remainingAmount = (float) $req->amount;
            $invoiceIds = $req->invoice_id;
            $lastInvoiceId = end($invoiceIds);

            foreach ($invoiceIds as $invoice_id) {
                $invoice = Invoice::find($invoice_id);
                if (!$invoice) {
                    Log::warning("Invoice not found during payment voucher creation", ['invoice_id' => $invoice_id]);
                    continue;
                }

                $totalPayable = (float) ($invoice->total_after_adv_tax > 0 ? $invoice->total_after_adv_tax : $invoice->total_after_gst);
                $alreadyPaid = (float) (($invoice->amount_received ?? 0) + ($invoice->bank_amount_received ?? 0));
                $due = max(0.0, $totalPayable - $alreadyPaid);

                // Determine how much of the current voucher applies to this invoice
                $currentVoucherPayment = 0.0;
                if ($remainingAmount > 0) {
                    $currentVoucherPayment = ($invoice_id == $lastInvoiceId) ? $remainingAmount : min($remainingAmount, $due);
                    $remainingAmount -= $currentVoucherPayment;
                }

                // Calculate all PREVIOUS payments made via vouchers for this invoice
                $previouslyPaidViaVouchers = DB::table('voucher_transaction_invoices')
                    ->join('vouchers', 'voucher_transaction_invoices.voucher_id', '=', 'vouchers.id')
                    ->where('voucher_transaction_invoices.invoice_id', $invoice_id)
                    ->where('vouchers.deleted_at', null)
                    ->sum('vouchers.total_amount');

                // Total = Current Voucher + Previous Vouchers + Initial Received Amounts
                $totalPaidWithVoucher = $alreadyPaid + $previouslyPaidViaVouchers + $currentVoucherPayment;

                $oldStatus = $invoice->payment_status;
                if ($totalPaidWithVoucher >= $totalPayable && $totalPayable > 0) {
                    $invoice->payment_status = 'paid';
                } elseif ($totalPaidWithVoucher > 0) {
                    $invoice->payment_status = 'partial_paid';
                } else {
                    $invoice->payment_status = 'unpaid';
                }

                Log::info("Payment Status Update Log", [
                    'invoice_id' => $invoice_id,
                    'totalPayable' => $totalPayable,
                    'alreadyPaid' => $alreadyPaid,
                    'previouslyPaidVouchers' => $previouslyPaidViaVouchers,
                    'currentVoucher' => $currentVoucherPayment,
                    'totalPaidSum' => $totalPaidWithVoucher,
                    'oldStatus' => $oldStatus,
                    'newStatus' => $invoice->payment_status
                ]);

                $invoice->save();

                VoucherTransactionInvoice::create([
                    'voucher_id' => $voucher_id,
                    'invoice_id' => $invoice_id,
                ]);
            }
        });

        return ["status" => "ok", "message" => "Payment Received successfully"];
    }



    public function poPaymentVoucher(Request $req)
    {
        $rules = array(
            'supplier_id' => 'required',
            'amount' => 'required|numeric',
            'po_id' => 'required',
            'date' => 'required',
            'account_id' => 'required|numeric'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        
        // Normalize po_id to always be an array
        if (!is_array($req->po_id)) {
            $req->merge(['po_id' => [$req->po_id]]);
        }
        
        // To get the date Financial Year Closed
        $getFinYearClosing = DB::table('fin_year_closing')->select('to_date')->orderBy('to_date', 'desc')->first();

        // Compare request date and date of Financial Year Closed
        if (Land::changeDateFormat($req->date) <= $getFinYearClosing->to_date) {

            return ['status' => 'error', 'message' => "Financial Year Closed." . '' . '(' . $getFinYearClosing->to_date . ')'];
        }
        DB::transaction(function () use ($req) {
            $customer = Person::find($req->supplier_id);
            $customer_coa_account_id = CoaAccount::where([['coa_sub_group_id', 2], ['person_id', $req->supplier_id]])->value('id');
            $customer_name = $customer->name;

            $poNumbers = [];
            foreach ($req->po_id as $po_id) {
                $po = PurchaseOrder::find($po_id);
                $poNumbers[] = $po->po_no;
            }

            //------------------Payment or receipt Voucher-----------
            $date = Land::changeDateFormat($req->date);

            $is_post_dated = isset($req->cheque_no) ? 1 : 0;
            $voucherType = 1;

            $voucherName = '';

            $getVoucherNo = DB::table('vouchers')->where('type', $voucherType)->orderBy('id', 'desc')->first();
            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
            $user = Auth::guard('api')->user();
            // $userId = $user->id;
            $userId = 1;
            $poNumbersString = implode(', ', $poNumbers);

            $voucher = new Voucher();
            $voucher->voucher_no = $newVoucherNo;
            $voucher->date = $date;
            $voucher->generated_at = $date;
            $voucher->name = $voucherName . $customer_name . " PO No: " . $poNumbersString;
            $voucher->type = $voucherType;
            $voucher->total_amount = $req->amount;
            $voucher->cheque_no = $req->cheque_no;
            $voucher->cheque_date = Land::changeDateFormat($req->cheque_date);
            $voucher->is_post_dated = $is_post_dated;
            $voucher->created_by = $userId;
            $voucher->isApproved = 1;
            $voucher->save();
            $voucher_id = $voucher->id;

            //--------------debiting cash account --------------------

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $date;
            $voucherTransaction->coa_account_id = $req->account_id;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $req->amount;
            // $voucherTransaction->po_id = $req->po_id;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = $voucherName . $customer_name . " PO No: " . $poNumbersString;
            $voucherTransaction->save();

            //---------------------Crediting investor account ------------------
            $voucherTransaction2 = new VoucherTransaction();
            $voucherTransaction2->voucher_id = $voucher_id;
            $voucherTransaction2->date = $date;
            $voucherTransaction2->coa_account_id = $customer_coa_account_id;
            $voucherTransaction2->debit = $req->amount;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction2->credit = 0;
            // $voucherTransaction2->po_id = $req->po_id;
            $voucherTransaction2->description = $voucherName . $customer_name . " PO No: " . $poNumbersString;
            $voucherTransaction2->save();

            foreach ($req->po_id as $po_id) {
                VoucherTransactionPoNumber::create([
                    'voucher_id' => $voucher_id,
                    'po_id' => $po_id,
                ]);
            }
        });

        return ["status" => "ok", "message" => "Amount Paid successfully"];
    }

    /**
     * Clearing or rejecting post dated vouchers
     *
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\Response
     */
    public function clearPostDatedVoucher(Request $req)
    {

        $rules = array(
            'voucher_id' => 'required|int|exists:vouchers,id',
            'is_post_dated' => 'required|int|in:0,1,2',
            'date' => 'required',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $voucher = Voucher::find($req->voucher_id);
        if ($voucher->is_auto == 1) {
            return ['status' => 'error', 'message' => "This is auto voucher you can't update"];
        }
        DB::transaction(function () use ($req) {
            $clearedDate = $req->is_post_dated == 1 ? null : Land::changeDateFormat($req->date);
            $voucher_id = $req->voucher_id;
            $voucher = Voucher::find($req->voucher_id);
            //----------Recording old values of voucher
            // $editedVoucher = new EditedVoucher;
            // $editedVoucher->voucher_id = $voucher->id;
            // $editedVoucher->type = $voucher->type;
            // $editedVoucher->name = $voucher->name;
            // $editedVoucher->date = $voucher->date;
            // $editedVoucher->generated_at = $voucher->generated_at;
            // $editedVoucher->total_amount = $voucher->total_amount;
            // $editedVoucher->cheque_no = $voucher->cheque_no;
            // $editedVoucher->cheque_date = $voucher->cheque_date;
            // $editedVoucher->isApproved = $voucher->isApproved;
            // $editedVoucher->cleared_date = $voucher->cleared_date;
            // $editedVoucher->save();

            //----------Recording new values of voucher
            $voucher->is_post_dated = $req->is_post_dated;
            $voucher->date = Land::changeDateFormat($req->date);
            $voucher->cleared_date = $clearedDate;
            $voucher->cheque_date = Land::changeDateFormat($req->date);
            $voucher->save();

            $oldVoucherTransactions = VoucherTransaction::where('voucher_id', $voucher_id)->get();

            //----------Recording old values of voucher transactions
            // foreach ($oldVoucherTransactions as $oldVoucherTransaction) {
            //     $editedVoucherTransaction = new EditedVoucherTransaction;
            //     $editedVoucherTransaction->edited_voucher_id = $editedVoucher->id;
            //     $editedVoucherTransaction->date = $voucher->date;
            //     $editedVoucherTransaction->coa_account_id = $oldVoucherTransaction->coa_account_id;
            //     $editedVoucherTransaction->debit = $oldVoucherTransaction->debit;
            //     $editedVoucherTransaction->credit = $oldVoucherTransaction->credit;
            //     $editedVoucherTransaction->balance = $oldVoucherTransaction->balance;
            //     $editedVoucherTransaction->description = $oldVoucherTransaction->description;
            //     $editedVoucherTransaction->land_id = $oldVoucherTransaction->land_id;
            //     $editedVoucherTransaction->land_payment_head_id = $oldVoucherTransaction->land_payment_head_id;
            //     $editedVoucherTransaction->loan_amortization_id = $oldVoucherTransaction->loan_amortization_id;
            //     $editedVoucherTransaction->save();
            // }

            VoucherTransaction::where('voucher_id', $voucher_id)->update(['date' => Land::changeDateFormat($req->date)]);
        });
        return ['status' => "ok", 'message' => 'Voucher updated successfully', 'id' => $req->voucher_id];
    }
}
