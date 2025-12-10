<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\StrengthUnit;
use App\Services\CustomErrorMessages;

class StrengthUnitController extends Controller
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
            $StrengthUnit = StrengthUnit::orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['status' => 'ok', 'StrengthUnit' => $StrengthUnit];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }


    public function StrengthUnitDropdown()
    {
        $StrengthUnit = StrengthUnit::get();

        return ['StrengthUnit' => $StrengthUnit];
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
            'name' => 'required|string|min:2|max:255|unique:strength_unit',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {
                $StrengthUnit = new StrengthUnit();
                $StrengthUnit->name = $request->name;
                $StrengthUnit->description = $request->description;
                $StrengthUnit->save();
            });
            return ['status' => "ok", 'message' => 'Strength Unit added successfully'];
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
            'id' => 'required|int|exists:strength_unit,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $StrengthUnit = StrengthUnit::find($req->id);
            return ['status' => 'ok', 'StrengthUnit' => $StrengthUnit];
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
            'id' => 'required|int|exists:strength_unit',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {
                $StrengthUnit = StrengthUnit::find($request->id);
                $StrengthUnit->name = $request->name;
                $StrengthUnit->description = $request->description;
                $StrengthUnit->save();
            });
            return ['status' => "ok", 'message' => 'StrengthUnit updated successfully'];
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
            'id' => 'required|int|exists:strength_unit,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $item = Item::where('strength_unit_id', $req->id)->get();
            if ($item) {
                return ['status' => "error", 'message' => 'StrengthUnit cannot deleted Available in Item'];
            } else {
                StrengthUnit::where('id', $req->id)->delete();
                return ['status' => "ok", 'message' => 'StrengthUnit deleted successfully'];
            }
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
