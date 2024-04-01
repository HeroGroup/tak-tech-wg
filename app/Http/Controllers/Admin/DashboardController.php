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
        $numberOServers = DB::table('servers')->count();
        
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
        
        $interfaces = DB::table('interfaces')->get();
        $servers = DB::table('servers')->get();
        foreach($interfaces as $interface) {
            $interface_total_usage = 0;
            foreach($servers as $server) {
                // find equivalent server_interface_id on each server
                $server_interface = DB::table('server_interfaces')
                    ->where('server_id', $server->id)
                    ->where('interface_id', $interface->id)
                    ->first();

                if($server_interface) {
                    $server_interface_usage = DB::table('server_interface_usages')
                        ->where('server_id', $server->id)
                        ->where('server_interface_id', $server_interface->server_interface_id)
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($server_interface_usage) {
                        $interface_total_usage += round(($server_interface_usage->tx / 1099511627776), 3) + round(($server_interface_usage->rx / 1099511627776), 3);
                    }
                }
            }
            $interface->total_usage = $interface_total_usage;
        }

        $interfaces_usages = json_encode($interfaces->pluck('total_usage', 'name')->toArray());

        return view('admin.dashboard', compact('numberOfServers', 'numberOfInterfaces', 'numberOfUnlimitedPeers', 'numberOfLimitedPeers', 'interfaces_usages'));
    }
}
