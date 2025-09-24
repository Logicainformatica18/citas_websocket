<?php

namespace App\Http\Controllers;

use App\Models\Support;
use App\Models\SupportDetail;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {


        return Inertia::render('dashboards/Dashboard', [

        ]);
    }
}
