<?php

namespace App\Http\Controllers;

use App\Models\CoaGroup;
use Illuminate\Http\Request;

class CoaGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $coaGroup = CoaGroup::orderBy('code')->get();
        return ['coaGroup' => $coaGroup];
    }
}
