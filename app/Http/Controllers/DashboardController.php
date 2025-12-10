<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Block;
use App\Models\Booking;
use App\Models\CoaAccount;
use App\Models\CoaGroup;
use App\Models\Land;
use App\Models\Person;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $totalPlots = 0;
        $totalUsers = User::count();
        $ActiveUsers = User::where('is_active', 1)->count();
        $ResidPlots = 0;
        $CommerPlots = 0;
        $BookPlots = 0;
        $ApprovedPlots = 0;

        $isActive = 1;
        $isInActive = 0;
        $ActiveEmployees = 0;
        $inActiveEmployees = 0;
        $PlotsData = [
            'totalPlots' => $totalPlots,
            'CommerPlots' => $CommerPlots,
            'ResidPlots' => $ResidPlots,
            'BookPlots' => $BookPlots,
            'ApprovedPlots' => $ApprovedPlots,
            'totalUsers' => $totalUsers,
            'ActiveUsers' => $ActiveUsers,
        ];
        $Employees = [
            'ActiveEmployees' => 12,
            'inActiveEmployees' => 2,
        ];

        return ['status' => 'ok',  'Employees' => $Employees];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTrailBalanceForDash(Request $req)
    {
        $todayDate = date("Y-m-d");
        $firstDay = Carbon::now()->startOfMonth()->toDateString();

        $lastDay = Carbon::now()->endOfMonth()->toDateString();

        $collection = collect([]);
        $months = collect([]);
        for ($i = 1; $i < 13; $i++) {

            $from = Land::changeDateFormat($firstDay);
            $to = Land::changeDateFormat($lastDay);
            $assets = CoaGroup::with(['nonDepreciationSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('parent', 'Assets')->select('id', 'name', 'parent', 'code')->get();

            $depreciation = CoaGroup::with(['depreciationSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('id', 2)->select('id', 'name', 'parent', 'code')->get();

            $count =  count($assets[1]->nonDepreciationSubGroups);
            $assets[1]->nonDepreciationSubGroups[$count] = count($depreciation[0]->depreciationSubGroups) > 0 ?  $depreciation[0]->depreciationSubGroups[0] : $depreciation[0];



            $revenues[$i] = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('parent', 'Revenues')->select('id', 'name', 'parent', 'code')->get();

            $expenses[$i] = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('parent', 'Expenses')->select('id', 'name', 'parent', 'code')->get();


            $cost[$i] = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('parent', 'Cost')->select('id', 'name', 'parent', 'code')->get();

            $getmonth = strtotime($firstDay);
            $month[$i] = date("F", $getmonth);
            $year[$i] = date("Y", $getmonth);

            $collection[] = array(
                'revenues' => $revenues[$i],
                'expenses' => $expenses[$i],
                'cost' => $cost[$i],
            );
            $months[] = array(
                'Month' => substr($month[$i], 0, 3) . ' ' . substr($year[$i], 2, 2)
            );

            $firstDay = Carbon::now()->startOfMonth()->modify(-$i . 'months')->toDateString();

            $lastDay = Carbon::now()->endOfMonth()->modify(-$i . 'months')->toDateString();
        }
        $collection = collect($collection);
        $month = collect($months);


        return ['status' => 'ok', 'Data' => $collection, 'months' => $months];
    }

    public function getTrailBalanceForDashtem(Request $req)
    {
        $todayDate = date("Y-m-d");
        $firstDay = Carbon::now()->startOfMonth()->toDateString();

        $lastDay = Carbon::now()->endOfMonth()->toDateString();

        $collection = collect([]);
        $months = collect([]);
        for ($i = 1; $i < 13; $i++) {

            $from = Land::changeDateFormat($firstDay);
            $to = Land::changeDateFormat($lastDay);
            $assets = CoaGroup::with(['nonDepreciationSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('parent', 'Assets')->select('id', 'name', 'parent', 'code')->get();

            $depreciation = CoaGroup::with(['depreciationSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('id', 2)->select('id', 'name', 'parent', 'code')->get();

            $count =  count($assets[1]->nonDepreciationSubGroups);
            $assets[1]->nonDepreciationSubGroups[$count] = count($depreciation[0]->depreciationSubGroups) > 0 ?  $depreciation[0]->depreciationSubGroups[0] : $depreciation[0];



            $revenues[$i] = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('parent', 'Revenues')->select('id', 'name', 'parent', 'code')->get();

            $expenses[$i] = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('parent', 'Expenses')->select('id', 'name', 'parent', 'code')->get();


            $cost[$i] = CoaGroup::with(['coaSubGroups.coaAccounts.balance' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from . " 00:00:00", $to . " 23:59:59"]);
            }])->where('parent', 'Cost')->select('id', 'name', 'parent', 'code')->get();

            $getmonth = strtotime($firstDay);
            $month[$i] = date("F", $getmonth);
            $year[$i] = date("Y", $getmonth);

            $collection[] = array(
                'revenues' => $revenues[$i],
                'expenses' => $expenses[$i],
                'cost' => $cost[$i],
            );
            $months[] = array(
                'Month' => substr($month[$i], 0, 3) . ' ' . substr($year[$i], 2, 2)
            );

            $firstDay = Carbon::now()->startOfMonth()->modify(-$i . 'months')->toDateString();

            $lastDay = Carbon::now()->endOfMonth()->modify(-$i . 'months')->toDateString();
        }
        $collection = collect($collection);
        $month = collect($months);


        return ['status' => 'ok', 'Data' => $collection, 'months' => $months];
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
}
