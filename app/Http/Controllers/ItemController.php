<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemInventory;
use App\Models\ItemManufacture;
use App\Models\Person;
use App\Models\PurchaseOrderChild;
use App\Models\Store;
use App\Models\SubCategory;
use App\Services\CustomErrorMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ItemController extends Controller
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
            $subcategory_id = $req->subcategory_id;
            $category_id = $req->category_id;
            $manufacture_id = $req->manufacture_id;
            $name = $req->name;
            $unit_id = $req->unit_id;
            $strength_unit_id = $req->strength_unit_id;
            if ($req->status == 0 && $req->status != null) {
                $status = '00';
            } else if ($req->status == 1) {
                $status = 1;
            } else {
                $status = '';
            }
            $Items = Item::with('category', 'subcategory', 'unit', 'strengthunit', 'manufacture')
                ->when($manufacture_id, function ($query) use ($manufacture_id) {
                    $query->whereHas('manufacture', function ($q) use ($manufacture_id) {
                        $q->where('manufacture_id', $manufacture_id);
                    });
                })
                ->when($name, function ($q, $name) {
                    return $q->where('name', 'LIKE', '%' . $name . '%');
                })
                ->when($status, function ($q, $status) {
                    return $q->where('isActive', $status);
                })
                ->when($subcategory_id, function ($q, $subcategory_id) {
                    return $q->where('subcategory_id', $subcategory_id);
                })

                ->when($unit_id, function ($q, $unit_id) {
                    return $q->where('unit_id', $unit_id);
                })
                ->when($strength_unit_id, function ($q, $strength_unit_id) {
                    return $q->where('strength_unit_id', $strength_unit_id);
                })
                ->when($category_id, function ($q, $category_id) {
                    return $q->where('category_id', $category_id);
                })
                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['Items' => $Items];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function itemDetails(Request $req)
    {
        $rules = array(
            'id' => 'required|int|exists:items,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $Item = Item::with('category', 'subcategory', 'unit')->where('id', $req->id)->first();
            return ['status' => "ok", 'Item' => $Item];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
public function getItemsBySupplier($personId)
{
    $items = Item::whereHas('purchaseOrders', function ($query) use ($personId) {
        $query->where('person_id', $personId);
    })->get(['id', 'name']); // Adjust the fields you need

    return response()->json(['items' => $items]);
}


    public function getItemsDropDown(Request $req)
    {
        try {
            $status = $req->status ?? 1;

            $category = $req->category_id;
            $sub_category_id = $req->sub_category_id;
            $items = Item::with('iteminventory', 'unit', 'strengthunit')
            // ->where('isActive', 1)
                ->when($status == 1, function ($q, $isActive) {
                    return $q->where('isActive', 1);
                })
                ->when($status == 2, function ($q, $isInActive) {
                    return $q->orWhere('isActive', 0);
                })
                ->when($status == 3, function ($q, $all) {
                    return $q->whereIn('isActive', [1, 0]);
                })
                ->when($category, function ($q, $category) {
                    return $q->where('category_id', $category);
                })
                ->when($sub_category_id, function ($q, $sub_category_id) {
                    return $q->where('subcategory_id', $sub_category_id);
                })
                ->orderBy('id')->get();

            // $batchNumber = ItemInventory::select('batch_no')->where('item_id', $childData[$i]->item_id)->get();
            return ['status' => 'ok', 'items' => $items];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function getItemsDropDownSale(Request $req)
    {
        try {
            $status = $req->status ?? 1;
            $items = Item::with('iteminventory', 'strengthunit', 'unit')
                ->when($status == 1, function ($q, $isActive) {
                    return $q->where('isActive', 1);
                })
                ->when($status == 2, function ($q, $isInActive) {
                    return $q->orWhere('isActive', 0);
                })
                ->when($status == 3, function ($q, $all) {
                    return $q->whereIn('isActive', [1, 0]);
                })->orderBy('id')->get();

            // $batchNumber = ItemInventory::select('batch_no')->where('item_id', $childData[$i]->item_id)->get();
            return ['status' => 'ok', 'items' => $items];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function getItemsDropDownQuotation(Request $req)
    {
        try {

            $status = $req->status ?? 1;
            $items = Item::with('iteminventory', 'unit', 'strengthunit')
                ->when($status == 1, function ($q, $isActive) {
                    return $q->where('isActive', 1);
                })
                ->when($status == 2, function ($q, $isInActive) {
                    return $q->orWhere('isActive', 0);
                })
                ->when($status == 3, function ($q, $all) {
                    return $q->whereIn('isActive', [1, 0]);
                })->orderBy('id')->get();
            $itemDropdown = array();
            foreach ($items as $items) {

                $manufacturerOptions = array();
                $manufacturer = ItemManufacture::with('manufacture', 'item')->where('item_id', $items->id)->get();
                foreach ($manufacturer as $manufacturer) {
                    $manufacturerOptions[] = array(
                        'id' => $manufacturer->manufacture->id,
                        'value' => $manufacturer->manufacture->id,
                        'label' => $manufacturer->manufacture->name,
                    );
                }
                $itemDropdown[] = array(
                    'id' => $items->id,
                    'category_id' => $items->category_id,
                    'subcategory_id' => $items->subcategory_id,
                    'name' => $items->name,
                    'rate' => $items->rate,
                    'pack' => $items->pack,
                    'unit' => $items->unit->name ?? '',
                    'strength' => $items->strength,
                    'manufacturerOptions' => $manufacturerOptions,

                );
            }
            // $batchNumber = ItemInventory::select('batch_no')->where('item_id', $childData[$i]->item_id)->get();
            return ['status' => 'ok', 'items' => $itemDropdown];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function getItemsDropDownPo(Request $req)
    {
        try {
            $status = $req->status ?? 1;
            $items = Item::with('iteminventory', 'unit', 'strengthunit')
                ->when($status == 1, function ($q, $isActive) {
                    return $q->where('isActive', 1);
                })
                ->when($status == 2, function ($q, $isInActive) {
                    return $q->orWhere('isActive', 0);
                })
                ->when($status == 3, function ($q, $all) {
                    return $q->whereIn('isActive', [1, 0]);
                })->orderBy('id')->get();
            $itemDropdown = array();
            foreach ($items as $items) {

                $manufacturerOptions = array();
                $manufacturer = ItemManufacture::with('manufacture', 'item')->where('item_id', $items->id)->get();
                foreach ($manufacturer as $manufacturer) {
                    $manufacturerOptions[] = array(
                        'id' => $manufacturer->manufacture->id ?? '',
                        'value' => $manufacturer->manufacture->id ?? '',
                        'label' => $manufacturer->manufacture->name ?? '',
                    );
                }
                $itemDropdown[] = array(
                    'id' => $items->id,
                    'category_id' => $items->category_id,
                    'subcategory_id' => $items->subcategory_id,
                    'name' => $items->name,
                    'pack' => $items->pack,
                    'strength' => $items->strength,
                    'unit' => $items->unit->name ?? '',
                    'manufacturerOptions' => $manufacturerOptions,
                    'strengthunit' => $items->strengthunit,

                );
            }
            $manufactures = Person::where('person_type', 3)->select('id', 'name')->get();
            $batchDetails = ItemInventory::select('id', 'batch_no')->groupby('id', 'batch_no')->get();
            // $batchNumber = ItemInventory::select('batch_no')->where('item_id', $childData[$i]->item_id)->get();
            return ['status' => 'ok', 'items' => $itemDropdown, 'batchDetails' => $batchDetails, 'manufactures' => $manufactures];
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
     * adding Stores data
     * @param \Illuminate\Http\Response subcategory_id
     * @param \Illuminate\Http\Response name
     * @param \Illuminate\Http\Response rate
     * @param \Illuminate\Http\Response type
     * @param \Illuminate\Http\Response strength
     * @param \Illuminate\Http\Response manufacture_id
     * @param \Illuminate\Http\Response nomenclature
     * @param \Illuminate\Http\Response minimumlevel
     * @param \Illuminate\Http\Response unit_id
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = array(
            'name' => 'required',
            'manufacture' => 'required',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {
                $item = new Item();
                $item->category_id = $request->category_id;
                $item->subcategory_id = $request->subcategory_id;
                $item->name = $request->name;
                $item->rate = $request->rate;
                $item->type = $request->type;
                $item->strength = $request->strength;
                $item->strength_unit_id = $request->strength_unit_id;
                $item->nomenclature = $request->nomenclature;
                $item->minimumlevel = $request->minimumlevel;
                $item->unit_id = 1;
                $item->save();

                $item_id = $item->id;

                foreach ($request->manufacture as $manuf) {
                    $manufacture = new ItemManufacture();
                    $manufacture->item_id = $item_id;
                    $manufacture->manufacture_id = $manuf['id'];
                    $manufacture->save();
                }
            });
            return ['status' => "ok", 'message' => 'Item added successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function addTestPerson(Request $req)
    {

        foreach ($req->data as $data) {
            $person = new Item();
            $person->name = $data['name'];
            $man = SubCategory::select('id')->where('name', $data['man'])->first();
            $person->manufacture_id = $man->id;
            $person->category_id = 1;
            $person->subcategory_id = $man->id;
            $person->nomenclature = 'NA';
            $person->minimumlevel = 50;
            $person->unit_id = 1;
            $person->isActive = 0;
            $person->save();
            $Item = $person->id;

            $manufacture = new ItemManufacture();
            $manufacture->item_id = $Item;
            $manufacture->manufacture_id = $man->id;
            $manufacture->save();
        }

        return ['status' => "ok", 'message' => 'person Stored Successfully'];
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
            'id' => 'required|int',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $item = Item::with('manufacture', 'strengthunit')->find($req->id);
            return ['status' => 'ok', 'item' => $item];
        } catch (\Exception $e) {
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
            'id' => 'required|int',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {
                $item = Item::find($request->id);
                $item->category_id = $request->category_id;
                $item->subcategory_id = $request->subcategory_id;
                $item->strength_unit_id = $request->strength_unit_id;
                $item->name = $request->name;
                $item->rate = $request->rate;
                $item->type = $request->type;
                $item->strength = $request->strength;
                $item->nomenclature = $request->nomenclature;
                $item->minimumlevel = $request->minimumlevel;
                $item->unit_id = 1;
                $item->save();
                $item_id = $item->id;

                if ($request->manufacture) {
                    foreach ($request->manufacture as $row) {
                        if (isset($row['item_manufacture_id'])) {
                            $item_manufacture = ItemManufacture::find($row['item_manufacture_id']);
                            // $item_manufacture->item_id  = $item_id;
                            // $item_manufacture->manufacture_id  =  $row['id'];
                            // $item_manufacture->save();
                        } else {
                            $item_manufacture = new ItemManufacture();
                        }
                        $item_manufacture->item_id = $request->id;
                        $item_manufacture->manufacture_id = $row['id'];
                        $item_manufacture->save();
                        $item_manufactureId[] = [$item_manufacture->id];
                    }

                    ItemManufacture::where('item_id', $request->id)->whereNotIn('id', $item_manufactureId)->delete();
                }
            });
            return ['status' => "ok", 'message' => 'Store updated successfully'];
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
        'id' => 'required|int|exists:items,id',
    );
    $validator = Validator::make($req->all(), $rules);
    if ($validator->fails()) {
        return ['status' => 'error', 'message' => $validator->errors()->first()];
    }

    try {
        // Check if the item exists in ItemInventory
        $existsInInventory = ItemInventory::where('item_id', $req->id)->exists();

        if ($existsInInventory) {
            return ['status' => 'error', 'message' => 'Item cannot be deleted as it exists in Stock'];
        }

        // Delete the item
        Item::where('id', $req->id)->delete();
        return ['status' => "ok", 'message' => 'Item deleted successfully'];
    } catch (\Exception $e) {
        $message = CustomErrorMessages::getCustomMessage($e);
        return ['status' => 'error', 'message' => $message];
    }
}


    public function itemInventory(Request $req)
    {
        $from = $req->from;
        $to = $req->to;

        $itemsInv = Item::with('itemAvaiableInventory')->where('id', $req->item_id)->first();

        $Price = PurchaseOrderChild::where('item_id', $req->item_id)
            ->when($from, function ($query, $from) use ($to) {
                $query->whereBetween('created_at', [$from, $to]);
            })
            ->groupBy('item_id')
            ->select(DB::raw('SUM(rate * quantity) / SUM(quantity) as AvgPrice'), 'item_id')
            ->first();

        // $AvgPrice = $Price->AvgPrice ?? 0;
        $AvgPrice = PurchaseOrderChild::getStoredAveragePrice($req->item_id);

        return ['itemsInv' => $itemsInv, 'AvgPrice' => $AvgPrice];
    }

    public function activeUnactiveItem(Request $request)
    {
        $rules = array(
            'id' => 'required|int|exists:items,id',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {

            $item = Item::where('id', $request->id)->where('isActive', 1)->first();
            if ($item) {
                $item = Item::find($request->id);
                $item->isActive = 0;
                $item->save();
                return ['status' => "ok", 'message' => 'Item InActive Successfully'];
            } else {
                $item = Item::find($request->id);
                $item->isActive = 1;
                $item->save();

                return ['status' => "ok", 'message' => 'Item Active Successfully'];
            }
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function getbatchNo(Request $request)
    {
        $date = date('y-m-d');
        $batch_no = ItemInventory::where('item_id', $request->item_id)
            ->where('manufacture_id', $request->manufacture_id)
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
            ->get();

        return ['batch_no' => $batch_no];
    }

    public function editItemPrices(Request $req)
    {

        $rules = array(
            'id' => 'required|int',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $item = Item::find($req->id);
            return ['status' => 'ok', 'item' => $item];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }

    public function updateItemPrices(Request $request)
    {

        $rules = array(
            'id' => 'required|int',
            'avg_cost' => 'required',
            'rate' => 'required',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {
                $item = Item::find($request->id);

                $item->avg_cost = $request->avg_cost;
                $item->rate = $request->rate;

                $item->save();
            });
            return ['status' => "ok", 'message' => 'Store updated successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
