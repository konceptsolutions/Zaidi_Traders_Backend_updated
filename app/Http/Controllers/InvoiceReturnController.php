<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CoaAccount;
use App\Models\Invoice;
use App\Models\InvoiceChild;
use Illuminate\Support\Facades\DB;
use App\Models\InvoiceReturn;
use App\Models\InvoiceChildReturn;
use App\Models\Item;
use App\Models\ItemInventory;
use App\Models\SubCategory;
use App\Models\ReturnPurchaseOrderChild;
use App\Models\Land;
use App\Models\Person;
use App\Models\CoaSubGroup;
use App\Models\PurchaseOrderChild;
use App\Models\Stock;
use App\Models\ItemManufacture;
use App\Models\User;
use App\Models\StrengthUnit;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use Illuminate\Support\Facades\Validator;
use App\Services\CustomErrorMessages;
use Illuminate\Support\Facades\Auth;
use stdClass;

class InvoiceReturnController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $req)
    {
        $rules = array(
            'records'  => 'required|int',
            'pageNo'   => 'required|int',
            'colName'  => 'required|string',
            'sort'     => 'required|string',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            $invoice_no = $req->invoice_no;
            $customer_id = $req->customer_id;
            $walk_in_customer_name = $req->walk_in_customer_name;
            $store_id = $req->store_id;
            $item_id = $req->item_id;
            $invoice_type = $req->isDeleted;
            $from = $req->from_date;
            $to = $req->to_date;
            $invoice_type = $req->invoice_type;
            $direct = 0;
            $salePo = 0;
            $isDeleted = 0;
            $quotation = 0;

            if ($invoice_type == 1) {
                $direct = 1;
            } elseif ($invoice_type == 2) {
                $salePo = 1;
            } elseif ($invoice_type == 3) {
                $quotation = 1;
            } elseif ($invoice_type == 4) {
                $isDeleted = 1;
            }
            $invoices = InvoiceReturn::with('invoice')
                ->when($walk_in_customer_name, function ($query, $walk_in_customer_name) {
                    $query->whereHas('invoice', function ($qu) use ($walk_in_customer_name) {
                        $qu->where('walk_in_customer_name',  'LIKE', '%' . $walk_in_customer_name . '%');
                    });
                })
                //  Original Invoice search
                // ->when($invoice_no, function ($query, $invoice_no) {
                //     $query->whereHas('invoice', function ($qu) use ($invoice_no) {
                //         $qu->where('invoice_no',  'LIKE', '%' . $invoice_no . '%');
                //     });
                // })
                //  Return Invoice search

                ->when($invoice_no, function ($q, $invoice_no) {
                    return  $q->where('ret_invoice_no',  'LIKE', '%' . $invoice_no . '%');
                })
                ->when($customer_id, function ($query, $customer_id) {
                    $query->whereHas('invoice', function ($qu) use ($customer_id) {
                        return $qu->where('customer_id', $customer_id);
                    });
                })
                ->when($quotation > 0, function ($query, $quotation) {
                    $query->whereHas('invoice', function ($qu) use ($quotation) {
                        return $qu->where('quotation_id', '!=', NULL);
                    });
                })
                ->when($salePo > 0, function ($query, $salePo) {
                    $query->whereHas('invoice', function ($qu) use ($salePo) {
                        return $qu->where('po_id', '!=', NULL);
                    });
                })
                ->when($direct > 0, function ($query, $direct) {
                    $query->whereHas('invoice', function ($qu) use ($direct) {
                        return $qu->where(['po_id' => NULL, 'quotation_id' => NULL]);
                    });
                })
                // ->when($quotation > 0, function ($q, $quotation) {
                //     return $q->where('quotation_id', '!=', NULL);
                // })
                // ->when($salePo > 0, function ($q, $salePo) {
                //     return $q->where('po_id', '!=', NULL);
                // })
                // ->when($direct > 0, function ($q, $direct) {
                //     return $q->where(['po_id' => NULL, 'quotation_id' => NULL]);
                // })
                ->when($from, function ($q, $from) {
                    return $q->where('return_date', '>=', $from);
                })
                ->when($to, function ($q, $to) {
                    return $q->where('return_date', '<=', $to);
                })
                ->when($item_id, function ($query, $item_id) {
                    $query->whereHas('invoiceChild', function ($qu) use ($item_id) {
                        $qu->where('item_id', $item_id);
                    });
                })
                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);
            return ['invoices' => $invoices];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // return response()->json($request->all());
        // $user = Auth::guard('api')->user();
        // $userId = $user->id;
        $rules = array(
            'childArray'    => 'required|array',
            'childArray.*.item_id'     => 'required|numeric',
            'childArray.*.quantity'  => 'required|numeric',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            // if ($request->amount_received  + $request->bank_amount_received != $request->total_after_gst && $request->sale_type == 1) {
            //     return ['status' => 'error', 'message' => "Total amount must be received"];
            // }
            $invoice_id = $request->id;
            $check = "";
            foreach ($request->childArray as $row) {
                $itemId = $row['item_id'];
                $invoiceChild = InvoiceChild::where(['invoice_id' => $request->id, 'item_id' => $itemId])->sum('quantity');
                $returninvoiceid = InvoiceReturn::where('invoice_id', $request->id)->get();
                $return_qty = 0;
                $stockQuantity = ItemInventory::getStockQuantity($row['batchDetails']['manufacturer_id'], $itemId, $row['batchDetails']['batchExpiry'], $row['batchDetails']['batch_no']);
                $newQty = 0;
                if ($returninvoiceid) {
                    foreach ($returninvoiceid as $childReturn) {
                        $return_qty = InvoiceChildReturn::where('ret_invoice_id', $childReturn->id)

                            ->where('item_id', $itemId)
                            ->where('expiry_date', $row['batchDetails']['batchExpiry'])
                            ->where('manufacture_id', $row['batchDetails']['manufacturer_id'])
                            ->where('batch_no', $row['batchDetails']['batch_no'])
                            ->value('quantity');
                        $newQty += $return_qty;
                    }
                    $return_qty =  $newQty;
                    if (empty($return_qty)) {
                        $totalReturnQuantity = 0 + $row['quantity'];
                    } else {
                        $totalReturnQuantity = $return_qty + $row['quantity'];
                    }
                } else {
                    $totalReturnQuantity = 0;
                }
                if (!($totalReturnQuantity <= $invoiceChild))
                    $check = "less";
            }
            if ($row['quantity'] > $row['sold_quantity'] - $return_qty) {
                return ['status' => 'error', 'message' => "Return quantity is greater than qty available for return: " . $row['sold_quantity'] - $return_qty];
            }
            // return ['status' => 'error', 'message' => "1Return quantity is greater than qty available for return: " . $row['sold_quantity'] - $return_qty];

            DB::transaction(function () use ($request) {
                $totalCashSaleVoucher = 0;
                $invoice = new InvoiceReturn();
                $invoice->invoice_id   = $request->id;
                $invoice->date   = Land::changeDateFormat($request->date);
                $invoice->return_date   = Land::changeDateFormat($request->return_date);
                $invoice->total_amount    = $request->total_amount;
                $invoice->discount    = $request->discount;
                $invoice->total_after_discount    = $request->total_after_discount;
                $invoice->gst    = $request->gst ?? 0;
                $invoice->gst_percentage    = $request->gst_percentage ?? 0;
                $invoice->total_after_gst    = $request->total_after_gst;
                $invoice->adv_tax    = $request->adv_tax;
                $invoice->adv_tax_percentage    = $request->adv_tax_percentage;
                $invoice->amount_received = $request->amount_received ?? 0;
                $invoice->bank_amount_received = $request->bank_amount_received ?? 0;
                $invoice->account_id   = $request->account_id;
                $invoice->bank_account_id  = $request->bank_account_id;
                $invoice->save();
                $invoice_id = $invoice->id;

                $invoice_number = Invoice::select('invoice_no')->where('id', $request->id)->first();
                $invoice_no = $invoice_number->invoice_no;
                $remarks = " Purchase Order ";
                $supplier_coa_account_id = CoaAccount::where([['person_id', $request->customer_id]])->select('id', 'coa_group_id', 'coa_sub_group_id')->first();
                $customer_id = Person::where('id', $request->customer_id)->value('name');
                $is_post_dated = isset($request->cheque_no) ? 1 : 0;
                $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
                ///// cost jv voucher
                $voucherInventoryCost = new Voucher();
                $voucherInventoryCost->voucher_no = $newVoucherNo;
                $voucherInventoryCost->date = Land::changeDateFormat($request->return_date);
                $voucherInventoryCost->name =  "Return Voucher Inventory Cost Reversed" . ", Invoice no: " . $invoice_no;
                $voucherInventoryCost->return_invoice_id
                    = $invoice_id;
                $voucherInventoryCost->type = 3;
                $voucherInventoryCost->isApproved = 1;
                $voucherInventoryCost->generated_at = date('y-m-d');
                $voucherInventoryCost->total_amount = 0;
                $voucherInventoryCost->cheque_no = $request->cheque_no;
                $voucherInventoryCost->cheque_date = $request->cheque_date;
                $voucherInventoryCost->is_post_dated = $is_post_dated;
                $voucherInventoryCost->is_auto = 1;
                $voucherInventoryCost->save();
                $voucherInvCost_id = $voucherInventoryCost->id;
                $debitside = 0;
                $creditside = 0;
                $totalAvgPrice = 0;
                $price = 0;


                foreach ($request->childArray as $row) {

                    $invoiceChild = new InvoiceChildReturn();
                    $invoiceChild->ret_invoice_id = $invoice_id;
                    $invoiceChild->item_id    = $row['item_id'];
                    $invoiceChild->quantity   = $row['quantity'];
                    $invoiceChild->rate       = $row['rate'];
                    $invoiceChild->cost       = $row['avg_price'];
                    $invoiceChild->sales_tax  = $row['sales_tax'];
                    $invoiceChild->amount     = $row['rate'] * $row['quantity'];
                    $invoiceChild->discount   = $row['item_discount'];
                    $invoiceChild->item_discount_per   = $row['item_discount_per'];
                    $invoiceChild->total_amount = $row['item_total_after_discount'];
                    $invoiceChild->batch_no = $row['batchDetails']['batch_no'];
                    $invoiceChild->manufacture_id = $row['batchDetails']['manufacturer_id'];
                    $invoiceChild->expiry_date = $row['batchDetails']['batchExpiry'];
                    $invoiceChild->save();

                    $invoiceChild_id = $invoiceChild->id;

                    $stockChilddata = new ItemInventory();
                    $stockChilddata->return_invoice_id = $invoiceChild_id;
                    $stockChilddata->inventory_type_id = 4;
                    $stockChilddata->item_id = $row['item_id'];
                    $stockChilddata->batch_no = $row['batchDetails']['batch_no'];
                    $stockChilddata->manufacture_id = $row['batchDetails']['manufacturer_id'];
                    $stockChilddata->expiry_date = $row['batchDetails']['batchExpiry'];
                    $stockChilddata->store_id = $request->store_id;
                    $stockChilddata->quantity_in = $row['quantity'];
                    $stockChilddata->date = $request->date;
                    $stockChilddata->save();

                    $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $row['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();
                    $itemName = $PurchasePrice->item->name;

                    $totalAvgPrice += $row['avg_price'] * $row['quantity'];
                    //   --------------Inventory credit  --------------------

                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherInvCost_id;
                    $voucherTransaction->date = date('y-m-d');
                    $voucherTransaction->coa_account_id = 1;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = $row['avg_price'] * $row['quantity'];
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->description = $row['item_id'] . '-' . $itemName . "Batch NO." . $row['batchDetails']['batch_no'] . " Inventory Returned. "  . '' . "Avg Cost:" . '' .  round($row['avg_price']) . ' ' . "," . ' ' . "Qty" . " " . $row['quantity'] . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $debitside += $row['avg_price'] * $row['quantity'];

                    //   --------------Cost debiting --------------------

                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherInvCost_id;
                    $voucherTransaction->date = date('y-m-d');
                    $voucherTransaction->coa_account_id = 3; //Cost Inventory
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $row['avg_price'] * $row['quantity'];
                    $voucherTransaction->description = $row['item_id'] . '-' . $itemName . "Batch NO." . $row['batchDetails']['batch_no'] . " Inventory Returned. "   . '' . "Avg Cost:" . '' . round($row['avg_price']) . ' ' . "," . ' ' . "Qty" . " " . $row['quantity'] . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $row['avg_price'] * $row['quantity'];
                }

                $updateVoucher = Voucher::find($voucherInvCost_id);
                $updateVoucher->total_amount = $totalAvgPrice;
                $updateVoucher->save();

                //// receipt voucher revenue
                if ($request->sale_type == 1) {
                    $is_post_dated = isset($request->cheque_no) ? 1 : 0;
                    $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
                    $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                    $voucherRevenueGen = new Voucher();
                    $voucherRevenueGen->voucher_no = $newVoucherNo;
                    $voucherRevenueGen->date = Land::changeDateFormat($request->return_date);
                    $voucherRevenueGen->name =  "Revenue Reversed For Return Invoice" . " ,Invoice no: " . $invoice_no;
                    $voucherRevenueGen->return_invoice_id
                        = $invoice_id;
                    $voucherRevenueGen->type = 2;
                    $voucherRevenueGen->isApproved = 1;
                    $voucherRevenueGen->generated_at = Land::changeDateFormat($request->return_date);
                    $voucherRevenueGen->total_amount = $request->total_after_discount;
                    $voucherRevenueGen->cheque_no = $request->cheque_no;
                    $voucherRevenueGen->cheque_date = $request->cheque_date;
                    $voucherRevenueGen->is_post_dated = $is_post_dated;
                    $voucherRevenueGen->is_auto = 1;
                    $voucherRevenueGen->save();
                    $voucherRevenueGenid = $voucherRevenueGen->id;
                    $voucherRevGen_id = $voucherRevenueGen->id;
                    foreach ($request->childArray as $list) {
                        $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $list['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();

                        $itemName = $PurchasePrice->item->name;
                        //---------------------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->return_date);
                        $voucherTransaction->coa_account_id = 50;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $list['rate'] * $list['quantity'];
                        $voucherTransaction->credit =   0;
                        $voucherTransaction->description = $list['item_id'] . '-' . $itemName . "Batch NO." . $list['batchDetails']['batch_no'] . " revenue reversed . "  . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $list['rate'] * $list['quantity'];
                    }
                    $totalCashSaleVoucher += $request->amount_received;
                    if ($request->amount_received > 0) {
                        //---------------------Cash 1 Debit ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->return_date);
                        $voucherTransaction->coa_account_id = $request->account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->amount_received;
                        $voucherTransaction->description = "Invoice no: " . $invoice_no . " Cash Paid ";
                        $voucherTransaction->save();
                        $creditside += $request->amount_received;
                    }
                    if ($request->bank_amount_received > 0) {
                        //---------------------Bank Debit ------------------
                        $totalCashSaleVoucher += $request->bank_amount_received;
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->return_date);
                        $voucherTransaction->coa_account_id = $request->bank_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->bank_amount_received;
                        $voucherTransaction->description = "Invoice no: " . $invoice_no . " Cash Paid By bank";
                        $voucherTransaction->save();
                        $creditside += $request->bank_amount_received;
                    }
                    ///////gst voucher
                    if ($request->gst > 0) {
                        //   $totalCashSaleVoucher += $request->gst;
                        //---------------------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->return_date);
                        $voucherTransaction->coa_account_id = 23;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->gst;
                        $voucherTransaction->credit =   0;
                        $voucherTransaction->description = "Gst Reversed " .  " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $request->gst;
                        //---------------------Cash 1 Debit ------------------
                        // $voucherTransaction = new VoucherTransaction();
                        // $voucherTransaction->voucher_id = $voucherRevGen_id;
                        // $voucherTransaction->date = date('y-m-d');
                        // $voucherTransaction->coa_account_id = $request->account_id;
                        // $voucherTransaction->is_approved = 1;
                        // $voucherTransaction->debit = $request->gst;
                        // $voucherTransaction->credit = 0;
                        // $voucherTransaction->description = "Gst " . " ,Invoice no: " . $invoice_no;
                        // $voucherTransaction->save();
                    }
                    if ($request->adv_tax > 0) {
                            //------ Advance Sale Tax  ------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherRevGen_id;
                            $voucherTransaction->date = Land::changeDateFormat($request->return_date);
                            $voucherTransaction->coa_account_id = 871;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $request->adv_tax;
                            $voucherTransaction->credit = 0;
                            $voucherTransaction->description = "Advance Sale Tax  " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $request->adv_tax;

                    }
                    foreach ($request->childArray as $list) {
                        if ($list['item_discount'] > 0) {
                            $totalCashSaleVoucher += $list['item_discount'];

                            //---------------------Debiting discount Expense   ------------------

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherRevGen_id;
                            $voucherTransaction->date = Land::changeDateFormat($request->return_date);
                            $voucherTransaction->coa_account_id = 49;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit = $list['item_discount'];
                            $voucherTransaction->description =  $list['item_discount_per'] . '%' . ' ' . 'Deduction' . '' .  ",Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $creditside += $list['item_discount'];
                        }
                        $voucher = Voucher::find($voucherRevenueGenid);
                        $voucher->total_amount = $totalCashSaleVoucher;
                        $voucher->save();
                    }
                } else {

                    $customer_account = CoaAccount::where('person_id', $request->customer_id)
                        ->where('coa_sub_group_id', 9)->first();
                    $customer_account_id = $customer_account->id;

                    foreach ($request->childArray as $list) {
                        $price += $list['rate'] * $list['quantity'];
                        $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $list['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();

                        $itemName = $PurchasePrice->item->name;
                        //---------------------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->return_date);
                        $voucherTransaction->coa_account_id = 50;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $list['rate'] * $list['quantity'];
                        $voucherTransaction->credit =   0;
                        $voucherTransaction->description = $list['item_id'] . '-' . $itemName . "Batch NO." . $list['batchDetails']['batch_no'] . " Returned . "  . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $list['rate'] * $list['quantity'];

                        //---------------------Cash 1 Debit ------------------

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $list['rate'] * $list['quantity'];
                        $voucherTransaction->description = $list['item_id'] . '-' . $itemName . "Batch NO." . $list['batchDetails']['batch_no'] . " Returned . "  . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $list['rate'] * $list['quantity'];

                        if ($list['item_discount'] > 0) {
                            // $totalCreditSaleVoucher += $request->discount;
                            //---------------------Crediting Customer Account Deu To discount ------------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherInvCost_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = $customer_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $list['item_discount'];
                            $voucherTransaction->credit =  0;
                            $voucherTransaction->description = $list['item_discount_per'] . '%' . ' ' . 'Deduction' . '' . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $list['item_discount'];

                            //---------------------Debiting discount Expense   ------------------

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherInvCost_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = 28;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit = $list['item_discount'];
                            $voucherTransaction->description = $list['item_discount_per'] . '%' . ' ' . 'Deduction' . '' . $invoice_no;
                            $voucherTransaction->save();
                            $creditside += $list['item_discount'];
                        }
                    }

                    if (($request->amount_received) + ($request->bank_amount_received) > 0) {
                        $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
                        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                        $voucherRevenueGen = new Voucher();
                        $voucherRevenueGen->voucher_no = $newVoucherNo;
                        $voucherRevenueGen->date = Land::changeDateFormat($request->return_date);
                        $voucherRevenueGen->name =  "Return  Invoice no: " . $invoice_no . "Amount Returned";
                        $voucherRevenueGen->return_invoice_id
                            = $invoice_id;
                        $voucherRevenueGen->type = 2;
                        $voucherRevenueGen->isApproved = 1;
                        $voucherRevenueGen->generated_at = date('y-m-d');
                        $voucherRevenueGen->total_amount = $request->amount_received + $request->bank_amount_received;
                        $voucherRevenueGen->cheque_no = $request->cheque_no;
                        $voucherRevenueGen->cheque_date = $request->cheque_date;
                        $voucherRevenueGen->is_post_dated = $is_post_dated;
                        $voucherRevenueGen->is_auto = 1;
                        $voucherRevenueGen->save();
                        $voucherRevGen_id = $voucherRevenueGen->id;
                        if ($request->amount_received > 0) {
                            // $totalCashSaleVoucher += $request->amount_received;


                            //---------------------Crediting Reneve ------------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherRevGen_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = $customer_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $request->amount_received;
                            $voucherTransaction->credit =   0;
                            $voucherTransaction->description = "Amount Paid against Returned " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $request->amount_received;

                            //---------------------Cash 1 Debit ------------------

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherRevGen_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = $request->account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit = $request->amount_received;
                            $voucherTransaction->description = $list['item_id'] . '-' . $itemName . "Batch NO." . $list['batchDetails']['batch_no'] . " " . " Return . "  . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $creditside += $request->amount_received;
                        }
                        ///bank
                        if ($request->bank_amount_received > 0) {



                            //---------------------Crediting Reneve ------------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherRevGen_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = $customer_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $request->bank_amount_received;
                            $voucherTransaction->credit =   0;
                            $voucherTransaction->description = "Amount Paid against " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $request->bank_amount_received;

                            //---------------------Cash 1 Debit ------------------

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherRevGen_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = $request->bank_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit = $request->bank_amount_received;
                            $voucherTransaction->description = $list['item_id'] . '-' . $itemName . "Batch NO." . $list['batchDetails']['batch_no'] . " " . " Return . "  . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $creditside += $request->bank_amount_received;
                        }
                    }
                    ///////gst voucher
                    if ($request->gst > 0) {
                        // $totalCreditSaleVoucher += $request->gst;

                        //---------------------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = 23;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->gst;
                        $voucherTransaction->credit =   0;
                        $voucherTransaction->description = "Gst Reversed" .  " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $request->gst;
                        //---------------------Cash 1 Debit ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->gst;
                        $voucherTransaction->description = "Gst Reversed" .  " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $request->gst;
                    }
                    if ($request->adv_tax > 0) {
                            // $totalCreditSaleVoucher += $request->gst;

                            //---------------------Crediting Reneve ------------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherInvCost_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = 871;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $request->adv_tax;
                            $voucherTransaction->credit =   0;
                            $voucherTransaction->description = "Advance Sale Tax " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $creditside += $request->adv_tax;
                            //---------------------Cash 1 Debit ------------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherInvCost_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = $customer_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit = $request->adv_tax;
                            $voucherTransaction->description = "Advance Sale Tax " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $request->adv_tax;
                        }
                }
                if ($creditside == $debitside) {

                    $voucher = Voucher::find($voucherInvCost_id);
                    $voucher->total_amount = $debitside;
                    $voucher->save();
                } else {
                    throw new \Exception('debit and credit sides are not equal ' .$creditside ." = ". $debitside);
                }
            });

            return ['status' => "ok", 'message' => 'Invoice Stored Successfully'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $req)
    {
        $rules = array(
            'id' => 'required|int|exists:invoices_return,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $invoices = InvoiceReturn::with('invoice', 'invoiceChild')->where('id', $req->id)->first();

        return ['invoices' => $invoices];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $req)
    {
        $rules = array(
            'id' => 'required|int|exists:invoices,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        // try {
        $parentData = Invoice::with('customer', 'store')->where(['id' => $req->id])->first();
        //  return $parentData->shop;
        if ($parentData->is_approved == 1) {
            return ['status' => "error", 'message' => "Approved Invoice Con't Edit"];
        } else {
            $childData = InvoiceChild::with('item')->where('invoice_id', $req->id)->get();

            $childDataList = [];

            for ($i = 0; $i < count($childData); $i++) {
                $batchNumber = ItemInventory::select('batch_no')->where('item_id', $childData[$i]->item_id)->get();

                $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $childData[$i]->item_id)->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();

                $batchOptions = [];
                $selectedbatchOptions = [];

                $selectedBatch = ItemInventory::with('manufacture', 'item')->where('invoice_id', $childData[$i]->id)->orderBy('id')->first();
                $batchOptions[] = array(
                    'id' => $selectedBatch->id . $selectedBatch->batch_no,
                    'value' => $selectedBatch->manufacture_id . $selectedBatch->batch_no,
                    'label' => $selectedBatch->manufacture_id . '-' . $selectedBatch->manufacture->name . '-' . $selectedBatch->batch_no . '-' . $selectedBatch->item->itemAvaiableInventory->item_available,
                    'batchQty' => $selectedBatch->item->itemAvaiableInventory->item_available,
                    'batchExpiry' => $selectedBatch->expiry_date,
                    'manufacturer_id' => $selectedBatch->manufacture->id,
                    'batch_no' => $selectedBatch->batch_no,


                );
                // $stockQuantity = ItemInventory::getStockQuantity($selectedBatch->manufacture->id, $selectedBatch->item_id, $selectedBatch->expiry_date, $selectedBatch->batch_no);
                $batchDetails = new stdClass;
                $batchDetails->batchExpiry = $selectedBatch->expiry_date;
                $batchDetails->batchQty = $selectedBatch->item->itemAvaiableInventory->item_available;
                $batchDetails->batch_no = $selectedBatch->batch_no;
                $batchDetails->manufacturer_id = $selectedBatch->manufacture_id;

                $selectedBatch = $selectedBatch->id . $selectedBatch->batch_no;

                // $batchDetails = array(
                //     'batchExpiry' => $selectedBatch->expiry_date,
                // );

                $itemsInv = Item::with('itemAvaiableInventory')->where('id', $childData[$i]->item_id)->first();

                if ($itemsInv->itemAvaiableInventory) {

                    $qty_available = $itemsInv->itemAvaiableInventory->item_available;
                } else {
                    $qty_available =  0;
                }
                $invoiceId = $childData[$i]->invoice_id;
                $itemId = $childData[$i]->item_id;
                $selectedBatch = ItemInventory::with('manufacture', 'item')->where('invoice_id', $childData[$i]->id)->orderBy('id')->first();
                $newQty = 0;
                $returninvoiceid = InvoiceReturn::where('invoice_id', $invoiceId)->get();
                if ($returninvoiceid) {
                    foreach ($returninvoiceid as $childReturn) {
                        $return_qty = InvoiceChildReturn::where('ret_invoice_id', $childReturn->id)
                            ->where('item_id', $itemId)
                            ->where('expiry_date', $selectedBatch->expiry_date)
                            ->where('manufacture_id', $selectedBatch->manufacture->id)
                            ->where('batch_no', $selectedBatch->batch_no)
                            ->value('quantity');
                        $newQty += $return_qty;
                    }
                    $return_qty =  $newQty;


                    if (empty($return_qty)) {
                        $totalReturnQuantity = 0;
                    } else {
                        $totalReturnQuantity = $return_qty;
                    }
                } else {
                    $totalReturnQuantity = 0;
                }
                $selectedBatch2 = new stdClass;
                $selectedBatch2->id = $selectedBatch->id . $selectedBatch->batch_no;
                $selectedBatch2->value =  $selectedBatch->manufacture_id . $selectedBatch->batch_no;
                $selectedBatch2->label = $selectedBatch->manufacture_id . '-' . $selectedBatch->manufacture->name . '-' . $selectedBatch->batch_no . '-' . $selectedBatch->item->itemAvaiableInventory->item_available;
                $selectedBatch2->batchQty = $selectedBatch->item->itemAvaiableInventory->item_available;
                $selectedBatch2->batchExpiry = $selectedBatch->expiry_date;
                $selectedBatch2->manufacturer_id = $selectedBatch->manufacture->id;
                $selectedBatch2->batch_no = $selectedBatch->batch_no;




                $childDataList[$i] = array(

                    "id" => $childData[$i]->id,
                    "invoice_id" => $childData[$i]->invoice_id,
                    "item_name" => $childData[$i]->item->name ?? '',
                    "item_id" => $childData[$i]->item_id,
                    "quantity" => $childData[$i]->quantity,
                    "batch_no" => $childData[$i]->batch_no,
                    "expiry_date" => $childData[$i]->expiry_date,
                    "rate" => $childData[$i]->rate,
                    "sales_tax" => $childData[$i]->sales_tax,
                    "amount" => $childData[$i]->amount,
                    "avg_price" => $childData[$i]->cost,
                    "discount" => $childData[$i]->discount,
                    "item_discount_per" => $childData[$i]->item_discount_per,
                    "sold_quantity" => $childData[$i]->quantity,
                    "total_amount" => $childData[$i]->total_amount,
                    "qty_available" => $qty_available,
                    "batchNumber" => $batchNumber,
                    'batchOptions' => $batchOptions,
                    'selectedBatch' => $selectedBatch2,
                    'batchDetails' => $batchDetails,
                    'returned_qty' => $totalReturnQuantity,


                );
            }
            $form = array(
                "id" => $parentData->id,
                "customer_id" => $parentData->customer_id ?? '',
                "sales_rep_id" => $parentData->sales_rep_id ?? '',
                "walk_in_customer_name" => $parentData->walk_in_customer_name ?? '',
                "walk_in_customer_phone" => $parentData->walk_in_customer_phone ?? '',
                "invoice_no" => $parentData->invoice_no,
                "sale_type" => $parentData->sale_type,
                "tax_type" => $parentData->tax_type,
                "gst" => $parentData->gst,
                "gst_percentage" => $parentData->gst_percentage,
                "total_after_gst" => $parentData->total_after_gst,
                "adv_tax_percentage" => $parentData->adv_tax_percentage,
                "adv_tax" => $parentData->adv_tax,
                "date" => $parentData->date,
                "remarks" => $parentData->remarks,
                "total_amount" => $parentData->total_amount,
                "amount_received" => $parentData->amount_received,
                "bank_amount_received" => $parentData->bank_amount_received,
                "account_id" => $parentData->account_id,
                "bank_account_id" => $parentData->bank_account_id,
                "store" => $parentData->store->name ?? '',
                "store_id" => $parentData->store_id,
                "quotation_id" => $parentData->quotation_id,
                "childArray" => $childDataList,
            );
            return ['status' => 'ok', 'data' => $form];
        }

        // } catch (\Exception $e) {
        //     return ['status' => 'error', 'message' => $e->getMessage()];
        // }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {

        $rules = array(
            'id' => 'required|int|exists:invoices_return,id',
        );

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {


            DB::transaction(function () use ($request) {
                $invno = $request->id;
                $ItemInventory = InvoiceChildReturn::where('ret_invoice_id', $invno)->select('id')->get();

                if ($ItemInventory) {
                    foreach ($ItemInventory as  $ItemInventory) {
                        ItemInventory::where('return_invoice_id', $ItemInventory->id)->delete();
                    }
                }
            });
            InvoiceChildReturn::where(['ret_invoice_id' => $request->id])->delete();
            $parentDelete = InvoiceReturn::where(['id' => $request->id])->delete();

            $voucher = Voucher::where('return_invoice_id', $request->id)->select('id')->get();

            if (count($voucher) > 0) {

                foreach ($voucher as  $voucher) {
                    Voucher::where('id', $voucher->id)->delete();
                    VoucherTransaction::where('voucher_id', $voucher->id)->delete();
                }
            } else {
                throw new \Exception('Voucher Not Found');
            }


            //  $ItemInventory = ItemInventory::with('invoicechild')->whereHas('invoicechild', function ($query) use ($invno) {
            //     $query->where('invoice_id', $invno);
            // })->get();


            return ['status' => 'ok', 'message' => 'Invoice Deleted Successfully'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function testapi(Request $request)
    {

        try {
            DB::transaction(function () use ($request) {

                foreach ($request->data as $data) {
                    $item = new Item();
                    $item->category_id = 1;
                    $item->subcategory_id = $data['Manufacturer ID'];
                    $item->name = $data['Product name'];
                    $item->rate = 1;
                    $item->type = 1;
                    if ($data['Strength_value'] && $data['Strength_unit ID'] && $data['Strength_unit ID'] > 0) {
                        $item->strength = $data['Strength_value'];

                        $item->strength_unit_id = $data['Strength_unit ID'];
                    }
                    $item->nomenclature = $data['Nomenclature'];
                    $item->minimumlevel = 10;
                    $item->unit_id      = 1;
                    $item->pack      = $data['Pack_value'] ?? 1;
                    $item->save();
                    $item_id = $item->id;

                    $manufacture = new ItemManufacture();
                    $manufacture->item_id = $item_id;
                    $manufacture->manufacture_id = $data['Manufacturer ID'];
                    $manufacture->save();
                }
            });

            return ['status' => "ok", 'message' => 'done'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function testapi3(Request $request)
    {
        $itemId = 18998; // Set the desired item ID
        $averagePrice = PurchaseOrderChild::getStoredAveragePrice($itemId);
        return $averagePrice;
    }
    public function testapi2(Request $request)
    {

        try {
            DB::transaction(function () use ($request) {

                // // DB::transaction(function () use ($request) {
                // foreach ($request->Manufacturer as $Manufacturer) {
                //     $subcategory = new SubCategory();


                //     $subcategory->name = $Manufacturer['Manufacturer'];;
                //     $subcategory->category_id = 1;

                //     $subcategory->save();
                // }
                $persons = Person::where('person_type', 2)->get();
                // return $persons;

                foreach ($persons as $Manufacturer) {
                    $person_id = $Manufacturer->id;
                    if ($person_id > 2002 && $person_id != 2200) {
                        $subgroup = CoaSubGroup::find(2);
                        $lastCode = CoaAccount::where('coa_sub_group_id', $subgroup->id)->orderBy('id', 'desc')->first();
                        if (!$lastCode) {
                            $newCode = $subgroup->code . '001';
                        } else {
                            $newCode = $lastCode->code + 1;
                        }
                        $coaAccount = new CoaAccount();
                        $coaAccount->name     = $Manufacturer['name'];
                        $coaAccount->code     = $newCode;
                        $coaAccount->coa_group_id     = 3;
                        $coaAccount->coa_sub_group_id     = 2;
                        $coaAccount->person_id     = $person_id;
                        $coaAccount->description     = $Manufacturer['name'];
                        $coaAccount->isDefault     = 1;
                        $coaAccount->save();
                    }
                }


                // foreach ($request->Strength_unit as $Strength_unit) {

                //     $StrengthUnit = new StrengthUnit();
                //     $StrengthUnit->name = $Strength_unit['Strength_unit'];
                //     $StrengthUnit->description = $Strength_unit['Strength_unit'];
                //     $StrengthUnit->save();
                // }
                // $subcategory = new SubCategory();
                // $subcategory->name = $request->name;
                // $subcategory->category_id = $request->category_id;

                // $subcategory->save();
            });

            return ['status' => "ok", 'message' => 'done'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
