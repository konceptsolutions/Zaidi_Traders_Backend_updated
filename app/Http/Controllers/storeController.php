<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\StoreType;
use App\Services\CustomErrorMessages;

class StoreController extends Controller
{
    /**
     * Display a listing of the stores.
     * @param \Illuminate\Http\Response colName
     * @param \Illuminate\Http\Response sort
     * @param \Illuminate\Http\Response records
     * @param \Illuminate\Http\Response pageNo
     * @return \Illuminate\Http\Response
     */

    public function index(Request $req)
    {
        $store_type_id = $req->store_type_id;
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
            $stores = Store::with('storeType')
                ->when($store_type_id, function ($q, $store_type_id) {
                    return $q->where('store_type_id', $store_type_id);
                })
                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['stores' => $stores];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }


    public function getStoreTypeDropDown()
    {
        try {
            $storeType = StoreType::orderBy('id')->get();
            return ['status' => 'ok', 'storeType' => $storeType];
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
     * adding Store data
     * @param \Illuminate\Http\Response tpye_id
     * @param \Illuminate\Http\Response name
     * @param \Illuminate\Http\Response address
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = array(
            'name' => 'required|string|min:2|max:255|unique:stores',
            'address' => 'required|string|min:2|max:255|unique:stores',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {
                $store = new Store();
                $store->name = $request->name;
                $store->store_type_id = $request->store_type_id;
                $store->address = $request->address;
                $store->save();
            });
            return ['status' => "ok", 'message' => 'Store added successfully'];
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
            $store = Store::find($req->id);
            return ['status' => 'ok', 'store' => $store];
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
                $store = Store::find($request->id);
                $store->name = $request->name;
                $store->store_type_id = $request->store_type_id;
                $store->address = $request->address;

                $store->save();
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
            'id' => 'required|int|exists:store,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            Store::where('id', $req->id)->delete();
            return ['status' => "ok", 'message' => 'Store deleted successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
    public function getStoredropdown(Request $req)

    {
        $store_type_id = $req->store_type_id;
        try {
            $store = Store::when($store_type_id, function ($q, $store_type_id) {
                return $q->where('store_type_id', $store_type_id);
            })
                ->orderBy('id')->get();
            return ['status' => 'ok', 'store' => $store];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
