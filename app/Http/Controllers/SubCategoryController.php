<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\SubCategory;
use App\Services\Helper;
use App\Services\CustomErrorMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubCategoryController extends Controller
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
            $category = $req->category_id;
            $subcategory = SubCategory::with('categories')
                ->when($category, function ($q, $category) {
                    return $q->where('category_id', $category);
                })
                ->orderBy($req->colName, $req->sort)->paginate($req->records, ['*'], 'page', $req->pageNo);

            return ['status' => 'ok', 'subcategory' => $subcategory];
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
        $rules = array(
            'name' => 'required|string|min:2|max:255|unique:sub_categories',

            'category_id' => 'required',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }

        try {
            DB::transaction(function () use ($request) {

                $subcategory = new SubCategory();
                $subcategory->name = $request->name;
                $subcategory->category_id = $request->category_id;

                $subcategory->save();
            });

            return ['status' => "ok", 'message' => 'Subcategory Stored Successfully'];
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
            'id' => 'required|int|exists:sub_categories,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            $subcategory = SubCategory::find($req->id);
            return ['status' => 'ok', 'subcategory' => $subcategory];
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
            'id' => 'required|int|exists:sub_categories',

        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            DB::transaction(function () use ($request) {
                $subcategory = SubCategory::find($request->id);
                $subcategory->name = $request->name;
                $subcategory->category_id = $request->category_id;
                $subcategory->save();
            });
            return ['status' => "ok", 'message' => 'subcategory updated successfully'];
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
            'id' => 'required|int|exists:sub_categories,id',
        );
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return ['status' => 'error', 'message' => $validator->errors()->first()];
        }
        try {
            SubCategory::where('id', $req->id)->delete();
            return ['status' => "ok", 'message' => 'Subcategory deleted successfully'];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
    public function getSubCategoriesDropDown(Request $req)
    {
        try {
            $category = $req->category_id;
            $subcategories = SubCategory::when($category, function ($q, $category) {
                return $q->where('category_id', $category);
            })->orderBy('name')->get();
            return ['status' => 'ok', 'subcategories' => $subcategories];
        } catch (\Exception $e) {
            $message = CustomErrorMessages::getCustomMessage($e);
            return ['status' => 'error', 'message' => $message];
        }
    }
}
