<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

// This class handles limited peers actions
class LimitedPeerController extends Controller
{
    // This functions runs periodically and stores tx, rx of to every limited peer
    public function storeUsages($request_token)
    {
        try {
            if ($request_token == env('STORE_PEERS_USAGES_TOKEN')) {
                $message = [];
                $now = date('Y-m-d H:i:s', time());
                $limitedInterfaces = DB::table('interfaces')
                    ->where('iType', 'limited')
                    ->pluck('name', 'id')
                    ->toArray();
                
                $servers = DB::table('servers')->get();
                foreach($servers as $server) {
                    $sId = $server->id;
                    $sAddress = $server->server_address;
                    $remotePeers = curl_general('GET', "$sAddress/rest/interface/wireguard/peers", '', false, 30);
                    if (is_array($remotePeers) && count($remotePeers) > 0) {
                        // filter limited interfaces
                        $limitedPeers = array_filter($remotePeers, function($elm) use ($limitedInterfaces) {
                            return in_array($elm['interface'], $limitedInterfaces);
                        });

                        foreach ($limitedPeers as $limitedPeer) {
                            DB::table('server_peer_usages')->insert([
                                'server_id' => $sId,
                                'server_peer_id' => $limitedPeer[".id"],
                                'tx' => $limitedPeer["tx"],
                                'rx' => $limitedPeer["rx"],
                                'created_at' => $now
                            ]);
                        }
                        array_push($message, "$sAddress: fetch successfull!");
                    } else {
                        array_push($message, "$sAddress: $remotePeers");
                    }
                }

                return implode("\r\n", $message);
            }

            return 'token mismatch!';
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }
}
