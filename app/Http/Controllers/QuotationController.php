<?php

namespace App\Http\Controllers;

use App\Models\CoaAccount;
use App\Models\Invoice;
use App\Models\InvoiceChild;
use App\Models\Item;
use App\Models\ItemInventory;
use App\Models\ItemManufacture;
use App\Models\Land;
use App\Models\Person;
use App\Models\PurchaseOrderChild;
use App\Models\Quotation;
use App\Models\QuotationChild;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use App\Services\CustomErrorMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QuotationController extends Controller
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
            $quotation_no = $req->quotation_no;
            $from = $req->from_date;
            $to = $req->to_date;
            // $searcField = $req->searcField;

            $quotationlist = Quotation::with('customer', 'salesrep')
                ->when($customer_id, function ($q, $customer_id) {
                    return $q->where('person_id', $customer_id);
                })
                ->when($sales_rep_id, function ($q, $sales_rep_id) {
                    return $q->where('sales_rep_id', $sales_rep_id);
                })
                ->when($quotation_no, function ($q, $quotation_no) {
                    return $q->where('quotation_no', $quotation_no);
                })
                ->when($from, function ($q, $from) {
                    return $q->where('date', '>=', $from);
                })
                ->when($to, function ($q, $to) {
                    return $q->where('date', '<=', $to);
                })
                // ->when($searcField, function ($q, $searcField) {
                //     return $q->where('remarks',  'LIKE', '%' . $searcField . '%');
                // })
                // ->get();

                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);


            return ['quotationlist' => $quotationlist];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function getLatestQuotationNo()
    {
        try {
            $Quot_no = Quotation::orderBy('id', 'desc')->first();
            if ($Quot_no) {
                $Quot_no = $Quot_no->quotation_no + 1;
            } else {
                $Quot_no =  1;
            }

            return ['status' => 'ok', 'Quot_no' => $Quot_no];
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
     * @param  \Illuminate\Http\Request  $customer_id
     * @param  \Illuminate\Http\Request  $quotation_no
     * @param  \Illuminate\Http\Request  $ref_no
     * @param  \Illuminate\Http\Request  $date
     * @param  \Illuminate\Http\Request  $termcondition
     * -------------------childArray
     * @param  \Illuminate\Http\Request  $item_id
     * @param  \Illuminate\Http\Request  $manufacture_id
     * @param  \Illuminate\Http\Request  $pack
     * @param  \Illuminate\Http\Request  $retail_price
     * @param  \Illuminate\Http\Request  $trade_price
     * @param  \Illuminate\Http\Request  $quantity
     * @param  \Illuminate\Http\Request  $quoted_price
     * @param  \Illuminate\Http\Request  $gst
     * @param  \Illuminate\Http\Request  $gst_amount
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = array(
            'date'   => 'required',
            'childArray'     => 'required|array',
            'childArray.*.item_id'   => 'required|int',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $Quot_no = Quotation::orderBy('id', 'desc')->first();
        if ($Quot_no) {
            $Quot_no = $Quot_no->quotation_no + 1;
        } else {
            $Quot_no =  1;
        }

        try {
            DB::transaction(function () use ($request) {

                $date = date('y-m-d');
                $Quot_no = Quotation::orderBy('id', 'desc')->first();
                if ($Quot_no) {
                    $Quot_no = $Quot_no->quotation_no + 1;
                } else {
                    $Quot_no =  1;
                }

                // Get initials from customer name if customer_id is provided
                $initials = 'XXX';
                if ($request->customer_id) {
                    $customer = Person::find($request->customer_id);
                    if ($customer) {
                        $name_parts = explode(' ', trim($customer->name));
                        $initials = '';
                        foreach ($name_parts as $part) {
                            if (!empty($part)) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                        }
                    }
                } elseif ($request->walk_in_customer_name) {
                    $name_parts = explode(' ', trim($request->walk_in_customer_name));
                    $initials = '';
                    foreach ($name_parts as $part) {
                        if (!empty($part)) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                    }
                }

                // Format date as YYMMDD
                $formatted_date = date('ymd');
                
                // Get last quotation number for auto-incrementing number
                $last_quotation = Quotation::orderBy('id', 'desc')->first();
                $increment_number = $last_quotation ? $last_quotation->id + 1 : 1;
                
                $ref_no = 'ZT/' . $initials . '/' . $formatted_date . '/' . $increment_number;
                $quotation = new Quotation();
                $quotation->person_id = $request->customer_id;
                $quotation->quotation_no = $Quot_no;
                $quotation->sales_rep_id = $request->sales_rep_id;
                $quotation->walk_in_customer_name = $request->walk_in_customer_name;
                $quotation->walk_in_customer_phone = $request->walk_in_customer_phone;
                $quotation->walk_in_customer_phone = $request->walk_in_customer_phone;
                $quotation->walk_in_customer_phone = $request->walk_in_customer_phone;
                $quotation->sale_type = $request->sale_type;
                $quotation->tax_type = $request->tax_type;
                $quotation->ref_no     = $ref_no;
                $quotation->date = $request->date;
                $quotation->remarks = $request->remarks;
                $quotation->termcondition = $request->termcondition;
                $quotation->save();
                $quotation_id = $quotation->id;

                foreach ($request->childArray as $row) {
                    $quotationChilddata = new QuotationChild();
                    $quotationChilddata->parent_id = $quotation_id;
                    $quotationChilddata->item_id = $row['item_id'];
                    $quotationChilddata->manufacture_id = $row['manufacture_id'];
                    $quotationChilddata->quantity = $row['quantity'];
                    $quotationChilddata->retail_price = $row['retail_price'];
                    $quotationChilddata->trade_price = $row['trade_price'];
                    $quotationChilddata->quoted_price = $row['quoted_price'];
                    $quotationChilddata->gst = $row['gst'];
                    $quotationChilddata->gst_amount = $row['gst_amount'];
                    $quotationChilddata->total = $row['quantity'] * $row['quoted_price'];
                    $quotationChilddata->save();

                    $itemUpdate =  Item::find($row['item_id']);
                    $itemUpdate->rate = $row['quoted_price'];
                    $itemUpdate->save();
                }
            });

            return ['status' => "ok", 'message' => 'Quotation created successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
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
    }

    /**
     * Show the form for editing the Purchase Oder.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ViewQuotationDetails(Request $req)
    {

        $rules = array(
            'id' => 'required|int|exists:quotations,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $quotation = Quotation::with('customer')->find($req->id);
            $quotChild = QuotationChild::with('item', 'manufacture')->where('parent_id', $req->id)->get();



            return ['quotation' => $quotation, 'quotChild' => $quotChild];
        } catch (\Exception $e) {
            return $e;
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
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
            'id' => 'required|int|exists:quotations,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $quotation = Quotation::with('customer')->find($req->id);

            $quotationChild = QuotationChild::with('item')->where('parent_id', $req->id)->get();
            $childItem = [];
            $itemname = [];
            foreach ($quotationChild as $child) {

                $itemsInv = Item::with('itemAvaiableInventory')->where('id', $child->item_id)->first();

                if ($itemsInv->itemAvaiableInventory) {

                    $qty_available = $itemsInv->itemAvaiableInventory->item_available;
                } else {
                    $qty_available =  0;
                }
                $AvgPrice =  PurchaseOrderChild::getStoredAveragePrice($child->item_id);

                $manufactureOptions = [];
                $manufacture = ItemManufacture::with('manufacture', 'item')->where('item_id', $child->item_id)->orderBy('id')->get();

                foreach ($manufacture as $manufacture) {
                    $manufactureOptions[] = array(
                        'id' => $manufacture->manufacture_id,
                        'value' => $manufacture->manufacture_id,
                        'label' => $manufacture->manufacture->id . '-' . $manufacture->manufacture->name,

                    );
                }
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
                        'batch_no' => $iteminventory->batch_no

                    );
                }

                $itemname[] = array(
                    'id' => $child->id,
                    'item_id' => $child->item_id,
                    'item_name' => $child->item->name,
                    'quantity' => $child->quantity ?? 0,
                    'manufacture_id' => $child->manufacture_id,
                    'pack' => $child->pack,
                    'retail_price' => $child->retail_price,
                    'trade_price' => $child->trade_price,
                    'quoted_price' => $child->quoted_price,
                    'rate' => $child->quoted_price,
                    'avg_price' => $AvgPrice ?? 0,
                    'gst' => $child->gst,
                    'pack' => $child->pack,
                    'gst_amount' => $child->gst_amount,
                    'total' => $child->total,
                    'qty_available' => $qty_available,
                    'batchOptions' => $batchOptions,
                    'manufactureOptions' => $manufactureOptions,
                    'items' => $items,
                );
            }
            $data = array(
                "id" => $quotation->id,
                "customer_id" => $quotation->person_id ?? '',
                "sales_rep_id" => $quotation->sales_rep_id ?? '',
                "walk_in_customer_name" => $quotation->walk_in_customer_name ?? '',
                "walk_in_customer_phone" => $quotation->walk_in_customer_phone ?? '',
                "ref_no" => $quotation->ref_no,
                "sale_type" => $quotation->sale_type,
                "tax_type" => $quotation->tax_type,
                "quotation_no" => $quotation->quotation_no,
                "remarks" => $quotation->remarks,
                "date" => $quotation->date,
                "total_amount" => $quotation->total_amount,
                "status" => $quotation->status,
                "remarks" => $quotation->remarks,
                "termcondition" => $quotation->termcondition,
                "supplier_name" => $quotation->customer->name ?? '',
                "store_name" => $quotation->store->name ?? '',
                "childArray" =>  $itemname
            );
            return ['data' => $data];
        } catch (\Exception $e) {
            return $e;
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function getQuotationForIntiaite(Request $req)
    {
        $rules = array(
            'id' => 'required|int|exists:quotations,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $quotation = Quotation::with('customer')->find($req->id);

            $quotationChild = QuotationChild::with('item')->where('parent_id', $req->id)->get();
            $childItem = [];

            foreach ($quotationChild as $child) {

                $itemsInv = Item::with('itemAvaiableInventory')->where('id', $child->item_id)->first();

                if ($itemsInv->itemAvaiableInventory) {

                    $qty_available = $itemsInv->itemAvaiableInventory->item_available;
                } else {
                    $qty_available =  0;
                }

                $AvgPrice =  PurchaseOrderChild::getStoredAveragePrice($child->item_id);


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
                        'batch_no' => $iteminventory->batch_no

                    );
                }

                $itemname[] = array(
                    'id' => $child->id,
                    'item_id' => $child->item_id,
                    'item_name' => $child->item->name,
                    'quantity' => $child->quantity ?? 0,
                    'manufacture_id' => $child->manufacture_id,
                    'pack' => $child->pack,
                    'retail_price' => $child->retail_price,
                    'trade_price' => $child->trade_price,
                    'quoted_price' => $child->quoted_price,
                    'rate' => $child->quoted_price,
                    'avg_price' => $AvgPrice ?? 0,
                    'gst' => $child->gst,
                    'gst_amount' => $child->gst_amount,
                    'total' => $child->total,
                    'qty_available' => $qty_available,
                    'batchOptions' => $batchOptions,
                    'items' => $items,
                );
            }
            $data = array(
                "id" => $quotation->id,
                "customer_id" => $quotation->person_id ?? '',
                "sales_rep_id" => $quotation->sales_rep_id ?? '',
                "walk_in_customer_name" => $quotation->walk_in_customer_name ?? '',
                "walk_in_customer_phone" => $quotation->walk_in_customer_phone ?? '',
                "ref_no" => $quotation->ref_no,
                "sale_type" => $quotation->sale_type,
                "tax_type" => $quotation->tax_type,
                "adv_tax_percentage" => $quotation->adv_tax_percentage,
                "adv_tax" => $quotation->adv_tax,
                "quotation_no" => $quotation->quotation_no,
                "remarks" => $quotation->remarks,
                "date" => $quotation->date,
                "total_amount" => $quotation->total_amount,
                "status" => $quotation->status,
                "remarks" => $quotation->remarks,
                "termcondition" => $quotation->termcondition,
                "supplier_name" => $quotation->customer->name ?? '',
                "store_name" => $quotation->store->name ?? '',
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
    public function update(Request $request)
    {
        $rules = array(
            'id' => 'required|int|exists:quotations,id',
            'date'   => 'required',
            'childArray'     => 'required|array',
            'childArray.*.item_id'   => 'required|int',
            'childArray.*.quantity'  => 'required|int',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {

                $quotation =  Quotation::find($request->id);
                $quotation->person_id = $request->supplier_id;
                $quotation->sales_rep_id = $request->sales_rep_id;
                $quotation->ref_no     = $request->ref_no;
                $quotation->date = $request->date;
                $quotation->remarks = $request->remarks;
                $quotation->termcondition = $request->termcondition;
                $quotation->save();

                $poChildIds = [];
                foreach ($request->childArray as $row) {

                    if (isset($row['id'])) {
                        $quotationChilddata = QuotationChild::find($row['id']);
                    } else {
                        $quotationChilddata = new QuotationChild();
                    }
                    $quotationChilddata->parent_id = $request->id;
                    $quotationChilddata->item_id = $row['item_id'];
                    $quotationChilddata->manufacture_id = $row['manufacture_id'];
                    $quotationChilddata->pack = $row['pack'];
                    $quotationChilddata->retail_price = $row['retail_price'];
                    $quotationChilddata->trade_price = $row['trade_price'];
                    $quotationChilddata->quantity = $row['quantity'];
                    $quotationChilddata->quoted_price = $row['quoted_price'];
                    $quotationChilddata->gst = $row['gst'];
                    $quotationChilddata->gst_amount = $row['gst_amount'];
                    $quotationChilddata->save();
                    $quotationChilddata->id;
                    //  return  $poChildIds[] = $row[$purchaseChilddata->id];
                    $poChildIds[] = $quotationChilddata->id;
                }
                QuotationChild::where('person_id', $request->id)->whereNotIn('id', $poChildIds)->delete();
            });

            return ['status' => "ok", 'message' => 'Quotation Update successfully'];
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
    public function destroy(Request $req)
    {
        $rules = array(
            'id' => 'required|int|exists:quotations,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            $quotation = Quotation::find($req->id);
            $InvoiceParent = Invoice::where('quotation_id', $req->id)->first();
            if ($quotation->is_inv_generated == 1) {
                return ['status' => "error", 'message' => 'Quotation already Initiated, cannot be deleted'];
            }
            if ($InvoiceParent) {
                return ['status' => "error", 'message' => 'Quotation has Invoices, cannot be deleted'];
            }

            if ($quotation->is_approved == 0) {
                DB::transaction(function () use ($req) {
                    $deleteQuotation = Quotation::where('id', $req->id)->delete();
                    $deleteQuotChild = QuotationChild::where('parent_id', $req->id)->delete();
                });
                return ['status' => "ok", 'message' => 'Quotation Deleted successfully'];
            } else {
                return ['status' => "error", 'message' => 'Quotation is Approved Can not be Delete'];
            }
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
    public function approveOrUnapproveQuotation(Request $req)
    {
        $rules = array(
            'id' => 'required|int'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $quotation = Quotation::find($req->id);
        if (!$quotation) {
            return ['status' => "error", 'message' => 'Quotation Not found'];
        }
        $message = '';
        DB::transaction(function () use ($req, $quotation, &$message) {
            $isApproved = $quotation->is_approved == 1 ? 0 : 1;
            $message = $quotation->is_approved == 1 ? 'Unapproved' : 'Approved';
            $quotation->is_approved = $isApproved;
            $quotation->save();
        });
        return ['status' => "ok", 'message' => 'Quotation ' . $message . ' successfully'];
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
    public function generateInvoiceQuotation(Request $request)
    {
        // return response()->json($request->all());
        $rules = array(
            'date'   => 'required',
            'childArray'     => 'required|array',
            'childArray.*.item_id'   => 'required|int',
            'childArray.*.quantity'  => 'required|int',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $quotation = Quotation::find($request->id);
            if ($quotation->is_inv_generated == 0) {
                DB::transaction(function () use ($request) {

                    if ($request->sale_type == 1) {
                        $walk_in_customer_name = $request->walk_in_customer_name;
                        $customer_id = NULL;
                    } elseif ($request->sale_type == 2) {
                        $customer_id = $request->customer_id;
                        $walk_in_customer_name = NULL;
                    }
                    $totalCashSaleVoucher = 0;
                    $adv_tax = $request->adv_tax;
                    $invoice = new Invoice();
                    $invoice->customer_id     = $customer_id;
                    $invoice->sales_rep_id     = $request->sales_rep_id;
                    $invoice->walk_in_customer_name = $walk_in_customer_name;
                    $invoice->walk_in_customer_phone = $request->walk_in_customer_phone;
                    $invoice->store_id        = $request->store_id;
                    $invoice->sale_type       = $request->sale_type;
                    $invoice->quotation_id    = $request->id;
                    $invoice->total_amount    = $request->total_amount;
                    $invoice->amount_received = $request->amount_received;
                    $invoice->bank_amount_received = $request->bank_amount_received;
                    $invoice->date        = $request->date;
                    $invoice->account_id  = $request->account_id;
                    $invoice->bank_account_id = $request->bank_account_id;
                    $invoice->remarks         = $request->remarks;
                    $invoice->tax_type        = $request->tax_type;
                    $invoice->adv_tax        = $request->adv_tax;
                    $invoice->adv_tax_percentage        = $request->adv_tax_percentage;
                    $invoice->save();
                    $invoice_id = $invoice->id;
                    $invoice_no = $invoice->invoice_no;

                    $remarks = " Quotation ";
                    $supplier_coa_account_id = CoaAccount::where([['person_id', $request->customer_id]])->select('id', 'coa_group_id', 'coa_sub_group_id')->first();
                    $customer_id = Person::where('id', $request->customer_id)->value('name');
                    $is_post_dated = isset($request->cheque_no) ? 1 : 0;
                    $getVoucherNo = DB::table('vouchers')->where('type', 3)->orderBy('id', 'desc')->first();
                    $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;
                    ///// cost jv voucher
                    $voucherInventoryCost = new Voucher();
                    $voucherInventoryCost->voucher_no = $newVoucherNo;
                    $voucherInventoryCost->date = date('y-m-d');
                    $voucherInventoryCost->name =  "Inventory Cost Out" . ", Invoice no: " . $invoice_no;
                    $voucherInventoryCost->invoice_id = $invoice_id;
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

                        $invoiceChild = new InvoiceChild();
                        $invoiceChild->invoice_id = $invoice_id;
                        $invoiceChild->item_id    = $row['item_id'];
                        $invoiceChild->quantity   = $row['quantity'];
                        $invoiceChild->rate       = $row['rate'];
                        // $invoiceChild->sales_tax  = $row['sales_tax'];
                        $invoiceChild->amount     = $row['total'];
                        $invoiceChild->discount   = $row['item_discount'];
                        $invoiceChild->item_discount_per   = $row['item_discount_per'];
                        $invoiceChild->total_amount = $row['rate'] * $row['quantity'];
                        $invoiceChild->save();

                        $invoiceChild_id = $invoiceChild->id;

                        $itemUpdate =  Item::find($row['item_id']);
                        $itemUpdate->rate = $row['rate'];
                        $itemUpdate->save();

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
                        $voucherTransaction->debit = 0;
                        $voucherTransaction->credit = $row['avg_price'] * $row['quantity'];
                        $voucherTransaction->description = $itemName . " Inventory Sold. "  . '' . "Avg Cost:" . '' .  round($row['avg_price']) . ' ' . "," . ' ' . "Qty" . " " . $row['quantity'] . " ,Invoice no: " . $invoice_no;
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
                        $voucherTransaction->description = $itemName . " Inventory Sold. "   . '' . "Avg Cost:" . '' . round($row['avg_price']) . ' ' . "," . ' ' . "Qty" . " " . $row['quantity'] . " ,Invoice no: " . $invoice_no;
                        $voucherTransaction->save();
                        $debitside += $row['avg_price'] * $row['quantity'];
                    }

                    $quotation_inv =  Quotation::find($request->id);
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
                        $voucherRevenueGen->name =  "Revenue Generated" . " ,Invoice no: " . $invoice_no;
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
                            $voucherTransaction->credit =   $list['rate'] * $list['quantity'];
                            $voucherTransaction->description = $itemName . " revenue . "  . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $creditside += $list['rate'] * $list['quantity'];
                        }
                        $totalCashSaleVoucher += $request->amount_received;
                        //---------------------Cash 1 Debit ------------------
                        if ($request->amount_received > 0) {
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
                        }
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
                        if ($request->gst > 0) {
                            //   $totalCashSaleVoucher += $request->gst;
                            //---------------------Crediting Reneve ------------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherRevGen_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = 23;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit =   $request->gst;
                            $voucherTransaction->description = "Gst " .  " ,Invoice no: " . $invoice_no;
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
                        if ($request->item_discount > 0) {
                            $totalCashSaleVoucher += $request->item_discount;

                            //-------------Debiting item_discount Expense   ------------------

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherRevGen_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = 28;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $request->item_discount;
                            $voucherTransaction->credit = 0;
                            $voucherTransaction->description = 'Discount' . '' .  ",Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $request->item_discount;
                        }
                        if ($request->adv_tax > 0) {
                            //------ Advance Sale Tax  ------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherRevGen_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = 871;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit =   $adv_tax;
                            $voucherTransaction->description = "Advance Sale Tax  " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $creditside += $request->adv_tax;
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
                            $PurchasePrice = PurchaseOrderChild::with('item')->where('item_id', $list['item_id'])->groupby('item_id')->select(DB::raw('SUM(rate*quantity) / (SUM(quantity)) as AvgPrice'), 'item_id')->first();

                            $itemName = $PurchasePrice->item->name;
                            //---------------------Crediting Reneve ------------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherInvCost_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = 4;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit =   $list['rate'] * $list['quantity'];
                            $voucherTransaction->description = $itemName . " sold . "  . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
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
                            $voucherTransaction->description = $itemName . " sold . "  . '' . "Rate: " . '' . $list['rate'] . ' ' . "," . ' ' . "Qty" . " " . $list['quantity'] . " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $list['rate'] * $list['quantity'];
                        }

                        if ($request->item_discount > 0) {
                            // $totalCreditSaleVoucher += $request->item_discount;
                            //---------------------Crediting Customer Account Deu To item_discount ------------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherInvCost_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = $customer_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit =  $request->item_discount;
                            $voucherTransaction->description = 'Discount' . '' . $invoice_no;
                            $voucherTransaction->save();
                            $creditside += $request->item_discount;

                            //---------------------Debiting discount Expense   ------------------

                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherInvCost_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = 28;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $request->item_discount;
                            $voucherTransaction->credit = 0;
                            $voucherTransaction->description = 'Discount' . '' . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $request->item_discount;
                        }
                        if ($request->amount_received > 0) {
                            // $totalCashSaleVoucher += $request->amount_received;
                            $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
                            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                            $voucherRevenueGen = new Voucher();
                            $voucherRevenueGen->voucher_no = $newVoucherNo;
                            $voucherRevenueGen->date = date('y-m-d');
                            $voucherRevenueGen->name =  "Customer Amount Received ";
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
                            $voucherTransaction->credit =   $request->amount_received;
                            $voucherTransaction->description = "Amount received (Cash) against " .  " ,Invoice no: " . $invoice_no;
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
                            $voucherTransaction->description = "Amount received (Cash) against " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $request->amount_received;
                        }
                        ///bank
                        if ($request->bank_amount_received > 0) {

                            // $totalCashSaleVoucher += $request->amount_received;
                            $getVoucherNo = DB::table('vouchers')->where('type', 2)->orderBy('id', 'desc')->first();
                            $newVoucherNo = $getVoucherNo ? $getVoucherNo->voucher_no + 1 : 1;

                            $voucherRevenueGen = new Voucher();
                            $voucherRevenueGen->voucher_no = $newVoucherNo;
                            $voucherRevenueGen->date = date('y-m-d');
                            $voucherRevenueGen->name =  "Customer Amount Received bank";
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
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = $customer_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = 0;
                            $voucherTransaction->credit =   $request->bank_amount_received;
                            $voucherTransaction->description = "Amount received (Bank) against " .  " ,Invoice no: " . $invoice_no;
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
                            $voucherTransaction->description = "Amount received (Bank) against " .  " ,Invoice no: " . $invoice_no;
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
                            $voucherTransaction->credit =   $request->gst;
                            $voucherTransaction->description = "Gst " .  " ,Invoice no: " . $invoice_no;
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
                            $voucherTransaction->description = "Gst " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
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
                            $voucherTransaction->credit =   $adv_tax;
                            $voucherTransaction->description = "Advance Sale Tax " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $creditside += $request->adv_tax;
                            //---------------------Cash 1 Debit ------------------
                            $voucherTransaction = new VoucherTransaction();
                            $voucherTransaction->voucher_id = $voucherInvCost_id;
                            $voucherTransaction->date = date('y-m-d');
                            $voucherTransaction->coa_account_id = $customer_account_id;
                            $voucherTransaction->is_approved = 1;
                            $voucherTransaction->debit = $adv_tax;
                            $voucherTransaction->credit = 0;
                            $voucherTransaction->description = "Advance Sale Tax " .  " ,Invoice no: " . $invoice_no;
                            $voucherTransaction->save();
                            $debitside += $request->adv_tax;
                        }
                    }

                    if ($creditside != $debitside) {
                        throw new \Exception('debit and credit sides are not equal'. $creditside .' = ' . $debitside);
                    }
                    $voucher = Voucher::find($voucherInvCost_id);
                    $voucher->total_amount = $debitside;
                    $voucher->save();
                });
                return ['status' => "ok", 'message' => 'Quotation created successfully'];
            } elseif ($quotation->is_approved == 0) {
                return ['status' => "error", 'message' => 'Approve The Quotation'];
            } elseif ($quotation->is_inv_generated == 1) {
                return ['status' => "error", 'message' => 'Quotation already initiated'];
            } else {
                return ['status' => "error", 'message' => 'Error Intiating'];
            }
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
