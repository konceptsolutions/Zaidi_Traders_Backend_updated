<?php

namespace App\Http\Controllers;

use App\Models\CoaAccount;
use App\Models\CoaGroup;
use App\Models\CoaSubGroup;
use App\Models\VoucherTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CoaSubGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $req)
    {
        if ($req->is_active == 0 && $req->is_active != null) {
            $is_active = '00';
        } else if ($req->is_active == 1) {
            $is_active = 1;
        } else {
            $is_active = $req->is_active;
        }
        $coaSubGroups = CoaSubGroup::where('isActive', 1)->orderBy('code')->with('coaGroup')
            ->when($is_active, function ($q, $is_active) {
                return $q->where('isActive', $is_active);
            })->get();
        return ['coaSubGroups' => $coaSubGroups];
    }

    //------------------------------------------------------------------------------
    /**
     * Store a newly created CoaSubGroup in storage.
     *
     * @param \Illuminate\Http\Request name
     * @param \Illuminate\Http\Request coa_group_id
     * @return \Illuminate\Http\Response message
     * @return \Illuminate\Http\Response status
     */
    public function store(Request $req)
    {
        $rules = array(
            'name' => 'required',
            'coa_group_id' => 'required',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 401);
        }
        DB::transaction(function () use ($req) {
            $group = CoaGroup::find($req->coa_group_id);
            $lastCode = CoaSubGroup::where('coa_group_id', $group->id)->orderBy('id', 'desc')->first();
            $coaSubGroup = CoaSubGroup::create($req->all());
            if (!$lastCode) {
                $newCode = $group->code . '01';
            } else {
                $newCode = $lastCode->code + 1;
            }
            CoaSubGroup::where('id', $coaSubGroup->id)->update(['code' => $newCode]);
        });
        return ['status' => "ok", 'message' => 'CoaSubGroup stored successfully'];
    }
    /**
     * Display a listing of the resource.
     * @param \Illuminate\Http\Request coa_group_id
     * @return \Illuminate\Http\Response
     */
    public function coaSubGroupsByGroup(Request $req)
    {
        $rules = array(
            'coa_group_id' => 'required',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 401);
        }
        $coaSubGroups = CoaSubGroup::where([['coa_group_id', $req->coa_group_id], ['isActive', 1]])->with('coaGroup')->orderBy('code')->get();
        return ['coaSubGroups' => $coaSubGroups];
    }

    /**
     * Making sub group active or incactive
     *
     * @param \Illuminate\Http\Request sub_group_id
     * @return \Illuminate\Http\Response
     */
    public function makeSubGroupActiveOrInactive(Request $req)
    {
        $rules = array(
            'sub_group_id' => 'required|int'
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        $CoaSubGroup = CoaSubGroup::find($req->sub_group_id);
        if (!$CoaSubGroup) {
            return ['status' => "error", 'message' => 'Sub Group Not found'];
        }
        $message = $CoaSubGroup->isActive == 1 ? 'Deactivated' : 'Activated';
        $CoaSubGroup->isActive = $CoaSubGroup->isActive == 1 ? 0 : 1;
        $CoaSubGroup->save();
        return ['status' => "ok", 'message' => 'CoaSubGroup ' . $message . ' successfully'];
    }

    /**
     * Display a listing of the resource.
     * @param \Illuminate\Http\Request type
     * @return \Illuminate\Http\Response
     */
    public function getRequiredSubGroups(Request $req)
    {
        $group_id = $req->group_id;
        if (isset($req->type)) {
            $coaSubGroups = CoaSubGroup::orderBy('code')
                ->with('coaGroup')
                ->where('isActive', $req->type)
                ->when($group_id, function ($query, $group_id) {
                    return $query->where('coa_group_id', '=', $group_id);
                })
                ->get();
        } else {
            $coaSubGroups = CoaSubGroup::orderBy('code')
                ->with('coaGroup')
                ->when($group_id, function ($query, $group_id) {
                    return $query->where('coa_group_id', '=', $group_id);
                })
                ->get();
        }
        return ['coaSubGroups' => $coaSubGroups];
    }

    /**
     * editing coa sub group
     *
     * @param \Illuminate\Http\Request sub_group_id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $req)
    {
        $coaSubGroup = CoaSubGroup::with('coaGroup')->find($req->sub_group_id);
        if (!$coaSubGroup) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }
        return ['coaSubGroup' => $coaSubGroup];
    }
    /**
     * Updating coa subgroup.
     *
     * @param \Illuminate\Http\Request name
     * @param \Illuminate\Http\Request coa_group_id
     * @param \Illuminate\Http\Request coa_sub_group_id
     * @return \Illuminate\Http\Response message
     * @return \Illuminate\Http\Response status
     */
    public function update(Request $req)
    {
        $rules = array(
            'name' => 'required',
            'coa_group_id' => 'required|int',
            'id' => 'required|int',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 401);
        }
        $CoaSubGroup = CoaSubGroup::find($req->id);
        if (!$CoaSubGroup) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }
        if ($CoaSubGroup->is_default == 1) {
            return ['status' => 'error', 'message' => "This is a default subgroup you can't update it"];
        }
        $coaAccounts = CoaAccount::where('coa_sub_group_id', $req->id)->count();
        if ($coaAccounts > 0) {
            return ['status' => 'error', 'message' => "This subgroup has some accounts you can't update it"];
        }
        $CoaSubGroup->coa_group_id = $req->coa_group_id;
        $CoaSubGroup->name = $req->name;
        $CoaSubGroup->save();
        return ['status' => 'ok', 'message' => 'Sub Group updated successfully'];
    }

    /**
     * Deleting coa sub group
     *
     * @param \Illuminate\Http\Request coa_sub_group_id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $req)
    {
        $CoaSubGroup = CoaSubGroup::find($req->coa_sub_group_id);
        if (!$CoaSubGroup) {
            return ['status' => 'error', 'message' => 'Subgroup not found'];
        }
        if ($CoaSubGroup->is_default == 1) {
            return ['status' => 'error', 'message' => "This is a default subgroup you can't delete it"];
        }
        $coaAccounts = CoaAccount::where('coa_sub_group_id', $req->coa_sub_group_id)->count();
        if ($coaAccounts > 0) {
            return ['status' => 'error', 'message' => "This subgroup has some accounts you can't delete it"];
        }
        CoaSubGroup::find($req->coa_sub_group_id)->delete();
        return ['status' => 'ok', 'message' => 'Sub Group deleted successfully'];
    }
}
