<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    // shows recent cron jobs logs
    public function cronJobs(Request $request)
    {
        $type = $request->query('type');
        
        $jobs = DB::table('cron_results');

        if ($type && $type != 'all') {
            $jobs = $jobs->where('cron_name', $type);
        }

        $jobs = $jobs->orderBy('id', 'desc')->simplePaginate(100);

        return view('admin.logs.cronJobs', compact('type', 'jobs'));
    }
}
