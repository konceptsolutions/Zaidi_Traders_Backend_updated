<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdjustInventory;
use App\Models\AdjustInventoryChild;
use App\Models\Item;
use App\Models\ItemInventory;
use App\Models\PurchaseOrderChild;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use App\Services\CustomErrorMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdjustInventoryController extends Controller
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
            $itemsInventory = AdjustInventory::orderBy($req->colName, $req->sort)
                ->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['itemsInventory' => $itemsInventory];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function view(Request $req)
    {
        try {

            $adjustInventory = AdjustInventory::find($req->id);
            $adjustInventoryChild = AdjustInventoryChild::with('item', 'manufacture')->where('adjust_inventory_id', $req->id)->get();
            return ['adjustInventory' => $adjustInventory, 'adjustInventoryChild' => $adjustInventoryChild];
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
        $rules = [
            'childArray.*.expiry_date' => ['required', 'date'],
            'childArray.*.batch_no' => ['required', 'string'],
            'childArray.*.manufacturer_id' => ['required'],
        ];

        $messages = [
            'childArray.*.batch_no.required' => 'batch_no required',
            'childArray.*.expiry_date.required' => 'expiry_date required',
            'childArray.*.manufacturer_id.required' => 'manufacturer required',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized']);
        }

        if ($user->role_id == 2) {
            $user_id = $user->id;
        } else {
            $user_id = $user->admin_id;
        }

        try {
            DB::transaction(function () use ($request, $user_id) {
                $current_date = date('Y-m-d');

                if ($request->adjust_type == 'add') {
                    $adjust_inventory = new AdjustInventory();
                    $adjust_inventory->remarks = $request->remarks;
                    $adjust_inventory->adjust_type = $request->adjust_type;
                    $adjust_inventory->date = $request->date;
                    $adjust_inventory->total_amount = $request->total_amount;
                    $adjust_inventory->save();

                    $adjust_inventory_id = $adjust_inventory->id;

                    $groupedItems = [];

                    foreach ($request->childArray as $row) {
                        $CheckExpDate = ItemInventory::where([
                            'batch_no' => $row['batch_no'],
                            'item_id' => $row['item_id'],
                            'manufacture_id' => $row['manufacturer_id'],
                        ])->first() ?? null;

                        if ($CheckExpDate && strtotime($CheckExpDate->expiry_date) <= strtotime($current_date)) {
                            throw new \Exception("Enter an expiry date greater than today's date: " . $CheckExpDate->expiry_date);
                        }

                        if (
                            $CheckExpDate == null ||
                            ($CheckExpDate->expiry_date == $row['expiry_date'] && $current_date <= $row['expiry_date'])
                        ) {
                            $itemId = $row['item_id'];
                            if (!isset($groupedItems[$itemId])) {
                                $groupedItems[$itemId] = [
                                    'quantity' => 0,
                                    'total' => 0,
                                ];
                            }

                            $groupedItems[$itemId]['quantity'] += (float) $row['quantity'];
                            $groupedItems[$itemId]['total'] += (float) $row['total'];
                        } else {
                            throw new \Exception('Batch already exists with a different expiry Date: ' . $row['batch_no'] . ' (' . $CheckExpDate->expiry_date . ')');
                        }
                    }

                    foreach ($groupedItems as $itemId => $data) {
                        $item = Item::find($itemId);
                        $currentStock = Item::calculateTotalStockQty($itemId);

                        $currentTotalAmount = $item->avg_cost * $currentStock;
                        $newStockQty = $currentStock + $data['quantity'];
                        $newTotalAmount = $currentTotalAmount + $data['total'];
                        $newAvgCost = $newTotalAmount / $newStockQty;

                        $item->avg_cost = $newAvgCost;
                        $item->save();
                    }

                    foreach ($request->childArray as $row) {
                        $CheckExpDate = ItemInventory::where([
                            'batch_no' => $row['batch_no'],
                            'item_id' => $row['item_id'],
                            'manufacture_id' => $row['manufacturer_id'],
                        ])->first() ?? null;

                        if ($CheckExpDate && strtotime($CheckExpDate->expiry_date) <= strtotime($current_date)) {
                            throw new \Exception("Enter an expiry date greater than today's date: " . $CheckExpDate->expiry_date);
                        }

                        if (
                            $CheckExpDate == null ||
                            ($CheckExpDate->expiry_date == $row['expiry_date'] && $current_date <= $row['expiry_date'])
                        ) {
                            $adjust_inventory_child = new AdjustInventoryChild();
                            $adjust_inventory_child->adjust_inventory_id = $adjust_inventory_id;
                            $adjust_inventory_child->item_id = $row['item_id'];
                            $adjust_inventory_child->manufacture_id = $row['manufacturer_id'];
                            $adjust_inventory_child->batch_no = $row['batch_no'];
                            $adjust_inventory_child->expiry_date = $row['expiry_date'];
                            $adjust_inventory_child->pack = $row['pack'];
                            $adjust_inventory_child->quantity_in = $row['quantity'];
                            $adjust_inventory_child->quantity_out = 0;
                            $adjust_inventory_child->purchase_price = $row['rate'];
                            $adjust_inventory_child->total = $row['total'];
                            $adjust_inventory_child->save();

                            $adjust_inventory_child_id = $adjust_inventory_child->id;

                            $itemInventory = new ItemInventory();
                            $itemInventory->adjust_inventory_id = $adjust_inventory_child_id;
                            $itemInventory->item_id = $row['item_id'];
                            $itemInventory->manufacture_id = $row['manufacturer_id'];
                            $itemInventory->batch_no = $row['batch_no'];
                            $itemInventory->expiry_date = $row['expiry_date'];
                            $itemInventory->inventory_type_id = 7;
                            $itemInventory->quantity_in = $row['quantity'];
                            $itemInventory->quantity_out = 0;
                            $itemInventory->purchase_price = $row['rate'];
                            $itemInventory->date = $request->date;
                            $itemInventory->save();

                            $itemUpdate = Item::find($row['item_id']);
                            $itemUpdate->pack = $row['pack'];
                            $itemUpdate->rate = $row['rate'];
                            $itemUpdate->save();
                        } else {
                            throw new \Exception('Batch already exists with a different expiry Date: ' . $row['batch_no'] . ' (' . $CheckExpDate->expiry_date . ')');
                        }
                    }

                    $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                    $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                    $voucher = new Voucher();
                    $voucher->name = "Add Adjust Inventory";
                    $voucher->voucher_no = $newVoucherNo;
                    $voucher->adjust_inventory_id = $adjust_inventory_id;
                    $voucher->date = $request->date;
                    $voucher->isApproved = 1;
                    $voucher->type = 3;
                    $voucher->generated_at = $request->date;
                    $voucher->total_amount = $request->total_amount;
                    $voucher->is_auto = 1;
                    $voucher->save();

                    $voucher_id = $voucher->id;

                    $debitside = 0;
                    $creditside = 0;

                    foreach ($request->childArray as $row) {
                        $PurchasePrice = Item::find($row['item_id']);
                        $itemName = $PurchasePrice ? $PurchasePrice->name : '';

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = 1;
                        $voucherTransaction->debit = $row['total'];
                        $voucherTransaction->credit = 0;
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->description = 'Item: ' . $itemName . ' is added from Adjust Inventory ' . ', Qty:' . $row['quantity'] . ', Rate: ' . $row['rate'];
                        $voucherTransaction->save();

                        $debitside += $row['total'];
                    }

                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucher_id;
                    $voucherTransaction->date = $request->date;
                    $voucherTransaction->coa_account_id = 1779; // OWNER CAPITAL
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->total_amount;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->description = 'Add Adjust Inventory:';
                    $voucherTransaction->save();

                    $creditside += $request->total_amount;
                } elseif ($request->adjust_type == 'remove') {
                    foreach ($request->childArray as $row) {
                        $CheckExpDate = ItemInventory::where([
                            'batch_no' => $row['batch_no'],
                            'item_id' => $row['item_id'],
                            'manufacture_id' => $row['manufacturer_id'],
                        ])->first();

                        if ($CheckExpDate && strtotime($CheckExpDate->expiry_date) <= strtotime($current_date)) {
                            throw new \Exception("Enter an expiry date greater than today's date: " . $CheckExpDate->expiry_date);
                        }
                    }

                    $groupedItems = [];

                    foreach ($request->childArray as $row) {
                        $itemId = $row['item_id'];
                        if (!isset($groupedItems[$itemId])) {
                            $groupedItems[$itemId] = ['quantity' => 0, 'total' => 0];
                        }

                        $groupedItems[$itemId]['quantity'] += $row['quantity'];
                        $groupedItems[$itemId]['total'] += $row['total'];
                    }

                    foreach ($groupedItems as $itemId => $values) {
                        $quantityToRemove = $values['quantity'];
                        $totalToRemove = $values['total'];

                        $item = Item::where('id', $itemId)->first();
                        $currentStockQty = Item::calculateTotalStockQty($itemId);
                        $currentTotalAmount = $currentStockQty * $item->avg_cost;

                        $newStockQty = $currentStockQty - $quantityToRemove;
                        $newTotalAmount = $currentTotalAmount - $totalToRemove;

                        // Check if new stock quantity is less than 0
                        if ($newStockQty < 0) {
                            throw new \Exception("Error: Quantity cannot be less than 0.");
                        }

                        // Calculate new average cost
                        if ($newStockQty > 0) {
                            $newAvgCost = $newTotalAmount / $newStockQty;
                        } elseif ($newTotalAmount > 0 && $newStockQty == 0) {
                            // Stock goes to zero but there is still positive value; keep previous average
                            $newAvgCost = $item->avg_cost;
                        } else {
                            // Both stock and value are zero (fully cleared); keep existing avg_cost instead of forcing zero
                            $newAvgCost = $item->avg_cost;
                        }

                        // Update item with new average cost
                        $item->avg_cost = $newAvgCost;
                        $item->save();
                    }

                    $adjust_inventory = new AdjustInventory();
                    $adjust_inventory->remarks = $request->remarks;
                    $adjust_inventory->adjust_type = $request->adjust_type;
                    $adjust_inventory->date = $request->date;
                    $adjust_inventory->total_amount = $request->total_amount;
                    $adjust_inventory->save();

                    $adjust_inventory_id = $adjust_inventory->id;

                    foreach ($request->childArray as $row) {
                        $CheckExpDate = ItemInventory::where([
                            'batch_no' => $row['batch_no'],
                            'item_id' => $row['item_id'],
                            'manufacture_id' => $row['manufacturer_id'],
                        ])->first();

                        if ($CheckExpDate == null || $CheckExpDate->expiry_date <= $current_date) {
                            throw new \Exception("Expiry date is less than or equal to today's date: " . ($CheckExpDate->expiry_date ?? ''));
                        }

                        $adjust_inventory_child = new AdjustInventoryChild();
                        $adjust_inventory_child->adjust_inventory_id = $adjust_inventory_id;
                        $adjust_inventory_child->item_id = $row['item_id'];
                        $adjust_inventory_child->manufacture_id = $row['manufacturer_id'];
                        $adjust_inventory_child->batch_no = $row['batch_no'];
                        $adjust_inventory_child->expiry_date = $row['expiry_date'];
                        $adjust_inventory_child->pack = $row['pack'];
                        $adjust_inventory_child->quantity_in = 0;
                        $adjust_inventory_child->quantity_out = $row['quantity'];
                        $adjust_inventory_child->purchase_price = $row['rate'];
                        $adjust_inventory_child->total = $row['total'];
                        $adjust_inventory_child->save();

                        $adjust_inventory_child_id = $adjust_inventory_child->id;

                        $itemInventory = new ItemInventory();
                        $itemInventory->adjust_inventory_id = $adjust_inventory_child_id;
                        $itemInventory->item_id = $row['item_id'];
                        $itemInventory->manufacture_id = $row['manufacturer_id'];
                        $itemInventory->batch_no = $row['batch_no'];
                        $itemInventory->expiry_date = $row['expiry_date'];
                        $itemInventory->inventory_type_id = 8;
                        $itemInventory->quantity_in = 0;
                        $itemInventory->quantity_out = $row['quantity'];
                        $itemInventory->purchase_price = $row['rate'];
                        $itemInventory->date = $request->date;
                        $itemInventory->save();
                    }

                    $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                    $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                    $voucher = new Voucher();
                    $voucher->name = "Dispose Adjust Inventory";
                    $voucher->voucher_no = $newVoucherNo;
                    $voucher->adjust_inventory_id = $adjust_inventory_id;
                    $voucher->date = $request->date;
                    $voucher->isApproved = 1;
                    $voucher->type = 3;
                    $voucher->generated_at = $request->date;
                    $voucher->total_amount = $request->total_amount;
                    $voucher->is_auto = 1;
                    $voucher->save();

                    $voucher_id = $voucher->id;

                    $debitside = 0;
                    $creditside = 0;

                    foreach ($request->childArray as $row) {
                        $item = Item::find($row['item_id']);
                        $itemName = $item ? $item->name : '';

                        $voucherTransaction = new VoucherTransaction();
                        $voucherTransaction->voucher_id = $voucher_id;
                        $voucherTransaction->date = $request->date;
                        $voucherTransaction->coa_account_id = 1;
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $row['total'];
                        $voucherTransaction->is_approved = 1;
                        $voucherTransaction->description = 'Item: ' . $itemName . ' is remove from Adjust Inventory ' . ', Qty:' . $row['quantity'] . ', Rate: ' . $row['rate'];
                        $voucherTransaction->save();

                        $debitside += $row['total'];
                    }

                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucher_id;
                    $voucherTransaction->date = $request->date;
                    $voucherTransaction->coa_account_id = 1778; //Dispose Inventory
                    $voucherTransaction->debit = $request->total_amount;
                    $voucherTransaction->credit = 0;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->description = 'Dispose Adjust Inventory:';
                    $voucherTransaction->save();

                    $creditside += $request->total_amount;
                }
            });

            return response()->json(['status' => 'ok', 'message' => 'Adjust Item created successfully']);
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return response()->json(['status' => 'error', 'message' => $message]);
        }
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
        try {
            $adjustInventory = AdjustInventory::findOrFail($req->id);
            $adjustInventoryChild = AdjustInventoryChild::with(['item', 'manufacture'])
                ->where('adjust_inventory_id', $req->id)
                ->get();

            // Compute batchQuantity for each child item
            $adjustInventoryChild->each(function ($child) use ($req) {
                $itemInventoryRecords = ItemInventory::where('item_inventory.item_id', $child->item_id)
                    ->where('item_inventory.manufacture_id', $child->manufacture_id)
                    ->join('adjust_inventory_children', function ($join) use ($req) {
                        $join->on(DB::raw('item_inventory.batch_no COLLATE utf8mb4_unicode_ci'), '=', DB::raw('adjust_inventory_children.batch_no COLLATE utf8mb4_unicode_ci'))
                            ->where('adjust_inventory_children.adjust_inventory_id', $req->id);
                    })
                    ->select('item_inventory.batch_no', DB::raw('SUM(item_inventory.quantity_in) - SUM(item_inventory.quantity_out) as item_available'))
                    ->groupBy('item_inventory.batch_no')
                    ->get();

                $child->batchQuantity = $itemInventoryRecords;
            });

            return [
                'adjustInventory' => $adjustInventory,
                'adjustInventoryChild' => $adjustInventoryChild,
            ];
        } catch (\Exception $e) {
            // Handle any exceptions and return an error message
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
            'adjustInventoryChild.*.expiry_date' => 'required',
        );

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized']);
        }

        if ($user->role_id == 2) {
            $user_id = $user->id;
        } else {
            $user_id = $user->admin_id;
        }

        try {
            DB::transaction(function () use ($request, $user_id) {
                $current_date = date('Y-m-d');

                if ($request->adjust_type == 'add') {
                    foreach ($request->adjustInventoryChild as $row) {
                        // Check if the batch, item, and manufacturer exist in ItemInventory
                        $CheckExpDate = ItemInventory::where([
                            'batch_no' => $row['batch_no'],
                            'item_id' => $row['item_id'],
                            'manufacture_id' => $row['manufacture_id'],
                        ])->first();

                        // If an entry exists, validate the expiry date
                        if ($CheckExpDate) {
                            if (strtotime($CheckExpDate->expiry_date) <= strtotime($current_date)) {
                                throw new \Exception(
                                    "Enter an expiry date greater than today's date: " . $CheckExpDate->expiry_date
                                );
                            }

                            // If the expiry date does not match, throw an exception
                            if ($CheckExpDate->expiry_date !== $row['expiry_date']) {
                                throw new \Exception(
                                    'Batch already exists with a different expiry Date: '
                                    . $row['batch_no'] . ' (' . $CheckExpDate->expiry_date . ')'
                                );
                            }
                        }
                    }

                    $adjustInventory = AdjustInventory::find($request->id);
                    if (!$adjustInventory) {
                        return response()->json(['status' => 'error', 'message' => 'AdjustInventory record not found'], 404);
                    }

                    $adjustInventory->remarks = $request->remarks;
                    $adjustInventory->date = $request->date;
                    $adjustInventory->adjust_type = $request->adjust_type;
                    $adjustInventory->total_amount = $request->total_amount;
                    $adjustInventory->save();

                    $adjust_inventory_id = $adjustInventory->id;

                    $adjustInventoryChildIds = AdjustInventoryChild::where('adjust_inventory_id', $adjust_inventory_id)->pluck('id')->toArray();

                    $addAdjustInventory = AdjustInventory::where('adjust_type', 'add')->where('id', $request->id)->first();
                    $addAdjustInventoryId = $addAdjustInventory ? $addAdjustInventory->id : null;

                    $groupedItems = [];

                    // Grouping items and calculations
                    foreach ($request->adjustInventoryChild as $row) {
                        $CheckExpDate = ItemInventory::where([
                            'batch_no' => $row['batch_no'],
                            'item_id' => $row['item_id'],
                            'manufacture_id' => $row['manufacture_id'],
                        ])->first();

                        if ($CheckExpDate && strtotime($CheckExpDate->expiry_date) <= strtotime($current_date)) {
                            throw new \Exception("Enter an expiry date greater than today's date: " . $CheckExpDate->expiry_date);
                        }

                        $itemId = $row['item_id'];
                        $stockQty = Item::calculateTotalStockQty($itemId);

                        if (!isset($groupedItems[$itemId])) {
                            $groupedItems[$itemId] = ['quantity' => 0, 'total' => 0];
                        }

                        $groupedItems[$itemId]['quantity'] += $row['quantity_in'];
                        $groupedItems[$itemId]['total'] += $row['total'];
                    }

                    if ($addAdjustInventoryId) {
                        foreach ($groupedItems as $itemId => $values) {
                            $quantityToAdd = $values['quantity'];
                            $totalToAdd = $values['total'];

                            // Get item details
                            $item = Item::where('id', $itemId)->first();

                            // Calculate remove totals
                            $removetotalQty = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                                ->where('adjust_inventory_children.item_id', $itemId)
                                ->where('adjust_inventories.id', $request->id)
                                ->where('quantity_in', 0)
                                ->sum('adjust_inventory_children.quantity_out');

                            $removetotalsum = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                                ->where('adjust_inventory_children.item_id', $itemId)
                                ->where('adjust_inventories.id', $request->id)
                                ->where('quantity_in', 0)
                                ->sum('adjust_inventory_children.total');

                            // Calculate add totals
                            $addtotalQty = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                                ->where('adjust_inventory_children.item_id', $itemId)
                                ->where('adjust_inventories.id', $request->id)
                                ->where('quantity_out', 0)
                                ->sum('adjust_inventory_children.quantity_in');

                            $addtotalsum = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                                ->where('adjust_inventory_children.item_id', $itemId)
                                ->where('adjust_inventories.id', $request->id)
                                ->where('quantity_out', 0)
                                ->sum('adjust_inventory_children.total');

                            // Calculate current totals
                            $currentStockQty = Item::calculateTotalStockQty($itemId);
                            $currentTotalAmount = $currentStockQty * $item->avg_cost;

                            // Update stock and cost
                            $newStockQty = $currentStockQty + $quantityToAdd - $addtotalQty + $removetotalQty;
                            $newTotalAmount = $currentTotalAmount + $totalToAdd - $addtotalsum + $removetotalsum;
                            $newAvgCost = $newStockQty > 0 ? $newTotalAmount / $newStockQty : 0;

                            // Update item details
                            $item->avg_cost = $newAvgCost;
                            $item->save();
                        }
                    }

                    AdjustInventoryChild::where('adjust_inventory_id', $adjust_inventory_id)->delete();
                    ItemInventory::whereIn('adjust_inventory_id', $adjustInventoryChildIds)->delete();

                    $voucher = Voucher::where('adjust_inventory_id', $adjust_inventory_id)->first();
                    if ($voucher) {
                        VoucherTransaction::where('voucher_id', $voucher->id)->delete();
                        $voucher->delete();
                    }

                    foreach ($request->adjustInventoryChild as $row) {
                        $adjust_inventory_child = new AdjustInventoryChild();
                        $adjust_inventory_child->adjust_inventory_id = $adjust_inventory_id;
                        $adjust_inventory_child->item_id = $row['item_id'];
                        $adjust_inventory_child->pack = $row['pack'];
                        $adjust_inventory_child->manufacture_id = $row['manufacture_id'];
                        $adjust_inventory_child->batch_no = $row['batch_no'];
                        $adjust_inventory_child->expiry_date = $row['expiry_date'];
                        $adjust_inventory_child->quantity_in = $row['quantity_in'];
                        $adjust_inventory_child->quantity_out = 0;
                        $adjust_inventory_child->purchase_price = $row['purchase_price'];
                        $adjust_inventory_child->total = $row['total'];
                        $adjust_inventory_child->save();

                        $adjust_inventory_child_id = $adjust_inventory_child->id;

                        $itemInventory = new ItemInventory();
                        $itemInventory->adjust_inventory_id = $adjust_inventory_child_id;
                        $itemInventory->item_id = $row['item_id'];
                        $itemInventory->manufacture_id = $row['manufacture_id'];
                        $itemInventory->batch_no = $row['batch_no'];
                        $itemInventory->expiry_date = $row['expiry_date'];
                        $itemInventory->inventory_type_id = 7;
                        $itemInventory->quantity_in = $row['quantity_in'];
                        $itemInventory->quantity_out = 0;
                        $itemInventory->purchase_price = $row['purchase_price'];
                        $itemInventory->date = $request->date;
                        $itemInventory->save();

                        $itemUpdate = Item::find($row['item_id']);
                        $itemUpdate->pack = $row['pack'];
                        $itemUpdate->rate = $row['purchase_price'];
                        $itemUpdate->save();
                    }

                    $debitside = 0;
                    $creditside = 0;
                    $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                    $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                    $voucher = new Voucher();
                    $voucher->name = "Add Adjust Inventory";
                    $voucher->voucher_no = $newVoucherNo;
                    $voucher->adjust_inventory_id = $adjust_inventory_id;
                    $voucher->date = $request->date;
                    $voucher->isApproved = 1;
                    $voucher->type = 3;
                    $voucher->generated_at = $request->date;
                    $voucher->total_amount = $request->total_amount;
                    $voucher->is_auto = 1;
                    $voucher->save();

                    $voucher_id = $voucher->id;

                    foreach ($request->adjustInventoryChild as $row) {
                        $PurchasePrice = Item::find($row['item_id']);
                        if ($PurchasePrice) {
                            $itemName = $PurchasePrice->name;
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucher_id;
                            $voucherTransaction->date = $request->date;
                            $voucherTransaction->coa_account_id = 1;
                            $voucherTransaction->debit = $row['total'];
                            $voucherTransaction->credit = 0;
                            $voucherTransaction->is_approved = 1;
                            $itemDescription = 'Item: ' . $itemName . ' is added from Adjust Inventory ' . ', Qty:' . $row['quantity_in'] . ', Rate: ' . $row['purchase_price'];
                            $voucherTransaction->description = $itemDescription;
                            $voucherTransaction->save();
                            $debitside += $row['purchase_price'];
                        }
                    }

                    $voucherTransaction = new VoucherTransaction();
                    $voucherTransaction->voucher_id = $voucher_id;
                    $voucherTransaction->date = $request->date;
                    $voucherTransaction->coa_account_id = 1779;
                    $voucherTransaction->debit = 0;
                    $voucherTransaction->credit = $request->total_amount;
                    $voucherTransaction->is_approved = 1;
                    $voucherTransaction->description = 'Add Adjust Inventory:';
                    $voucherTransaction->save();

                    $creditside += $row['purchase_price'];
                }

                if ($request->adjust_type == 'remove') {
                    $date = date('y-m-d');

                    foreach ($request->adjustInventoryChild as $row) {
                        $itemId = $row['item_id'];

                        $removetotalQty = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                            ->where('adjust_inventory_children.item_id', $itemId)
                            ->where('adjust_inventories.id', $request->id)
                            ->where('quantity_in', 0)
                            ->sum('adjust_inventory_children.quantity_out');

                        // Check expiry date for the batch
                        $addtotalQty = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                            ->where('adjust_inventory_children.item_id', $itemId)
                            ->where('adjust_inventories.id', $request->id)
                            ->where('quantity_out', 0)
                            ->sum('adjust_inventory_children.quantity_in');

                        $CheckExpDate = ItemInventory::where([
                            'batch_no' => $row['batch_no'],
                            'item_id' => $row['item_id'],
                            'manufacture_id' => $row['manufacture_id'],
                        ])->first();

                        if ($CheckExpDate && strtotime($CheckExpDate->expiry_date) <= strtotime($current_date)) {
                            throw new \Exception("Enter an expiry date greater than today's date: " . $CheckExpDate->expiry_date);
                        }

                        // Validate batch inventory
                        $batchInventory = ItemInventory::where('item_id', $row['item_id'])
                            ->where('manufacture_id', $row['manufacture_id'])
                            ->where('batch_no', $row['batch_no'])
                            ->select(
                                'item_id',
                                'batch_no',
                                'manufacture_id',
                                'expiry_date',
                                DB::raw('SUM(quantity_in) - SUM(quantity_out) as item_available')
                            )
                            ->groupBy('item_id', 'batch_no', 'manufacture_id', 'expiry_date')
                            ->having('expiry_date', '>', $date)
                            ->where('is_dummy', 0)
                            ->first();

                        if ($batchInventory && $batchInventory->item_available + $removetotalQty < $row['quantity_out']) {
                            throw new \Exception("Batch quantity for batch_no " . $row['batch_no'] . " is insufficient. Available: " . ($batchInventory->item_available + $removetotalQty));
                        }
                    }

                    $adjustInventory = AdjustInventory::find($request->id);
                    if (!$adjustInventory) {
                        return response()->json(['status' => 'error', 'message' => 'AdjustInventory record not found'], 404);
                    }

                    $adjustInventory->remarks = $request->remarks;
                    $adjustInventory->date = $request->date;
                    $adjustInventory->adjust_type = $request->adjust_type;
                    $adjustInventory->total_amount = $request->total_amount;
                    $adjustInventory->save();

                    $adjust_inventory_id = $adjustInventory->id;

                    $adjustInventoryChildIds = AdjustInventoryChild::where('adjust_inventory_id', $adjust_inventory_id)->pluck('id')->toArray();

                    $removeAdjustInventory = AdjustInventory::where('adjust_type', 'remove')
                        ->where('id', $request->id)
                        ->first();

                    $removeAdjustInventoryId = $removeAdjustInventory ? $removeAdjustInventory->id : null;

                    $groupedItems = [];

                    foreach ($request->adjustInventoryChild as $row) {
                        $addtotalQty = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                            ->where('adjust_inventory_children.item_id', $row['item_id'])
                            ->where('adjust_inventories.id', $request->id)
                            ->where('quantity_out', 0)
                            ->sum('adjust_inventory_children.quantity_in');

                        // Check expiry date for the batch
                        $CheckExpDate = ItemInventory::where([
                            'batch_no' => $row['batch_no'],
                            'item_id' => $row['item_id'],
                            'manufacture_id' => $row['manufacture_id'],
                        ])->first();

                        if ($CheckExpDate && strtotime($CheckExpDate->expiry_date) <= strtotime($current_date)) {
                            throw new \Exception("Enter an expiry date greater than today's date: " . $CheckExpDate->expiry_date);
                        }

                        // Group items for further processing
                        $itemId = $row['item_id'];
                        if (!isset($groupedItems[$itemId])) {
                            $groupedItems[$itemId] = ['quantity' => 0, 'total' => 0];
                        }

                        $groupedItems[$itemId]['quantity'] += $row['quantity_out'];
                        $groupedItems[$itemId]['total'] += $row['total'];
                    }

                    if ($removeAdjustInventoryId) {
                        foreach ($groupedItems as $itemId => $values) {
                            $quantityToRemove = $values['quantity'];
                            $totalToRemove = $values['total'];

                            $stockQty = Item::calculateTotalStockQty($itemId);

                            $removetotalQty = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                                ->where('adjust_inventory_children.item_id', $itemId)
                                ->where('adjust_inventories.id', $request->id)
                                ->where('quantity_in', 0)
                                ->sum('adjust_inventory_children.quantity_out');

                            $removetotalsum = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                                ->where('adjust_inventory_children.item_id', $itemId)
                                ->where('adjust_inventories.id', $request->id)
                                ->where('quantity_in', 0)
                                ->sum('adjust_inventory_children.total');

                            $addtotalQty = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                                ->where('adjust_inventory_children.item_id', $itemId)
                                ->where('adjust_inventories.id', $request->id)
                                ->where('quantity_out', 0)
                                ->sum('adjust_inventory_children.quantity_in');

                            $addtotalsum = AdjustInventoryChild::join('adjust_inventories', 'adjust_inventories.id', '=', 'adjust_inventory_children.adjust_inventory_id')
                                ->where('adjust_inventory_children.item_id', $itemId)
                                ->where('adjust_inventories.id', $request->id)
                                ->where('quantity_out', 0)
                                ->sum('adjust_inventory_children.total');

                            $item = Item::where('id', $itemId)->first();
                            $currentStockQty = $stockQty;
                            $currentTotalAmount = $currentStockQty * $item->avg_cost;

                            $newStockQty = $currentStockQty - $quantityToRemove - $addtotalQty + $removetotalQty;

                            if ($newStockQty < 0) {
                                $availableStockQty = $currentStockQty - $addtotalQty;
                                throw new \Exception(
                                    "Quantity cannot be less than zero. New Available stock is $availableStockQty. Please adjust the input or check the available stock."
                                );
                            }

                            $newTotalAmount = $currentTotalAmount - $totalToRemove - $addtotalsum + $removetotalsum;

                            // Calculate new average cost
                            if ($newStockQty > 0) {
                                $newAvgCost = $newTotalAmount / $newStockQty;
                            } elseif ($newTotalAmount > 0 && $newStockQty == 0) {
                                // Stock goes to zero but there is still positive value; keep previous average
                                $newAvgCost = $item->avg_cost;
                            } else {
                                // Both stock and value are zero (fully cleared); keep existing avg_cost instead of forcing zero
                                $newAvgCost = $item->avg_cost;
                            }

                            $item->avg_cost = $newAvgCost;
                            $item->save();
                        }
                    }

                    AdjustInventoryChild::where('adjust_inventory_id', $adjust_inventory_id)->delete();
                    ItemInventory::whereIn('adjust_inventory_id', $adjustInventoryChildIds)->delete();

                    $voucher = Voucher::where('adjust_inventory_id', $adjust_inventory_id)->first();
                    if ($voucher) {
                        VoucherTransaction::where('voucher_id', $voucher->id)->delete();
                        $voucher->delete();
                    }

                    foreach ($request->adjustInventoryChild as $row) {
                        $adjust_inventory_child = new AdjustInventoryChild();
                        $adjust_inventory_child->adjust_inventory_id = $adjust_inventory_id;
                        $adjust_inventory_child->item_id = $row['item_id'];
                        $adjust_inventory_child->pack = $row['pack'];
                        $adjust_inventory_child->manufacture_id = $row['manufacture_id'];
                        $adjust_inventory_child->batch_no = $row['batch_no'];
                        $adjust_inventory_child->expiry_date = $row['expiry_date'];
                        $adjust_inventory_child->quantity_in = 0;
                        $adjust_inventory_child->quantity_out = $row['quantity_out'];
                        $adjust_inventory_child->purchase_price = $row['purchase_price'];
                        $adjust_inventory_child->total = $row['total'];
                        $adjust_inventory_child->save();

                        $adjust_inventory_child_id = $adjust_inventory_child->id;

                        $itemInventory = new ItemInventory();
                        $itemInventory->adjust_inventory_id = $adjust_inventory_child_id;
                        $itemInventory->item_id = $row['item_id'];
                        $itemInventory->manufacture_id = $row['manufacture_id'];
                        $itemInventory->batch_no = $row['batch_no'];
                        $itemInventory->expiry_date = $row['expiry_date'];
                        $itemInventory->inventory_type_id = 8;
                        $itemInventory->quantity_in = 0;
                        $itemInventory->quantity_out = $row['quantity_out'];
                        $itemInventory->purchase_price = $row['purchase_price'];
                        $itemInventory->date = $request->date;
                        $itemInventory->save();
                    }

                    $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                    $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                    $voucher = new Voucher();
                    $voucher->name = "Dispose Adjust Inventory";
                    $voucher->voucher_no = $newVoucherNo;
                    $voucher->adjust_inventory_id = $adjust_inventory_id;
                    $voucher->date = $request->date;
                    $voucher->isApproved = 1;
                    $voucher->type = 3;
                    $voucher->generated_at = $request->date;
                    $voucher->total_amount = $request->total_amount;
                    $voucher->is_auto = 1;
                    $voucher->save();

                    $voucher_id = $voucher->id;

                    foreach ($request->adjustInventoryChild as $row) {
                        $PurchasePrice = Item::find($row['item_id']);
                        if ($PurchasePrice) {
                            $itemName = $PurchasePrice->name;
                            $debitTransaction = new VoucherTransaction();
                            $debitTransaction->voucher_id = $voucher_id;
                            $debitTransaction->date = $request->date;
                            $debitTransaction->coa_account_id = 1;
                            $debitTransaction->debit = 0;
                            $debitTransaction->credit = $row['total'];
                            $debitTransaction->is_approved = 1;
                            $itemDescription = 'Item: ' . $itemName . ' is remove from Adjust Inventory ' . ', Qty:' . $row['quantity_out'] . ', Rate: ' . $row['purchase_price'];
                            $debitTransaction->description = $itemDescription;
                            $debitTransaction->save();
                        }
                    }

                    $creditTransaction = new VoucherTransaction();
                    $creditTransaction->voucher_id = $voucher_id;
                    $creditTransaction->date = $request->date;
                    $creditTransaction->coa_account_id = 1778;
                    $creditTransaction->debit = $request->total_amount;
                    $creditTransaction->credit = 0;
                    $creditTransaction->is_approved = 1;
                    $creditTransaction->description = 'Dispose Adjust Inventory:';
                    $creditTransaction->save();
                }
            });

            return ['status' => 'ok', 'message' => 'Adjust Item Updated successfully'];
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
        DB::beginTransaction();

        try {
            $voucher = Voucher::where('adjust_inventory_id', $request->id)->first();
            if ($voucher) {
                $voucherId = $voucher->id;
                VoucherTransaction::where('voucher_id', $voucherId)->delete();
                ItemInventory::where('adjust_inventory_id', $request->id)->delete();
                adjustInventoryChild::where('adjust_inventory_id', $request->id)->delete();
                AdjustInventory::find($request->id)->delete();
                Voucher::find($voucherId)->delete();
            }

            DB::commit();

            return ['status' => 'ok', 'message' => 'Adjust Item Deleted successfully'];
        } catch (\Exception $e) {
            DB::rollBack();

            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
