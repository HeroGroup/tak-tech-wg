<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard()
    {   
        $numberOfServers = DB::table('servers')->count();
        if (auth()->user()->is_admin) {
            $numberOfInterfaces = DB::table('interfaces')->count();
            $numberOfPeers = DB::table('peers')->count();
        } else {
            $numberOfInterfaces = DB::table('user_interfaces')->where('user_id', auth()->user()->id)->count();
            $numberOfPeers = DB::table('user_interfaces')
                ->where('user_id', auth()->user()->id)
                ->join('peers', 'user_interfaces.interface_id', '=', 'peers.interface_id')
                ->count();
        }
        $numberOfLimitedPeers = 0;

        return view('admin.dashboard', compact('numberOfServers', 'numberOfInterfaces', 'numberOfPeers', 'numberOfLimitedPeers'));
    }
}
