<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// This class has some statistical methods to show to user
class DashboardController extends Controller
{
    // This function return a few statistics to show on diagrams
    public function dashboard()
    {   
        $numberOfServers = DB::table('servers')->count();
        
        $numberOfInterfaces = DB::table('user_interfaces')->where('user_id', auth()->user()->id)->count();
        
        $numberOfUnlimitedPeers = DB::table('user_interfaces')
            ->where('user_id', auth()->user()->id)
            ->join('interfaces', 'user_interfaces.interface_id', '=', 'interfaces.id')
            ->join('peers', 'interfaces.id', '=', 'peers.interface_id')
            ->where('interfaces.iType', 'unlimited')
            ->count();
            
        
        $numberOfLimitedPeers = DB::table('user_interfaces')
            ->where('user_id', auth()->user()->id)
            ->join('interfaces', 'user_interfaces.interface_id', '=', 'interfaces.id')
            ->join('peers', 'interfaces.id', '=', 'peers.interface_id')
            ->where('interfaces.iType', 'limited')
            ->count();
        
        return view('admin.dashboard', compact('numberOfServers', 'numberOfInterfaces', 'numberOfUnlimitedPeers', 'numberOfLimitedPeers'));
    }
}
