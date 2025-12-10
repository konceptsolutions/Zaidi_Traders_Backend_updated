<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Voucher;

use App\Models\CoaAccount;
use App\Models\PersonType;

use App\Models\CoaSubGroup;
use Illuminate\Http\Request;
use App\Models\VoucherTransaction;
use Illuminate\Support\Facades\DB;
use App\Services\CustomErrorMessages;
use Illuminate\Support\Facades\Validator;

class PersonController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param  int  $person_type_id
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $cnic = $request->cnic;
        $id = $request->id;
        $phone_no = $request->phone_no;
        $name = $request->name;
        $ntn = $request->ntn;
        $gst = $request->gst;
        $dsl = $request->dsl;
        $person_type = $request->person_type;
        $isActive = $request->status;
        // if ($request->isActive == 0 && $request->isActive != null) {
        //     $isActive = '00';
        // } else if ($request->isActive == 1) {
        //     $isActive = 1;
        // } else {
        //     $isActive = '';
        // }

        $persons = Person::with('personType')
            ->when($isActive !== null, function ($q) use ($isActive) {
                return $q->where('isActive', $isActive);
            })

            ->when($person_type, function ($q, $person_type) {
                return $q->where('person_type', $person_type);
            })
            ->when($name, function ($q, $name) {
                return $q->where('name', 'LIKE', '%' . $name . '%');
            })
            ->when($cnic, function ($q, $cnic) {
                return $q->where('cnic', 'LIKE', '%' . $cnic . '%');
            })
            ->when($phone_no, function ($q, $phone_no) {
                return $q->where('phone_no', 'LIKE', '%' . $phone_no . '%');
            })
            ->when($ntn, function ($q, $ntn) {
                return $q->where('ntn', 'LIKE', '%' . $ntn . '%');
            })
            ->when($gst, function ($q, $gst) {
                return $q->where('gst', 'LIKE', '%' . $gst . '%');
            })
            ->when($dsl, function ($q, $dsl) {
                return $q->where('dsl', 'LIKE', '%' . $dsl . '%');
            })
            ->when($id, function ($q, $id) {
                return $q->where('id', $id);
            })
            // ->whereHas('PersonType', function ($query) use ($request) {
            //     $query->when($request->person_type_id, function ($query) use ($request) {
            //         return $query->where('id', $request->person_type_id);
            //     });
            // })
            ->orderBy($request->colName, $request->sort)->paginate($request->records, ['*'], 'page', $request->pageNo);
        return ['persons' => $persons];
    }

    public function testAccount()
    {
        $persons = Person::where('person_type', 2)->get();

        foreach ($persons as $persons) {
            $subgroup = CoaSubGroup::find(2);
            $lastCode = CoaAccount::where('coa_sub_group_id', 2)->orderBy('id', 'desc')->first();
            if (!$lastCode) {
                $newCode = $subgroup->code . '001';
            } else {
                $newCode = $lastCode->code + 1;
            }
            $coaAccount = new CoaAccount();
            $coaAccount->name = $persons->name;
            $coaAccount->code = $newCode;
            $coaAccount->coa_group_id = 3;
            $coaAccount->coa_sub_group_id  = 2;
            $coaAccount->person_id  = $persons->id;
            $coaAccount->description = $persons->name;
            $coaAccount->isActive = $persons->isActive;
            $coaAccount->isDefault = 1;
            $coaAccount->save();
        }

        return ['status' => "ok", 'message' => 'Coa Accounts Stored Successfully'];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $person_id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $req)
    {
        $rules = array(
            'id' => 'required|int|exists:people,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $person = Person::with('PersonType', 'coaAccount')->find($req->id);
            return ['status' => 'ok', 'person' => $person];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
        return ['person' => $person];
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $person_id
     * @param \Illuminate\Http\Request name
     * @param \Illuminate\Http\Request phone_no
     * @param \Illuminate\Http\Request email
     * @param \Illuminate\Http\Request cnic
     * @param \Illuminate\Http\Request address
     * @param \Illuminate\Http\Request personTypes(array)
     * @param \Illuminate\Http\Request person_type_id
     * @return \Illuminate\Http\Response message
     */
    public function update(Request $request)
    {
        $rules = array(
            'id' => 'required|int|exists:people,id',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {
                $person = Person::find($request->id);
                $person->name = $request->name;
                $person->father_name = $request->father_name;
                $person->phone_no  = $request->phone_no;
                $person->email = $request->email;
                $person->cnic = $request->cnic;
                $person->ntn = $request->ntn;
                $person->gst = $request->gst;
                $person->dsl = $request->dsl;
                $person->address = $request->address;
                $person->person_type = $request->person_type_id;
                $person->opening_balance = $request->opening_balance;
                $person->date = $request->date ? $request->date : null;
                $person->save();
                $person_id = $request->id;

                if ($request->person_type_id == 2) {

                    $voucher = Voucher::where('person_id', $request->id)->first();
                    if ($voucher) {
                        $voucherId = $voucher->id;
                        VoucherTransaction::where('voucher_id', $voucherId)->delete();
                        Voucher::find($voucherId)->delete();
                    }

                    $coaAccount = CoaAccount::where('person_id', $request->id)
                        ->where('coa_group_id', 3)
                        ->where('coa_sub_group_id', 2)->first();
                    $coaAccount->name     = $request->name;
                    $coaAccount->description     = $request->name;
                    $coaAccount->save();

                    $coa_account_id = $coaAccount->id;

                    if (empty($request->opening_balance) || empty($request->date)) {
                        return ['status' => "ok", 'message' => 'Person updated successfully'];
                    } else {
                        if ($request->opening_balance > 0 && $request->date) {
                            $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                            $voucher = new Voucher();
                            $voucher->voucher_no = $newVoucherNo;
                            $voucher->date = $request->date;
                            $voucher->name = $request->name;
                            $voucher->type = 3;
                            $voucher->isApproved = 1;
                            $voucher->generated_at = $request->date;
                            $voucher->total_amount = $request->opening_balance;
                            $voucher->person_id = $person_id;
                            $voucher->cheque_no = $request->cheque_no;
                            $voucher->cheque_date = $request->cheque_date;
                            $voucher->is_auto = 1;
                            $voucher->save();
                            $voucher_id = $voucher->id;

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucher_id;
                            $voucherTransaction->date = $request->date;
                            $voucherTransaction->coa_account_id = $coa_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit = $request->opening_balance;
                            $voucherTransaction->description = $request->name . ' Supplier  ' . ' Opening Balance : ' . $request->opening_balance;
                            $voucherTransaction->save();

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucher_id;
                            $voucherTransaction->date = $request->date;
                            $voucherTransaction->coa_account_id = 1779;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $request->opening_balance;
                            $voucherTransaction->credit = 0;
                            $voucherTransaction->description = $request->name . ' Supplier ' . ' Opening Balance : ' . $request->opening_balance;
                            $voucherTransaction->save();
                        } else if ($request->opening_balance < 0 && $request->date) {
                            $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                            $voucher = new Voucher();
                            $voucher->voucher_no = $newVoucherNo;
                            $voucher->date = $request->date;
                            $voucher->name = $request->name;
                            $voucher->type = 3;
                            $voucher->isApproved = 1;
                            $voucher->generated_at = $request->date;
                            $voucher->total_amount = abs($request->opening_balance);
                            $voucher->person_id = $person_id;
                            $voucher->cheque_no = $request->cheque_no;
                            $voucher->cheque_date = $request->cheque_date;
                            $voucher->is_auto = 1;
                            $voucher->save();
                            $voucher_id = $voucher->id;

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucher_id;
                            $voucherTransaction->date = $request->date;
                            $voucherTransaction->coa_account_id = $coa_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = abs($request->opening_balance);
                            $voucherTransaction->credit = 0;
                            $voucherTransaction->description = $request->name . ' Supplier  ' . ' Opening Balance : ' . abs($request->opening_balance);
                            $voucherTransaction->save();

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucher_id;
                            $voucherTransaction->date = $request->date;
                            $voucherTransaction->coa_account_id = 1779;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit = abs($request->opening_balance);
                            $voucherTransaction->description = $request->name . ' Supplier ' . ' Opening Balance : ' . abs($request->opening_balance);
                            $voucherTransaction->save();
                        }
                    }
                }
                if ($request->person_type_id == 1) {


                    $voucher = Voucher::where('person_id', $request->id)->first();
                    if ($voucher) {
                        $voucherId = $voucher->id;
                        VoucherTransaction::where('voucher_id', $voucherId)->delete();
                        Voucher::find($voucherId)->delete();
                    }

                    $coaAccount = CoaAccount::where('person_id', $request->id)
                        ->where('coa_group_id', 1)
                        ->where('coa_sub_group_id', 9)->first();
                    $coaAccount->name     = $request->name;
                    $coaAccount->description     = $request->name;
                    $coaAccount->save();

                    $coa_account_id = $coaAccount->id;

                    if (empty($request->opening_balance) || empty($request->date)) {
                        return ['status' => "ok", 'message' => 'Person updated successfully'];
                    } else {
                        $coa_account_id = $coaAccount->id;
                        if ($request->opening_balance > 0 && $request->date) {
                            $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                            $voucher = new Voucher();
                            $voucher->voucher_no = $newVoucherNo;
                            $voucher->date = $request->date;
                            $voucher->name = $request->name;
                            $voucher->type = 3;
                            $voucher->isApproved = 1;
                            $voucher->generated_at = $request->date;
                            $voucher->total_amount = $request->opening_balance;
                            $voucher->person_id = $person_id;
                            $voucher->cheque_no = $request->cheque_no;
                            $voucher->cheque_date = $request->cheque_date;
                            $voucher->is_auto = 1;
                            $voucher->save();
                            $voucher_id = $voucher->id;

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucher_id;
                            $voucherTransaction->date = $request->date;
                            $voucherTransaction->coa_account_id = $coa_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $request->opening_balance;
                            $voucherTransaction->credit = 0;
                            $voucherTransaction->description = $request->name . ' Customer ' . ' Opening Balance : ' . $request->opening_balance;
                            $voucherTransaction->save();

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucher_id;
                            $voucherTransaction->date = $request->date;
                            $voucherTransaction->coa_account_id = 1779;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit = $request->opening_balance;
                            $voucherTransaction->description = $request->name . ' Customer ' . ' Opening Balance : ' . $request->opening_balance;
                            $voucherTransaction->save();
                        } else if ($request->opening_balance < 0 && $request->date) {
                            $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                            $voucher = new Voucher();
                            $voucher->voucher_no = $newVoucherNo;
                            $voucher->date = $request->date;
                            $voucher->name = $request->name;
                            $voucher->type = 3;
                            $voucher->isApproved = 1;
                            $voucher->generated_at = $request->date;
                            $voucher->total_amount = abs($request->opening_balance);
                            $voucher->person_id = $person_id;
                            $voucher->cheque_no = $request->cheque_no;
                            $voucher->cheque_date = $request->cheque_date;
                            $voucher->is_auto = 1;
                            $voucher->save();
                            $voucher_id = $voucher->id;

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucher_id;
                            $voucherTransaction->date = $request->date;
                            $voucherTransaction->coa_account_id = $coa_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit = abs($request->opening_balance);
                            $voucherTransaction->description = $request->name . ' Customer ' . ' Opening Balance : ' . abs($request->opening_balance);
                            $voucherTransaction->save();

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucher_id;
                            $voucherTransaction->date = $request->date;
                            $voucherTransaction->coa_account_id = 1779;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = abs($request->opening_balance);
                            $voucherTransaction->credit = 0;
                            $voucherTransaction->description = $request->name . ' Customer ' . ' Opening Balance : ' . abs($request->opening_balance);
                            $voucherTransaction->save();
                        }
                    }
                }
            });
            return ['status' => "ok", 'message' => 'Person updated successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $person_id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $rules = array(
            'id' => 'required',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        $person = Person::find($request->id);
        if (!$person) {
            return ['status' => 'error', 'message' => 'Person not found'];
        }
        try {
            DB::transaction(function () use ($request) {
                CoaAccount::where('person_id', $request->id)
                    ->where('coa_group_id', 3)
                    ->where('coa_sub_group_id', 2)->delete();
                Person::find($request->id)->delete();
                PersonType::where('person_id', $request->id)->delete();
            });
            return ['status' => 'ok', 'message' => 'Person deleted successfully'];
        } catch (\Exception $e) {

            $coaaccount = CoaAccount::where('person_id', $request->id)
                ->where('coa_group_id', 3)
                ->where('coa_sub_group_id', 2)->first();
            $coaaccount->isActive = 0;
            $coaaccount->save();
            $person = Person::find($request->id);
            $person->isActive = 0;
            $person->save();
            $message = CustomErrorMessages::getCustomMessage2($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
    /**
     * @param \Illuminate\Http\Request person_type_id
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPersonsByPersonType(Request $req)
    {
        $rules = array(
            'person_type_id' => 'required',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        $person_type_id = $req->person_type_id;
        $persons = Person::with('peoplePersonType.personType')->whereHas('peoplePersonType', function ($q) use ($person_type_id) {
            return $q->where('person_type_id', $person_type_id);
        })->where('isActive', '=', 1)
            ->orderBy('name')->get();
        if (!$persons) {
            return ['status' => 'Record not found'];
        }

        return ['persons' => $persons];
    }
    //------------------------------------------------------------------------------
    /**
     * Store a newly created Person in storage.
     *
     * @param \Illuminate\Http\Request name
     * @param \Illuminate\Http\Request phone_no
     * @param \Illuminate\Http\Request email
     * @param \Illuminate\Http\Request cnic
     * @param \Illuminate\Http\Request address
     * @param \Illuminate\Http\Request person_type
     * @return \Illuminate\Http\Response message
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $person = new Person();
            $person->name = $request->name;
            $person->father_name = $request->father_name;
            $person->phone_no = $request->phone_no;
            $person->email = $request->email;
            $person->cnic = $request->cnic;
            $person->address = $request->address;
            $person->ntn = $request->ntn;
            $person->gst = $request->gst;
            $person->dsl = $request->dsl;
            $person->person_type = $request->person_type_id;
            $person->opening_balance = $request->opening_balance;
            $person->date = $request->date ? $request->date : null;
            $person->save();
            $person_id = $person->id;

            if ($request->person_type_id == 2) {
                $subgroup = CoaSubGroup::find(2);
                $lastCode = CoaAccount::where('coa_sub_group_id', $subgroup->id)->orderBy('id', 'desc')->first();
                $newCode = $lastCode ? $lastCode->code + 1 : $subgroup->code . '001';

                $coaAccount = new CoaAccount();
                $coaAccount->name = $request->name;
                $coaAccount->code = $newCode;
                $coaAccount->coa_group_id = 3;
                $coaAccount->coa_sub_group_id = 2;
                $coaAccount->person_id = $person_id;
                $coaAccount->description = $request->name;
                $coaAccount->isDefault = 1;
                $coaAccount->save();

                $coa_account_id = $coaAccount->id;

                if (empty($request->opening_balance) || empty($request->date)) {
                    return ['status' => "ok", 'message' => 'Person stored successfully'];
                } else {
                    if ($request->opening_balance > 0 && $request->date) {
                        $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                        $voucher = new Voucher();
                        $voucher->voucher_no = $newVoucherNo;
                        $voucher->date = $request->date;
                        $voucher->name = $request->name;
                        $voucher->type = 3;
                        $voucher->isApproved = 1;
                        $voucher->generated_at = $request->date;
                        $voucher->total_amount = $request->opening_balance;
                        $voucher->person_id = $person_id;
                        $voucher->cheque_no = $request->cheque_no;
                        $voucher->cheque_date = $request->cheque_date;
                        $voucher->is_auto = 1;
                        $voucher->save();
                        $voucher_id = $voucher->id;

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = $coa_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->opening_balance;
                        $voucherTransaction->description = $request->name . ' Supplier  ' . ' Opening Balance : ' . $request->opening_balance;
                        $voucherTransaction->save();

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = 1779;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->opening_balance;
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $request->name . ' Supplier ' . ' Opening Balance : ' . $request->opening_balance;
                        $voucherTransaction->save();
                    } else if ($request->opening_balance < 0 && $request->date) {
                        $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                        $voucher = new Voucher();
                        $voucher->voucher_no = $newVoucherNo;
                        $voucher->date = $request->date;
                        $voucher->name = $request->name;
                        $voucher->type = 3;
                        $voucher->isApproved = 1;
                        $voucher->generated_at = $request->date;
                        $voucher->total_amount = abs($request->opening_balance);
                        $voucher->person_id = $person_id;
                        $voucher->cheque_no = $request->cheque_no;
                        $voucher->cheque_date = $request->cheque_date;
                        $voucher->is_auto = 1;
                        $voucher->save();
                        $voucher_id = $voucher->id;

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = $coa_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = abs($request->opening_balance);
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $request->name . ' Supplier  ' . ' Opening Balance : ' . abs($request->opening_balance);
                        $voucherTransaction->save();

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = 1779;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = abs($request->opening_balance);
                        $voucherTransaction->description = $request->name . ' Supplier ' . ' Opening Balance : ' . abs($request->opening_balance);
                        $voucherTransaction->save();
                    }
                }
            } elseif ($request->person_type_id == 1) {
                $subgroup = CoaSubGroup::find(9);
                $lastCode = CoaAccount::where('coa_sub_group_id', $subgroup->id)->orderBy('id', 'desc')->first();
                $newCode = $lastCode ? $lastCode->code + 1 : $subgroup->code . '001';

                $coaAccount = new CoaAccount();
                $coaAccount->name = $request->name;
                $coaAccount->code = $newCode;
                $coaAccount->coa_group_id = 1;
                $coaAccount->coa_sub_group_id = 9;
                $coaAccount->person_id = $person_id;
                $coaAccount->description = $request->name;
                $coaAccount->isDefault = 1;
                $coaAccount->save();

                $coa_account_id = $coaAccount->id;

                if (empty($request->opening_balance) || empty($request->date)) {
                    return ['status' => "ok", 'message' => 'Person stored successfully'];
                } else {

                    if ($request->opening_balance > 0 && $request->date) {
                        $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                        $voucher = new Voucher();
                        $voucher->voucher_no = $newVoucherNo;
                        $voucher->date = $request->date;
                        $voucher->name = $request->name;
                        $voucher->type = 3;
                        $voucher->isApproved = 1;
                        $voucher->generated_at = $request->date;
                        $voucher->total_amount = $request->opening_balance;
                        $voucher->person_id = $person_id;
                        $voucher->cheque_no = $request->cheque_no;
                        $voucher->cheque_date = $request->cheque_date;
                        $voucher->is_auto = 1;
                        $voucher->save();
                        $voucher_id = $voucher->id;

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = $coa_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->opening_balance;
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $request->name . ' Customer ' . ' Opening Balance : ' . $request->opening_balance;
                        $voucherTransaction->save();

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = 1779;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->opening_balance;
                        $voucherTransaction->description = $request->name . ' Customer ' . ' Opening Balance : ' . $request->opening_balance;
                        $voucherTransaction->save();
                    } else if ($request->opening_balance < 0 && $request->date) {
                        $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                        $voucher = new Voucher();
                        $voucher->voucher_no = $newVoucherNo;
                        $voucher->date = $request->date;
                        $voucher->name = $request->name;
                        $voucher->type = 3;
                        $voucher->isApproved = 1;
                        $voucher->generated_at = $request->date;
                        $voucher->total_amount = abs($request->opening_balance);
                        $voucher->person_id = $person_id;
                        $voucher->cheque_no = $request->cheque_no;
                        $voucher->cheque_date = $request->cheque_date;
                        $voucher->is_auto = 1;
                        $voucher->save();
                        $voucher_id = $voucher->id;

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = $coa_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = abs($request->opening_balance);
                        $voucherTransaction->description = $request->name . ' Customer ' . ' Opening Balance : ' . abs($request->opening_balance);
                        $voucherTransaction->save();

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = 1779;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = abs($request->opening_balance);
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $request->name . ' Customer ' . ' Opening Balance : ' . abs($request->opening_balance);
                        $voucherTransaction->save();
                    }
                }
            } elseif ($request->person_type_id == 3) {
                // This is for Manufacture
            }

            return ['status' => "ok", 'message' => 'Person stored successfully'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }




    /**
     * Gettng Person Types.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPersonTypes()
    {
        $personTypes = PersonType::orderBy('type')->get();
        return ['personTypes' => $personTypes];
    }

    /**
     * Gettng Person .
     *
     * @return \Illuminate\Http\Response
     */
    public function getPersonsDropDown(Request $req)
    {
        if ($req->isActive == 0 && $req->isActive != null) {
            $isActive = '00';
        } else if ($req->isActive == 1) {
            $isActive = 1;
        } else {
            $isActive = '';
        }
        $person_type = $req->person_type;
        $persons = Person::where('isActive', 1)
            ->when($isActive, function ($q, $isActive) {
                return $q->where('isActive', $isActive);
            })
            ->when($person_type, function ($q, $person_type) {
                return $q->where('person_type', $person_type);
            })
            ->get();
        return ['persons' => $persons];
    }

    public function activeUnactivePerson(Request $request)
    {
        $rules = array(
            'id' => 'required|int|exists:people,id',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {

            $item = Person::where('id', $request->id)->where('isActive', 1)->first();
            if ($item) {
                $item = Person::find($request->id);
                $item->isActive = 0;
                $item->save();
                return ['status' => "ok", 'message' => 'Person InActive Successfully'];
            } else {
                $item = Person::find($request->id);
                $item->isActive = 1;
                $item->save();

                return ['status' => "ok", 'message' => 'Person Active Successfully'];
            }
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    /**
     * Gettng files by person or mouza.
     *
     * @param \Illuminate\Http\Request account_id
     * @return \Illuminate\Http\Response
     */
    // public function getFilesByPersonOrMouza(Request $req, $id = 0)
    // {
    //     if (isset($req->account_id)) {
    //         $account_id = $req->account_id;
    //     } else {
    //         $account_id = $id;
    //     }
    //     $coaAccount = CoaAccount::find($account_id);
    //     $files = [];
    //     if ($coaAccount) {
    //         //-------------------if loan aortizaion getting loan files--------------------
    //         if ($coaAccount->coa_sub_group_id == 29 || $coaAccount->coa_sub_group_id == 56) {
    //             if ($coaAccount->coa_sub_group_id == 56) {
    //                 $files = LoanAmortization::select('id', 'file_no', 'name as file_name')->get();
    //             } elseif ($coaAccount->coa_sub_group_id == 29) {
    //                 $files = LoanAmortization::where('investor_id', $coaAccount->person_id)->select('id', 'file_no', 'name as file_name')->get();
    //             }
    //         }
    //         //-------------------if block account getting plots--------------------
    //         elseif ($coaAccount->coa_sub_group_id == 20 || $coaAccount->coa_sub_group_id == 48 || $coaAccount->coa_sub_group_id == 49  || $coaAccount->coa_sub_group_id == 59) {
    //             if ($coaAccount->coa_sub_group_id == 59) {
    //                 $files = Plot::where('is_booked', 1)->select('id', 'reg_no as file_name', 'id as file_no')->get();
    //             } else {
    //                 if (($coaAccount->block_id == null || $coaAccount->block_id == 0) && $coaAccount->coa_sub_group_id != 20) {
    //                     $files = Plot::where([['is_booked', 1], ['block_id', null]])->select('id', 'reg_no as file_name', 'id as file_no')->get();
    //                 } else {
    //                     if ($coaAccount->coa_sub_group_id == 20) {
    //                         $block = Block::where('coa_account_id', $coaAccount->id)->first();
    //                         $files = Plot::where([['is_booked', 1], ['block_id', $block->id]])->select('id', 'reg_no as file_name', 'id as file_no')->get();
    //                     } else {
    //                         $files = Plot::where([['is_booked', 1], ['block_id', $coaAccount->block_id]])->select('id', 'reg_no as file_name', 'id as file_no')->get();
    //                     }
    //                 }
    //             }
    //         }
    //         //-------------------if land account land files--------------------
    //         elseif ($coaAccount->coa_sub_group_id == 1 || $coaAccount->coa_sub_group_id == 22) {
    //             if ($coaAccount->type == 'mouza') {
    //                 $getMouza = Mouza::where("coa_account_id", $account_id)->first();
    //                 $mouza_id = optional($getMouza)->id;
    //                 $files = Land::where('mouza_id', $mouza_id)->select('id', 'file_no', 'file_name')->get();
    //             } else {
    //                 if ($coaAccount->person_id != null) {
    //                     $getFiles = LandPerson::where('person_id', $coaAccount->person_id)->with('land')->get('land_id');
    //                     $files = [];
    //                     $i = 0;
    //                     foreach ($getFiles as $file) {
    //                         $files[$i] = $file->land;
    //                         $i++;
    //                     }
    //                     $getFiles = RegistryPerson::where('seller_id', $coaAccount->person_id)->with('land')->get('land_id');

    //                     foreach ($getFiles as $file) {
    //                         $files[$i] = $file->land;
    //                         $i++;
    //                     }
    //                     $files = array_unique($files);
    //                     $files = array_values($files);
    //                 }
    //             }
    //         }
    //     }
    //     return ['files' => $files];
    // }

    /**
     * Getting person all accounts.
     * @param  int  $person_id
     *
     * @return \Illuminate\Http\Response
     */
    public function getPersonAllAccounts(Request $req)
    {
        $rules = array(
            'person_id' => 'required|int'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $person_id = $req->person_id;
        $receiveableAccounts = CoaAccount::with('coaSubGroup', 'balance')
            ->when($person_id, function ($query) use ($person_id) {
                $query->where([['person_id', $person_id]]);
            })
            ->whereHas('coaGroup', function ($query) {
                $query->where('parent', 'Assets');
            })
            ->orderBy('name')->get();
        $payableAccounts = CoaAccount::with('coaSubGroup', 'balance')
            ->when($person_id, function ($query) use ($person_id) {
                $query->where([['person_id', $person_id]]);
            })
            ->whereHas('coaGroup', function ($query) {
                $query->where('parent', 'Liabilities');
            })
            ->orderBy('name')->get();
        return ['receiveableAccounts' => $receiveableAccounts, 'payableAccounts' => $payableAccounts];
    }

    /**
     * Getting persons accounts balance.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPersonCoaAccountsBalance(Request $req)
    {
        $person_id = $req->person_id;
        $people = Person::with('peoplePersonType.personType', 'receiveableBalance', 'payableBalance')
            ->when($person_id, function ($query) use ($person_id) {
                $query->where([['id', $person_id]]);
            })->orderBy('name')
            ->select('name', 'id', 'isActive')
            ->get();
        return ['people' => $people];
    }

    /**
     * @param \Illuminate\Http\Request person_type_id
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getRequiredPersons(Request $req)
    {
        $rules = array(
            'person_type_id' => 'required',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        $person_type_id = $req->person_type_id;
        $persons = Person::whereHas('peoplePersonType', function ($q) use ($person_type_id) {
            return $q->where('person_type_id', '!=', $person_type_id);
        })->where('isActive', '=', 1)
            ->orderBy('name')->get();
        if (!$persons) {
            return ['status' => 'error', 'message' => 'Record not found'];
        }

        return ['persons' => $persons];
    }
    public function getActiveSuppliers(Request $request)
    {
        $persons = Person::with('peoplePersonType')
            ->whereHas('peoplePersonType', fn($q) => $q->where('person_type_id', '=', 2))
            ->where('isActive', 1)
            ->orderBy('name')->get();
        return ['persons' => $persons];
    }
}
