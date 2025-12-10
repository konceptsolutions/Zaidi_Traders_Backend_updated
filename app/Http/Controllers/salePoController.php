<?php

namespace App\Http\Controllers;

use App\Models\AdjustInventoryChild;
use App\Models\CoaAccount;
use App\Models\Invoice;
use App\Models\InvoiceChild;
use App\Models\Item;
use App\Models\ItemInventory;
use App\Models\Person;
use App\Models\PurchaseOrderChild;
use App\Models\SalePO;
use App\Models\SalePoChild;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use App\Services\CustomErrorMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class salePoController extends Controller
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
            $customer_id = $req->customer_id;
            $sales_rep_id = $req->sales_rep_id;
            $po_no = $req->po_no;
            $store_id = $req->store_id;
            $store_type_id = $req->store_type_id;
            $from = $req->from_date;
            $to = $req->to_date;
            $searcField = $req->searcField;

            $purchaseorderlist = SalePO::with('customer', 'store', 'salesrep')
                ->when($customer_id, function ($q, $customer_id) {
                    return $q->where('person_id', $customer_id);
                })
                ->when($sales_rep_id, function ($q, $sales_rep_id) {
                    return $q->where('salesrep', $sales_rep_id);
                })
                ->when($po_no, function ($q, $po_no) {
                    return $q->where('po_no', $po_no);
                })
                ->when($store_id, function ($q, $store_id) {
                    return $q->where('store_id', $store_id);
                })

                ->when($store_type_id, function ($query) use ($store_type_id) {
                    $query->whereHas('store', fn($q) => $q->where('store_type_id', '=', $store_type_id));
                })
                ->when($from, function ($q, $from) {
                    return $q->where('request_date', '>=', $from);
                })
                ->when($to, function ($q, $to) {
                    return $q->where('request_date', '<=', $to);
                })
                ->when($searcField, function ($q, $searcField) {
                    return $q->where('remarks', 'LIKE', '%' . $searcField . '%');
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
     * Displaying latest po + 1.
     *
     * @return \Illuminate\Http\Response
     */
    public function getLatestSalepono()
    {
        try {
            $po_no = SalePO::orderBy('id', 'desc')->first();
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
     * @param  \Illuminate\Http\Request  $po_no
     * @param  \Illuminate\Http\Request  $customer_id
     * @param  \Illuminate\Http\Request  $store_id
     * @param  \Illuminate\Http\Request  $request_date
     * @param  \Illuminate\Http\Request  $remarks
     * -------------------childArray
     * @param  \Illuminate\Http\Request  $item_id
     * @param  \Illuminate\Http\Request  $batch_no
     * @param  \Illuminate\Http\Request  $pack
     * @param  \Illuminate\Http\Request  $quoted_rate
     * @param  \Illuminate\Http\Request  $rate
     * @param  \Illuminate\Http\Request  $quantity
     * @param  \Illuminate\Http\Request  $total
     * @param  \Illuminate\Http\Request  $remarks
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = array(
            'customer_id' => 'required|int|exists:people,id',
            'request_date' => 'required',
            'childArray' => 'required|array',
            'childArray.*.item_id' => 'required|int',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {

                $purchaseorder = new SalePO();
                $purchaseorder->po_no = $request->po_no;
                $purchaseorder->person_id = $request->customer_id;
                $purchaseorder->sales_rep_id = $request->sales_rep_id;
                $purchaseorder->remarks = $request->remarks;
                $purchaseorder->request_date = $request->request_date;
                $purchaseorder->end_date = $request->end_date;
                $purchaseorder->save();
                $purchase_id = $purchaseorder->id;

                foreach ($request->childArray as $row) {
                    $purchaseChilddata = new SalePoChild();
                    $purchaseChilddata->purchase_order_id = $purchase_id;
                    $purchaseChilddata->item_id = $row['item_id'];
                    $purchaseChilddata->quoted_rate = $row['quoted_rate'];
                    $purchaseChilddata->rate = $row['quoted_rate'];
                    $purchaseChilddata->quantity = $row['quantity'];
                    $purchaseChilddata->total = $row['total'];
                    $purchaseChilddata->save();
                }
            });

            return ['status' => "ok", 'message' => 'Sale Purchase order created successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    /**
     * Approve or unapprove voucher
     *
     * @param \Illuminate\Http\Request voucher_id
     * @return \Illuminate\Http\Response
     */
    public function approveSalePo(Request $req)
    {
        $rules = array(
            'id' => 'required|int',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $Purchaseorder = SalePO::find($req->id);
        if (!$Purchaseorder) {
            return ['status' => "error", 'message' => 'PurchaseOrder Not found'];
        }
        $message = '';
        DB::transaction(function () use ($req, $Purchaseorder, &$message) {
            $isApproved = $Purchaseorder->is_approved == 1 ? 0 : 1;
            $message = $Purchaseorder->is_approved == 1 ? 'Unapproved' : 'Approved';
            $Purchaseorder->is_approved = $isApproved;
            $Purchaseorder->save();
        });
        return ['status' => "ok", 'message' => 'Purchaseorder ' . $message . ' successfully'];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
            'id' => 'required|int|exists:sales_po,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $purchaseOrder = SalePO::with('customer', 'store', 'salesrep')->find($req->id);

            $poChild = SalePoChild::with('item')->where('purchase_order_id', $req->id)->get();
            $childItem = [];
            foreach ($poChild as $child) {

                $itemsInv = Item::with('itemAvaiableInventory')->where('id', $child->item_id)->first();

                if ($itemsInv->itemAvaiableInventory) {

                    $qty_available = $itemsInv->itemAvaiableInventory->item_available;
                } else {
                    $qty_available = 0;
                }

                $AvgPrice = PurchaseOrderChild::getStoredAveragePrice($child->item_id);

                $batchOptions = [];

                $items = Item::with('iteminventory')->where('id', $child->item_id)->orderBy('id')->first();

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
                $itemname[] = array(
                    'id' => $child->id,
                    'item_id' => $child->item_id,
                    'item_name' => $child->item->name,
                    'quantity' => $child->quantity,
                    'received_quantity' => $child->received_quantity,
                    'quoted_rate' => $child->quoted_rate,
                    'rate' => $child->rate,
                    'avg_price' => $AvgPrice ?? 0,
                    'total' => $child->total,
                    'returned_quantity' => 0,
                    'remarks' => $child->remarks,
                    'qty_available' => $qty_available,
                    'batchOptions' => $batchOptions,
                    'items' => $items,
                );
            }
            $data = array(
                "id" => $purchaseOrder->id,
                "customer_id" => $purchaseOrder->person_id,
                "sales_rep_id" => $purchaseOrder->sales_rep_id,
                "store_id" => $purchaseOrder->store_id,
                "po_no" => $purchaseOrder->po_no,
                "remarks" => $purchaseOrder->remarks,
                "is_received" => $purchaseOrder->is_received,
                "is_approved" => $purchaseOrder->is_approved,
                "is_cancel" => $purchaseOrder->is_cancel,
                "request_date" => $purchaseOrder->request_date,
                "total" => $purchaseOrder->total,
                "discount" => $purchaseOrder->discount,
                "tax" => $purchaseOrder->tax,
                "total_after_tax" => $purchaseOrder->total_after_tax,
                "tax_in_figure" => $purchaseOrder->tax_in_figure,
                "total_after_discount" => $purchaseOrder->total_after_discount,
                "discount" => $purchaseOrder->discount,
                "customer_name" => $purchaseOrder->customer->name,
                "store_name" => $purchaseOrder->store->name ?? '',
                "childArray" => $itemname,
            );
            return ['data' => $data];
        } catch (\Exception $e) {
            return $e;
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function editSalePurchaseOrderForInitiate(Request $req)
    {
        $rules = array(
            'id' => 'required|int|exists:sales_po,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $purchaseOrder = SalePO::with('customer', 'store', 'salesrep')->find($req->id);

            $poChild = SalePoChild::with('item')->where('purchase_order_id', $req->id)->get();
            $childItem = [];
            foreach ($poChild as $child) {

                $itemsInv = Item::with('itemAvaiableInventory')->where('id', $child->item_id)->first();

                if ($itemsInv->itemAvaiableInventory) {

                    $qty_available = $itemsInv->itemAvaiableInventory->item_available;
                } else {
                    $qty_available = 0;
                }

                $AvgPrice = PurchaseOrderChild::getStoredAveragePrice($child->item_id);

                $batchOptions = [];

                $items = Item::with('iteminventory')->where('id', $child->item_id)->orderBy('id')->first();

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

                $itemname[] = array(
                    'id' => $child->id,
                    'item_id' => $child->item_id,
                    'item_name' => $child->item->name,
                    'quantity' => $child->quantity,
                    'received_quantity' => $child->received_quantity,
                    'quoted_rate' => $child->quoted_rate,
                    'rate' => $child->rate,
                    'discount' => '',
                    'avg_price' => $AvgPrice ?? 0,
                    'total' => $child->total,
                    'returned_quantity' => 0,
                    'remarks' => $child->remarks,
                    'qty_available' => $qty_available,
                    'batchOptions' => $batchOptions,
                    'items' => $items,
                );
            }
            $data = array(
                "id" => $purchaseOrder->id,
                "customer_id" => $purchaseOrder->person_id,
                "sales_rep_id" => $purchaseOrder->sales_rep_id,
                "store_id" => $purchaseOrder->store_id,
                "po_no" => $purchaseOrder->po_no,
                "remarks" => $purchaseOrder->remarks,
                "is_received" => $purchaseOrder->is_received,
                "is_approved" => $purchaseOrder->is_approved,
                "is_cancel" => $purchaseOrder->is_cancel,
                "request_date" => $purchaseOrder->request_date,
                "total" => $purchaseOrder->total,
                "discount" => $purchaseOrder->discount,
                "tax" => $purchaseOrder->tax,
                "total_after_tax" => $purchaseOrder->total_after_tax,
                "tax_in_figure" => $purchaseOrder->tax_in_figure,
                "total_after_discount" => $purchaseOrder->total_after_discount,
                "discount" => $purchaseOrder->discount,
                "customer_name" => $purchaseOrder->customer->name,
                "store_name" => $purchaseOrder->store->name ?? '',
                "childArray" => $itemname,
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
    public function update(Request $request)
    {
        $rules = array(
            'id' => 'required|int|exists:sales_po,id',
            'customer_id' => 'required|int|exists:people,id',
            'request_date' => 'required',
            'childArray' => 'required|array',
            'childArray.*.item_id' => 'required|int',
            'childArray.*.quantity' => 'required|int',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {

                $purchaseorder = SalePO::find($request->id);
                $purchaseorder->person_id = $request->customer_id;
                $purchaseorder->po_no = $request->po_no;
                $purchaseorder->sales_rep_id = $request->sales_rep_id;
                $purchaseorder->remarks = $request->remarks;
                $purchaseorder->request_date = $request->request_date;
                $purchaseorder->save();

                $poChildIds = [];
                foreach ($request->childArray as $row) {

                    if (isset($row['id'])) {
                        $purchaseChilddata = SalePoChild::find($row['id']);
                    } else {
                        $purchaseChilddata = new SalePoChild();
                    }
                    $purchaseChilddata->purchase_order_id = $request->id;
                    $purchaseChilddata->item_id = $row['item_id'];
                    $purchaseChilddata->quoted_rate = $row['quoted_rate'];
                    $purchaseChilddata->rate = $row['rate'];
                    $purchaseChilddata->total = $row['total'];
                    $purchaseChilddata->quantity = $row['quantity'];
                    $purchaseChilddata->remarks = $row['remarks'];
                    $purchaseChilddata->save();
                    $purchaseChilddata->id;
                    //  return  $poChildIds[] = $row[$purchaseChilddata->id];
                    $poChildIds[] = $purchaseChilddata->id;
                }
                SalePoChild::where('purchase_order_id', $request->id)->whereNotIn('id', $poChildIds)->delete();
            });

            return ['status' => "ok", 'message' => 'Sale Purchase Order Update successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    /**
     * Show the form for editing the Purchase Oder.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ViewSalePODetails(Request $req)
    {
        $rules = [
            'id' => 'required|int|exists:sales_po,id',
        ];
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            // Fetch the Purchase Order and associated Customer and Store
            $purchaseOrder = SalePO::with('customer', 'store')->find($req->id);

            // Fetch the related children (items) of the Purchase Order
            $poChild = SalePoChild::with('item')->where('purchase_order_id', $req->id)->get();

            // Iterate over the PO children to add batch_no and expiry_date
            foreach ($poChild as $child) {
                // Initialize the batch_no and expiry_date as empty strings
                $batchNos = '';
                $expiryDates = '';

                // Fetch the item and its related inventory
                $items = Item::with('itemInventory')->where('id', $child->item_id)->first();

                if ($items && $items->itemInventory) {
                    // Loop through the item inventory and concatenate the batch_no and expiry_date
                    foreach ($items->itemInventory as $inventory) {
                        $batchNos .= $inventory->batch_no . ', ';
                        $expiryDates .= $inventory->expiry_date . ', ';
                    }

                    // Remove trailing commas
                    $batchNos = rtrim($batchNos, ', ');
                    $expiryDates = rtrim($expiryDates, ', ');
                }

                // Assign the concatenated values to the child
                $child->batch_no = $batchNos;
                $child->expiry_date = $expiryDates;
            }

            // Return the purchase order and its children
            return ['purchaseOrder' => $purchaseOrder, 'poChild' => $poChild];
        } catch (\Exception $e) {
            // Return custom error messages
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }



    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $req)
    {
        $rules = array(
            'id' => 'required|int|exists:sales_po,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $SalePO = SalePO::find($req->id);

        $InvoiceParent = Invoice::where('po_id', $req->id)->first();
        if ($SalePO->is_inv_generated == 1) {
            return ['status' => "error", 'message' => 'Sales PO already Initiated, cannot be deleted'];
        }
        if ($InvoiceParent) {
            return ['status' => "error", 'message' => 'Sales PO has Invoices, cannot be deleted'];
        }
        try {
            if ($SalePO->is_approved == 0) {
                DB::transaction(function () use ($req) {
                    $deletePO = SalePO::where('id', $req->id)->delete();
                    $deletePOChild = SalePoChild::where('purchase_order_id', $req->id)->delete();
                });
            } else {
                return ['status' => "error", 'message' => 'Sales PO is Approved Can not be Delete'];
            }
            return ['status' => "ok", 'message' => 'Sale PO Delete successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $id
     * @param  \Illuminate\Http\Request  $store_id
     * @param  \Illuminate\Http\Request  $customer_id
     * @param  \Illuminate\Http\Request  $quotation_id
     * @param  \Illuminate\Http\Request  $invoice_no
     * @param  \Illuminate\Http\Request  $amount_received
     * @param  \Illuminate\Http\Request  $total_amount
     * @param  \Illuminate\Http\Request  $walk_in_customer_name
     * @param  \Illuminate\Http\Request  $date
     * @param  \Illuminate\Http\Request  $remarks
     * -------------------childArray
     * @param  \Illuminate\Http\Request  $item_id
     * @param  \Illuminate\Http\Request  $batch_no
     * @param  \Illuminate\Http\Request  $expiry_date
     * @param  \Illuminate\Http\Request  $rate
     * @param  \Illuminate\Http\Request  $sales_tax
     * @param  \Illuminate\Http\Request  $quantity
     * @param  \Illuminate\Http\Request  $amount
     * @return \Illuminate\Http\Response
     */
    public function generateInvoiceSalePo(Request $request)
    {

        $rules = array(
            'date' => 'required',
            'childArray' => 'required|array',
            'childArray.*.item_id' => 'required|int',
            'childArray.*.quantity' => 'required|int',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
        $quotation = SalePO::find($request->id);
        if ($quotation->is_inv_generated == 0) {
            DB::transaction(function () use ($request) {

                $adv_tax = $request->adv_tax;
                $invoice = new Invoice();
                $invoice->customer_id = $request->customer_id;
                $invoice->sales_rep_id = $request->sales_rep_id;
                $invoice->walk_in_customer_name = $request->walk_in_customer_name;
                $invoice->store_id = $request->store_id;
                $invoice->po_id = $request->id;
                $invoice->sale_type = 2;
                $invoice->total_amount = $request->total_amount;
                $invoice->amount_received = $request->amount_received;
                $invoice->bank_amount_received = $request->bank_amount_received;
                $invoice->date = $request->date;
                $invoice->account_id = $request->account_id;
                $invoice->bank_account_id = $request->bank_account_id;
                $invoice->remarks = $request->remarks;
                $invoice->pono = $request->po_no;
                $invoice->tax_type = $request->tax_type;
                $invoice->adv_tax_percentage = $request->adv_tax_percentage;
                $invoice->adv_tax = $request->adv_tax;
                $invoice->save();
                $invoice_id = $invoice->id;
                $invoice_no = $invoice->invoice_no;

                $remarks = " SalePO ";
                $supplier_coa_account_id = CoaAccount::where([['person_id', $request->customer_id]])->select('id', 'coa_group_id', 'coa_sub_group_id')->first();
                $customer_id = Person::where('id', $request->customer_id)->value('name');
                $is_post_dated = isset($request->cheque_no) ? 1 : 0;
                $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
                ///// cost jv voucher
                $voucherInventoryCost = new Voucher();
                $voucherInventoryCost->voucher_no = $newVoucherNo;
                $voucherInventoryCost->date = date('y-m-d');
                $voucherInventoryCost->name = "Inventory Cost Out" . ", Invoice no: " . $invoice_no;
                $voucherInventoryCost->invoice_id = $invoice_id;
                $voucherInventoryCost->type = 3;
                $voucherInventoryCost->isApproved = 1;
                $voucherInventoryCost->generated_at = date('y-m-d');
                $voucherInventoryCost->total_amount = $request->total_after_discount;
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

                    $invoiceChild = new InvoiceChild();
                    $invoiceChild->invoice_id = $invoice_id;
                    $invoiceChild->item_id = $row['item_id'];
                    $invoiceChild->quantity = $row['quantity'];
                    $invoiceChild->rate = $row['rate'];
                    // $invoiceChild->sales_tax  = $row['sales_tax'];
                    $invoiceChild->amount = $row['total'];
                    $invoiceChild->discount = $row['discount'];
                    $invoiceChild->item_discount_per = $row['item_discount_per'];
                    $invoiceChild->total_amount = $row['rate'] * $row['quantity'];
                    $invoiceChild->save();

                    $invoiceChild_id = $invoiceChild->id;

                    $itemUpdate = Item::find($row['item_id']);
                    $itemUpdate->rate = $row['rate'];
                    $itemUpdate->save();

                    $stockChilddata = new ItemInventory();
                    $stockChilddata->invoice_id = $invoiceChild_id;
                    $stockChilddata->inventory_type_id = 8;
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
                    $voucherTransaction->date = date('y-m-d');
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
                    $voucherTransaction->date = date('y-m-d');
                    $voucherTransaction->coa_account_id = 3;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = $row['avg_price'] * $row['quantity'];
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->description = $itemName . " Inventory Sold. " . '' . "Avg Cost:" . '' . round($row['avg_price']) . ' ' . "," . ' ' . "Qty" . " " . $row['quantity'] . " ,Invoice no: " . $invoice_no;
                    $voucherTransaction->save();
                    $debitside += $row['avg_price'] * $row['quantity'];
                }
                $totalCashSaleVoucher = 0;
                $quotation_inv = SalePO::find($request->id);
                $quotation_inv->is_inv_generated = 1;
                $quotation_inv->save();

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
                    $voucherRevenueGen->date = date('y-m-d');
                    $voucherRevenueGen->name = "Revenue Generated" . " ,Invoice no: " . $invoice_no;
                    $voucherRevenueGen->invoice_id = $invoice_id;
                    $voucherRevenueGen->type = 2;
                    $voucherRevenueGen->isApproved = 1;
                    $voucherRevenueGen->generated_at = date('y-m-d');
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
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = 4;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $list['rate'] * $list['quantity'];
                        $voucherTransaction->description = $itemName . " revenue . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $list['rate'] * $list['quantity'];
                    }
                    $totalCashSaleVoucher += $request->amount_received;
                    //---------------------Cash 1 Debit ------------------
                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucherRevGen_id;
                    $voucherTransaction->date = date('y-m-d');
                    $voucherTransaction->coa_account_id = $request->account_id;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->debit = $request->amount_received;
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->description = "Invoice no: " . $invoice_no . " Cash Received";
                    $voucherTransaction->save();
                    $debitside += $request->amount_received;
                    if ($request->bank_amount_received > 0) {
                        //---------------------Bank Debit ------------------
                        $totalCashSaleVoucher += $request->bank_amount_received;
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = $request->bank_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->bank_amount_received;
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = "Invoice no: " . $invoice_no . " Cash Received By bank";
                        $voucherTransaction->save();
                        $debitside += $request->bank_amount_received;
                    }
                    ///////gst voucher
                    if ($request->adv_tax > 0) {
                        //----------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = 871;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $adv_tax;
                        $voucherTransaction->description = "Advance Tax " . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $adv_tax;
                    }
                    ///////gst voucher
                    if ($request->gst > 0) {
                        //   $totalCashSaleVoucher += $request->gst;
                        //---------------------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = date('y-m-d');
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
                    if ($request->discount > 0) {
                        $totalCashSaleVoucher += $request->discount;
                        //------------------- Debiting discount Expense ---

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = 28;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->discount;
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = 'Discount' . '' . ",Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $request->discount;
                    }
                    $voucher = Voucher::find($voucherRevenueGenid);
                    $voucher->total_amount = $totalCashSaleVoucher;
                    $voucher->save();
                } else {
                    ///////////////////
                    $customer_account = CoaAccount::where('person_id', $request->customer_id)
                        ->where('coa_sub_group_id', 9)->first();
                    $customer_account_id = $customer_account->id;

                    //////////
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
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = 4;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $list['rate'] * $list['quantity'];
                        $voucherTransaction->description = $itemName . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $list['rate'] * $list['quantity'];

                        //---------------------Cash 1 Debit ------------------

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $list['rate'] * $list['quantity'];
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = $itemName . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $list['rate'] * $list['quantity'];
                    }

                    if ($request->discount > 0) {
                        // $totalCreditSaleVoucher += $request->discount;
                        //---------------------Crediting Customer Account Deu To discount ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->discount;
                        $voucherTransaction->description = 'Discount' . '' . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $request->discount;

                        //---------------------Debiting discount Expense   ------------------

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = 28;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->discount;
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = 'Discount' . '' . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $request->discount;
                    }
                    if ($request->amount_received > 0) {
                        // $totalCashSaleVoucher += $request->amount_received;
                        $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
                        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                        $voucherRevenueGen = new Voucher();
                        $voucherRevenueGen->voucher_no = $newVoucherNo;
                        $voucherRevenueGen->date = date('y-m-d');
                        $voucherRevenueGen->name = "Customer Amount Received ";
                        $voucherRevenueGen->invoice_id = $invoice_id;
                        $voucherRevenueGen->type = 2;
                        $voucherRevenueGen->isApproved = 1;
                        $voucherRevenueGen->generated_at = date('y-m-d');
                        $voucherRevenueGen->total_amount = $request->amount_received;
                        $voucherRevenueGen->cheque_no = $request->cheque_no;
                        $voucherRevenueGen->cheque_date = $request->cheque_date;
                        $voucherRevenueGen->is_post_dated = $is_post_dated;
                        $voucherRevenueGen->is_auto = 1;
                        $voucherRevenueGen->save();
                        $voucherRevGen_id = $voucherRevenueGen->id;

                        //---------------------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->amount_received;
                        $voucherTransaction->description = "Amount received against " . " ,Invoice no: " . $invoice_no;
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
                        $voucherTransaction->description = $itemName . " " . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $request->amount_received;
                    }
                    ///bank
                    if ($request->bank_amount_received > 0) {

                        // $totalCashSaleVoucher += $request->amount_received;
                        // $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
                        // $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                        // $voucherRevenueGen = new Voucher();
                        // $voucherRevenueGen->voucher_no = $newVoucherNo;
                        // $voucherRevenueGen->date = date('y-m-d');
                        // $voucherRevenueGen->name =  "Customer Amount Received bank";
                        // $voucherRevenueGen->invoice_id = $invoice_id;
                        // $voucherRevenueGen->type = 2;
                        // $voucherRevenueGen->isApproved = 1;
                        // $voucherRevenueGen->generated_at = date('y-m-d');
                        // $voucherRevenueGen->total_amount = $request->bank_amount_received;
                        // $voucherRevenueGen->cheque_no = $request->cheque_no;
                        // $voucherRevenueGen->cheque_date = Land::changeDateFormat($request->cheque_date);
                        // $voucherRevenueGen->is_post_dated = $is_post_dated;
                        // $voucherRevenueGen->is_auto = 1;
                        // $voucherRevenueGen->save();
                        // $voucherRevGen_id = $voucherRevenueGen->id;

                        //---------------------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherRevGen_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->bank_amount_received;
                        $voucherTransaction->description = "Amount received against " . " ,Invoice no: " . $invoice_no;
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
                        $voucherTransaction->description = $itemName . " " . " sold . " . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $request->bank_amount_received;
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
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $request->gst;
                        $voucherTransaction->description = "Gst " . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $request->gst;
                        //---------------------Cash 1 Debit ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $request->gst;
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = "Gst " . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $request->gst;
                    }
                    /////// advance tax voucher
                    if ($request->adv_tax > 0) {

                        //-------------Crediting Reneve ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = 871;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $adv_tax;
                        $voucherTransaction->description = "Advance Tax credit " . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $creditside += $adv_tax;
                        //---------------------Cash 1 Debit ------------------
                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucherInvCost_id;
                        $voucherTransaction->date = date('y-m-d');
                        $voucherTransaction->coa_account_id = $customer_account_id;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->debit = $adv_tax;
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->description = "Advance Tax debit  " . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $adv_tax;
                    }
                }
                if ($creditside != $debitside) {
                    throw new \Exception('debit and credit sides are not equal');
                }
                $voucher = Voucher::find($voucherInvCost_id);
                $voucher->total_amount = $creditside;
                $voucher->save();
            });
            return ['status' => "ok", 'message' => 'Invoice from PO created successfully'];
        } elseif ($quotation->is_approved == 0) {
            return ['status' => "error", 'message' => 'Approve The SalePO'];
        } elseif ($quotation->is_inv_generated == 1) {
            return ['status' => "error", 'message' => 'SalePO already initiated'];
        } else {
            return ['status' => "error", 'message' => 'Error Intiating'];
        }
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
