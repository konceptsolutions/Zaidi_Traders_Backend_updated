<?php

namespace App\Http\Controllers;

use App\Models\AdjustInventoryChild;
use App\Models\CoaAccount;
use App\Models\Invoice;
use App\Models\InvoiceChild;
use App\Models\InvoiceReturn;
use App\Models\Item;
use App\Models\ItemInventory;
use App\Models\ItemOemPartModeles;
use App\Models\Land;
use App\Models\Person;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderChild;
use App\Models\Quotation;
use App\Models\SalePO;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use App\Services\CustomErrorMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use stdClass;

class InvoiceController extends Controller
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
            $invoices = Invoice::with('customer', 'salesrep', 'store', 'invoiceChild')
                ->when($walk_in_customer_name, function ($q, $walk_in_customer_name) {
                    return $q->where('walk_in_customer_name', 'LIKE', '%' . $walk_in_customer_name . '%');
                })
                ->when($isDeleted > 0, function ($query) {
                    return $query->onlyTrashed();
                })
                ->when($store_id, function ($q, $store_id) {
                    return $q->where('store_id', $store_id);
                })
                ->when($invoice_no, function ($q, $invoice_no) {
                    return $q->where('invoice_no', 'LIKE', '%' . $invoice_no . '%');
                })
                ->when($store_id, function ($q, $store_id) {
                    return $q->where('store_id', $store_id);
                })
                ->when($quotation > 0, function ($q, $quotation) {
                    return $q->where('quotation_id', '!=', null);
                })
                ->when($salePo > 0, function ($q, $salePo) {
                    return $q->where('po_id', '!=', null);
                })
                ->when($direct > 0, function ($q, $direct) {
                    return $q->where(['po_id' => null, 'quotation_id' => null]);
                })
                ->when($customer_id, function ($q, $customer_id) {
                    return $q->where('customer_id', $customer_id);
                })
                ->when($from, function ($q, $from) {
                    return $q->where('date', '>=', $from);
                })
                ->when($to, function ($q, $to) {
                    return $q->where('date', '<=', $to);
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

    public function getLatestSalesPoNO()
    {
        try {
            $po_no = Invoice::orderBy('id', 'desc')->first();
            if ($po_no) {
                $po_no = $po_no->po_no + 1;
            } else {
                $po_no = 1;
            }

            return ['status' => 'ok', 'po_no' => $po_no];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function getInoicesByCutomer(Request $req)
    {
        $customer_id = $req->customer_id;

        $invoices = Invoice::where('customer_id', $customer_id)->get();

        return ['invoices' => $invoices];
    }
    public function getPoBySupplier(Request $req)
    {
        $supplier_id = $req->supplier_id;

        $PurchaseOrders = PurchaseOrder::where('person_id', $supplier_id)->get();

        return ['PurchaseOrders' => $PurchaseOrders];
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
     *@param \Illuminate\Http\Request customer_id
     *@param \Illuminate\Http\Request walk_in_customer_name
     *@param \Illuminate\Http\Request store_id
     *@param \Illuminate\Http\Request total_amount
     *@param \Illuminate\Http\Request amount_received
     *@param \Illuminate\Http\Request date
     *@param \Illuminate\Http\Request remarks
     *@param \Illuminate\Http\Request childArray
     *@param \Illuminate\Http\Request item_id
     *@param \Illuminate\Http\Request quantity
     *@param  \Illuminate\Http\Request batch_no
     *@param  \Illuminate\Http\Request expiry_date
     *@param  \Illuminate\Http\Request rate
     *@param  \Illuminate\Http\Request sales_tax
     *@param  \Illuminate\Http\Request amount
     *@return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // return response()->json($request->all());
        // $user = Auth::guard('api')->user();
        // $userId = $user->id;
        $rules = array(
            'date' => 'required',
            'list' => 'required|array',
            'list.*.item_id' => 'required|numeric',
            'list.*.quantity' => 'required|numeric',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        // try {
        // if (
        //     ($request->amount_received + $request->bank_amount_received) != $request->total_after_gst ||
        //     ($request->amount_received + $request->bank_amount_received) != $request->total_after_adv_tax &&
        //     $request->sale_type == 1
        // ) {
        //     return ['status' => 'error', 'message' => "Total amount must be received"
        //     . ($request->amount_received + $request->bank_amount_received) .' adv '. $request->total_after_adv_tax . ' gst '. $request->total_after_gst];
        // }
        foreach ($request->list as $row) {

            $stockQuantity = ItemInventory::getStockQuantity($row['batchDetails']['manufacturer_id'], $row['item_id'], $row['batchDetails']['batchExpiry'], $row['batchDetails']['batch_no']);
            if ($row['quantity'] > $stockQuantity) {
                return ['status' => 'error', 'message' => "Sale quantity is greater tan available stock: " . $stockQuantity];
            }
        }
        DB::transaction(function () use ($request) {

            $check = 0;
            $itemName = '';
            //checking if sale quantitiy is greater than stock quantity and if negative inventory is allowed starts
            foreach ($request->list as $row) {
                $stock_quantity = ItemInventory::getStockQuantity($row['batchDetails']['manufacturer_id'], $row['item_id'], $row['batchDetails']['batchExpiry'], $row['batchDetails']['batch_no']);

                if ($row['quantity'] > $stock_quantity) {
                    $check = 1;
                }
            }

            if ($request->isNegative == 0 && $check == 1) {
                throw new \Exception($itemName . 'Item Qty Not available in stock');
            }
            //checking if sale quantitiy is greater than stock quantity and if negative inventory is allowed ends
            //invoice for negative inventory starts
            if ($request->isNegative == 1 && $check == 1) {
                $this->negativeInventoryInvoice($request);
            }

            $totalCashSaleVoucher = 0;
            $invoice = new Invoice();
            $invoice->sale_type = $request->sale_type;
            $invoice->pono = $request->po_no;
            $invoice->tax_type = $request->tax_type;
            $invoice->customer_id = $request->customer_id;
            $invoice->sales_rep_id = $request->sales_rep_id;
            $invoice->walk_in_customer_name = $request->walk_in_customer_name;
            $invoice->walk_in_customer_phone = $request->walk_in_customer_phone;
            $invoice->store_id = $request->store_id;
            $invoice->total_amount = $request->total_amount;
            $invoice->discount = $request->discount;
            $invoice->total_after_discount = $request->total_after_discount;
            $invoice->gst = $request->gst ?? 0;
            $invoice->gst_percentage = $request->gst_percentage ?? 0;
            $invoice->total_after_adv_tax = $request->total_after_adv_tax ?? 0;
            $invoice->total_after_gst = $request->total_after_gst;
            $invoice->adv_tax_percentage = $request->adv_tax_percentage;
            $invoice->adv_tax = $request->adv_tax;
            $invoice->amount_received = $request->amount_received;
            $invoice->bank_amount_received = $request->bank_amount_received;
            $invoice->date = $request->date;
            $invoice->account_id = $request->account_id;
            $invoice->bank_account_id = $request->bank_account_id;
            $invoice->remarks = $request->remarks;
            $invoice->adv_tax_type = $request->adv_tax_type;
            $invoice->order_date = $request->order_date;
            $invoice->save();
            $invoice_id = $invoice->id;
            $invoice_no = $invoice->invoice_no;

            $remarks = " Purchase Order ";
            $supplier_coa_account_id = CoaAccount::where([['person_id', $request->customer_id]])->select('id', 'coa_group_id', 'coa_sub_group_id')->first();
            $customer_id = Person::where('id', $request->customer_id)->value('name');
            $is_post_dated = isset($request->cheque_no) ? 1 : 0;
            $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
            ///// cost jv voucher
            $voucherInventoryCost = new Voucher();
            $voucherInventoryCost->voucher_no = $newVoucherNo;
            $voucherInventoryCost->date = Land::changeDateFormat($request->date);
            $voucherInventoryCost->name = "Inventory Cost Out" . ", Invoice no: " . $invoice_no;
            $voucherInventoryCost->invoice_id = $invoice_id;
            $voucherInventoryCost->type = 3;
            $voucherInventoryCost->isApproved = 1;
            $voucherInventoryCost->generated_at = Land::changeDateFormat($request->date);
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

            foreach ($request->list as $row) {

                $invoiceChild = new InvoiceChild();
                $invoiceChild->invoice_id = $invoice_id;
                $invoiceChild->item_id = $row['item_id'];
                $invoiceChild->quantity = $row['quantity'];
                $invoiceChild->rate = $row['rate'];
                $invoiceChild->cost = $row['avg_price'];
                $invoiceChild->sales_tax = $row['sales_tax'];
                $invoiceChild->amount = $row['rate'] * $row['quantity'];
                $invoiceChild->discount = $row['item_discount'];
                $invoiceChild->item_discount_per = $row['item_discount_per'];
                $invoiceChild->sNo = $row['sNo'];
                $invoiceChild->total_amount = $row['item_total_after_discount'];
                $invoiceChild->save();

                $invoiceChild_id = $invoiceChild->id;

                $stockChilddata = new ItemInventory();
                $stockChilddata->invoice_id = $invoiceChild_id;
                $stockChilddata->inventory_type_id = 7;
                $stockChilddata->item_id = $row['item_id'];
                $stockChilddata->batch_no = $row['batchDetails']['batch_no'];
                $stockChilddata->manufacture_id = $row['batchDetails']['manufacturer_id'];
                $stockChilddata->expiry_date = $row['batchDetails']['batchExpiry'];
                $stockChilddata->store_id = $request->store_id;
                $stockChilddata->quantity_out = $row['quantity'];

                $stockChilddata->date = $request->date;
                $stockChilddata->save();

                $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $row['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();
                $itemName = $PurchasePrice->item->name ?? '';

                // $PurchasePrice = PurchaseOrderChild::with('item')
                //     ->where('item_id', $row['item_id'])
                //     ->groupBy('item_id')
                //     ->select(DB::raw('SUM(rate * quantity) / SUM(quantity) as AvgPrice'), 'item_id')
                //     ->first();

                // if ($PurchasePrice) {
                //     // If PurchaseOrderChild has a result
                //     $itemName = $PurchasePrice->item->name ?? '';
                // } else {
                //     // If not found in PurchaseOrderChild, check AdjustInventoryChild
                //     $PurchasePrice = AdjustInventoryChild::with('item')
                //         ->where('item_id', $row['item_id'])
                //         ->groupBy('item_id')
                //         ->select(DB::raw('SUM(purchase_price * quantity_in) / SUM(quantity_in) as AvgPrice'), 'item_id')
                //         ->first();

                //     // Get item name if found in AdjustInventoryChild
                //     $itemName = $PurchasePrice->item->name ?? '';
                // }

                $totalAvgPrice += $row['avg_price'] * $row['quantity'];
                //   --------------Inventory credit  --------------------

                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherInvCost_id;
                $voucherTransaction->date = Land::changeDateFormat($request->date);
                $voucherTransaction->coa_account_id = 1; //Inventory
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = 0;
                $voucherTransaction->credit = $row['avg_price'] * $row['quantity'];
                $voucherTransaction->description = $row['item_id'] . '-' . $row['item_name'] . "Batch NO." . $row['batchDetails']['batch_no'] . " Inventory Sold. " . '' . "Avg Cost:" . '' . round($row['avg_price']) . ' ' . "," . ' ' . "Qty" . " " . $row['quantity'] . " ,Invoice no: " . $invoice_no;
                $voucherTransaction->save();
                $creditside += $row['avg_price'] * $row['quantity'];

                //   --------------Cost debiting --------------------

                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherInvCost_id;
                $voucherTransaction->date = Land::changeDateFormat($request->date);
                $voucherTransaction->coa_account_id = 3; //Cost Inventory
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = $row['avg_price'] * $row['quantity'];
                $voucherTransaction->credit = 0;
                $voucherTransaction->description = $row['item_id'] . '-' . $row['item_name'] . "Batch NO." . $row['batchDetails']['batch_no'] . " Inventory Sold. " . '' . "Avg Cost:" . '' . round($row['avg_price']) . ' ' . "," . ' ' . "Qty" . " " . $row['quantity'] . " ,Invoice no: " . $invoice_no;
                $voucherTransaction->save();
                $debitside += $row['avg_price'] * $row['quantity'];
                // $debitside += $totalAvgPrice;
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
                $voucherRevenueGen->date = Land::changeDateFormat($request->date);
                $voucherRevenueGen->name = "Revenue Generated" . " ,Invoice no: " . $invoice_no;
                $voucherRevenueGen->invoice_id = $invoice_id;
                $voucherRevenueGen->type = 2;
                $voucherRevenueGen->isApproved = 1;
                $voucherRevenueGen->generated_at = Land::changeDateFormat($request->date);
                $voucherRevenueGen->total_amount = $request->total_after_discount;
                $voucherRevenueGen->cheque_no = $request->cheque_no;
                $voucherRevenueGen->cheque_date = $request->cheque_date;
                $voucherRevenueGen->is_post_dated = $is_post_dated;
                $voucherRevenueGen->is_auto = 1;
                $voucherRevenueGen->save();
                $voucherRevenueGenid = $voucherRevenueGen->id;
                $voucherRevGen_id = $voucherRevenueGen->id;
                foreach ($request->list as $list) {
                    $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $row['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();
                    $itemName = $PurchasePrice->item->name ?? '';

                    // $PurchasePrice = PurchaseOrderChild::with('item')
                    //     ->where('item_id', $row['item_id'])
                    //     ->groupBy('item_id')
                    //     ->select(DB::raw('SUM(rate * quantity) / SUM(quantity) as AvgPrice'), 'item_id')
                    //     ->first();

                    // if ($PurchasePrice) {
                    //     // If PurchaseOrderChild has a result
                    //     $itemName = $PurchasePrice->item->name ?? '';
                    // } else {
                    //     // If not found in PurchaseOrderChild, check AdjustInventoryChild
                    //     $PurchasePrice = AdjustInventoryChild::with('item')
                    //         ->where('item_id', $row['item_id'])
                    //         ->groupBy('item_id')
                    //         ->select(DB::raw('SUM(purchase_price * quantity_in) / SUM(quantity_in) as AvgPrice'), 'item_id')
                    //         ->first();

                    //     // Get item name if found in AdjustInventoryChild
                    //     $itemName = $PurchasePrice->item->name ?? '';
                    // }
                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 4; //Goods Sold
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $list['rate'] * $list['quantity'];
                    $voucherTransaction->description = $list['item_id'] . '-' . $list['item_name'] . "Batch NO." . $list['batchDetails']['batch_no'] . " revenue . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $list['rate'] * $list['quantity'];
                }
                // $totalCashSaleVoucher += $request->amount_received;
                if ($request->amount_received > 0) {
                    $totalCashSaleVoucher += $request->amount_received;
                    //---------------------Cash 1 Debit ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = $request->account_id;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = $request->amount_received;
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->description = "Invoice no: " . $invoice_no . " Cash Received";
                    $voucherTransaction->save();
                    $debitside += $request->amount_received;
                }
                if ($request->bank_amount_received > 0) {
                    //---------------------Bank Debit ------------------
                    $totalCashSaleVoucher += $request->bank_amount_received;
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = $request->bank_account_id;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = $request->bank_amount_received;
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->description = "Invoice no: " . $invoice_no . " Cash Received By bank";
                    $voucherTransaction->save();
                    $debitside += $request->bank_amount_received;
                }
                if ($request->adv_tax > 0) {
                    //------ Advance Sale Tax  ------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 871; //Advance Sale Tax Payable
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->adv_tax;
                    $voucherTransaction->description = "Advance Sale Tax  " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->adv_tax;
                }
                ///////gst voucher
                if ($request->gst > 0) {
                    //   $totalCashSaleVoucher += $request->gst;

                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 23; //GST
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->gst;
                    $voucherTransaction->description = "Gst " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->gst;
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
                foreach ($request->list as $list) {
                    if ($list['item_discount'] > 0) {
                        $totalCashSaleVoucher += $list['item_discount'];

                        //---------------------Debiting discount Expense   ------------------

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = 28;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $list['item_discount'];
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $list['item_discount_per'] . '%' . ' ' . 'Discount' . '' . ",Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $list['item_discount'];
                    }
                    $voucher = Voucher::find($voucherRevenueGenid);
                    $voucher->total_amount = $totalCashSaleVoucher;
                    $voucher->save();
                }
            } else {

                $customer_account = CoaAccount::where('person_id', $request->customer_id)
                    ->where('coa_sub_group_id', 9)->first();
                $customer_account_id = $customer_account->id;

                foreach ($request->list as $list) {
                    $price += $list['rate'] * $list['quantity'];
                    $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $row['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();
                    $itemName = $PurchasePrice->item->name ?? '';

                    $PurchasePrice = PurchaseOrderChild::with('item')
                        ->where('item_id', $row['item_id'])
                        ->groupBy('item_id')
                        ->select(DB::raw('SUM(rate * quantity) / SUM(quantity) as AvgPrice'), 'item_id')
                        ->first();

                    if ($PurchasePrice) {
                        // If PurchaseOrderChild has a result
                        $itemName = $PurchasePrice->item->name ?? '';
                    } else {
                        // If not found in PurchaseOrderChild, check AdjustInventoryChild
                        $PurchasePrice = AdjustInventoryChild::with('item')
                            ->where('item_id', $row['item_id'])
                            ->groupBy('item_id')
                            ->select(DB::raw('SUM(purchase_price * quantity_in) / SUM(quantity_in) as AvgPrice'), 'item_id')
                            ->first();

                        // Get item name if found in AdjustInventoryChild
                        $itemName = $PurchasePrice->item->name ?? '';
                    }
                    $PurchasePrice = Item::where('id', $row['item_id'])->first();
                    $itemName = $PurchasePrice->name;

                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherInvCost_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 4; //Goods Sold
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $list['rate'] * $list['quantity'];
                    $voucherTransaction->description = $list['item_id'] . '-' . $list['item_name'] . "Batch NO." . $list['batchDetails']['batch_no'] . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $list['rate'] * $list['quantity'];

                    // //---------------------Cash 1 Debit ------------------

                    // $voucherTransaction = new VoucherTransaction();
                    // $voucherTransaction->voucher_id = $voucherInvCost_id;
                    // $voucherTransaction->date = Land::changeDateFormat($request->date);
                    // $voucherTransaction->coa_account_id = $customer_account_id;
                    // $voucherTransaction->is_approved = 1;
                    // $voucherTransaction->debit = $list['rate'] * $list['quantity'];
                    // $voucherTransaction->credit = 0;
                    // $voucherTransaction->description = $list['item_id'] . '-' . $itemName . "Batch NO." . $list['batchDetails']['batch_no'] . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                    // $voucherTransaction->save();
                    $debitside += $list['rate'] * $list['quantity'];

                    if ($list['item_discount'] > 0) {
                        // $totalCreditSaleVoucher += $request->discount;
                        //---------------------Crediting Customer Account Deu To discount ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $list['item_discount'];
                        $voucherTransaction->description = $list['item_discount_per'] . '%' . ' ' . 'Discount' . '' . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $list['item_discount'];

                        //---------------------Debiting discount Expense   ------------------

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = 28;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $list['item_discount'];
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $list['item_discount_per'] . '%' . ' ' . 'Discount' . '' . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $list['item_discount'];
                    }
                }

                if (($request->amount_received) + ($request->bank_amount_received) > 0) {
                    $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
                    $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                    $voucherRevenueGen = new Voucher();
                    $voucherRevenueGen->voucher_no = $newVoucherNo;
                    $voucherRevenueGen->date = Land::changeDateFormat($request->date);
                    $voucherRevenueGen->name = "Customer Amount Received ";
                    $voucherRevenueGen->invoice_id = $invoice_id;
                    $voucherRevenueGen->type = 2;
                    $voucherRevenueGen->isApproved = 1;
                    $voucherRevenueGen->generated_at = Land::changeDateFormat($request->date);
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
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->amount_received;
                        $voucherTransaction->description = "Amount received (Cash) against " . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $request->amount_received;

                        //---------------------Cash 1 Debit ------------------

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = $request->account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->amount_received;
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $list['item_id'] . '-' . $list['item_name'] . "Batch NO." . $list['batchDetails']['batch_no'] . " " . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $request->amount_received;

                    }
                    ///bank
                    if ($request->bank_amount_received > 0) {

                        //---------------------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->bank_amount_received;
                        $voucherTransaction->description = "Amount received (Bank) against " . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $request->bank_amount_received;

                        //---------------------Cash 1 Debit ------------------

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = $request->bank_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->bank_amount_received;
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $list['item_id'] . '-' . $list['item_name'] . "Batch NO." . $list['batchDetails']['batch_no'] . " " . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $request->bank_amount_received;
                    }
                }
                ///////gst voucher
                if ($request->gst > 0) {
                    // $totalCreditSaleVoucher += $request->gst;

                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherInvCost_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 23;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->gst;
                    $voucherTransaction->description = "Gst " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->gst;
                    // //---------------------Cash 1 Debit ------------------
                    // $voucherTransaction = new VoucherTransaction();
                    // $voucherTransaction->voucher_id = $voucherInvCost_id;
                    // $voucherTransaction->date = Land::changeDateFormat($request->date);
                    // $voucherTransaction->coa_account_id = $customer_account_id;
                    // $voucherTransaction->is_approved = 1;
                    // $voucherTransaction->debit = $request->gst;
                    // $voucherTransaction->credit = 0;
                    // $voucherTransaction->description = "Gst " . " ,Invoice no: " . $invoice_no;
                    // $voucherTransaction->save();
                    $debitside += $request->gst;
                }
                if ($request->adv_tax > 0) {
                    // $totalCreditSaleVoucher += $request->gst;

                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherInvCost_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 871; //Advance Sale Tax Payable
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->adv_tax;
                    $voucherTransaction->description = "Advance Sale Tax " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->adv_tax;
                    //---------------------Cash 1 Debit ------------------
                    // $voucherTransaction = new VoucherTransaction();
                    // $voucherTransaction->voucher_id = $voucherInvCost_id;
                    // $voucherTransaction->date = Land::changeDateFormat($request->date);
                    // $voucherTransaction->coa_account_id = $customer_account_id;
                    // $voucherTransaction->is_approved = 1;
                    // $voucherTransaction->debit = $request->adv_tax;
                    // $voucherTransaction->credit = 0;
                    // $voucherTransaction->description = "Advance Sale Tax " . " ,Invoice no: " . $invoice_no;
                    // $voucherTransaction->save();
                    $debitside += $request->adv_tax;
                }
                // //---------------------Cash 1 Debit ------------------

                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherInvCost_id;
                $voucherTransaction->date = Land::changeDateFormat($request->date);
                $voucherTransaction->coa_account_id = $customer_account_id;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = $request->total_after_adv_tax;
                $voucherTransaction->credit = 0;
                $voucherTransaction->description = "Goods Sold . " . '' . " ,Invoice no: " . $invoice_no;
                $voucherTransaction->save();
            }

            /*----------------End vouchers of labor and carriage amount */

            $tolerance = 0.001; // Adjust the tolerance value as per your requirement

            if (abs($creditside - $debitside) < $tolerance) {

                $voucher = Voucher::find($voucherInvCost_id);
                if (($request->amount_received) + ($request->bank_amount_received) > 0) {
                    $voucher->total_amount = $debitside - ($request->amount_received + $request->bank_amount_received);
                } else {
                    $voucher->total_amount = $debitside;
                }
                $voucher->save();
            } else {
                throw new \Exception('debit and credit sides are not equal ' . $creditside . '---- ' . $debitside);
            }
        });

        return ['status' => "ok", 'message' => 'Invoice Stored Successfully'];
        // } catch (\Exception $e) {
        //     return ['status' => 'error', 'message' => $e->getMessage()];
        // }
    }
    private function negativeInventoryInvoice($request)
    {
        $totalCashSaleVoucher = 0;
        $invoice = new Invoice();
        $invoice->customer_id = $request->customer_id;
        $invoice->delivered_to = $request->delivered_to;
        $invoice->walk_in_customer_name = $request->walk_in_customer_name;
        $invoice->remarks = $request->remarks;
        $invoice->store_id = $request->store_id;
        $invoice->total_amount = $request->total_amount;
        $invoice->discount = $request->discount ?? 0;
        $invoice->total_after_discount = $request->total_after_discount;
        $invoice->received_amount = $request->amount_received;
        $invoice->bank_received_amount = $request->bank_received_amount;
        $invoice->date = $request->date;
        $invoice->tax_type = $request->tax_type;
        $invoice->total_after_adv_tax = $request->total_after_adv_tax ?? 0;
        $invoice->sale_type = $request->sale_type;
        $invoice->gst = $request->gst;
        $invoice->total_after_gst = $request->total_after_gst;
        //$invoice->is_pending = 1;
        $invoice->is_pending_neg_inventory = 1;
        $invoice->adv_tax_type = $request->adv_tax_type;
        $invoice->save();
        $invoice_id = $invoice->id;
        $invoice_no = 'INO-' . $invoice_id;
        Invoice::where('id', '=', $invoice_id)->update(['invoice_no' => $invoice_no]);

        $remarks = " Purchase Order ";
        $supplier_coa_account_id = CoaAccount::where([['person_id', $request->customer_id]])->select('id', 'coa_group_id', 'coa_sub_group_id')->first();
        $customer_id = Person::where('id', $request->customer_id)->value('name');

        $debitside = 0;
        $creditside = 0;
        $totalAvgPrice = 0;
        $price = 0;

        foreach ($request->list as $row) {
            $itemoem = ItemOemPartModeles::where('id', $row['item_id'])->first();
            $itemoem->last_sale_price = $row['price'];
            $itemoem->save();

            $invoiceChild = new InvoiceChild();
            $invoiceChild->invoice_id = $invoice_id;
            $invoiceChild->item_id = $row['item_id'];
            $invoiceChild->quantity = $row['qty'];
            $invoiceChild->price = $row['price'];
            $invoiceChild->cost = $row['avg_price'];
            $invoiceChild->sNo = $row['sNo'];
            $invoiceChild->is_negative = 1;
            $invoiceChild->save();

            $stockChilddata = new ItemInventory();
            $stockChilddata->inventory_type_id = 8;
            $stockChilddata->item_id = $row['item_id'];
            $stockChilddata->invoice_id = $invoice_id;
            $stockChilddata->store_id = $request->store_id;
            $stockChilddata->quantity_out = $row['qty'];
            $stockChilddata->date = $request->date;
            $stockChilddata->save();
        }
        //// receipt voucher revenue
        $this->revenueVoucher($request, $invoice_no, $invoice_id);

        //invoice for negative inventory    ends
    }
    public function storeDummyInvoice(Request $request)
    {
        $rules = array(
            'date' => 'required',
            'list' => 'required|array',
            'list.*.item_id' => 'required|numeric',
            'list.*.quantity' => 'required|numeric',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {

            DB::transaction(function () use ($request) {

                $invoice = new Invoice();
                $invoice->sale_type = $request->sale_type;
                $invoice->tax_type = $request->tax_type;
                $invoice->customer_id = $request->customer_id;
                $invoice->sales_rep_id = $request->sales_rep_id;
                $invoice->walk_in_customer_name = $request->walk_in_customer_name;
                $invoice->walk_in_customer_phone = $request->walk_in_customer_phone;
                $invoice->store_id = $request->store_id;
                $invoice->total_amount = $request->total_amount;
                $invoice->discount = $request->discount;
                $invoice->total_after_discount = $request->total_after_discount;
                $invoice->gst = $request->gst ?? 0;
                $invoice->gst_percentage = $request->gst_percentage ?? 0;
                $invoice->total_after_adv_tax = $request->total_after_adv_tax ?? 0;
                $invoice->total_after_gst = $request->total_after_gst;
                $invoice->date = $request->date;
                $invoice->remarks = $request->remarks;
                $invoice->is_dummy = 1;
                $invoice->adv_tax_type = $request->adv_tax_type;
                $invoice->adv_tax_percentage = $request->adv_tax_percentage;
                $invoice->adv_tax = $request->adv_tax;
                $invoice->amount_received = $request->amount_received;
                $invoice->bank_amount_received = $request->bank_amount_received;
                $invoice->account_id = $request->account_id;
                $invoice->bank_account_id = $request->bank_account_id;
                $invoice->save();

                $invoice_id = $invoice->id;
                $invoice_no = $invoice->invoice_no;
                Invoice::where('id', '=', $invoice_id)->update(['invoice_no' => $invoice_no]);

                foreach ($request->list as $row) {

                    $invoiceChild = new InvoiceChild();
                    $invoiceChild->invoice_id = $invoice_id;
                    $invoiceChild->item_id = $row['item_id'];
                    $invoiceChild->quantity = $row['quantity'];
                    $invoiceChild->rate = $row['rate'];
                    $invoiceChild->cost = $row['avg_price'];
                    $invoiceChild->sales_tax = $row['sales_tax'];
                    $invoiceChild->amount = $row['rate'] * $row['quantity'];
                    $invoiceChild->discount = $row['item_discount'];
                    $invoiceChild->item_discount_per = $row['item_discount_per'];
                    $invoiceChild->total_amount = $row['item_total_after_discount'];
                    $invoiceChild->save();

                    $invoiceChild_id = $invoiceChild->id;
                    $stockChilddata = new ItemInventory();
                    $stockChilddata->invoice_id = $invoiceChild_id;
                    $stockChilddata->inventory_type_id = 9;
                    $stockChilddata->item_id = $row['item_id'];
                    $stockChilddata->batch_no = $row['batchDetails']['batch_no'] ?? null;
                    $stockChilddata->manufacture_id = $row['batchDetails']['manufacturer_id'] ?? null;
                    $stockChilddata->expiry_date = $row['batchDetails']['batchExpiry'] ?? null;
                    $stockChilddata->store_id = $request->store_id;
                    $stockChilddata->quantity_out = $row['quantity'];
                    $stockChilddata->date = $request->date;
                    $stockChilddata->is_dummy = 1;
                    $stockChilddata->save();
                }
            });

            return ['status' => "ok", 'message' => 'Dummy Invoice Stored Successfully'];
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
        $rules = [
            'id' => 'required|int|exists:invoices,id',
        ];

        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            $parentData = Invoice::with('store', 'customer', 'posale', 'quotation')->find($req->id);

            if ($parentData) {
                $parentData->order_date = $parentData->order_date ?? '';
            }
            $childData = InvoiceChild::with('item', 'manufacturer')->where('invoice_id', $req->id)->get();

            return ['status' => 'success', 'parentData' => $parentData, 'childData' => $childData];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
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

                $AvgPrice = PurchaseOrderChild::getStoredAveragePrice($childData[$i]->item_id);
                $batchOptions = [];
                $items = Item::with('iteminventory')->where('id', $childData[$i]->item_id)->orderBy('id')->first();
                foreach ($items->iteminventory as $iteminventory) {
                    $batchOptions[] = array(
                        'id' => $iteminventory->manufacture->id . $iteminventory->batch_no,
                        'value' => $iteminventory->manufacture->id . $iteminventory->batch_no,
                        'label' => $iteminventory->manufacture->id . '-' . $iteminventory->manufacture->name . '-' . $iteminventory->batch_no . '-' . $iteminventory->item_available,
                        'batchQty' => $iteminventory->item_available,
                        'batchExpiry' => $iteminventory->expiry_date,
                        'manufacturer_id' => $iteminventory->manufacture->id,
                        'batch_no' => $iteminventory->batch_no,

                    );
                }

                $itemsInv = Item::with('itemAvaiableInventory')->where('id', $childData[$i]->item_id)->first();

                if ($itemsInv && $itemsInv->itemAvaiableInventory) {

                    $qty_available = $itemsInv->itemAvaiableInventory->item_available;
                } else {
                    $qty_available = 0;
                }

                $selectedBatch = ItemInventory::with('manufacture', 'item2')->where('invoice_id', $childData[$i]->id)->orderBy('id')->first();

                $batchDetails = new stdClass;
                $batchDetails->batchExpiry = $selectedBatch ? $selectedBatch->expiry_date : '';
                $batchDetails->batchQty = $selectedBatch->item2->itemAvaiableAndExpiredInventory->item_available ?? 0;
                $batchDetails->batch_no = $selectedBatch !== null ? $selectedBatch->batch_no : '';
                $batchDetails->manufacturer_id = $selectedBatch !== null ? $selectedBatch->manufacture_id : '';

                $selectedBatch = $selectedBatch !== null ? $selectedBatch->id . $selectedBatch->batch_no : '';

                $selectedBatch = ItemInventory::with('manufacture', 'item2')->where('invoice_id', $childData[$i]->id)->orderBy('id')->first();
                if ($selectedBatch && $selectedBatch->manufacture) {
                    $selectedBatch2 = new stdClass();

                    // Safely concatenate properties with null checks
                    $selectedBatch2->id = $selectedBatch->id . ($selectedBatch->batch_no ?? '');
                    $selectedBatch2->value = ($selectedBatch->manufacture_id ?? '') . ($selectedBatch->batch_no ?? '');

                    // Concatenating with null checks for nested properties
                    $selectedBatch2->label = ($selectedBatch->manufacture_id ?? '') . '-' .
                        ($selectedBatch->manufacture->name ?? '') . '-' .
                        ($selectedBatch->batch_no ?? '') . '-' .
                        ($selectedBatch->item->itemAvaiableAndExpiredInventory->item_available ?? '');

                    $selectedBatch2->batchQty = $selectedBatch->item2->itemAvaiableAndExpiredInventory->item_available ?? '';
                    $selectedBatch2->batchExpiry = $selectedBatch->expiry_date ?? '';
                    $selectedBatch2->manufacturer_id = $selectedBatch->manufacture->id ?? '';
                    $selectedBatch2->batch_no = $selectedBatch->batch_no ?? '';
                }

                // Calculating Selected Batch Ends
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
                    "avg_price" => $AvgPrice,
                    "discount" => $childData[$i]->discount,
                    "item_discount_per" => $childData[$i]->item_discount_per,
                    "total_amount" => $childData[$i]->total_amount,
                    "qty_available" => $qty_available,
                    "batchNumber" => $batchNumber,
                    'batchOptions' => $batchOptions,
                    'selectedBatch' => $selectedBatch2 ?? '',
                    'batchDetails' => $batchDetails ?? '',
                    'sNo' => $childData[$i]->sNo,
                );
            }
            $form = array(
                "id" => $parentData->id,
                "po_no" => $parentData->pono,
                "customer_id" => $parentData->customer_id ?? '',
                "sales_rep_id" => $parentData->sales_rep_id ?? '',
                "walk_in_customer_name" => $parentData->walk_in_customer_name ?? '',
                "walk_in_customer_phone" => $parentData->walk_in_customer_phone ?? '',
                "invoice_no" => $parentData->invoice_no,
                "is_dummy" => $parentData->is_dummy,
                "sale_type" => $parentData->sale_type,
                "tax_type" => $parentData->tax_type,
                "gst" => $parentData->gst,
                "gst_percentage" => $parentData->gst_percentage,
                "total_after_gst" => $parentData->total_after_gst,
                "adv_tax_percentage" => $parentData->adv_tax_percentage,
                "total_after_adv_tax" => $parentData->total_after_adv_tax,
                "adv_tax" => $parentData->adv_tax,
                "date" => $parentData->date,
                "order_date" => $parentData->order_date ?? '',
                "remarks" => $parentData->remarks,
                "total_amount" => $parentData->total_amount,
                "amount_received" => $parentData->amount_received,
                "bank_amount_received" => $parentData->bank_amount_received,
                "account_id" => $parentData->account_id,
                "bank_account_id" => $parentData->bank_account_id,
                "store" => $parentData->store->name ?? '',
                "store_id" => $parentData->store_id,
                "adv_tax_type" => $parentData->adv_tax_type,
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
    public function update(Request $request)
    {
        // return response()->json($request->all());
        $rules = array(
            'date' => 'required',
            'childArray' => 'required|array',
            'childArray.*.item_id' => 'required|numeric',
            'childArray.*.quantity' => 'required|numeric',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        // try {

        DB::transaction(function () use ($request) {
            $totalCashSaleVoucher = 0;
            $invoice = Invoice::find($request->id);
            $invoice->sale_type = $request->sale_type;
            $invoice->pono = $request->po_no;
            $invoice->tax_type = $request->tax_type;
            if ($request->sale_type == 2) {
                $invoice->customer_id = $request->customer_id;
            }
            if ($request->sale_type == 1) {
                $invoice->walk_in_customer_name = $request->walk_in_customer_name;
                $invoice->walk_in_customer_phone = $request->walk_in_customer_phone;
            }

            $invoice->sales_rep_id = $request->sales_rep_id;
            $invoice->sales_rep_id = $request->sales_rep_id;
            $invoice->total_amount = $request->total_amount;
            $invoice->discount = $request->discount;
            $invoice->total_after_discount = $request->total_after_discount;
            $invoice->gst = $request->gst;
            $invoice->gst_percentage = $request->gst_percentage;
            $invoice->total_after_gst = $request->total_after_gst;
            $invoice->total_after_adv_tax = $request->total_after_adv_tax;
            $invoice->adv_tax_percentage = $request->adv_tax_percentage;
            $invoice->adv_tax = $request->adv_tax ?? 0;
            $invoice->amount_received = $request->amount_received;
            $invoice->bank_amount_received = $request->bank_amount_received;
            $invoice->date = Land::changeDateFormat($request->date);
            $invoice->account_id = $request->account_id;
            $invoice->bank_account_id = $request->bank_account_id;
            $invoice->remarks = $request->remarks;
            $invoice->is_dummy = 0;
            $invoice->adv_tax_type = $request->adv_tax_type;
            $invoice->order_date = $request->order_date;
            $invoice->save();
            $invoice_id = $invoice->id;
            $invoiceChilddata = InvoiceChild::where('invoice_id', $request->id)->get();

            $voucher = Voucher::where('invoice_id', $request->id)->select('id')->get();

            if ($voucher) {
                foreach ($voucher as $voucher) {
                    Voucher::where('id', $voucher->id)->delete();
                    VoucherTransaction::where('voucher_id', $voucher->id)->delete();
                }
            }
            $invoice_no = $request->invoice_no;

            $remarks = " Purchase Order ";
            $supplier_coa_account_id = CoaAccount::where([['person_id', $request->customer_id]])->select('id', 'coa_group_id', 'coa_sub_group_id')->first();
            $customer_id = Person::where('id', $request->customer_id)->value('name');
            $is_post_dated = isset($request->cheque_no) ? 1 : 0;
            $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
            ///// cost jv voucher
            $voucherInventoryCost = new Voucher();
            $voucherInventoryCost->voucher_no = $newVoucherNo;
            $voucherInventoryCost->date = Land::changeDateFormat($request->date);
            $voucherInventoryCost->name = "Inventory Cost Out" . ", Invoice no: " . $invoice_no;
            $voucherInventoryCost->invoice_id = $invoice_id;
            $voucherInventoryCost->type = 3;
            $voucherInventoryCost->isApproved = 1;
            $voucherInventoryCost->generated_at = Land::changeDateFormat($request->date);
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
            
            // Get old invoice children before deleting
            $oldInvoiceChildren = InvoiceChild::where('invoice_id', $request->id)->get();
            
            // Group old invoice items by item_id for average cost calculation
            $oldGroupedItems = [];
            foreach ($oldInvoiceChildren as $child) {
                $itemId = $child->item_id;
                if (!isset($oldGroupedItems[$itemId])) {
                    $oldGroupedItems[$itemId] = [
                        'quantity' => 0,
                        'total' => 0,
                    ];
                }
                $oldGroupedItems[$itemId]['quantity'] += (float) $child->quantity;
                $oldGroupedItems[$itemId]['total'] += (float) ($child->cost * $child->quantity);
            }
            
            // Group new invoice items by item_id
            $newGroupedItems = [];
            foreach ($request->childArray as $row) {
                $itemId = $row['item_id'];
                if (!isset($newGroupedItems[$itemId])) {
                    $newGroupedItems[$itemId] = [
                        'quantity' => 0,
                        'total' => 0,
                    ];
                }
                $newGroupedItems[$itemId]['quantity'] += (float) $row['quantity'];
                $newGroupedItems[$itemId]['total'] += (float) ($row['avg_price'] * $row['quantity']);
            }
            
            // Calculate average cost BEFORE deleting ItemInventory records
            // We need to reverse the old sale and apply the new sale
            $allItemIds = array_unique(array_merge(array_keys($oldGroupedItems), array_keys($newGroupedItems)));
            
            foreach ($allItemIds as $itemId) {
                $item = Item::find($itemId);
                if (!$item) {
                    continue;
                }
                
                // Get current stock BEFORE ItemInventory deletion (still includes quantity_out from old invoice)
                $currentStock = Item::calculateTotalStockQty($itemId);
                
                // Calculate total amount using current avg_cost and stock
                $currentTotalAmount = $item->avg_cost * $currentStock;
                
                // Get old and new quantities and amounts
                $oldQuantity = $oldGroupedItems[$itemId]['quantity'] ?? 0;
                $oldTotal = $oldGroupedItems[$itemId]['total'] ?? 0;
                $newQuantity = $newGroupedItems[$itemId]['quantity'] ?? 0;
                $newTotal = $newGroupedItems[$itemId]['total'] ?? 0;
                
                // Reverse old sale (add back) and apply new sale (subtract)
                // After deleting ItemInventory, stock will increase by old quantity
                // Then we subtract new quantity
                $newStockQty = $currentStock + $oldQuantity - $newQuantity;
                $newTotalAmount = $currentTotalAmount + $oldTotal - $newTotal;
                
                // Calculate new average cost
                if ($newStockQty > 0) {
                    $newAvgCost = $newTotalAmount / $newStockQty;
                } else {
                    $newAvgCost = 0;
                }
                
                // Update the item's average cost
                $item->avg_cost = $newAvgCost;
                $item->save();
            }
            
            // Delete old ItemInventory records (this removes quantity_out entries, adding stock back)
            $ItemInventory = InvoiceChild::where('invoice_id', $request->id)->select('id')->get();
            if ($ItemInventory) {
                foreach ($ItemInventory as $ItemInventory) {
                    ItemInventory::where('invoice_id', $ItemInventory->id)->delete();
                }
            }
            foreach ($request->childArray as $row) {

                if (isset($row['id'])) {
                    $invoiceChild = InvoiceChild::find($row['id']);
                } else {
                    $invoiceChild = new InvoiceChild();
                }

                $invoiceChild->invoice_id = $invoice_id;
                $invoiceChild->item_id = $row['item_id'];
                $invoiceChild->quantity = $row['quantity'];
                $invoiceChild->rate = $row['rate'];
                $invoiceChild->cost = $row['avg_price'];
                $invoiceChild->sales_tax = $row['sales_tax'];
                $invoiceChild->amount = $row['rate'] * $row['quantity'];
                $invoiceChild->discount = $row['item_discount'];
                $invoiceChild->sNo = $row['sNo'];
                $invoiceChild->item_discount_per = $row['item_discount_per'] ?? 0;
                $invoiceChild->total_amount = $row['item_total_after_discount'];
                $invoiceChild->save();
                $invoiceChild_id = $invoiceChild->id;
                $invChildIds[] = $invoiceChild->id;
                $invno = $request->id;

                //  $ItemInventory = ItemInventory::with('invoicechild')->whereHas('invoicechild', function ($query) use ($invno) {
                //     $query->where('invoice_id', $invno);
                // })->get();

                $stockChilddata = new ItemInventory();
                $stockChilddata->invoice_id = $invoiceChild_id;
                $stockChilddata->inventory_type_id = 7;
                $stockChilddata->item_id = $row['item_id'];
                $stockChilddata->batch_no = $row['batchDetails']['batch_no'];
                $stockChilddata->manufacture_id = $row['batchDetails']['manufacturer_id'];
                $stockChilddata->expiry_date = $row['batchDetails']['batchExpiry'];
                $stockChilddata->store_id = $request->store_id;
                $stockChilddata->quantity_out = $row['quantity'];
                $stockChilddata->date = $request->date;
                $stockChilddata->is_dummy = 0;
                $stockChilddata->save();

                $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $row['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();
                $itemName = $PurchasePrice->item->name ?? '';

                $PurchasePrice = PurchaseOrderChild::with('item')
                    ->where('item_id', $row['item_id'])
                    ->groupBy('item_id')
                    ->select(DB::raw('SUM(rate * quantity) / SUM(quantity) as AvgPrice'), 'item_id')
                    ->first();

                if ($PurchasePrice) {
                    // If PurchaseOrderChild has a result
                    $itemName = $PurchasePrice->item->name ?? '';
                } else {
                    // If not found in PurchaseOrderChild, check AdjustInventoryChild
                    $PurchasePrice = AdjustInventoryChild::with('item')
                        ->where('item_id', $row['item_id'])
                        ->groupBy('item_id')
                        ->select(DB::raw('SUM(purchase_price * quantity_in) / SUM(quantity_in) as AvgPrice'), 'item_id')
                        ->first();

                    // Get item name if found in AdjustInventoryChild
                    $itemName = $PurchasePrice->item->name ?? '';
                }

                $totalAvgPrice += $row['avg_price'] * $row['quantity'];
                //   --------------Inventory credit  --------------------

                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherInvCost_id;
                $voucherTransaction->date = Land::changeDateFormat($request->date);
                $voucherTransaction->coa_account_id = 1;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = 0;
                $voucherTransaction->credit = $row['avg_price'] * $row['quantity'];
                $voucherTransaction->description = $itemName . " Inventory Sold. " . '' . "Avg Cost:" . '' . round($row['avg_price']) . ' ' . "," . ' ' . "Qty" . " " . $row['quantity'] . " ,Invoice no: " . $invoice_no;
                $voucherTransaction->save();
                $creditside += $row['avg_price'] * $row['quantity'];

                //   --------------Cost debiting --------------------

                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherInvCost_id;
                $voucherTransaction->date = Land::changeDateFormat($request->date);
                $voucherTransaction->coa_account_id = 3;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = $row['avg_price'] * $row['quantity'];
                $voucherTransaction->credit = 0;
                $voucherTransaction->description = $itemName . " Inventory Sold. " . '' . "Avg Cost:" . '' . round($row['avg_price']) . ' ' . "," . ' ' . "Qty" . " " . $row['quantity'] . " ,Invoice no: " . $invoice_no;
                $voucherTransaction->save();
                $debitside += $row['avg_price'] * $row['quantity'];
            }
            InvoiceChild::where('invoice_id', $request->id)->whereNotIn('id', $invChildIds)->delete();

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
                $voucherRevenueGen->date = Land::changeDateFormat($request->date);
                $voucherRevenueGen->name = "Revenue Generated" . " ,Invoice no: " . $invoice_no;
                $voucherRevenueGen->invoice_id = $invoice_id;
                $voucherRevenueGen->type = 2;
                $voucherRevenueGen->isApproved = 1;
                $voucherRevenueGen->generated_at = Land::changeDateFormat($request->date);
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

                    $itemName = $PurchasePrice->item->name ?? '';
                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 4;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $list['rate'] * $list['quantity'];
                    $voucherTransaction->description = $list['item_name'] . " revenue . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $list['rate'] * $list['quantity'];
                }

                if ($request->amount_received > 0) {
                    $totalCashSaleVoucher += $request->amount_received;
                    //---------------------Cash 1 Debit ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = $request->account_id;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = $request->amount_received;
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->description = "Invoice no: " . $invoice_no . " Cash Received";
                    $voucherTransaction->save();
                    $debitside += $request->amount_received;
                }
                if ($request->bank_amount_received > 0) {
                    //---------------------Bank Debit ------------------
                    $totalCashSaleVoucher += $request->bank_amount_received;
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = $request->bank_account_id;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = $request->bank_amount_received;
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->description = "Invoice no: " . $invoice_no . " Cash Received By bank";
                    $voucherTransaction->save();
                    $debitside += $request->bank_amount_received;
                }
                ///////gst voucher
                if ($request->gst > 0) {
                    //   $totalCashSaleVoucher += $request->gst;
                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 23;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->gst;
                    $voucherTransaction->description = "Gst " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->gst;
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
                    $voucherTransaction->date = date('y-m-d');
                    $voucherTransaction->coa_account_id = 871;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->adv_tax;
                    $voucherTransaction->description = "Advance Sale Tax  " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->adv_tax;
                }
                foreach ($request->childArray as $list) {
                    if ($list['item_discount'] > 0) {
                        $totalCashSaleVoucher += $list['item_discount'];

                        //---------------------Debiting discount Expense   ------------------

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = 28;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $list['item_discount'];
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $list['item_discount_per'] . '%' . ' ' . 'Discount' . '' . ",Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $list['item_discount'];
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
                    $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $row['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();
                    $itemName = $PurchasePrice->item->name ?? '';

                    $PurchasePrice = PurchaseOrderChild::with('item')
                        ->where('item_id', $row['item_id'])
                        ->groupBy('item_id')
                        ->select(DB::raw('SUM(rate * quantity) / SUM(quantity) as AvgPrice'), 'item_id')
                        ->first();

                    if ($PurchasePrice) {
                        // If PurchaseOrderChild has a result
                        $itemName = $PurchasePrice->item->name ?? '';
                    } else {
                        // If not found in PurchaseOrderChild, check AdjustInventoryChild
                        $PurchasePrice = AdjustInventoryChild::with('item')
                            ->where('item_id', $row['item_id'])
                            ->groupBy('item_id')
                            ->select(DB::raw('SUM(purchase_price * quantity_in) / SUM(quantity_in) as AvgPrice'), 'item_id')
                            ->first();

                        // Get item name if found in AdjustInventoryChild
                        $itemName = $PurchasePrice->item->name ?? '';
                    }
                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherInvCost_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 4;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $list['rate'] * $list['quantity'];
                    $voucherTransaction->description = $list['item_name']. " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $list['rate'] * $list['quantity'];

                    $debitside += $list['rate'] * $list['quantity'];

                    if ($list['item_discount'] > 0) {
                        // $totalCreditSaleVoucher += $request->discount;
                        //---------------------Crediting Customer Account Deu To discount ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $list['item_discount'];
                        $voucherTransaction->description = $list['item_discount_per'] . '%' . ' ' . 'Discount' . '' . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $list['item_discount'];

                        //---------------------Debiting discount Expense   ------------------

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = Land::changeDateFormat($request->date);
                        $voucherTransaction->coa_account_id = 28;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $list['item_discount'];
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $list['item_discount_per'] . '%' . ' ' . 'Discount' . '' . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $list['item_discount'];
                    }
                }
                // the discount will be in list
                // if ($request->discount > 0) {
                //     // $totalCreditSaleVoucher += $request->discount;
                //     //---------------------Crediting Customer Account Deu To discount ------------------
                //     $voucherTransaction = new VoucherTransaction();
                //     $voucherTransaction->voucher_id = $voucherInvCost_id;
                //     $voucherTransaction->date = date('y-m-d');
                //     $voucherTransaction->coa_account_id = $customer_account_id;
                //     $voucherTransaction->is_approved = 1;
                //     $voucherTransaction->debit = 0;
                //     $voucherTransaction->credit =  $request->discount;
                //     $voucherTransaction->description = 'Discount' . '' . $invoice_no;
                //     $voucherTransaction->save();
                //     $creditside += $request->discount;

                //     //---------------------Debiting discount Expense   ------------------

                //     $voucherTransaction = new VoucherTransaction();
                //     $voucherTransaction->voucher_id = $voucherInvCost_id;
                //     $voucherTransaction->date = date('y-m-d');
                //     $voucherTransaction->coa_account_id = 28;
                //     $voucherTransaction->is_approved = 1;
                //     $voucherTransaction->debit = $request->discount;
                //     $voucherTransaction->credit = 0;
                //     $voucherTransaction->description = 'Discount' . '' . $invoice_no;
                //     $voucherTransaction->save();
                //     $debitside += $request->discount;
                // }
                if ($request->amount_received > 0) {
                    // $totalCashSaleVoucher += $request->amount_received;
                    $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
                    $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                    $voucherRevenueGen = new Voucher();
                    $voucherRevenueGen->voucher_no = $newVoucherNo;
                    $voucherRevenueGen->date = Land::changeDateFormat($request->date);
                    $voucherRevenueGen->name = "Customer Amount Received ";
                    $voucherRevenueGen->invoice_id = $invoice_id;
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

                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = $customer_account_id;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->amount_received;
                    $voucherTransaction->description = "Amount received (Cash) against " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->amount_received;

                    //---------------------Cash 1 Debit ------------------

                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = $request->account_id;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = $request->amount_received;
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->description = $list['item_name'] . " " . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $debitside += $request->amount_received;
                }
                ///bank
                if ($request->bank_amount_received > 0) {

                    $totalCashSaleVoucher += $request->amount_received;
                    $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
                    $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                    $voucherRevenueGen = new Voucher();
                    $voucherRevenueGen->voucher_no = $newVoucherNo;
                    $voucherRevenueGen->date = date('y-m-d');
                    $voucherRevenueGen->name = "Customer Amount Received bank";
                    $voucherRevenueGen->invoice_id = $invoice_id;
                    $voucherRevenueGen->type = 2;
                    $voucherRevenueGen->isApproved = 1;
                    $voucherRevenueGen->generated_at = date('y-m-d');
                    $voucherRevenueGen->total_amount = $request->bank_amount_received;
                    $voucherRevenueGen->cheque_no = $request->cheque_no;
                    $voucherRevenueGen->cheque_date = Land::changeDateFormat($request->cheque_date);
                    $voucherRevenueGen->is_post_dated = $is_post_dated;
                    $voucherRevenueGen->is_auto = 1;
                    $voucherRevenueGen->save();
                    $voucherRevGen_id = $voucherRevenueGen->id;

                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = $customer_account_id;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->bank_amount_received;
                    $voucherTransaction->description = "Amount received (Bank) against " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->bank_amount_received;

                    //---------------------Cash 1 Debit ------------------

                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = $request->bank_account_id;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = $request->bank_amount_received;
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->description = $list['item_name']. " " . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $debitside += $request->bank_amount_received;
                }
                ///////gst voucher
                if ($request->gst > 0) {
                    // $totalCreditSaleVoucher += $request->gst;

                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherInvCost_id;
                    $voucherTransaction->date = Land::changeDateFormat($request->date);
                    $voucherTransaction->coa_account_id = 23;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->gst;
                    $voucherTransaction->description = "Gst " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->gst;
                    //---------------------Cash 1 Debit ------------------
                    // $voucherTransaction = new VoucherTransaction();
                    // $voucherTransaction->voucher_id = $voucherInvCost_id;
                    // $voucherTransaction->date = Land::changeDateFormat($request->date);
                    // $voucherTransaction->coa_account_id = $customer_account_id;
                    // $voucherTransaction->is_approved = 1;
                    // $voucherTransaction->debit = $request->gst;
                    // $voucherTransaction->credit = 0;
                    // $voucherTransaction->description = "Gst " . " ,Invoice no: " . $invoice_no;
                    // $voucherTransaction->save();
                    $debitside += $request->gst;
                }
                if ($request->adv_tax > 0) {
                    // $totalCreditSaleVoucher += $request->gst;

                    //---------------------Crediting Reneve ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherInvCost_id;
                    $voucherTransaction->date = date('y-m-d');
                    $voucherTransaction->coa_account_id = 871;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->adv_tax;
                    $voucherTransaction->description = "Advance Sale Tax " . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $creditside += $request->adv_tax;
                    //---------------------Cash 1 Debit ------------------
                    // $voucherTransaction = new VoucherTransaction();
                    // $voucherTransaction->voucher_id = $voucherInvCost_id;
                    // $voucherTransaction->date = date('y-m-d');
                    // $voucherTransaction->coa_account_id = $customer_account_id;
                    // $voucherTransaction->is_approved = 1;
                    // $voucherTransaction->debit = $request->adv_tax;
                    // $voucherTransaction->credit = 0;
                    // $voucherTransaction->description = "Advance Sale Tax " . " ,Invoice no: " . $invoice_no;
                    // $voucherTransaction->save();
                    $debitside += $request->adv_tax;
                }

                // //---------------------Cash 1 Debit ------------------

                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucherInvCost_id;
                $voucherTransaction->date = Land::changeDateFormat($request->date);
                $voucherTransaction->coa_account_id = $customer_account_id;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->debit = $request->total_after_adv_tax;
                $voucherTransaction->credit = 0;
                $voucherTransaction->description = "Goods Sold . " . '' . " ,Invoice no: " . $invoice_no;
                $voucherTransaction->save();
            }
            /*----------------End vouchers of labor and carriage amount */

            $tolerance = 0.001; // Adjust the tolerance value as per your requirement

            if (abs($creditside - $debitside) < $tolerance) {

                $voucher = Voucher::find($voucherInvCost_id);
                if (($request->amount_received) + ($request->bank_amount_received) > 0) {
                    $voucher->total_amount = $debitside - ($request->amount_received + $request->bank_amount_received);
                } else {
                    $voucher->total_amount = $debitside;
                }
                $voucher->save();
            } else {
                throw new \Exception('debit and credit sides are not equal ' . $creditside . '---- ' . $debitside);
            }
        });

        return ['status' => "ok", 'message' => 'Invoice Update successfully'];
        // } catch (\Exception $e) {
        //     return ['status' => 'error', 'message' => $e->getMessage()];
        // }
    }

    public function updateDummyInvoice(Request $request)
    {
        // return $request->all();
        $rules = array(
            'date' => 'required',
            'childArray' => 'required|array',
            'childArray.*.item_id' => 'required|numeric',
            'childArray.*.quantity' => 'required|numeric',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {

            DB::transaction(function () use ($request) {

                $invoice = Invoice::find($request->id);
                $invoice->sale_type = $request->sale_type;
                $invoice->tax_type = $request->tax_type;
                if ($request->sale_type == 2) {
                    $invoice->customer_id = $request->customer_id;
                }
                if ($request->sale_type == 1) {
                    $invoice->walk_in_customer_name = $request->walk_in_customer_name;
                    $invoice->walk_in_customer_phone = $request->walk_in_customer_phone;
                }

                $invoice->sales_rep_id = $request->sales_rep_id;

                $invoice->total_amount = $request->total_amount;
                $invoice->discount = $request->discount;

                $invoice->total_after_adv_tax = $request->total_after_adv_tax;
                $invoice->gst = $request->gst ?? 0;
                $invoice->gst_percentage = $request->gst_percentage ?? 0;
                $invoice->total_after_gst = $request->total_after_gst;
                $invoice->date = $request->date;
                $invoice->remarks = $request->remarks;
                $invoice->store_id = $request->store_id;
                $invoice->total_after_discount = $request->total_after_discount;
                $invoice->total_after_adv_tax = $request->total_after_adv_tax ?? 0;
                $invoice->is_dummy = 1;
                $invoice->adv_tax_type = $request->adv_tax_type;
                $invoice->adv_tax_percentage = $request->adv_tax_percentage;
                $invoice->adv_tax = $request->adv_tax;
                $invoice->amount_received = $request->amount_received;
                $invoice->bank_amount_received = $request->bank_amount_received;
                $invoice->account_id = $request->account_id;
                $invoice->bank_account_id = $request->bank_account_id;

                $invoice->save();
                $invoice_id = $invoice->id;
                $invoiceChilddata = InvoiceChild::where('invoice_id', $request->id)->get();

                $voucher = VCoucher::where('invoice_id', $request->id)->select('id')->get();

                $invoice_no = $request->invoice_no;

                foreach ($request->childArray as $row) {

                    if (isset($row['id'])) {
                        $invoiceChild = InvoiceChild::find($row['id']);
                    } else {
                        $invoiceChild = new InvoiceChild();
                    }

                    $invoiceChild->invoice_id = $invoice_id;
                    $invoiceChild->item_id = $row['item_id'];
                    $invoiceChild->quantity = $row['quantity'];
                    $invoiceChild->rate = $row['rate'];
                    $invoiceChild->cost = $row['avg_price'];
                    $invoiceChild->sales_tax = $row['sales_tax'];
                    $invoiceChild->amount = $row['rate'] * $row['quantity'];
                    $invoiceChild->discount = $row['item_discount'];
                    $invoiceChild->item_discount_per = $row['item_discount_per'];
                    $invoiceChild->total_amount = $row['item_total_after_discount'];
                    $invoiceChild->save();
                    $invoiceChild_id = $invoiceChild->id;
                    $invChildIds[] = $invoiceChild->id;
                    $invno = $request->id;

                    $ItemInventory = InvoiceChild::where('invoice_id', $invno)->select('id')->get();
                    ItemInventory::where('invoice_id', $invoiceChild_id)->delete();

                    $stockChilddata = new ItemInventory();
                    $stockChilddata->invoice_id = $invoiceChild_id;
                    $stockChilddata->inventory_type_id = 9;
                    $stockChilddata->item_id = $row['item_id'];
                    $stockChilddata->batch_no = $row['batchDetails']['batch_no'];
                    $stockChilddata->manufacture_id = $row['batchDetails']['manufacturer_id'];
                    $stockChilddata->expiry_date = $row['batchDetails']['batchExpiry'];
                    $stockChilddata->store_id = $request->store_id;
                    $stockChilddata->quantity_out = $row['quantity'];
                    $stockChilddata->date = $request->date;
                    $stockChilddata->is_dummy = 1;
                    $stockChilddata->save();
                }

                // InvoiceChild::where('invoice_id', $request->id)->whereNotIn('id', $invChildIds)->delete();
            });

            return ['status' => "ok", 'message' => 'Dummy Invoice Update successfully'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
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
            'id' => 'required|int|exists:invoices,id',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {

            $parentData = Invoice::where('id', $request->id)->first();
            $returninvoice = InvoiceReturn::where('invoice_id', $request->id)->first();
            if ($parentData->is_approved == 1) {
                return ['status' => 'error', 'message' => "You Can't Delete  Approved Invoice"];
            } elseif ($returninvoice) {
                return ['status' => 'error', 'message' => "You Can't Delete Invoice because it has return invoices"];
            }

            DB::transaction(function () use ($request) {
                $invno = $request->id;
                // Start

                $InvoiceParent = Invoice::find($request->id);
                if ($InvoiceParent->po_id != null && $InvoiceParent->quotation_id == null) {
                    $SalePO = SalePO::find($InvoiceParent->po_id);
                    if ($SalePO) {
                        $SalePO->is_inv_generated = 0;
                        $SalePO->save();
                    }
                    // return $SalePO;
                }
                if ($InvoiceParent->po_id == null && $InvoiceParent->quotation_id != null) {
                    $Quotation = Quotation::find($InvoiceParent->quotation_id);
                    if ($Quotation) {
                        $Quotation->is_inv_generated = 0;
                        $Quotation->save();
                    }
                    // return $Quotation;
                }
                // End

                // Get invoice children before deleting
                $invoiceChildren = InvoiceChild::where('invoice_id', $invno)->get();

                // Group items by item_id for average cost calculation
                $groupedItems = [];
                foreach ($invoiceChildren as $child) {
                    $itemId = $child->item_id;
                    if (!isset($groupedItems[$itemId])) {
                        $groupedItems[$itemId] = [
                            'quantity' => 0,
                            'total' => 0,
                        ];
                    }

                    $groupedItems[$itemId]['quantity'] += (float) $child->quantity;
                    $groupedItems[$itemId]['total'] += (float) ($child->cost * $child->quantity);
                }

                // Calculate average cost BEFORE deleting ItemInventory records
                // We need to reverse the sale by adding back the sold quantity and cost
                foreach ($groupedItems as $itemId => $data) {
                    // Get current item
                    $item = Item::find($itemId);
                    if (!$item) {
                        continue;
                    }
                    
                    // Get current stock BEFORE ItemInventory deletion (still includes quantity_out from invoice)
                    $currentStock = Item::calculateTotalStockQty($itemId);
                    
                    // Calculate total amount using current avg_cost and stock
                    // This is the correct approach for average costing
                    $currentTotalAmount = $item->avg_cost * $currentStock;

                    // Calculate new stock and total amount (add back the sold quantity and cost)
                    // After deleting ItemInventory, stock will increase by sold quantity
                    // So new stock = current stock (with sale) + sold quantity = stock before sale
                    $newStockQty = $currentStock + $data['quantity'];
                    // New total amount = current amount + sold cost = amount before sale
                    $newTotalAmount = $currentTotalAmount + $data['total'];

                    // Calculate new average cost
                    if ($newStockQty > 0) {
                        $newAvgCost = $newTotalAmount / $newStockQty;
                    } else {
                        $newAvgCost = 0;
                    }

                    // Update the item's average cost
                    $item->avg_cost = $newAvgCost;
                    $item->save();
                }

                // Delete ItemInventory records (this removes quantity_out entries, adding stock back)
                $ItemInventory = InvoiceChild::where('invoice_id', $invno)->select('id')->get();
                if ($ItemInventory) {
                    foreach ($ItemInventory as $ItemInventory) {
                        ItemInventory::where('invoice_id', $ItemInventory->id)->delete();
                    }
                }
                
                // Delete invoice children and parent
                InvoiceChild::where(['invoice_id' => $request->id])->delete();
                $parentDelete = Invoice::where(['id' => $request->id])->delete();

                $voucher = Voucher::where('invoice_id', $request->id)->select('id')->get();

                if ($voucher) {
                    foreach ($voucher as $voucher) {
                        Voucher::where('id', $voucher->id)->delete();
                        VoucherTransaction::where('voucher_id', $voucher->id)->delete();
                    }
                }
            });

            return ['status' => 'ok', 'message' => 'Invoice Deleted Successfully'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    /**
     *  Invoice Details for table.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getSalesDetails(Request $req)
    {
        try {
            $invoices = InvoiceChild::with('invoiceNo', 'item')
                ->get();
            return ['invoices_child' => $invoices];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Sales Report.
     * @param  \Illuminate\Http\Request  $sale_type
     * @param  \Illuminate\Http\Request  $walk_in_customer_name
     * @param  \Illuminate\Http\Request  $customer_id
     * @param  \Illuminate\Http\Request  $item_id
     * @return \Illuminate\Http\Response
     */
    public function getSalesReport(Request $req)
{
    $sale_type = $req->sale_type;
    $walk_in_customer_name = $req->walk_in_customer_name;
    $customer_id = $req->customer_id;
    $item_ids = $req->item_id ? explode(',', $req->item_id) : [];
    $from = $req->from_date;
    $to = $req->to_date;

    try {
        $salesReport = Invoice::with([
                'invoiceChild' => function ($q) use ($item_ids) {
                    if (!empty($item_ids)) {
                        $q->whereIn('item_id', $item_ids);
                    }
                },
                'customer',
                'salesrep',
                'store',
                'invoiceReturn'
            ])
            ->when($sale_type, function ($query, $sale_type) {
                $query->where('sale_type', $sale_type);
            })
            ->when($walk_in_customer_name, function ($query, $walk_in_customer_name) {
                $query->where('walk_in_customer_name', 'LIKE', '%' . $walk_in_customer_name . '%');
            })
            ->when($customer_id, function ($query, $customer_id) {
                $query->where('customer_id', $customer_id);
            })
            ->when(!empty($item_ids), function ($query) use ($item_ids) {
                $query->whereHas('invoiceChild', function ($q) use ($item_ids) {
                    $q->whereIn('item_id', $item_ids);
                });
            })
            ->when($from, function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            })
            ->get();

        return ['salesReport' => $salesReport];
    } catch (\Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}



    /**
     * Manufacturewise Report.
     * @param  \Illuminate\Http\Request  $sale_type
     * @param  \Illuminate\Http\Request  $walk_in_customer_name
     * @param  \Illuminate\Http\Request  $customer_id
     * @param  \Illuminate\Http\Request  $manufacturer_id
     * @param  \Illuminate\Http\Request  $from
     * @param  \Illuminate\Http\Request  $to
     * @return \Illuminate\Http\Response
     */

public function getSalesReportManufacturewise(Request $req)
{
    $sale_type = $req->sale_type;
    $customer_id = $req->customer_id;
    $manufacture_ids = $req->manufacture_id ? explode(',', $req->manufacture_id) : [];
    $from = $req->from_date;
    $to = $req->to_date;

    try {
        $manufacturers = Person::whereHas('personType', function ($q) {
                $q->where('id', 3); // Manufacturer
            })
            ->whereHas('itemManufacture.invoiceChild.invoice', function ($q) use ($sale_type, $customer_id, $from, $to) {
                if ($sale_type) {
                    $q->where('sale_type', $sale_type);
                }
                if ($customer_id) {
                    $q->where('customer_id', $customer_id);
                }
                if ($from && $to) {
                    $q->whereBetween('date', [$from, $to]);
                }
            })
            ->when(!empty($manufacture_ids), function ($q) use ($manufacture_ids) {
                $q->whereIn('id', $manufacture_ids);
            })
            ->with(['itemManufacture' => function ($q) use ($sale_type, $customer_id, $from, $to) {
                $q->with(['invoiceChild' => function ($q) use ($sale_type, $customer_id, $from, $to) {
                    $q->whereHas('invoice', function ($q) use ($sale_type, $customer_id, $from, $to) {
                        if ($sale_type) {
                            $q->where('sale_type', $sale_type);
                        }
                        if ($customer_id) {
                            $q->where('customer_id', $customer_id);
                        }
                        if ($from && $to) {
                            $q->whereBetween('date', [$from, $to]);
                        }
                    })->with(['invoice' => function ($q) use ($sale_type, $customer_id, $from, $to) {
                        if ($sale_type) {
                            $q->where('sale_type', $sale_type);
                        }
                        if ($customer_id) {
                            $q->where('customer_id', $customer_id);
                        }
                        if ($from && $to) {
                            $q->whereBetween('date', [$from, $to]);
                        }
                    }]);
                }]);
            }, 'personType'])
            ->get();

        return ['invoices' => $manufacturers];
    } catch (\Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}




    /**
     * Customer Wise Sales Report.
     * @param  \Illuminate\Http\Request  $sale_type
     * @param  \Illuminate\Http\Request  $customer_id
     * @param  \Illuminate\Http\Request  $from
     * @param  \Illuminate\Http\Request  $to
     * @return \Illuminate\Http\Response
     */

    public function getSalesReportCustomerWise(Request $req)
{
    $sale_type = $req->sale_type;
    $customer_id = $req->customer_id;
    $item_ids = $req->item_id; // can be array or string
    $from = $req->from_date;
    $to = $req->to_date;

    try {
        // Handle Registered Customers (sale_type = 2)
        $invoices = Person::with(['invoice' => function ($query) use ($item_ids, $from, $to) {
            $query->with(['invoiceChild' => function ($query) use ($item_ids) {
                $query->when($item_ids, function ($query) use ($item_ids) {
                    $query->whereIn('item_id', is_array($item_ids) ? $item_ids : explode(',', $item_ids));
                });
            }])
            ->when($from && $to, function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            });
        }, 'personType'])
        ->when($customer_id, function ($query) use ($customer_id) {
            $query->where('id', $customer_id);
        })
        ->when($sale_type, function ($query) use ($sale_type) {
            $query->whereHas('invoice', function ($q) use ($sale_type) {
                $q->where('sale_type', $sale_type);
            });
        })
        ->get();

        // Handle Walk-in Customers (sale_type = 1)
        if ($sale_type == '1') {
            $invoices = Invoice::with(['invoiceChild' => function ($query) use ($item_ids) {
                $query->when($item_ids, function ($query) use ($item_ids) {
                    $query->whereIn('item_id', is_array($item_ids) ? $item_ids : explode(',', $item_ids));
                });
            }, 'store'])
            ->when($from && $to, function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            })
            ->when($customer_id, function ($query) use ($customer_id) {
                $query->where('customer_id', $customer_id);
            })
            ->when($sale_type, function ($query) use ($sale_type) {
                $query->where('sale_type', $sale_type);
            })
            ->get();
        }

        return ['invoices' => $invoices];
    } catch (\Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}


    public function getSalesReportSalesRepWise(Request $req)
    {
        $sale_type = $req->sale_type;
        $sales_rep_id = $req->sales_rep_id;
        $store_id = $req->store_id;
        $from = $req->from_date;
        $to = $req->to_date;
        try {
            $invoices = Person::with('salesRepInvoice', 'personType')
                ->when($from, function ($query, $from) use ($to) {
                    $query->whereHas('salesRepInvoice', function ($query) use ($from, $to) {
                        $query->whereBetween('date', [$from, $to]);
                    });
                })
                ->when($sales_rep_id, function ($query, $sales_rep_id) {
                    $query->where('id', $sales_rep_id);
                })

                ->when($sale_type, function ($query, $sale_type) {
                    $query->whereHas('salesRepInvoice', function ($query) use ($sale_type) {
                        $query->where('sale_type', '=', $sale_type);
                    });
                })

            // ->wherehas('personType', function ($query) use ($store_id) {
            //     $query->where('id', 4);
            // })

                ->get();
            // $coa_id=CoaAccount::where('')
            // $coaAccountLedgerBal = CoaAccount::getCoaAccountBal($plot->coa_account_id);

            return ['invoices' => $invoices];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    public function getSalesHistory(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required|int',
        ]);

        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            $invoice_id = $req->id;

            $parentData = Invoice::with('store', 'customer', 'posale', 'quotation')->find($req->id);

            $childData = InvoiceChild::with('item', 'manufacturer')->where('invoice_id', $req->id)->get();
            // $parentData->$childData = $childData;
            $Salehistory = InvoiceReturn::with('invoice', 'invoiceChild')
                ->where('invoice_id', $invoice_id)
                ->get();
            $parentData->childData = $childData;
            $parentData->Salehistory = $Salehistory;
            return ['parentData' => $parentData];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
