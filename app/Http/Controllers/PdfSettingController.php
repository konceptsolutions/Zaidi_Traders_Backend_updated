<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PdfSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $pdfSetting = DB::table('pdf_setting')->select('*')->where('id', 1)->first();
        return ['pdfSetting' => $pdfSetting];
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
        $pdfSetting = DB::table('pdf_setting')->where('id', 1)
            ->update([
                'address' => $request->address,
                'ntn' => $request->ntn,
                'email' => $request->email,
                'national_tax_no' => $request->national_tax_no,
                'sale_tax_no' => $request->sale_tax_no,
                'drug_sale_license_no' => $request->drug_sale_license_no,
                'phone' => $request->phone,
                'cell' => $request->cell,
                'fax' => $request->fax,
                'term_condition' => $request->term_condition,
                'deal_in' => $request->deal_in,
                'website' => $request->website,
            ]);

        if ($pdfSetting) {
            return ['status' => "ok", 'message' => 'Pdf Setting Update successfully'];
        } else {
            return ['status' => "error", 'message' => 'Something wents wrong'];
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
}
