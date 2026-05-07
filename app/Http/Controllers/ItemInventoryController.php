<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceReturn;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\ItemInventory;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use App\Models\PurchaseOrderChild;

use App\Models\Person;
use App\Models\PurchaseOrder;
use App\Models\ReturnPurchaseOrder;
use App\Services\CustomErrorMessages;

class ItemInventoryController extends Controller
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
            $category_id = $req->category_id;
            $subcategory_id = $req->subcategory_id;
            $item_id = $req->item_id;
            $batch_no = $req->batch_no;
            $manufacture_id = $req->manufacture_id;
            $expiry_from = $req->expiry_from;
            $expiry_to = $req->expiry_to;
            $toDayDate = date('y-m-d');
            // $supplier_id = $req->supplier_id;
            // $store_id = $req->store_id;
            // $store_type_id = $req->store_type_id;
            $itemsInventory = ItemInventory::with(['item', 'purchaseOrder', 'invoice', 'manufacture'])
                ->where('expiry_date', '>', $toDayDate)
                ->when($item_id, function ($query) use ($item_id) {
                    $query->where('item_id', $item_id);
                })
                ->when($batch_no, function ($query) use ($batch_no) {
                    $query->where('batch_no', $batch_no);
                })
                ->when($batch_no, function ($q, $batch_no) {
                    return $q->where('batch_no',  'LIKE', '%' . $batch_no . '%');
                })
                ->when($expiry_from, function ($q, $expiry_from) {
                    return $q->where('expiry_date', '>=', $expiry_from);
                })
                ->when($expiry_to, function ($q, $expiry_to) {
                    return $q->where('expiry_date', '<=', $expiry_to);
                })
                ->when($manufacture_id, function ($query) use ($manufacture_id) {
                    $query->where('manufacture_id', $manufacture_id);
                })
                ->when($category_id, function ($query) use ($category_id) {
                    $query->whereHas('item', function ($query) use ($category_id) {
                        $query->where('category_id', $category_id);
                    });
                })
                ->when($subcategory_id, function ($query) use ($subcategory_id) {
                    $query->whereHas('item', function ($query) use ($subcategory_id) {
                        $query->where('subcategory_id', $subcategory_id);
                    });
                })
                ->where('is_dummy', 0)
                ->groupBy('item_id', 'batch_no', 'manufacture_id', 'expiry_date')
                ->select('item_id', 'item_id as id', 'batch_no', 'manufacture_id', 'expiry_date', DB::raw('SUM(quantity_in) - SUM(quantity_out) as quantity'))
                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['itemsInventory' => $itemsInventory];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function getExpiredItemsInventory(Request $req)
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
            $category_id = $req->category_id;
            $subcategory_id = $req->subcategory_id;
            $item_id = $req->item_id;
            $batch_no = $req->batch_no;
            $manufacture_id = $req->manufacture_id;
            $expiry_from = $req->expiry_from;
            $expiry_to = $req->expiry_to;
            $toDayDate = date('y-m-d');
            // $supplier_id = $req->supplier_id;
            // $store_id = $req->store_id;
            // $store_type_id = $req->store_type_id;
            $itemsInventory = ItemInventory::with(['item', 'purchaseOrder', 'invoice', 'manufacture'])
                ->where('expiry_date', '<=', $toDayDate)
                ->when($item_id, function ($query) use ($item_id) {
                    $query->where('item_id', $item_id);
                })
                ->when($batch_no, function ($query) use ($batch_no) {
                    $query->where('batch_no', $batch_no);
                })
                ->when($batch_no, function ($q, $batch_no) {
                    return $q->where('batch_no',  'LIKE', '%' . $batch_no . '%');
                })
                ->when($expiry_from, function ($q, $expiry_from) {
                    return $q->where('expiry_date', '>=', $expiry_from);
                })
                ->when($expiry_to, function ($q, $expiry_to) {
                    return $q->where('expiry_date', '<=', $expiry_to);
                })
                ->when($manufacture_id, function ($query) use ($manufacture_id) {
                    $query->where('manufacture_id', $manufacture_id);
                })
                ->when($category_id, function ($query) use ($category_id) {
                    $query->whereHas('item', function ($query) use ($category_id) {
                        $query->where('category_id', $category_id);
                    });
                })
                ->when($subcategory_id, function ($query) use ($subcategory_id) {
                    $query->whereHas('item', function ($query) use ($subcategory_id) {
                        $query->where('subcategory_id', $subcategory_id);
                    });
                })
                ->where('is_dummy', 0)
                ->groupBy('item_id', 'batch_no', 'manufacture_id', 'expiry_date')
                ->havingRaw('SUM(quantity_in) - SUM(quantity_out) > 0') // filter for quantity > 0
                ->select('item_id', 'item_id as id', 'batch_no', 'manufacture_id', 'expiry_date', DB::raw('SUM(quantity_in) - SUM(quantity_out) as quantity'))
                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['itemsInventory' => $itemsInventory];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
    public function getDisposedStockItemsInventory(Request $req)
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
            $category_id = $req->category_id;
            $subcategory_id = $req->subcategory_id;
            $item_id = $req->item_id;
            $batch_no = $req->batch_no;
            $manufacture_id = $req->manufacture_id;
            $expiry_from = $req->expiry_from;
            $expiry_to = $req->expiry_to;
            $toDayDate = date('y-m-d');
            // $supplier_id = $req->supplier_id;
            // $store_id = $req->store_id;
            // $store_type_id = $req->store_type_id;
            $itemsInventory = ItemInventory::with(['item', 'purchaseOrder', 'invoice', 'manufacture'])
                //  ->where('expiry_date', '<=', $toDayDate)
                ->when($item_id, function ($query) use ($item_id) {
                    $query->where('item_id', $item_id);
                })
                ->when($batch_no, function ($query) use ($batch_no) {
                    $query->where('batch_no', $batch_no);
                })
                ->when($batch_no, function ($q, $batch_no) {
                    return $q->where('batch_no',  'LIKE', '%' . $batch_no . '%');
                })
                ->when($expiry_from, function ($q, $expiry_from) {
                    return $q->where('expiry_date', '>=', $expiry_from);
                })
                ->when($expiry_to, function ($q, $expiry_to) {
                    return $q->where('expiry_date', '<=', $expiry_to);
                })
                ->when($manufacture_id, function ($query) use ($manufacture_id) {
                    $query->where('manufacture_id', $manufacture_id);
                })
                ->when($category_id, function ($query) use ($category_id) {
                    $query->whereHas('item', function ($query) use ($category_id) {
                        $query->where('category_id', $category_id);
                    });
                })
                ->when($subcategory_id, function ($query) use ($subcategory_id) {
                    $query->whereHas('item', function ($query) use ($subcategory_id) {
                        $query->where('subcategory_id', $subcategory_id);
                    });
                })
                ->where('inventory_type_id', 6)
                ->where('is_dummy', 0)
                ->groupBy('item_id', 'batch_no', 'manufacture_id', 'expiry_date')
                ->select('item_id', 'item_id as id', 'batch_no', 'manufacture_id', 'expiry_date', DB::raw('SUM(quantity_in) - SUM(quantity_out) as quantity'))
                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['itemsInventory' => $itemsInventory];
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
        //
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
    public function edit($id)
    {
        //
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
    public function destroy($id)
    {
        //
    }
    public function getItemsInventoryLedger(Request $req)
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
            $store_id = $req->store_id;
            $inventory_type_id = $req->inventory_type_id;
            $purchase_order_id = $req->purchase_order_id;
            $invoice_id = $req->invoice_id;
            $itemsInventory = ItemInventory::with('item', 'store', 'inventoryType', 'invoice', 'purchaseOrder')
                ->when($item_id, function ($query) use ($item_id) {
                    $query->where('item_id', $item_id);
                })
                ->when($store_id, function ($query) use ($store_id) {
                    $query->where('store_id', $store_id);
                })
                ->when($inventory_type_id, function ($query) use ($inventory_type_id) {
                    $query->where('inventory_type_id', $inventory_type_id);
                })
                ->when($purchase_order_id, function ($query) use ($purchase_order_id) {
                    $query->where('purchase_order_id', $purchase_order_id);
                })
                ->when($invoice_id, function ($query) use ($invoice_id) {
                    $query->whereHas('invoice', function ($query) use ($invoice_id) {
                        $query->where('invoice_id', $invoice_id);
                    });
                })
                // ->get();
                // ->groupBy('item_id', 'store_id')
                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['itemsInventory' => $itemsInventory];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function getBatchNo(Request $req)
    {

        $batchNumber = ItemInventory::select('batch_no')->distinct()->get();

        return ['batch_no' => $batchNumber];
    }

    public function getStockReport(Request $req)
    {
        $sale_type = $req->sale_type;
        $walk_in_customer_name = $req->walk_in_customer_name;
        $customer_id = $req->customer_id;
        $item_id = $req->item_id;
        $inventory_type_id = $req->inventory_type_id;
        $manufacture_id = $req->manufacture_id;
        $category_id = $req->category_id;
        $subcategory_id = $req->subcategory_id;
        $from_date = $req->from_date;
        $to_date = $req->to_date;

        try {
            $itemsInventory = ItemInventory::with(['item', 'purchaseOrder', 'returnInvoice', 'returnPurchaseOrder', 'invoice', 'manufacture', 'inventoryType', 'purchaseOrderchild', 'invoicechild', 'returnPurchaseOrderchild.returnPurchaseOrder.purchaseorder', 'returnInvoicechild.invoice.invoice'])
                ->when($category_id, function ($query, $category_id) {
                    $query->whereHas('item', function ($squery) use ($category_id) {
                        $squery->where('category_id', $category_id);
                    });
                })
                ->when($subcategory_id, function ($query, $subcategory_id) {
                    $query->whereHas('item', function ($squery) use ($subcategory_id) {
                        $squery->where('subcategory_id', $subcategory_id);
                    });
                })
                ->when($item_id, function ($query, $item_id) {
                    $item_ids = explode(',', $item_id);
                    $query->whereIn('item_id', $item_ids);
                })
                ->when($inventory_type_id, function ($query, $inventory_type_id) {
                    $query->where('inventory_type_id', $inventory_type_id);
                })
                ->when($manufacture_id, function ($query, $manufacture_id) {
                    $query->where('manufacture_id', $manufacture_id);
                })
                ->when($from_date, function ($query, $from_date) {
                    $query->where('date', '>=', $from_date);
                })
                ->when($to_date, function ($query, $to_date) {
                    $query->where('date', '<=', $to_date);
                })
                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);

            // Calculate opening stock per item (stock before the selected date range)
            $openingStocks = [];
            if (!empty($from_date)) {
                $openingStocks = ItemInventory::when($category_id, function ($query, $category_id) {
                        $query->whereHas('item', function ($squery) use ($category_id) {
                            $squery->where('category_id', $category_id);
                        });
                    })
                    ->when($subcategory_id, function ($query, $subcategory_id) {
                        $query->whereHas('item', function ($squery) use ($subcategory_id) {
                            $squery->where('subcategory_id', $subcategory_id);
                        });
                    })
                    ->when($item_id, function ($query, $item_id) {
                        $item_ids = explode(',', $item_id);
                        $query->whereIn('item_id', $item_ids);
                    })
                    ->when($inventory_type_id, function ($query, $inventory_type_id) {
                        $query->where('inventory_type_id', $inventory_type_id);
                    })
                    ->when($manufacture_id, function ($query, $manufacture_id) {
                        $query->where('manufacture_id', $manufacture_id);
                    })
                    ->where('date', '<', $from_date)
                    ->groupBy('item_id')
                    ->select('item_id', DB::raw('COALESCE(SUM(quantity_in) - SUM(quantity_out),0) as opening_stock'))
                    ->pluck('opening_stock', 'item_id')
                    ->toArray();
            }

            return [
                'itemsInventory' => $itemsInventory,
                // opening stock per item_id, e.g. { "5": 120, "8": 30 }
                'opening_stock' => $openingStocks,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function disposeExpiredStock(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $itemId = $request->item_id;
                $disposedQuantity = (float) $request->quantity;

                // Get item and current avg_cost BEFORE disposal
                $item = Item::find($itemId);
                if (!$item) {
                    throw new \Exception('Item not found');
                }

                // Store avg_cost before disposal for voucher
                $avgCostBeforeDisposal = $item->avg_cost;

                // Get current stock BEFORE disposal (ItemInventory record not created yet)
                $currentStock = Item::calculateTotalStockQty($itemId);

                // Calculate total amount before disposal using avg_cost
                // Always use avg_cost * currentStock because avg_cost is the weighted average
                // that accounts for all purchases, sales, and returns
                if ($currentStock > 0 && $item->avg_cost > 0) {
                    $totalAmountBeforeDisposal = $item->avg_cost * $currentStock;
                } else {
                    $totalAmountBeforeDisposal = 0;
                }

                // Calculate disposed amount at current avg_cost
                $disposedAmount = $item->avg_cost * $disposedQuantity;

                // Create ItemInventory record for disposal
                $disposestock = new ItemInventory();
                $disposestock->inventory_type_id = 6;
                $disposestock->item_id = $request->item_id;
                $disposestock->batch_no = $request->batch_no;
                $disposestock->manufacture_id = $request->manufacture_id;
                $disposestock->quantity_out = $request->quantity;
                $disposestock->expiry_date = $request->expiry_date;
                $disposestock->date = date('y-m-d');
                $disposestock->is_dummy = 0;
                $disposestock->save();

                // Calculate new stock and total amount after disposal
                $newStockQty = $currentStock - $disposedQuantity;
                $newTotalAmount = $totalAmountBeforeDisposal - $disposedAmount;

                // Calculate new average cost
                if ($newStockQty > 0) {
                    $newAvgCost = $newTotalAmount / $newStockQty;
                } else {
                    $newAvgCost = 0;
                }

                // Update the item's average cost
                $item->avg_cost = $newAvgCost;
                $item->save();

                // Get item details for voucher
                $itemname = Item::where('id', $itemId)->value('name');
                $manudacturer = Person::where('id', $request->manufacture_id)->value('name');

                // Voucher for dispose (use the avg_cost before disposal for the transaction)
                $this->VoucherForDisposedStock($request, $avgCostBeforeDisposal, $itemname, $itemId, $manudacturer);
            });
            return ['status' => "ok", 'message' => 'Inventory disposed successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
    private function VoucherForDisposedStock($request, $averagePrice, $itemname, $itemId, $manudacturer)
    {
        $debitside = 0;
        $creditside = 0;
        $remarks = "Disposed Stock Voucher ";
        $is_post_dated = isset($request->cheque_no) ? 1 : 0;
        $getVoucherNo = DB::table('vouchers')->where('type', 1)->orderBy('id', 'desc')->first();
        $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
        $voucher = new Voucher();
        $voucher->voucher_no = $newVoucherNo;
        $voucher->date = date('y-m-d');
        $voucher->name =  "Disposed Stock Voucher ";
        $voucher->type = 3;
        $voucher->generated_at = date('y-m-d');
        $voucher->total_amount = 0;
        //     $voucher->purchase_order_id = $purchase_id;
        $voucher->isApproved = 1;
        $voucher->cheque_no = $request->cheque_no;
        $voucher->cheque_date = $request->cheque_date;
        $voucher->is_post_dated = $is_post_dated;
        $voucher->is_auto = 1;
        $voucher->save();
        $voucher_id = $voucher->id;

        //   --------------crediting inventory --------------------

        $voucherTransaction = new VoucherTransaction();
        $voucherTransaction->voucher_id = $voucher_id;
        $voucherTransaction->date = date('y-m-d');
        $voucherTransaction->coa_account_id = 1;
        $voucherTransaction->debit = 0;
        $voucherTransaction->credit = ($request->quantity) * ($averagePrice);
        $voucherTransaction->is_approved = 1;
        $voucherTransaction->description =  $itemId . '-' . $itemname . " Batch NO. " . $request->batch_no  . " Manufacturer ." . $manudacturer . " Inventory Disposed. "  . '' . "Avg Cost:" . '' .  round($averagePrice) . ' ' . "," . ' ' . "Qty" . " " . $request->quantity;
        $voucherTransaction->save();
        $debitside += ($request->quantity) * ($averagePrice);

        // debiting Bad Dead Loss Expense
        $voucherTransaction = new VoucherTransaction();
        $voucherTransaction->voucher_id = $voucher_id;
        $voucherTransaction->date = $request->request_date;
        $voucherTransaction->coa_account_id = 1778;
        $voucherTransaction->debit = ($request->quantity) * ($averagePrice);
        $voucherTransaction->credit = 0;
        $voucherTransaction->is_approved = 1;
        $voucherTransaction->description =  "Bad Dead Loss Expense ";
        $voucherTransaction->description = $itemId . '-' . $itemname . "Batch NO." . $request->batch_no  . "Manufacturer ." . $manudacturer . " Inventory Disposed. "  . '' . "Avg Cost:" . '' .  round($averagePrice) . ' ' . "," . ' ' . "Qty" . " " . $request->quantity;
        $voucherTransaction->save();
        $creditside += ($request->quantity) * ($averagePrice);


        if ($creditside == $debitside) {
            $voucher =  Voucher::find($voucher_id);
            $voucher->total_amount = $creditside;
            $voucher->save();
        } else {
            throw new \Exception('debit and credit sides are not equal');
        }
    }
    public function getNotifications(Request $req)
    {


        try {
            $total_sales = Invoice::count();
            $total_po = PurchaseOrder::count();
            $unreceived_po = PurchaseOrder::where('is_received', 0)->where('is_approved', 1)->count();
            $pending_po = PurchaseOrder::where('is_approved', 0)->count();
            $return_po = ReturnPurchaseOrder::count();
            $return_sale = InvoiceReturn::count();
            $activeitems = Item::where('isActive', 1)->count();
            $activecustomers = Person::where('person_type', 1)->count();
            $activemanufacturers = Person::where('person_type', 3)->count();
            $activesuppliers = Person::where('person_type', 2)->count();
            $activesalesrep = Person::where('person_type', 4)->count();
            $below_min_level_stock = $this->get_below_min_level_stock();

            return [
                'activecustomers' => $activecustomers,
                'activemanufacturers' => $activemanufacturers,
                'activesuppliers' => $activesuppliers,
                'activesalesrep' => $activesalesrep,
                'activeitems' => $activeitems,
                'return_sale' => $return_sale,
                'total_sales' => $total_sales,
                'total_po' => $total_po,
                'pending_po' => $pending_po,
                'unreceived_po' => $unreceived_po,
                'return_po' => $return_po,
                'below_min_level_stock' => $below_min_level_stock
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    // private function get_below_min_level_stock()
    // {
    //     $toDayDate = date('y-m-d');
    //     $itemsInventory = ItemInventory::with(['item', 'purchaseOrder', 'invoice', 'manufacture'])
    //         ->where('expiry_date', '>', $toDayDate)
    //         ->where('is_dummy', 0)
    //         ->groupBy('item_id', 'batch_no', 'manufacture_id', 'expiry_date')
    //         ->select('item_id', 'item_id as id', 'batch_no', 'manufacture_id', 'expiry_date', DB::raw('SUM(quantity_in) - SUM(quantity_out) as quantity'))
    //         // ->count() // ->orderBy('', 'desc')
    //         ->get()->count();
    //     return $itemsInventory;
    // }
    private function get_below_min_level_stock()
    {
        $toDayDate = date('y-m-d');
        $itemsInventoryCount = ItemInventory::Join('items', 'item_inventory.item_id', '=', 'items.id')
            ->where('expiry_date', '>', $toDayDate)
            ->where('is_dummy', 0)
            ->where('items.isActive', 1)
            ->groupBy('item_id', 'batch_no', 'manufacture_id', 'expiry_date', 'items.minimumlevel')
            ->havingRaw('SUM(quantity_in) - SUM(quantity_out) < items.minimumlevel')
            ->select('items.minimumlevel', 'item_id', 'item_id as id', 'batch_no', 'manufacture_id', 'expiry_date', DB::raw('SUM(quantity_in) - SUM(quantity_out) as quantity'))
            ->get()
            ->count();

        return $itemsInventoryCount;
    }


    public function getAdjustItemId(Request $request)
    {

        $getLastPurchasePrice = ItemInventory::select(
            'item_id',
            DB::raw('(SUM(quantity_in) - SUM(quantity_out)) as quantity')
        )
            ->where('item_id', $request->item_id)
            ->groupBy('item_id')
            ->first();

        if (empty($getLastPurchasePrice)) {
            $getLastPurchasePrice = 0;
        }

        $getRate = ItemInventory::where('item_id', $request->item_id)
            ->where(function ($query) {
                $query->where('purchase_order_id', '>', 0)
                    ->orWhere('adjust_inventory_id', '>', 0);
            })
            ->orderBy('created_at', 'desc')
            ->where('is_dummy', 0)
            ->value('purchase_price');

        if (empty($getRate)) {
            $getRate = 0;
        }
        return [
            'getLastPurchasePrice' => $getLastPurchasePrice,
            'getRate' => $getRate,
        ];
    }

    public function updateBatchInfo(Request $request)
    {
        $rules = array(
            'item_id' => 'required|int',
            'manufacture_id' => 'required|int',
            'old_batch_no' => 'required|string',
            'old_expiry_date' => 'required|date',
            'new_batch_no' => 'required|string',
            'new_expiry_date' => 'required|date',
        );

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            // Update only item_inventory table
            DB::table('item_inventory')
                ->where('item_id', $request->item_id)
                ->where('manufacture_id', $request->manufacture_id)
                ->where('batch_no', $request->old_batch_no)
                ->where('expiry_date', $request->old_expiry_date)
                ->update([
                    'batch_no' => $request->new_batch_no,
                    'expiry_date' => $request->new_expiry_date,
                ]);

            return ['status' => 'ok', 'message' => 'Batch information updated successfully in inventory'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
