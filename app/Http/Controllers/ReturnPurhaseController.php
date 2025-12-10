<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CoaAccount;
use App\Models\Item;
use App\Models\ItemInventory;
use App\Models\ItemManufacture;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderChild;
use App\Models\ReturnPurchaseOrder;
use App\Models\ReturnPurchaseOrderChild;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use App\Services\CustomErrorMessages;

class ReturnPurhaseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $req)
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
        try {
            $supplier_id = $req->supplier_id;
            $po_no = $req->po_no;
            $store_id = $req->store_id;
            $store_type_id = $req->store_type_id;
            $received_from = $req->receive_from;
            $received_to = $req->receive_to;
            $from = $req->from_return_date;
            $po_type = $req->po_type;
            $to = $req->to_return_date;
            $searcField = $req->searcField;

            $purchaseorderlist = ReturnPurchaseOrder::with('purchaseorder')
                ->when($supplier_id, function ($query, $supplier_id) {
                    $query->whereHas('purchaseorder', function ($qu) use ($supplier_id) {
                        return $qu->where('person_id', $supplier_id);
                    });
                })
                ->when($po_no, function ($query, $po_no) {
                    $query->whereHas('purchaseorder', function ($qu) use ($po_no) {
                        return $qu->where('po_no', $po_no);
                    });
                })
                ->when($po_type, function ($query, $po_type) {
                    $query->whereHas('purchaseorder', function ($qu) use ($po_type) {
                        return $qu->where('po_type', $po_type);
                    });
                })

                ->when($store_id, function ($q, $store_id) {
                    return $q->where('store_id', $store_id);
                })

                ->when($store_type_id, function ($query) use ($store_type_id) {
                    $query->whereHas('store', fn ($q) => $q->where('store_type_id', '=', $store_type_id));
                })
                ->when($from, function ($q, $from) {
                    return $q->where('return_date', '>=', $from);
                })
                ->when($to, function ($q, $to) {
                    return $q->where('return_date', '<=', $to);
                })
                ->when($received_from, function ($q, $received_from) {
                    return $q->where('receive_date', '>=', $received_from);
                })
                ->when($received_from, function ($query, $received_from) {
                    $query->whereHas('purchaseorder', function ($qu) use ($received_from) {
                        return $qu->where('receive_date', '>=', $received_from);
                    });
                })
                ->when($received_to, function ($query, $received_to) {
                    $query->whereHas('purchaseorder', function ($qu) use ($received_to) {
                        return $qu->where('receive_date', '<=', $received_to);
                    });
                })

                ->when($searcField, function ($query, $searcField) {
                    $query->whereHas('invoice', function ($qu) use ($searcField) {
                        $qu->where('remarks',  'LIKE', '%' . $searcField . '%');
                    });
                })
                // ->get();

                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);


            return ['purchaseorderlist' => $purchaseorderlist];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
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

        $rules = array(
            // 'name'     => 'string',
            'po_type'     => 'required|numeric',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            foreach ($request->childArray as $row) {
                $itemId = $row['item_id'];

                $newQty = 0;
                // checking if qty is not greater than qty available for return
                $returnpoid = ReturnPurchaseOrder::where('po_id', $request->id)->get();
                if ($returnpoid) {
                    foreach ($returnpoid as $childReturn) {
                        $return_qty = ReturnPurchaseOrderChild::where('ret_purchase_order_id', $childReturn->id)

                            ->where('item_id', $itemId)
                            ->where('batch_no', $row['batch_no'])
                            ->where('manufacturer_id', $row['manufacturer_id'])
                            ->where('expiry_date', $row['expiry_date'])
                            ->groupBy('item_id', 'batch_no', 'manufacturer_id', 'expiry_date')
                            ->sum('quantity');
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
                // checking if qty is not greater than qty available for return
                if ($row['quantity'] > $row['purchased_qty'] - $totalReturnQuantity) {
                    return ['status' => 'error', 'message' => "Return quantity is greater than qty available for return: " . $row['purchased_qty'] - $totalReturnQuantity];
                }
                // End
                // checking if qty is not greater than stock available

                $stockQuantity = ItemInventory::getStockQuantity($row['manufacturer_id'], $itemId, $row['expiry_date'], $row['batch_no']);
                if ($row['quantity'] > $stockQuantity) {
                    return ['status' => 'error', 'message' => "Return quantity is greate than available stock.Available stock: " . $stockQuantity];
                }
            }
            DB::transaction(function () use ($request) {

                $purchaseorder = new ReturnPurchaseOrder();
                $purchaseorder->po_id    = $request->id;
                $purchaseorder->return_date    = $request->return_date;
                $purchaseorder->total       = $request->total;
                $purchaseorder->discount    = $request->discount;
                $purchaseorder->tax           = $request->tax;
                $purchaseorder->total_after_tax = $request->total_after_tax;
                $purchaseorder->tax_in_figure = $request->tax_in_figure;
                $purchaseorder->adv_tax_percentage = $request->adv_tax_percentage;
                $purchaseorder->adv_tax = $request->adv_tax;
                $purchaseorder->total_after_discount = $request->total_after_discount;
                $purchaseorder->save();
                $purchase_id = $purchaseorder->id;
                $purchaseOrderNumber = PurchaseOrder::find($request->id);
                $purchaseOrderNumber = $purchaseOrderNumber->po_no;
                foreach ($request->childArray as $row) {

                    $purchaseChilddata = new ReturnPurchaseOrderChild();
                    $purchaseChilddata->ret_purchase_order_id = $purchase_id;
                    $purchaseChilddata->item_id = $row['item_id'];
                    $purchaseChilddata->received_quantity = $row['received_quantity'];
                    $purchaseChilddata->batch_no = $row['batch_no'];
                    $purchaseChilddata->manufacturer_id = $row['manufacturer_id'];
                    $purchaseChilddata->expiry_date = $row['expiry_date'];
                    $purchaseChilddata->quantity = $row['quantity'];
                    $purchaseChilddata->pack = $row['pack'];
                    $purchaseChilddata->rate = $row['rate'];
                    $purchaseChilddata->total = $row['amount'];
                    // $purchaseChilddata->remarks = $row['remarks'];
                    $purchaseChilddata->save();
                    $purchaseChild_id = $purchaseChilddata->id;

                    $itemUpdate =  Item::find($row['item_id']);
                    $itemUpdate->pack = $row['pack'];
                    $itemUpdate->rate = $row['rate'];
                    $itemUpdate->save();

                    $itemInventory = new ItemInventory();
                    $itemInventory->return_po_id = $purchaseChild_id;
                    $itemInventory->store_id = $request->store_id;

                    $itemInventory->batch_no = $row['batch_no'];
                    $itemInventory->item_id = $row['item_id'];
                    $itemInventory->manufacture_id = $row['manufacturer_id'];
                    $itemInventory->expiry_date = $row['expiry_date'];
                    $itemInventory->inventory_type_id = 5;
                    $itemInventory->quantity_out = $row['quantity'];
                    $itemInventory->purchase_price = $row['rate'];
                    $itemInventory->date = date('Y-m-d');
                    $itemInventory->save();
                }

                if ($request->po_type == 3) {

                    $this->simpleSupplierVoucher($request, $purchase_id, $purchaseOrderNumber);
                } else {
                    $this->registerSupplierVoucher($request, $purchase_id, $purchaseOrderNumber);
                }
            });

            return ['status' => "ok", 'message' => 'Return Purchase Order successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
    private function registerSupplierVoucher($request, $purchase_id, $purchaseOrderNumber)
    {
        $debitside = 0;
        $creditside = 0;
        $remarks = "Returned PO No: ";
        $supplier_coa_account_id = CoaAccount::where([['person_id', $request->supplier_id]])->value('id');
        $supplier_name = Person::where('id', $request->supplier_id)->value('name');
        $is_post_dated = isset($request->cheque_no) ? 1 : 0;
        $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

        $voucher = new Voucher();
        $voucher->voucher_no = $newVoucherNo;
        $voucher->date = date('y-m-d');
        $voucher->name =  "Returned PO No: " . $purchaseOrderNumber . '';
        $voucher->type = 3;
        $voucher->isApproved = 1;
        $voucher->generated_at = date('y-m-d');
        $voucher->total_amount = 0;
        $voucher->return_po_id = $purchase_id;
        $voucher->cheque_no = $request->cheque_no;
        $voucher->cheque_date = $request->cheque_date;
        $voucher->is_post_dated = $is_post_dated;
        $voucher->is_auto = 1;
        $voucher->save();
        $voucher_id = $voucher->id;
        $debitside = 0;
        $creditside = 0;
        //---------------------Debit Inventory  account ------------------
        foreach ($request->childArray as $row) {
            $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $row['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();
            $itemName = $PurchasePrice->item;

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = date('y-m-d');
            $voucherTransaction->coa_account_id = 1;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $row['amount'] + $row['cost'];
            $voucherTransaction->description = $remarks  . $purchaseOrderNumber . ', Item: ' . $itemName->id . '-' . $itemName->name . " Inventory Returned to Supplier. " . ' Pack size: ' . $row['pack'] . ', Qty:' . $row['received_quantity'] . ', Total Qty:' . $row['received_quantity'] . ', Rate: ' . $row['purchase_price'] . ', Batch No: ' . $row['batch_no'];

            $voucherTransaction->save();
            $creditside += $row['amount'] + $row['cost'];
        }
        //   --------------Crediting Supplier account --------------------
        $suppliername = CoaAccount::where('id', $supplier_coa_account_id)->value('name');
        $voucherTransaction = new VoucherTransaction();
        $voucherTransaction->voucher_id = $voucher_id;
        $voucherTransaction->date = date('y-m-d');
        $voucherTransaction->coa_account_id = $supplier_coa_account_id;
        $voucherTransaction->credit = 0;
        $voucherTransaction->debit = $request->total;
        $voucherTransaction->is_approved = 1;
        $voucherTransaction->description = $remarks  . $purchaseOrderNumber .  '  '  . $suppliername . " Amount Adjusted";
        $voucherTransaction->save();
        $debitside += $request->total;


        if ($request->discount > 0) {
            $sumAmountofdiscount = 0;
            //---------------------Crediting cost invenotry discount ------------------
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = date('y-m-d');
            $voucherTransaction->coa_account_id = 383;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = $request->discount;
            $voucherTransaction->credit =  0;
            $voucherTransaction->description = 'Returned PO No: ' . $purchaseOrderNumber . " Deduction";
            $voucherTransaction->save();
            $debitside += $request->discount;
            //---------------------Debiting supplier discount   ------------------

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = date('y-m-d');
            $voucherTransaction->coa_account_id = $supplier_coa_account_id;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $request->discount;
            $voucherTransaction->description = 'Returned PO No: ' . $purchaseOrderNumber . " Deduction Adjusted";
            $voucherTransaction->save();
            $sumAmountofdiscount += $request->discount;
            $creditside += $request->discount;
        }

        if ($request->tax_in_figure > 0) {
            //---------------------Crediting purchase tax payable  tax ------------------
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = date('y-m-d');
            $voucherTransaction->coa_account_id = 31;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = $request->tax_in_figure;
            $voucherTransaction->credit =  0;
            $voucherTransaction->description = 'Returned PO No: ' . $purchaseOrderNumber . " Tax Payable Adjusted";
            $voucherTransaction->save();
            $debitside += $request->tax_in_figure;
            //---------------------Debiting purchase tax expenses    ------------------

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = date('y-m-d');
            $voucherTransaction->coa_account_id = 30;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $request->tax_in_figure;
            $voucherTransaction->description = 'Returned PO No: ' . $purchaseOrderNumber . " Tax expense Reversed";
            $voucherTransaction->save();
            $creditside += $request->tax_in_figure;
        }
        if ($request->amount_received + $request->bank_amount_received > 0) {
            $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

            $voucherRevenueGen = new Voucher();
            $voucherRevenueGen->voucher_no = $newVoucherNo;
            $voucherRevenueGen->date = date('y-m-d');
            $voucherRevenueGen->name =  "Returned PO No: " . $purchaseOrderNumber . ', Amount Received';

            $voucherRevenueGen->type = 2;
            $voucherRevenueGen->return_po_id = $purchase_id;
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


                //---------------------Crediting Reneve ------------------
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherRevGen_id;
                $voucherTransaction->date = date('y-m-d');
                $voucherTransaction->coa_account_id = $supplier_coa_account_id;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = 0;
                $voucherTransaction->credit =   $request->amount_received;
                $voucherTransaction->description = 'Returned PO No: ' . $purchaseOrderNumber . " Amount Received (Cash) ";
                $voucherTransaction->save();
                $creditside += $request->amount_received;

                //---------------------Cash 1 Debit ------------------

                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherRevGen_id;
                $voucherTransaction->date = date('y-m-d');
                $voucherTransaction->coa_account_id = $request->account_id;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = $request->amount_received;
                $voucherTransaction->credit = 0;
                $voucherTransaction->description = 'Returned PO No: ' . $purchaseOrderNumber . " Amount Received (Cash) ";
                $voucherTransaction->save();
                $debitside += $request->amount_received;
            }
            ///bank
            if ($request->bank_amount_received > 0) {

                //---------------------Crediting Reneve ------------------
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherRevGen_id;
                $voucherTransaction->date = date('y-m-d');
                $voucherTransaction->coa_account_id = $supplier_coa_account_id;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = 0;
                $voucherTransaction->credit =  $request->bank_amount_received;
                $voucherTransaction->description = 'Returned PO No: ' . $purchaseOrderNumber . " Amount Received (Bank) ";
                $voucherTransaction->save();
                $creditside += $request->bank_amount_received;

                //---------------------Cash 1 Debit ------------------

                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherRevGen_id;
                $voucherTransaction->date = date('y-m-d');
                $voucherTransaction->coa_account_id = $request->bank_account_id;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = $request->bank_amount_received;
                $voucherTransaction->credit = 0;
                $voucherTransaction->description = 'Returned PO No: ' . $purchaseOrderNumber . " Amount Received (Bank) ";
                $voucherTransaction->save();
                $debitside += $request->bank_amount_received;
            }
        }
        if($request->adv_tax > 0)
        {
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $request->receive_date;
            $voucherTransaction->coa_account_id = 873;
            $voucherTransaction->debit = $request->adv_tax;
            $voucherTransaction->credit = 0;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description =  "Advance Purchase Tax " . 'PO no: ' . $request->po_no;
            $voucherTransaction->save();
            $debitside += $request->adv_tax;

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $request->receive_date;
            $voucherTransaction->coa_account_id = 872;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $request->adv_tax;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description =  "Advance Purchase Tax " . 'PO no: ' . $request->po_no;
            $voucherTransaction->save();
            $creditside += $request->adv_tax;
        }

        if ($creditside != $debitside) {
            throw new \Exception('debit and credit sides are not equal '.$creditside ." = ". $debitside );
        } else {
            $updateVoucher = Voucher::find($voucher_id);
            $updateVoucher->total_amount = $debitside;
            $updateVoucher->save();
        }
    }

    private function simpleSupplierVoucher($request, $purchase_id, $purchaseOrderNumber)
    {
        $debitside = 0;
        $creditside = 0;

        $remarks = " Purchase Order ";
        $is_post_dated = isset($request->cheque_no) ? 1 : 0;
        $getVoucherNo = DB::table('vouchers')->where('type', 1)->orderBy('id', 'desc')->first();
        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
        $voucher = new Voucher();
        $voucher->voucher_no = $newVoucherNo;
        $voucher->date = date('y-m-d');
        $voucher->name =  "Returned PO no: " . $request->po_no;
        $voucher->type = 2;
        $voucher->generated_at = date('y-m-d');
        $voucher->total_amount = 0;
        $voucher->return_po_id = $purchase_id;
        $voucher->isApproved = 1;
        $voucher->cheque_no = $request->cheque_no;
        $voucher->cheque_date = $request->cheque_date;
        $voucher->is_post_dated = $is_post_dated;
        $voucher->is_auto = 1;
        $voucher->save();
        $voucher_id = $voucher->id;

        //   --------------debiting Supplier account --------------------
        foreach ($request->childArray as $row) {
            $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $row['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();
            $itemName = $PurchasePrice->item;

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = date('y-m-d');
            $voucherTransaction->coa_account_id = 1;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $row['amount'];
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = $remarks  . $purchaseOrderNumber . ', Item: ' . $itemName->id . '-' . $itemName->name . " Inventory Returned to Supplier. " . ' Pack size: ' . $row['pack'] . ', Qty:' . $row['received_quantity'] . ', Total Qty:' . $row['received_quantity']  . ', Rate: ' . $row['rate'] . ', Batch No: ' . $row['batch_no'];
            $voucherTransaction->save();

            $creditside += $row['amount'];
        }
        //---------------------Crediting cash account ------------------
        if ($request->amount_received + $request->bank_amount_received > 0) {

            if ($request->amount_received) {
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = date('y-m-d');
                $voucherTransaction->coa_account_id = $request->account_id;
                $voucherTransaction->debit = $request->amount_received;
                $voucherTransaction->credit = 0;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->description = 'PO No: ' . $purchaseOrderNumber . " Amount Received (Cash) ";
                $voucherTransaction->save();
                $debitside += $request->amount_received;
            }

            if ($request->bank_amount_received > 0) {
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = date('y-m-d');
                $voucherTransaction->coa_account_id = $request->bank_account_id;
                $voucherTransaction->debit = $request->bank_amount_received;
                $voucherTransaction->credit = 0;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->description = 'PO No: ' . $purchaseOrderNumber . " Amount Received (Bank) ";
                $voucherTransaction->save();
                $debitside += $request->bank_amount_received;
            }
        }
        if ($request->tax_in_figure > 0) {
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = date('y-m-d');
            $voucherTransaction->coa_account_id = 30;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $request->tax_in_figure;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = 'PO No: ' . $purchaseOrderNumber . " Tax Reversed";

            $voucherTransaction->save();
            $creditside += $request->tax_in_figure;
        }
        if ($request->adv_tax > 0) {
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = date('y-m-d');
            $voucherTransaction->coa_account_id = 873;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $request->adv_tax;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = 'PO No: ' . $purchaseOrderNumber . " Tax Reversed";

            $voucherTransaction->save();
            $creditside += $request->adv_tax;
        }

        if ($request->discount > 0) {
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = date('y-m-d');
            $voucherTransaction->coa_account_id = 383;
            $voucherTransaction->debit = $request->discount;
            $voucherTransaction->credit = 0;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = 'PO No: ' . $purchaseOrderNumber . " Deduction";
            $voucherTransaction->save();
            $debitside += $request->discount;
        }
        if ($creditside == $debitside) {
            $voucher =  Voucher::find($voucher_id);
            $voucher->total_amount = $creditside;
            $voucher->save();
        } else {
            throw new \Exception('debit and credit sides are not equal' . $creditside . '/' . $debitside);
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
            'id' => 'required|int|exists:return_po,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $po = ReturnPurchaseOrder::with('purchaseorder', 'pochild')->where('id', $req->id)->first();

        return ['po' => $po];
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
            'id' => 'required|int|exists:purchase_orders,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {

            $purchaseOrder = PurchaseOrder::with('supplier', 'store')->find($req->id);

            $poChild = PurchaseOrderChild::with('item')->where('purchase_order_id', $req->id)->get();
            $childItem = [];
            foreach ($poChild as $child) {
                $manufacturerOptions = array();
                $manufacturer = ItemManufacture::with('manufacture', 'item')->where('item_id', $child->item_id)->get();
                foreach ($manufacturer as $manufacturer) {
                    $manufacturerOptions[] = array(
                        'id' => $manufacturer->manufacture->id,
                        'value' => $manufacturer->manufacture->id,
                        'label' => $manufacturer->manufacture->name ?? '',
                    );
                }
                $returnpoid = ReturnPurchaseOrder::where('po_id', $req->id)->get();
                $newQty = 0;
                $return_qty = 0;
                if ($returnpoid) {
                    foreach ($returnpoid as $childReturn) {
                        $return_qty = ReturnPurchaseOrderChild::where('ret_purchase_order_id', $childReturn->id)

                            ->where('item_id', $child->item_id)
                            ->where('batch_no', $child->batch_no)
                            ->where('manufacturer_id', $child->manufacturer_id)
                            ->where('expiry_date', $child->expiry_date)
                            ->groupBy('item_id', 'batch_no', 'manufacturer_id', 'expiry_date')
                            ->sum('quantity');
                        $newQty += $return_qty;
                    }
                    $return_qty =  $newQty;

                    // if (empty($return_qty)) {
                    //     $totalReturnQuantity = 0;
                    // } else {
                    //     $totalReturnQuantity = $return_qty;
                    // }
                }
                //  else {
                //     $return_qty = 0;
                // }
                $itemname[] = array(
                    'id' => $child->id,
                    'item_id' => $child->item_id,
                    'item_name' => $child->item->name ?? '',
                    'unit_id' => $child->item->unit_id,
                    'unit' => $child->item->unit->name ?? '',
                    'quantity' => $child->quantity,
                    'received_quantity' => $child->received_quantity - $child->returned_quantity > 0 ? $child->received_quantity - $child->returned_quantity : $child->quantity - $child->returned_quantity,
                    // 'purchase_price' => $child->purchase_price > 0 ? $child->purchase_price : '',
                    'purchase_price' => $child->item->purchase_price,
                    'manufacturer_id' => $child->manufacturer_id,
                    'pack' => $child->pack,
                    'batch_no' => $child->batch_no,
                    'rate' => $child->rate,
                    'amount' => $child->total,
                    'cost' => 0,
                    'returned_quantity' => 0,
                    'remarks' => $child->remarks,
                    'manufacturer_id' => $child->manufacturer_id,
                    'expiry_date' => $child->expiry_date,
                    'po_type' => $child->po_type,
                    'manufacturerOptions' => $manufacturerOptions,
                    'purchased_qty' => $child->quantity,
                    'returned_qty' => $return_qty,
                );
            }
            $data = array(
                "id" => $purchaseOrder->id,
                "supplier_id" => $purchaseOrder->person_id,
                "name" => $purchaseOrder->name,
                "store_id" => $purchaseOrder->store_id,
                "manufacturer_id" => $purchaseOrder->manufacture_id,
                "po_no" => $purchaseOrder->po_no,
                "po_type" => $purchaseOrder->po_type,
                "remarks" => $purchaseOrder->remarks,
                "is_received" => $purchaseOrder->is_received,
                "is_approved" => $purchaseOrder->is_approved,
                "is_cancel" => $purchaseOrder->is_cancel,
                "request_date" => $purchaseOrder->request_date,
                "total" => $purchaseOrder->total,
                "discount" => $purchaseOrder->discount,
                "tax" => $purchaseOrder->tax,
                "adv_tax_percentage" => $purchaseOrder->adv_tax_percentage,
                "adv_tax" => $purchaseOrder->adv_tax,
                "total_after_tax" => $purchaseOrder->total_after_tax,
                "tax_in_figure" => $purchaseOrder->tax_in_figure,
                "total_after_discount" => $purchaseOrder->total_after_discount,
                "discount" => $purchaseOrder->discount,
                "supplier_name" => $purchaseOrder->supplier->name ?? '',
                "store_name" => $purchaseOrder->store->name ?? '',
                "childArray" =>  $itemname
            );
            return ['data' => $data];
        } catch (\Exception $e) {
            return $e;
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
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
            'id' => 'required|int|exists:return_po,id',
        );

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {


            DB::transaction(function () use ($request) {
                $poid = $request->id;
                $ItemInventory = ReturnPurchaseOrderChild::where('ret_purchase_order_id', $poid)->select('id')->get();


                if ($ItemInventory) {
                    foreach ($ItemInventory as  $ItemInventorys) {
                        ItemInventory::where('return_po_id', $ItemInventorys->id)->delete();
                    }
                }
                ReturnPurchaseOrderChild::where(['ret_purchase_order_id' => $request->id])->delete();
                $parentDelete = ReturnPurchaseOrder::where(['id' => $request->id])->delete();

                $voucher = Voucher::where('return_po_id', $request->id)->select('id')->get();

                if (count($voucher) > 0) {

                    foreach ($voucher as  $voucher) {
                        Voucher::where('id', $voucher->id)->delete();
                        VoucherTransaction::where('voucher_id', $voucher->id)->delete();
                    }
                } else {
                    throw new \Exception('Voucher Not Found');
                }


                //  $ItemInventory = ItemInventory::with('invoicechild')->whereHas('invoicechild', function ($query) use ($poid) {
                //     $query->where('invoice_id', $invno);
                // })->get();

            });

            return ['status' => 'ok', 'message' => 'Invoice Deleted Successfully'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
