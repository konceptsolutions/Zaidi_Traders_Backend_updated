<?php

namespace App\Http\Controllers;

use App\Models\AdjustInventoryChild;
use App\Models\CoaAccount;
use App\Models\Item;
use App\Models\ItemInventory;
use App\Models\ItemManufacture;
use App\Models\Person;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderChild;
use App\Models\ReturnPurchaseOrder;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use App\Services\CustomErrorMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }
    /**
     * Displaying latest po + 1.
     *
     * @return \Illuminate\Http\Response
     */
    public function getLatestpono()
    {
        try {
            $po_no = PurchaseOrder::orderBy('id', 'desc')->first();
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
     * Displaying purchase orders list.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPolist(Request $req)
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
            $from = $req->from_date;
            $from = $req->from_date;
            $po_type = $req->po_type;
            $to = $req->to_date;
            $searcField = $req->searcField;

            $purchaseorderlist = PurchaseOrder::with('supplier', 'store')
                ->when($supplier_id, function ($q, $supplier_id) {
                    return $q->where('person_id', $supplier_id);
                })
                ->when($po_no, function ($q, $po_no) {
                    return $q->where('po_no', $po_no);
                })
                ->when($po_type, function ($q, $po_type) {
                    return $q->where('po_type', $po_type);
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
                ->when($received_from, function ($q, $received_from) {
                    return $q->where('receive_date', '>=', $received_from);
                })
                ->when($received_to, function ($q, $received_to) {
                    return $q->where('receive_date', '<=', $received_to);
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
     * @param  \Illuminate\Http\Request  $supplier_id
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
            'po_no' => 'required|int',
            'supplier_id' => 'required|int|exists:people,id',
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

                $purchaseorder = new PurchaseOrder();
                $purchaseorder->person_id = $request->supplier_id;
                $purchaseorder->invoice_no = $request->invoice_no;
                $purchaseorder->po_type = $request->po_type;
                $purchaseorder->manufacture_id = $request->manufacturer_id;
                $purchaseorder->remarks = $request->remarks;
                $purchaseorder->request_date = $request->request_date;
                $purchaseorder->save();
                $purchase_id = $purchaseorder->id;

                foreach ($request->childArray as $row) {
                    $purchaseChilddata = new PurchaseOrderChild();
                    $purchaseChilddata->purchase_order_id = $purchase_id;
                    $purchaseChilddata->item_id = $row['item_id'];
                    $purchaseChilddata->pack = $row['pack'];
                    // $purchaseChilddata->batch_no = $row['batch_no'];
                    $purchaseChilddata->rate = $row['rate'];
                    $purchaseChilddata->quantity = $row['quantity'];
                    $purchaseChilddata->total = $row['total'];
                    $purchaseChilddata->save();

                    $itemUpdate = Item::find($row['item_id']);
                    $itemUpdate->pack = $row['pack'];
                    $itemUpdate->rate = $row['rate'];
                    $itemUpdate->save();
                }
            });

            return ['status' => "ok", 'message' => 'Purchase order created successfully'];
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
    public function approveOrUnapprovePO(Request $req)
    {
        $rules = array(
            'id' => 'required|int',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $Purchaseorder = PurchaseOrder::find($req->id);
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
     * Show the form for editing the Purchase Oder.
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
                    'pack' => $child->pack,
                    'batch_no' => $child->batch_no,
                    'purchase_price' => $child->rate,
                    'amount' => $child->total,
                    'cost' => 0,
                    'returned_quantity' => 0,
                    'remarks' => $child->remarks,
                    'manufacturer_id' => $child->manufacturer_id,
                    'expiry_date' => $child->expiry_date,
                    'po_type' => $child->po_type,
                    'manufacturerOptions' => $manufacturerOptions,
                );
            }
            $data = array(
                "id" => $purchaseOrder->id,
                "supplier_id" => $purchaseOrder->person_id,
                "store_id" => $purchaseOrder->store_id,
                "manufacturer_id" => $purchaseOrder->manufacture_id,
                "po_no" => $purchaseOrder->po_no,
                "invoice_no" => $purchaseOrder->invoice_no,
                "po_type" => $purchaseOrder->po_type,
                "remarks" => $purchaseOrder->remarks,
                "is_received" => $purchaseOrder->is_received,
                "is_approved" => $purchaseOrder->is_approved,
                "is_cancel" => $purchaseOrder->is_cancel,
                "request_date" => $purchaseOrder->request_date,
                "receive_date" => $purchaseOrder->receive_date,
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
                "childArray" => $itemname,
            );
            return ['data' => $data];
        } catch (\Exception $e) {
            return $e;
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function receivePObyid(Request $req)
    {

        $rules = array(
            'id' => 'required|int|exists:purchase_orders,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {

            $Purchaseorder = PurchaseOrder::find($req->id);
            if ($Purchaseorder->is_approved == 0) {
                return ['status' => "error", 'message' => 'Approve PurchaseOrder'];
            } else {
                $purchaseOrder = PurchaseOrder::with('supplier', 'store')->find($req->id);

                $poChild = PurchaseOrderChild::with('item')->where('purchase_order_id', $req->id)->get();
                $childItem = [];
                foreach ($poChild as $child) {
                    $itemname[] = array(
                        'id' => $child->id,
                        'item_id' => $child->item_id,
                        'item_name' => $child->item->name,
                        'quantity' => $child->quantity,
                        'received_quantity' => $child->returned_quantity,
                        // 'purchase_price' => $child->purchase_price > 0 ? $child->purchase_price : '',
                        'purchase_price' => $child->item->purchase_price,
                        'pack' => $child->pack,
                        'batch_no' => $child->batch_no,
                        'purchase_price' => $child->rate,
                        'amount' => $child->total,
                        'cost' => 0,
                        'returned_quantity' => 0,
                        'remarks' => $child->remarks,
                        'expiry_date' => $child->expiry_date,
                        'manufacturer_id' => $child->manufacturer_id,
                    );
                }
                $data = array(
                    "id" => $purchaseOrder->id,
                    "supplier_id" => $purchaseOrder->person_id,
                    "store_id" => $purchaseOrder->store_id,
                    "manufacturer_id" => $purchaseOrder->manufacture_id,
                    "invoice_no" => $purchaseOrder->invoice_no,
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
                    "total_after_tax" => $purchaseOrder->total_after_tax,
                    "adv_tax_percentage" => $purchaseOrder->adv_tax_percentage,
                    "adv_tax" => $purchaseOrder->adv_tax,
                    "tax_in_figure" => $purchaseOrder->tax_in_figure,
                    "total_after_discount" => $purchaseOrder->total_after_discount,
                    "discount" => $purchaseOrder->discount,
                    "supplier_name" => $purchaseOrder->supplier->name ?? '',
                    "store_name" => $purchaseOrder->store->name ?? '',
                    "childArray" => $itemname,
                );
                return ['data' => $data];
            }
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
            'id' => 'required|int|exists:purchase_orders,id',
            'supplier_id' => 'required|int|exists:people,id',
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
            $Purchaseorder = PurchaseOrder::find($request->id);
            if ($Purchaseorder->is_approved == 1) {
                return ['status' => "error", 'message' => 'PurchaseOrder cannot be modified'];
            } else {
                DB::transaction(function () use ($request) {

                    $purchaseorder = PurchaseOrder::find($request->id);
                    $purchaseorder->person_id = $request->supplier_id;
                    $purchaseorder->invoice_no = $request->invoice_no;
                    $purchaseorder->remarks = $request->remarks;
                    $purchaseorder->request_date = $request->request_date;
                    $purchaseorder->save();

                    $poChildIds = [];
                    foreach ($request->childArray as $row) {

                        if (isset($row['id'])) {
                            $purchaseChilddata = PurchaseOrderChild::find($row['id']);
                        } else {
                            $purchaseChilddata = new PurchaseOrderChild();
                        }
                        $purchaseChilddata->purchase_order_id = $request->id;
                        $purchaseChilddata->item_id = $row['item_id'];
                        $purchaseChilddata->pack = $row['pack'];
                        $purchaseChilddata->batch_no = $row['batch_no'] ?? '';
                        $purchaseChilddata->rate = $row['purchase_price'];
                        $purchaseChilddata->total = $row['amount'];
                        $purchaseChilddata->quantity = $row['quantity'];
                        $purchaseChilddata->save();
                        $purchaseChilddata->id;
                        //  return  $poChildIds[] = $row[$purchaseChilddata->id];
                        $poChildIds[] = $purchaseChilddata->id;
                    }
                    PurchaseOrderChild::where('purchase_order_id', $request->id)->whereNotIn('id', $poChildIds)->delete();
                });

                return ['status' => "ok", 'message' => 'Purchase Order Update successfully'];
            }
        } catch (\Exception $e) {
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
    public function destroy(Request $request)
    {
        $rules = array(
            'id' => 'required|int|exists:purchase_orders,id',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $parentData = PurchaseOrder::where('id', $request->id)->first();
        $returnpo = ReturnPurchaseOrder::where('po_id', $request->id)->first();
        if ($parentData->is_approved == 1) {
            return ['status' => 'error', 'message' => "You Can't Delete  Approved PO"];
        } elseif ($returnpo) {
            return ['status' => 'error', 'message' => "You Can't Delete PO because it has return PO's"];
        }
        try {
            DB::transaction(function () use ($request) {

                $deletePOChild = PurchaseOrderChild::where('purchase_order_id', $request->id)->delete();
                $deletePO = PurchaseOrder::where('id', $request->id)->delete();
            });
            return ['status' => "ok", 'message' => 'Purchase Order Deleted successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    /**
     * receiving purchase order
     *
     * @param  \Illuminate\Http\Request  $id
     * @param  \Illuminate\Http\Request  $store_id
     * @param  \Illuminate\Http\Request  $total
     * @param  \Illuminate\Http\Request  $discount
     * @param  \Illuminate\Http\Request  $tax
     * @param  \Illuminate\Http\Request  $tax_in_figure
     * @param  \Illuminate\Http\Request  $total_after_discount
     * @param  \Illuminate\Http\Request  $store_id
     * @param  \Illuminate\Http\Request  $remarks
     * -------------------childArray
     * @param  \Illuminate\Http\Request  $id
     * @param  \Illuminate\Http\Request  $item_id
     * @param  \Illuminate\Http\Request  $batch_no
     * @param  \Illuminate\Http\Request  $received_quantity
     * @param  \Illuminate\Http\Request  $rate
     * @param  \Illuminate\Http\Request  $pack
     * @param  \Illuminate\Http\Request  $total
     * @param  \Illuminate\Http\Request  $remarks
     * @return \Illuminate\Http\Response
     */
    public function receivePurchaseOrder(Request $request)
    {
        // return response()->json($request->all());
        $rules = array(
            'id' => 'required|int|exists:purchase_orders,id',
            'total' => 'required|numeric',
            'discount' => 'required|numeric',
            'tax' => 'required|numeric',
            'po_type' => 'required|numeric',
            'total_after_tax' => 'required|numeric',
            'tax_in_figure' => 'required|numeric',
            'total_after_discount' => 'required|numeric',
            'childArray' => 'required|array',
            'childArray.*.id' => 'required|int|exists:purchase_order_children,id',
            'childArray.*.received_quantity' => 'required|int',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        DB::beginTransaction();
        try {

            $purchaseorder = PurchaseOrder::find($request->id);
            $purchaseorder->store_id = $request->store_id;
            $purchaseorder->manufacture_id = $request->manufacturer_id;
            $purchaseorder->remarks = $request->remarks;
            $purchaseorder->is_received = 1;
            $purchaseorder->invoice_no = $request->invoice_no;
            $purchaseorder->total = $request->total;
            $purchaseorder->discount = $request->discount;
            $purchaseorder->tax = $request->tax;
            $purchaseorder->adv_tax_percentage = $request->adv_tax_percentage;
            $purchaseorder->adv_tax = $request->adv_tax;
            $purchaseorder->receive_date = $request->receive_date;
            $purchaseorder->total_after_tax = $request->total_after_tax;
            $purchaseorder->tax_in_figure = $request->tax_in_figure;
            $purchaseorder->total_after_discount = $request->total_after_discount;
            $purchaseorder->save();

            /*===== delete ItemInventory =====*/
            foreach ($request->childArray as $row) {
                $purchaseChilddata = PurchaseOrderChild::find($row['id']);
                $ItemInventory = ItemInventory::where('purchase_order_id', $purchaseChilddata->id)->get();
                foreach ($ItemInventory as $item) {
                    $item->delete();
                }
            }
            /*===== End delete ItemInventory =====*/

            // Validate expiry dates first
            foreach ($request->childArray as $row) {
                if ($request->po_type == 1) {
                    $a = $row['manufacturer_id'];
                } elseif ($request->po_type == 2) {
                    $a = $request->manufacturer_id;
                }

                $CheckExpDate = ItemInventory::where(['batch_no' => $row['batch_no'], 'item_id' => $row['item_id'], 'manufacture_id' => $a])->first() ?? null;

                if ($CheckExpDate != null && $CheckExpDate->expiry_date != $row['expiry_date']) {
                    DB::rollback();
                    return ['status' => "error", 'message' => 'Batch already exists with different expiry Date' . ' ' . $row['batch_no'] . ' ' . '(' . $CheckExpDate->expiry_date . ')'];
                }
            }

            // Group items by item_id for average cost calculation
            $groupedItems = [];
            foreach ($request->childArray as $row) {
                $itemId = $row['item_id'];
                if (!isset($groupedItems[$itemId])) {
                    $groupedItems[$itemId] = [
                        'quantity' => 0,
                        'total' => 0,
                    ];
                }

                $groupedItems[$itemId]['quantity'] += (float) $row['received_quantity'];
                $groupedItems[$itemId]['total'] += (float) $row['amount'];
            }

            // Calculate average cost for grouped items
            foreach ($groupedItems as $itemId => $data) {
                // Get old totals from existing PO (if it was already received)
                $totalSumPurchaseOrder = PurchaseOrderChild::join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_children.purchase_order_id')
                    ->where('purchase_order_children.item_id', $itemId)
                    ->where('purchase_orders.id', $request->id)
                    ->where('purchase_orders.is_received', 1)
                    ->sum('purchase_order_children.total');

                $totalQtyPurchaseOrder = PurchaseOrderChild::join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_children.purchase_order_id')
                    ->where('purchase_order_children.item_id', $itemId)
                    ->where('purchase_orders.id', $request->id)
                    ->where('purchase_orders.is_received', 1)
                    ->sum('purchase_order_children.received_quantity');

                // Get current item
                $item = Item::find($itemId);
                $currentStock = Item::calculateTotalStockQty($itemId);
                $currentTotalAmount = $item->avg_cost * $currentStock;

                // Calculate new stock and total cost (reverse old PO totals, add new ones)
                $newStockQty = $currentStock + $data['quantity'] - $totalQtyPurchaseOrder;
                $newTotalAmount = $currentTotalAmount + $data['total'] - $totalSumPurchaseOrder;

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

            // Process individual items
            foreach ($request->childArray as $row) {
                if ($request->po_type == 1) {
                    $a = $row['manufacturer_id'];
                } elseif ($request->po_type == 2) {
                    $a = $request->manufacturer_id;
                }

                $CheckExpDate = ItemInventory::where(['batch_no' => $row['batch_no'], 'item_id' => $row['item_id'], 'manufacture_id' => $a])->first() ?? null;

                if ($CheckExpDate == null || ($CheckExpDate != null && $CheckExpDate->expiry_date == $row['expiry_date'])) {
                    $purchaseChilddata = PurchaseOrderChild::find($row['id']);
                    $purchaseChilddata->received_quantity = $row['received_quantity'];

                    if ($request->po_type == 1) {
                        $purchaseChilddata->manufacturer_id = $row['manufacturer_id'];
                    } elseif ($request->po_type == 2) {
                        $purchaseChilddata->manufacturer_id = $request->manufacturer_id;
                    }
                    $purchaseChilddata->batch_no = $row['batch_no'];
                    $purchaseChilddata->pack = $row['pack'];
                    $purchaseChilddata->expiry_date = $row['expiry_date'];
                    $purchaseChilddata->rate = $row['purchase_price'];
                    $purchaseChilddata->total = $row['amount'];
                    $purchaseChilddata->remarks = $row['remarks'];
                    $purchaseChilddata->save();
                    $purchaseChild_id = $purchaseChilddata->id;

                    $itemInventory = new ItemInventory();
                    $itemInventory->purchase_order_id = $purchaseChild_id;
                    $itemInventory->store_id = $request->store_id;
                    $itemInventory->batch_no = $row['batch_no'];
                    $itemInventory->item_id = $row['item_id'];
                    if ($request->po_type == 1) {
                        $itemInventory->manufacture_id = $row['manufacturer_id'];
                    } elseif ($request->po_type == 2) {
                        $itemInventory->manufacture_id = $request->manufacturer_id;
                    }
                    $itemInventory->expiry_date = $row['expiry_date'];
                    $itemInventory->inventory_type_id = 1;
                    $itemInventory->quantity_in = $row['received_quantity'];
                    $itemInventory->purchase_price = $row['purchase_price'];
                    $itemInventory->date = $request->receive_date;
                    $itemInventory->save();

                    $itemUpdate = Item::find($row['item_id']);
                    $itemUpdate->pack = $row['pack'];
                    $itemUpdate->rate = $row['purchase_price'];
                    $itemUpdate->save();
                } else {
                    DB::rollback();
                    return ['status' => "error", 'message' => 'Batch already exists with different expiry Date' . ' ' . $row['batch_no'] . ' ' . '(' . $CheckExpDate->expiry_date . ')'];
                }
            }

            // $remarks = " PO: ";
            // $supplier_coa_account_id = CoaAccount::where([['person_id', $request->supplier_id]])->value('id');
            // $supplier_name = Person::where('id', $request->supplier_id)->first();
            // $is_post_dated = isset($request->cheque_no) ? 1 : 0;
            // $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
            // $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

            // $voucher = new Voucher();
            // $voucher->voucher_no = $newVoucherNo;
            // $voucher->date = $request->receive_date;
            // $voucher->name =  "Purchase Order PO no: " .  $purchaseorder->po_no . ' Supplier: ' . $supplier_name->id . '-' . $supplier_name->name;
            // $voucher->type = 3;
            // $voucher->isApproved = 1;
            // $voucher->generated_at = $request->receive_date;;
            // $voucher->total_amount = 0;
            // $voucher->purchase_order_id = $request->id;
            // $voucher->cheque_no = $request->cheque_no;
            // $voucher->cheque_date = $request->cheque_date;
            // $voucher->is_post_dated = $is_post_dated;
            // $voucher->is_auto = 1;
            // $voucher->save();
            // $voucher_id = $voucher->id;
            // $debitside = 0;
            // $creditside = 0;
            // //---------------------Debit Inventory  account ------------------
            // foreach ($request->childArray as $row) {
            //     $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $row['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity * pack)) as AvgPrice'), 'item_id')->first();
            //     $itemName = $PurchasePrice->item;

            //     $voucherTransaction = new VoucherTransaction();
            //     $voucherTransaction->voucher_id = $voucher_id;
            //     $voucherTransaction->date = $request->receive_date;;
            //     $voucherTransaction->coa_account_id = 1;
            //     $voucherTransaction->is_approved = 1;
            //     $voucherTransaction->debit = $row['amount'] + $row['cost'];
            //     $voucherTransaction->credit = 0;
            //     $voucherTransaction->description = $remarks . 'PO no: ' . $purchaseorder->po_no . ', Item: ' . $itemName->id . '-' . $itemName->name . " Inventory Added. " . ' Pack size: ' . $row['pack'] . ', Qty:' . $row['received_quantity'] . ', Total Qty:' . $row['received_quantity'] * $row['pack'] . ', Rate: ' . $row['purchase_price'] . ', Batch No: ' . $row['batch_no'];

            //     $voucherTransaction->save();
            //     $debitside += $row['amount'] + $row['cost'];
            // }
            // //   --------------Crediting Supplier account --------------------
            // $suppliername = CoaAccount::where('id', $supplier_coa_account_id)->value('name');
            // $voucherTransaction = new VoucherTransaction();
            // $voucherTransaction->voucher_id = $voucher_id;
            // $voucherTransaction->date = $request->receive_date;;
            // $voucherTransaction->coa_account_id = $supplier_coa_account_id;
            // $voucherTransaction->credit = $request->total;
            // $voucherTransaction->debit = 0;
            // $voucherTransaction->is_approved = 1;
            // $voucherTransaction->description = $remarks . 'PO no: ' . $purchaseorder->po_no .  '  '  . $suppliername . " Liability Created";
            // $voucherTransaction->save();
            // $creditside += $request->total;

            // if ($request->discount > 0) {
            //     $sumAmountofdiscount = 0;
            //     //---------------------Crediting cost invenotry discount ------------------
            //     $voucherTransaction = new VoucherTransaction();
            //     $voucherTransaction->voucher_id = $voucher_id;
            //     $voucherTransaction->date = $request->receive_date;;
            //     $voucherTransaction->coa_account_id = 27;
            //     $voucherTransaction->is_approved = 1;
            //     $voucherTransaction->debit = 0;
            //     $voucherTransaction->credit =  $request->discount;
            //     $voucherTransaction->description = 'Discount' . 'PO no: ' . $purchaseorder->po_no .  '  '  . $suppliername . " Discount availed";

            //     $voucherTransaction->save();
            //     $creditside += $request->discount;
            //     //---------------------Debiting supplier discount   ------------------

            //     $voucherTransaction = new VoucherTransaction();
            //     $voucherTransaction->voucher_id = $voucher_id;
            //     $voucherTransaction->date = $request->receive_date;;
            //     $voucherTransaction->coa_account_id = $supplier_coa_account_id;
            //     $voucherTransaction->is_approved = 1;
            //     $voucherTransaction->debit = $request->discount;
            //     $voucherTransaction->credit = 0;
            //     $voucherTransaction->description = 'Discount' . 'PO no: ' . $purchaseorder->po_no .  '  '  . $suppliername . " Discount availed";
            //     $voucherTransaction->save();
            //     $sumAmountofdiscount += $request->discount;
            //     $debitside += $request->discount;
            // }

            // if ($request->tax_in_figure > 0) {
            //     //---------------------Crediting purchase tax payable  tax ------------------
            //     $voucherTransaction = new VoucherTransaction();
            //     $voucherTransaction->voucher_id = $voucher_id;
            //     $voucherTransaction->date = $request->receive_date;;
            //     $voucherTransaction->coa_account_id = 31;
            //     $voucherTransaction->is_approved = 1;
            //     $voucherTransaction->debit = 0;
            //     $voucherTransaction->credit =  $request->tax_in_figure;
            //     $voucherTransaction->description = 'Tax ' . 'PO no: ' . $purchaseorder->po_no .  '  '  . $suppliername . " Tax liability";
            //     $voucherTransaction->save();
            //     $creditside += $request->tax_in_figure;
            //     //---------------------Debiting purchase tax expenses    ------------------

            //     $voucherTransaction = new VoucherTransaction();
            //     $voucherTransaction->voucher_id = $voucher_id;
            //     $voucherTransaction->date = $request->receive_date;;
            //     $voucherTransaction->coa_account_id = 30;
            //     $voucherTransaction->is_approved = 1;
            //     $voucherTransaction->debit = $request->tax_in_figure;
            //     $voucherTransaction->credit = 0;
            //     $voucherTransaction->description = 'Tax ' . 'PO no: ' . $purchaseorder->po_no .  '  '  . $suppliername . " Tax expense";
            //     $voucherTransaction->save();
            //     $debitside += $request->tax_in_figure;
            // }

            // if($request->adv_tax > 0)
            // {
            //     $voucherTransaction = new VoucherTransaction();
            //     $voucherTransaction->voucher_id = $voucher_id;
            //     $voucherTransaction->date = $request->receive_date;
            //     $voucherTransaction->coa_account_id = 873;
            //     $voucherTransaction->debit = $request->adv_tax;
            //     $voucherTransaction->credit = 0;
            //     $voucherTransaction->is_approved = 1;
            //     $voucherTransaction->description =  "Advance Purchase Tax " . 'PO no: ' . $request->po_no;
            //     $voucherTransaction->save();
            //     $debitside += $request->adv_tax;

            //     $voucherTransaction = new VoucherTransaction();
            //     $voucherTransaction->voucher_id = $voucher_id;
            //     $voucherTransaction->date = $request->receive_date;
            //     $voucherTransaction->coa_account_id = 872;
            //     $voucherTransaction->debit = 0;
            //     $voucherTransaction->credit = $request->adv_tax;
            //     $voucherTransaction->is_approved = 1;
            //     $voucherTransaction->description =  "Advance Purchase Tax " . 'PO no: ' . $request->po_no;
            //     $voucherTransaction->save();
            //     $creditside += $request->adv_tax;
            // }

            // if ($creditside != $debitside) {
            //     throw new \Exception('debit and credit sides are not equal');
            // } else {
            //     $updateVoucher = Voucher::find($voucher_id);
            //     $updateVoucher->total_amount = $debitside;
            //     $updateVoucher->save();
            // }

            DB::commit();
            return ['status' => "ok", 'message' => 'Purchase Order Receive successfully'];
        } catch (\Exception $e) {
            DB::rollback();
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
    /**
     * Direct purchase order
     *
     * @param  \Illuminate\Http\Request  $po_no
     * @param  \Illuminate\Http\Request  $request_date
     * @param  \Illuminate\Http\Request  $store_id
     * @param  \Illuminate\Http\Request  $remarks
     * @param  \Illuminate\Http\Request  $total
     * -------------------childArray
     * @param  \Illuminate\Http\Request  $item_id
     * @param  \Illuminate\Http\Request  $batch_no
     * @param  \Illuminate\Http\Request  $rate
     * @param  \Illuminate\Http\Request  $pack
     * @param  \Illuminate\Http\Request  $quantity
     * @param  \Illuminate\Http\Request  $total
     * @param  \Illuminate\Http\Request  $remarks
     * @return \Illuminate\Http\Response
     */
    public function directPurchaseOrder(Request $request)
    {

        $rules = array(
            'name' => 'required',
            'po_type' => 'required|numeric',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            DB::beginTransaction();
            // DB::transaction(function () use ($request) {

            $purchaseorder = new PurchaseOrder();
            $purchaseorder->name = $request->name;
            $purchaseorder->store_id = $request->store_id;
            $purchaseorder->invoice_no = $request->invoice_no;
            $purchaseorder->po_type = $request->po_type;
            $purchaseorder->request_date = $request->request_date;
            $purchaseorder->receive_date = $request->request_date;
            $purchaseorder->manufacture_id = $request->manufacturer_id;
            $purchaseorder->remarks = $request->remarks;
            $purchaseorder->is_received = 1;
            $purchaseorder->is_approved = 1;
            $purchaseorder->total = $request->total;
            $purchaseorder->discount = $request->discount;
            $purchaseorder->tax = $request->tax;
            $purchaseorder->adv_tax = $request->adv_tax;
            $purchaseorder->adv_tax_percentage = $request->adv_tax_percentage;
            $purchaseorder->total_after_tax = $request->total_after_tax;
            $purchaseorder->tax_in_figure = $request->tax_in_figure;
            $purchaseorder->total_after_discount = $request->total_after_discount;
            $purchaseorder->save();
            $purchase_id = $purchaseorder->id;

            // Validate expiry dates first
            foreach ($request->childArray as $row) {
                $CheckExpDate = ItemInventory::where(['batch_no' => $row['batch_no'], 'item_id' => $row['item_id'], 'manufacture_id' => $row['manufacturer_id']])->first() ?? null;

                if ($CheckExpDate != null && $CheckExpDate->expiry_date != $row['expiry_date']) {
                    DB::rollback();
                    return ['status' => "error", 'message' => 'Batch already exists with different expiry Date' . ' ' . $row['batch_no'] . ' ' . '(' . $CheckExpDate->expiry_date . ')'];
                }
            }

            // Group items by item_id for average cost calculation
            $groupedItems = [];
            foreach ($request->childArray as $row) {
                $itemId = $row['item_id'];
                if (!isset($groupedItems[$itemId])) {
                    $groupedItems[$itemId] = [
                        'quantity' => 0,
                        'total' => 0,
                    ];
                }

                $groupedItems[$itemId]['quantity'] += (float) $row['received_quantity'];
                $groupedItems[$itemId]['total'] += (float) $row['amount'];
            }

            // Calculate average cost for grouped items
            foreach ($groupedItems as $itemId => $data) {
                $item = Item::find($itemId);
                $currentStock = Item::calculateTotalStockQty($itemId);
                $currentTotalAmount = $item->avg_cost * $currentStock;

                // Calculate new stock and total cost
                $newStockQty = $currentStock + $data['quantity'];
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

            // Process individual items
            foreach ($request->childArray as $row) {
                $CheckExpDate = ItemInventory::where(['batch_no' => $row['batch_no'], 'item_id' => $row['item_id'], 'manufacture_id' => $row['manufacturer_id']])->first() ?? null;

                if ($CheckExpDate == null || ($CheckExpDate != null && $CheckExpDate->expiry_date == $row['expiry_date'])) {
                    $purchaseChilddata = new PurchaseOrderChild();
                    $purchaseChilddata->purchase_order_id = $purchase_id;
                    $purchaseChilddata->item_id = $row['item_id'];
                    $purchaseChilddata->received_quantity = $row['received_quantity'];
                    $purchaseChilddata->batch_no = $row['batch_no'];
                    $purchaseChilddata->expiry_date = $row['expiry_date'];
                    $purchaseChilddata->manufacturer_id = $row['manufacturer_id'];
                    $purchaseChilddata->quantity = $row['received_quantity'];
                    $purchaseChilddata->pack = $row['pack'];
                    $purchaseChilddata->rate = $row['rate'];
                    $purchaseChilddata->total = $row['amount'];
                    $purchaseChilddata->save();
                    $purchaseChild_id = $purchaseChilddata->id;

                    $itemInventory = new ItemInventory();
                    $itemInventory->purchase_order_id = $purchaseChild_id;
                    $itemInventory->store_id = $request->store_id;
                    $itemInventory->batch_no = $row['batch_no'];
                    $itemInventory->item_id = $row['item_id'];
                    $itemInventory->manufacture_id = $row['manufacturer_id'];
                    $itemInventory->expiry_date = $row['expiry_date'];
                    $itemInventory->inventory_type_id = 1;
                    $itemInventory->quantity_in = $row['received_quantity'];
                    $itemInventory->purchase_price = $row['rate'];
                    $itemInventory->date = $request->request_date;
                    $itemInventory->save();

                    //  updating item data
                    $itemUpdate = Item::find($row['item_id']);
                    $itemUpdate->pack = $row['pack'];
                    $itemUpdate->rate = $row['rate'];
                    $itemUpdate->save();
                } else {
                    DB::rollback();
                    return ['status' => "error", 'message' => 'Batch already exists with different expiry Date' . ' ' . $row['batch_no'] . ' ' . '(' . $CheckExpDate->expiry_date . ')'];
                }
            }
            $debitside = 0;
            $creditside = 0;
            $remarks = " Purchase Order ";
            $is_post_dated = isset($request->cheque_no) ? 1 : 0;
            $getVoucherNo = DB::table('vouchers')->where('type', 1)->orderBy('id', 'desc')->first();
            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
            $voucher = new Voucher();
            $voucher->voucher_no = $newVoucherNo;
            $voucher->date = $request->request_date;
            $voucher->name = "Direct Purchase PO no: " . $request->po_no;
            $voucher->type = 1;
            $voucher->generated_at = $request->request_date;
            $voucher->total_amount = 0;
            $voucher->purchase_order_id = $purchase_id;
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
                $voucherTransaction->date = $request->request_date;
                $voucherTransaction->coa_account_id = 1; // Inventory Account
                $voucherTransaction->debit = $row['amount'];
                $voucherTransaction->credit = 0;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->description = $remarks . 'PO no: ' . $request->po_no . ', Item: ' . $itemName->id . '-' . $itemName->name . " Inventory Added. " . ' Pack size: ' . $row['pack'] . ', Qty:' . $row['received_quantity'] . ', Total Qty:' . $row['received_quantity'] . ', Rate: ' . $row['rate'] . ', Batch No: ' . $row['batch_no'];
                $voucherTransaction->save();
                $debitside += $row['amount'];
            }
            //---------------------Crediting cash account ------------------
            if ($request->amount_received > 0) {
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = $request->request_date;
                $voucherTransaction->coa_account_id = $request->account_id; // Supplier account
                $voucherTransaction->debit = 0;
                $voucherTransaction->credit = $request->amount_received;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->description = 'Direct Purchase Order ' . 'PO no: ' . $request->po_no . ', Amount Paid (Cash) ';
                $voucherTransaction->save();
                $creditside += $request->amount_received;
            }
            //---------------------Bank account ------------------
            if ($request->bank_amount_received > 0) {
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = $request->request_date;
                $voucherTransaction->coa_account_id = $request->bank_account_id; // Bank Account
                $voucherTransaction->debit = 0;
                $voucherTransaction->credit = $request->bank_amount_received;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->description = 'Direct Purchase Order ' . 'PO no: ' . $request->po_no . ', Amount Paid (Bank) ';
                $voucherTransaction->save();
                $creditside += $request->bank_amount_received;
            }
            //---------------------Tax Account ------------------
            if ($request->tax_in_figure > 0) {
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = $request->request_date;
                $voucherTransaction->coa_account_id = 30;
                $voucherTransaction->debit = $request->tax_in_figure; //Purchase Tax Expense
                $voucherTransaction->credit = 0;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->description = "Direct Purchase Order " . 'PO no: ' . $request->po_no;
                $voucherTransaction->save();
                $debitside += $request->tax_in_figure;
            }
            //---------------------Discount Account ------------------
            if ($request->discount > 0) {
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = $request->request_date;
                $voucherTransaction->coa_account_id = 27;  //Cost Inventory (Discounts)
                $voucherTransaction->debit = 0;
                $voucherTransaction->credit = $request->discount;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->description = "Direct Purchase Order " . 'PO no: ' . $request->po_no;
                $voucherTransaction->save();
                $creditside += $request->discount;
            }
        //---------------------Advance Tax Account ------------------
            if ($request->adv_tax > 0) {
                $voucherTransaction = new VoucherTransaction();
                $voucherTransaction->voucher_id = $voucher_id;
                $voucherTransaction->date = $request->request_date;
                $voucherTransaction->coa_account_id = 873; // Advance Purchase Tax Payable
                $voucherTransaction->debit = $request->adv_tax;
                $voucherTransaction->credit = 0;
                $voucherTransaction->is_approved = 1;
                $voucherTransaction->description = "Advance Purchase Tax " . 'PO no: ' . $request->po_no;
                $voucherTransaction->save();
                $debitside += $request->adv_tax;

                // $voucherTransaction = new VoucherTransaction();
                // $voucherTransaction->voucher_id = $voucher_id;
                // $voucherTransaction->date = $request->request_date;
                // $voucherTransaction->coa_account_id = 872;
                // $voucherTransaction->debit = 0;
                // $voucherTransaction->credit = $request->adv_tax;
                // $voucherTransaction->is_approved = 1;
                // $voucherTransaction->description =  "Advance Purchase Tax " . 'PO no: ' . $request->po_no;
                // $voucherTransaction->save();
            }

            if ($creditside == $debitside) {
                $voucher = Voucher::find($voucher_id);
                $voucher->total_amount = $creditside;
                $voucher->save();
            } else {
                throw new \Exception('debit and credit sides are not equal' . $creditside . ' ' . $debitside);
            }
            // });
            DB::commit();
            return ['status' => "ok", 'message' => 'Purchase Order Receive successfully'];
        } catch (\Exception $e) {
            DB::rollback();
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
    public function getPoDetails(Request $req)
    {

        try {

            $po_id = $req->po_id;
            $purchaseorderlist = PurchaseOrder::with('supplier', 'store', 'purchaseorderchild', 'purchaseOrderReturn')
                ->where('id', $po_id)
                ->first();

            $coaSubGrouppurchase = 1;
            $coaSubGroupExpence = 7;

            $purchaseorderVoucher = Voucher::with('voucherTransactions', 'voucherType')
                ->where('purchase_order_id', $po_id)
                ->when($coaSubGrouppurchase, function ($query) use ($coaSubGrouppurchase) {
                    $query->whereHas('voucherTransactions', function ($query) use ($coaSubGrouppurchase) {
                        $query->whereHas('coaAccount', function ($query) use ($coaSubGrouppurchase) {
                            $query->whereHas('coaGroup', function ($query) use ($coaSubGrouppurchase) {
                                $query->whereHas('coaSubGroups', function ($query) use ($coaSubGrouppurchase) {
                                    $query->where('id', $coaSubGrouppurchase);
                                });
                            });
                        });
                    });
                })
                ->first();

            $purchaseExpenseVoucher = Voucher::with('voucherTransactions', 'voucherType')
                ->where('purchase_order_id', $po_id)
                ->when($coaSubGroupExpence, function ($query) use ($coaSubGroupExpence) {
                    $query->whereHas('voucherTransactions', function ($query) use ($coaSubGroupExpence) {
                        $query->whereHas('coaAccount', function ($query) use ($coaSubGroupExpence) {
                            $query->whereHas('coaGroup', function ($query) use ($coaSubGroupExpence) {
                                $query->whereHas('coaSubGroups', function ($query) use ($coaSubGroupExpence) {
                                    $query->where('id', $coaSubGroupExpence);
                                });
                            });
                        });
                    });
                })
                ->first();

            return ['purchaseorderlist' => $purchaseorderlist, 'purchaseorderVoucher' => $purchaseorderVoucher, 'purchaseExpenseVoucher' => $purchaseExpenseVoucher];
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
    public function editDirectPurchaseOrder(Request $req)
    {

        $rules = array(
            'id' => 'required|int|exists:purchase_orders,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $purchaseOrder = PurchaseOrder::with('store')->find($req->id);

            $poChild = PurchaseOrderChild::with('item')->where('purchase_order_id', $req->id)->get();
            $childItem = [];
            foreach ($poChild as $child) {
                $itemname[] = array(
                    'id' => $child->id,
                    'item_id' => $child->item_id,
                    'item_name' => $child->item->name,
                    'quantity' => $child->quantity,
                    'received_quantity' => $child->received_quantity - $child->returned_quantity > 0 ? $child->received_quantity - $child->returned_quantity : $child->quantity - $child->returned_quantity,
                    'purchase_price' => $child->purchase_price > 0 ? $child->purchase_price : '',
                    'amount' => $child->amount,
                    'returned_quantity' => 0,
                    'remarks' => $child->remarks,
                );
            }
            $data = array(
                "id" => $purchaseOrder->id,
                "invoice_no" => $purchaseOrder->invoice_no,
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
     * Show the form for editing the Purchase Oder.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ViewPurchaseOrderDetails(Request $req)
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

            return ['purchaseOrder' => $purchaseOrder, 'poChild' => $poChild];
        } catch (\Exception $e) {
            return $e;
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
    public function getPoChild(Request $req)
    {
        try {
            $pochild = PurchaseOrderChild::with('PoNo', 'item')
                ->get();
            return ['pochild' => $pochild];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    public function getPoHistory(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required|int',
        ]);

        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {

            $purchase_order_id = $req->id;
            $purchaseOrder = PurchaseOrder::with('supplier', 'store')->find($purchase_order_id);
            $poChild = PurchaseOrderChild::with('item')->where('purchase_order_id', $purchase_order_id)->get();
            $pohistory =
            ReturnPurchaseOrder::with('purchaseorder', 'pochild')
                ->where('po_id', $purchase_order_id)
                ->get();
            $purchaseOrder->poChild = $poChild;
            return ['purchaseOrder' => $purchaseOrder, 'pohistory' => $pohistory];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function supplierPurchaseOrder(Request $req)
    {
        $status = $req->status ?? 1;

        $category = $req->category_id;
        $sub_category_id = $req->sub_category_id;
        $items = ItemManufacture::with('person', 'item')->where('manufacture_id', $req->manufacturer_id)
            ->whereHas('item', function ($q) use ($status) {
                if ($status == 1) {
                    $q->where('isActive', 1);
                } elseif ($status == 2) {
                    $q->where('isActive', 0);
                } elseif ($status == 3) {
                    $q->whereIn('isActive', [1, 0]);
                }
            })
            ->get();
        return ['items' => $items];
    }

    /**
     * Purchase Report.
     * @param  \Illuminate\Http\Request  $supplier_id
     * @param  \Illuminate\Http\Request  $from
     * @param  \Illuminate\Http\Request  $to
     * @param  \Illuminate\Http\Request  $store_id
     * @param  \Illuminate\Http\Request  $item_id
     * @return \Illuminate\Http\Response
     */

    public function getPurchaseReport(Request $req)
    {
        $rules = array(
            // 'records' => 'required|int',
            // 'pageNo' => 'required|int',
            // 'colName' => 'required|string',
            // 'sort' => 'required|string',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $supplier_id = $req->supplier_id;
            $from = $req->from_date;
            $to = $req->to_date;
            $store_id = $req->store_id;
            $item_ids = $req->item_id ? explode(',', $req->item_id) : [];

            if ($req->po_type == '') {
                $purchaseorderlist = PurchaseOrder::with('purchaseorderchild', 'store', 'supplier')

                    ->when($supplier_id, function ($q, $supplier_id) {
                        return $q->where('person_id', $supplier_id);
                    })

                    ->when(!empty($item_ids), function ($query) use ($item_ids) {
    $query->with(['purchaseorderchild' => function ($q) use ($item_ids) {
        $q->whereIn('item_id', $item_ids);
    }]);
})
                    ->when($from, function ($query, $from) {
                        return $query->where('request_date', '>=', $from);
                    })
                    ->when($to, function ($query, $to) {
                        return $query->where('request_date', '<=', $to);
                    })
                    ->when($store_id, function ($query, $store_id) {
                        return $query->where('store_id', $store_id);
                    })
                    ->where('is_received', 1)
                // ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);
                    ->get();
            } elseif ($req->po_type == 1) {
                $purchaseorderlist = PurchaseOrder::with('purchaseorderchild', 'store', 'supplier')
                    ->where('po_type', 1)
                    ->when($supplier_id, function ($q, $supplier_id) {
                        return $q->where('person_id', $supplier_id);
                    })

                    ->when(!empty($item_ids), function ($query) use ($item_ids) {
    $query->with(['purchaseorderchild' => function ($q) use ($item_ids) {
        $q->whereIn('item_id', $item_ids);
    }]);
})
                    ->when($from, function ($query, $from) {
                        return $query->where('request_date', '>=', $from);
                    })
                    ->when($to, function ($query, $to) {
                        return $query->where('request_date', '<=', $to);
                    })
                    ->when($store_id, function ($query, $store_id) {
                        return $query->where('store_id', $store_id);
                    })
                    ->where('is_received', 1)
                // ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);
                    ->get();
            } elseif ($req->po_type == 2) {

                $purchaseorderlist = PurchaseOrder::with('purchaseorderchild', 'store', 'supplier')->where('po_type', 2)
                    ->when(!empty($item_ids), function ($query) use ($item_ids) {
    $query->whereHas('purchaseorderchild', function ($q) use ($item_ids) {
        $q->whereIn('item_id', $item_ids);
    });
})
                    ->when($item_id, function ($query) use ($item_id) {
                        $query->with('purchaseorderchild', fn($q) => $q->where('item_id', '=', $item_id));
                    })
                    ->when($from, function ($query, $from) {
                        return $query->where('request_date', '>=', $from);
                    })
                    ->when($to, function ($query, $to) {
                        return $query->where('request_date', '<=', $to);
                    })
                // ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);
                    ->when($store_id, function ($query, $store_id) {
                        return $query->where('store_id', $store_id);
                    })
                    ->where('is_received', 1)
                    ->get();
            } elseif ($req->po_type == 3) {

                $purchaseorderlist = PurchaseOrder::with('purchaseorderchild', 'store', 'supplier')->where('po_type', 3)
                    ->when($item_id, function ($query, $item_id) {
                        $query->whereHas('purchaseorderchild', function ($query) use ($item_id) {
                            $query->where('item_id', $item_id);
                        });
                    })
                    ->when($item_id, function ($query) use ($item_id) {
                        $query->with('purchaseorderchild', fn($q) => $q->where('item_id', '=', $item_id));
                    })
                    ->when($from, function ($query, $from) {
                        return $query->where('request_date', '>=', $from);
                    })
                    ->when($to, function ($query, $to) {
                        return $query->where('request_date', '<=', $to);
                    })
                // ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);
                    ->when($store_id, function ($query, $store_id) {
                        return $query->where('store_id', $store_id);
                    })
                    ->where('is_received', 1)
                    ->get();
            }

            return ['purchaseorderlist' => $purchaseorderlist];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
   public function getPurchaseReportSupplierWise(Request $req)
{
    $rules = array(
        // 'records' => 'required|int',
        // 'pageNo' => 'required|int',
        // 'colName' => 'required|string',
        // 'sort' => 'required|string',
    );

    $validator = Validator::make($req->all(), $rules);
    if ($validator->fails()) {
        return ['status' => 'error', 'message' => $validator->errors()->first()];
    }

    try {
        $supplier_id = $req->supplier_id;
        $from = $req->from_date;
        $to = $req->to_date;
        $store_id = $req->store_id;
        $item_ids = $req->item_id ? explode(',', $req->item_id) : [];
        $po_type = $req->po_type;

        $purchaseorder = Person::with(['purchaseorder' => function ($q) use ($po_type, $from, $to, $item_ids) {
                // Apply filters for purchase order
                if ($po_type) {
                    $q->where('po_type', '=', $po_type);
                }
                if ($from) {
                    $q->where('request_date', '>=', $from);
                }
                if ($to) {
                    $q->where('request_date', '<=', $to);
                }

                // Apply filters for purchase order children
                if (!empty($item_ids)) {
                    $q->whereHas('purchaseorderchild', function ($q2) use ($item_ids) {
                        $q2->whereIn('item_id', $item_ids);
                    });
                    $q->with(['purchaseorderchild' => function ($q2) use ($item_ids) {
                        $q2->whereIn('item_id', $item_ids);
                    }]);
                }
            }])
            ->when($supplier_id, function ($query, $supplier_id) {
                return $query->where('id', $supplier_id);
            })
            ->wherehas('personType', function ($query) use ($store_id) {
                $query->where('id', 2); // Assuming store_type = 2 for suppliers
            })
            ->get();

        return ['purchaseorder' => $purchaseorder];
    } catch (\Exception $e) {
        $message = CustomErrorMessages::getCustomMessage($e);
        return ['status' => 'error', 'message' => $message];
    }
}


    public function getItemsRates(Request $req)
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
            $item_id = $req->item_id;
            $sub_category_id = $req->sub_category_id;
            $category_id = $req->category_id;

            // Query Item model directly using avg_cost
            $PurchasePrice = Item::with('unit')
                ->when($item_id, function ($query, $item_id) {
                    $query->where('id', $item_id);
                })
                ->when($sub_category_id, function ($query, $sub_category_id) {
                    $query->where('subcategory_id', $sub_category_id);
                })
                ->when($category_id, function ($query, $category_id) {
                    $query->where('category_id', $category_id);
                })
                ->select('id', 'id as item_id', 'avg_cost as AvgPrice')
                ->orderBy($req->colName, $req->sort)
                ->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['PurchasePrice' => $PurchasePrice];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function receivePurchaseOrderComplete(Request $request)
    {
        $rules = array(
            'id' => 'required|int|exists:purchase_orders,id',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $po_id = $request->id;

        DB::beginTransaction();
        try {

            // if ($request->is_pending == 0) {
            $purchaseorder = PurchaseOrder::find($request->id);

            // Always create voucher/transactions regardless of current completion flag
            // $purchaseorder->is_completed = '1';
            $purchaseorder->is_received = '1';
            $purchaseorder->save();
            $purchaseorder->po_no;
            $po_id = $request->id;

            $this->vouchersForReceiveCompleted($po_id);

            DB::commit();

            return ['status' => "ok", 'message' => 'Purchase Order Receive successfully'];

        } catch (\Exception $e) {
            DB::rollback();
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
        return ['status' => "ok", 'message' => 'Completed successfully'];
    }

    private function vouchersForReceiveCompleted($po_id)
    {
        $remarks = " PO: ";
        $purchaseorder = PurchaseOrder::find($po_id);
        $supplier_coa_account_id = CoaAccount::where('person_id', $purchaseorder->person_id)->value('id');
        $supplier_name = Person::where('id', $purchaseorder->person_id)->first();

        $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();

        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

        $voucher = new Voucher();
        $voucher->voucher_no = $newVoucherNo;
        $voucher->date = $purchaseorder->receive_date;
        $voucher->name = "Purchase Order PO no: " . $purchaseorder->po_no . ' Supplier: ' . $supplier_name->id . '-' . $supplier_name->name;
        $voucher->type = 3;
        $voucher->isApproved = 1;
        $voucher->generated_at = $purchaseorder->receive_date;
        $voucher->total_amount = 0;
        $voucher->purchase_order_id = $purchaseorder->id;
        $voucher->is_auto = 1;
        $voucher->save();
        $voucher_id = $voucher->id;
        $debitside = 0;
        $creditside = 0;

        //---------------------Debit Inventory account ------------------
        $childs = PurchaseOrderChild::with('itemName')->where('purchase_order_id', $po_id)->get();
        foreach ($childs as $row) {
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $purchaseorder->receive_date;
            $voucherTransaction->coa_account_id = 1;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = $row->received_quantity * $row->rate;
            $voucherTransaction->credit = 0;
            $voucherTransaction->description = $remarks . 'PO no: ' . $purchaseorder->po_no; // . ', Item: ' . $row->item_id;
            $voucherTransaction->save();
            $debitside += ($row->received_quantity * $row->rate);
        }

        /* Debit for Onloading Expense */
        if ($purchaseorder->unloading_expense > 0) {
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $purchaseorder->receive_date;
            $voucherTransaction->coa_account_id = 1;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = $purchaseorder->unloading_expense;
            $voucherTransaction->credit = 0;
            $voucherTransaction->description = $remarks . 'PO no: ' . $purchaseorder->po_no . ' Debit Unloading Expense ' . $purchaseorder->unloading_expense_description;
            $voucherTransaction->save();
            $debitside += $purchaseorder->unloading_expense;

            //--------------Crediting unloading Expense --------------------
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $purchaseorder->receive_date;
            $voucherTransaction->coa_account_id = 951;
            $voucherTransaction->credit = $purchaseorder->unloading_expense;
            $voucherTransaction->debit = 0;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = $remarks . 'PO no: ' . $purchaseorder->po_no . ' Credit Unloading Expense' . $purchaseorder->unloading_expense_description;
            $voucherTransaction->save();
            $creditside += $purchaseorder->unloading_expense;
        }

        if ($purchaseorder->vendor_account_id && $purchaseorder->purchase_expense_account_id && $purchaseorder->expense_amount) {
            /* Builty Account Debit */
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = Land::changeDateFormat($purchaseorder->request_date);
            $voucherTransaction->coa_account_id = $purchaseorder->purchase_expense_account_id;
            $voucherTransaction->debit = $purchaseorder->expense_amount;
            $voucherTransaction->credit = 0;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = 'Builty Expense ' . 'PO no: ' . $purchaseorder->po_no;
            $voucherTransaction->save();
            $debitside += $purchaseorder->expense_amount;

            /* Vendor Account Credit */
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = Land::changeDateFormat($purchaseorder->request_date);
            $voucherTransaction->coa_account_id = $purchaseorder->vendor_account_id;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $purchaseorder->expense_amount;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = 'Purchase Order Expense ' . 'PO no: ' . $purchaseorder->po_no;
            $voucherTransaction->save();
            $creditside += $purchaseorder->expense_amount;
        }

        // --------------Crediting Supplier account --------------------
        $suppliername = CoaAccount::where('id', $supplier_coa_account_id)->value('name');
        $voucherTransaction = new VoucherTransaction();
        $voucherTransaction->voucher_id = $voucher_id;
        $voucherTransaction->date = $purchaseorder->receive_date;
        $voucherTransaction->coa_account_id = $supplier_coa_account_id;
        $voucherTransaction->credit = $purchaseorder->total;
        $voucherTransaction->debit = 0;
        $voucherTransaction->is_approved = 1;
        $voucherTransaction->description = $remarks . 'PO no: ' . $purchaseorder->po_no . '  ' . $suppliername . " Liability Created";
        $voucherTransaction->save();
        $creditside += $purchaseorder->total;

        if ($purchaseorder->discount > 0) {
            $sumAmountofdiscount = 0;
            //---------------------Crediting cost invenotry discount ------------------
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $purchaseorder->receive_date;
            $voucherTransaction->coa_account_id = 27;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $purchaseorder->discount;
            $voucherTransaction->description = 'Discount' . 'PO no: ' . $purchaseorder->po_no . '  ' . $suppliername . " Discount availed";
            $voucherTransaction->save();
            $creditside += $purchaseorder->discount;
            //---------------------Debiting supplier discount ------------------

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $purchaseorder->receive_date;
            $voucherTransaction->coa_account_id = $supplier_coa_account_id;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = $purchaseorder->discount;
            $voucherTransaction->credit = 0;
            $voucherTransaction->description = 'Discount' . 'PO no: ' . $purchaseorder->po_no . '  ' . $suppliername . " Discount availed";
            $voucherTransaction->save();
            $sumAmountofdiscount += $purchaseorder->discount;
            $debitside += $purchaseorder->discount;
        }

        if ($purchaseorder->tax_in_figure > 0) {
            //---------------------Crediting purchase tax payable tax ------------------
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $purchaseorder->receive_date;
            $voucherTransaction->coa_account_id = 31;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $purchaseorder->tax_in_figure;
            $voucherTransaction->description = 'Tax ' . 'PO no: ' . $purchaseorder->po_no . '  ' . $suppliername . " Tax liability";
            $voucherTransaction->save();
            $creditside += $purchaseorder->tax_in_figure;
            //---------------------Debiting purchase tax expenses ------------------

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $purchaseorder->receive_date;
            $voucherTransaction->coa_account_id = 30;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->debit = $purchaseorder->tax_in_figure;
            $voucherTransaction->credit = 0;
            $voucherTransaction->description = 'Tax ' . 'PO no: ' . $purchaseorder->po_no . '  ' . $suppliername . " Tax expense";
            $voucherTransaction->save();
            $debitside += $purchaseorder->tax_in_figure;
        }


        //---------------------Advance Tax Account ------------------
        if ($purchaseorder->adv_tax > 0) {
            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $purchaseorder->request_date;
            $voucherTransaction->coa_account_id = 873;
            $voucherTransaction->debit = $purchaseorder->adv_tax;
            $voucherTransaction->credit = 0;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description = "Advance Purchase Tax " . 'PO no: ' . $purchaseorder->po_no;
            $voucherTransaction->save();
            $debitside += $purchaseorder->adv_tax;

            $voucherTransaction = new VoucherTransaction();
            $voucherTransaction->voucher_id = $voucher_id;
            $voucherTransaction->date = $purchaseorder->request_date;
            $voucherTransaction->coa_account_id = 872; // Advance Purchase Tax Payable
            $voucherTransaction->debit = 0;
            $voucherTransaction->credit = $purchaseorder->adv_tax;
            $voucherTransaction->is_approved = 1;
            $voucherTransaction->description =  "Advance Purchase Tax " . 'PO no: ' . $purchaseorder->po_no;
            $voucherTransaction->save();
            $creditside += $purchaseorder->adv_tax;
        }
        /*========== Purchase Expense ===================*/
        // if ($purchaseorder->vendor_account_id && $purchaseorder->purchase_expense_account_id && $purchaseorder->expense_amount) {
        //     $getVoucherNo2 = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
        //     $newVoucherNo2 = $getVoucherNo2 ? $getVoucherNo2->voucher_no + 1 : 1;
        //     $account = CoaAccount::find($purchaseorder->vendor_account_id);
        //     $vendor = Person::find($account->person_id);

        //     $voucher2 = new Voucher();
        //     $voucher2->voucher_no = $newVoucherNo2;
        //     $voucher2->date = $purchaseorder->receive_date;
        //     $voucher2->name =  "Purchase Expense Order PO no: " .  $purchaseorder->po_no . 'Vendor: ';// . $vendor->id . '-' . $vendor->name;
        //     $voucher2->type = 3;
        //     $voucher2->isApproved = 1;
        //     $voucher2->generated_at = Land::changeDateFormat($purchaseorder->request_date);
        //     $voucher2->total_amount = $purchaseorder->expense_amount;
        //     $voucher2->purchase_order_id = $purchaseorder->id;
        //     // $voucher2->cheque_no = $request->cheque_no; // Replace with the actual variable if available
        //     $voucher2->cheque_date = Land::changeDateFormat($purchaseorder->request_date); // Replace with the actual variable if available
        //     $voucher2->is_post_dated = 0; // Replace with the actual variable if available
        //     $voucher2->is_auto = 1;
        //     $voucher2->save();
        //     $voucher_id2 = $voucher2->id;

        //     $voucherTransaction2 = new VoucherTransaction();
        //     $voucherTransaction2->voucher_id = $voucher_id2;
        //     $voucherTransaction2->date = Land::changeDateFormat($purchaseorder->request_date);
        //     $voucherTransaction2->coa_account_id = $purchaseorder->purchase_expense_account_id;
        //     $voucherTransaction2->debit = $purchaseorder->expense_amount;
        //     $voucherTransaction2->credit = 0;
        //     $voucherTransaction2->is_approved = 1;
        //     $voucherTransaction2->description =  'Purchase Order Expense ' . 'PO no: ' . $purchaseorder->po_no;
        //     $voucherTransaction2->save();
        //     $creditside += $purchaseorder->expense_amount;

        //     $voucherTransaction3 = new VoucherTransaction();
        //     $voucherTransaction3->voucher_id = $voucher_id2;
        //     $voucherTransaction3->date = Land::changeDateFormat($purchaseorder->request_date);
        //     $voucherTransaction3->coa_account_id = $purchaseorder->vendor_account_id;
        //     $voucherTransaction3->debit = 0;
        //     $voucherTransaction3->credit = $purchaseorder->expense_amount;
        //     $voucherTransaction3->is_approved = 1;
        //     $voucherTransaction3->description =  'Purchase Order Expense ' . 'PO no: ' . $purchaseorder->po_no;
        //     $voucherTransaction3->save();
        // }

        // $creditside += $purchaseorder->expense_amount;
        $tolerance = 0.00001; // Define a small tolerance value
        if (abs($creditside - $debitside) > $tolerance) {
            throw new \Exception('debit and credit sides are not equal ' . $creditside . ' de ' . $debitside);
        } else {
            $updateVoucher = Voucher::find($voucher_id);
            $updateVoucher->total_amount = $debitside;
            $updateVoucher->save();
        }
    }

    private function avgCost($itemId, $receivedQuantity, $purchaseOrderId, $childArray)
    {
        // Initialize an array to accumulate quantities and amounts for the same item_id
        $groupedItems = [];

        // Group the items by `item_id` and accumulate `received_quantity` and `total` for each
        foreach ($childArray as $row) {
            $itemId = $row['item_id']; // Get the current item_id

            if (!isset($groupedItems[$itemId])) {
                $groupedItems[$itemId] = [
                    'quantity' => 0,
                    'total' => 0,
                ];
            }

            // Accumulate the received quantity and amount for the current item_id
            $groupedItems[$itemId]['quantity'] += (float) $row['received_quantity'];
            $groupedItems[$itemId]['total'] += (float) $row['amount'];
        }

        // Process each grouped item (should be only one item_id in this case)
        foreach ($groupedItems as $itemId => $data) {
            $quantityToAdd = $data['quantity'];
            $totalToAdd = $data['total'];

            // Fetch the item and current stock details
            $item = Item::find($itemId);

            if (!$item) {
                Log::warning("Item not found for item_id: $itemId");
                continue; // Skip if item not found
            }

            // Log the current item details
            Log::info("Processing item_id: $itemId", [
                'quantityToAdd' => $quantityToAdd,
                'totalToAdd' => $totalToAdd,
                'existingAvgCost' => $item->avg_cost,
            ]);

            // Get the current stock and calculate the total amount in stock
            $currentStock = Item::calculateTotalStockQty($itemId);
            $currentTotalAmount = $item->avg_cost * $currentStock;

            // Log current stock details
            Log::info("Current stock details for item_id: $itemId", [
                'currentStock' => $currentStock,
                'currentTotalAmount' => $currentTotalAmount,
            ]);

            // Find the corresponding purchase order child
            $purchaseChildData = PurchaseOrderChild::where('item_id', $itemId)
                ->where('purchase_order_id', $purchaseOrderId)
                ->first();

            // Check if the item has a non-zero average cost
            if ($item->avg_cost != 0) {
                if ($purchaseChildData && $purchaseChildData->received_quantity > 0) {
                    // Sum of totals and quantities from the purchase order
                    $totalSumPurchaseOrder = PurchaseOrderChild::join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_children.purchase_order_id')
                        ->where('purchase_order_children.item_id', $itemId)
                        ->where('purchase_orders.id', $purchaseOrderId)
                        ->where('purchase_orders.is_received', 1)
                        ->sum('purchase_order_children.total');

                    $totalQtyPurchaseOrder = PurchaseOrderChild::join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_children.purchase_order_id')
                        ->where('purchase_order_children.item_id', $itemId)
                        ->where('purchase_orders.id', $purchaseOrderId)
                        ->where('purchase_orders.is_received', 1)
                        ->sum('purchase_order_children.received_quantity');

                    $difference = $totalToAdd - $totalSumPurchaseOrder;

                    $newTotalAmount = ($difference > 0)
                        ? $currentTotalAmount + $difference
                        : $currentTotalAmount - abs($difference);

                    // Reverse old PO quantities and add new ones
                    $newStockQty = $currentStock + $quantityToAdd - $totalQtyPurchaseOrder;

                    // Prevent division by zero
                    if ($newStockQty <= 0) {
                        $newAvgCost = 0;
                    } else {
                        $newAvgCost = $newTotalAmount / $newStockQty;
                    }

                    Log::info("Calculated new values for item_id: $itemId", [
                        'newTotalAmount' => $newTotalAmount,
                        'newStockQty' => $newStockQty,
                        'newAvgCost' => $newAvgCost,
                    ]);

                    $item->avg_cost = round($newAvgCost, 2);
                    $item->save();
                }
            } else {
                if ($quantityToAdd > 0) {
                    $rowsArray = [
                        'totalToAdd' => $totalToAdd,
                        'quantityToAdd' => $quantityToAdd,
                        'existingStockQty' => $item->quantity ?? 0,
                        'existingAvgCost' => $item->avg_cost,
                    ];

                    Log::info("Debugging AvgCost Calculation for item_id: $itemId", $rowsArray);

                    $newTotalAmount = $totalToAdd;
                    $newStockQty = $quantityToAdd;
                    $newAvgCost = $newTotalAmount / $newStockQty;

                    Log::info("Final calculated values for item_id: $itemId", [
                        'newTotalAmount' => $newTotalAmount,
                        'newStockQty' => $newStockQty,
                        'newAvgCost' => $newAvgCost,
                    ]);

                    $item->avg_cost = round($newAvgCost, 2);
                    $item->save();
                }
            }
        }
    }
}
