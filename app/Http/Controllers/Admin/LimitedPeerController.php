<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

// This class handles limited peers actions
class LimitedPeerController extends Controller
{
    // returns list of limited interfaces and peers
    public function index(Request $request)
    {
        $limitedInterfaces = DB::table('interfaces')
            ->where('iType', 'limited')
            ->pluck('name', 'id')
            ->toArray();
            
        $interface = $request->query('interface');
        if ($interface && $interface != 'all') {
            $limitedPeers = DB::table('peers')->where('interface_id', $interface)->get();
        } else {
            $limitedInterfacesKeys = array_keys($limitedInterfaces);
            $limitedPeers = DB::table('peers')->whereIn('interface_id', $limitedInterfacesKeys)->get();
        }

        // calculate tx, rx and total usage

        foreach($limitedPeers as $peer) {
            $pId = $peer->id;
            $servers = DB::table('servers')->get();
            $sum_tx = 0;
            $sum_rx = 0;
            foreach ($servers as $server) {
                $sId = $server->id;
                $server_peer = DB::table('server_peers')
                    ->where('server_id', $sId)
                    ->where('peer_id', $pId)
                    ->first();
                if ($server_peer) {
                    $record = DB::table('server_peer_usages')
                        ->where('server_id', $sId)
                        ->where('server_peer_id', $server_peer->server_peer_id)
                        ->orderBy('id', 'desc')
                        ->first ();
                    $sum_tx += $record->tx ?? 0;
                    $sum_rx += $record->rx ?? 0;
                }
                
            }
            $peer->tx = $sum_tx;
            $peer->rx = $sum_rx;
            $peer->total_usage = $sum_tx + $sum_rx;
        }
        
        return view('admin.limited.index', compact('limitedInterfaces', 'interface', 'limitedPeers'));
    }
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
                            $latest = DB::table('server_peer_usages')
                                ->where('server_id', $sId)
                                ->where('server_peer_id', $limitedPeer[".id"])
                                ->orderBy('id', 'desc')
                                ->first();
                            
                            $latest_tx = $latest ? $latest->tx : 0;
                            $latest_rx = $latest ? $latest->rx : 0;
                            
                            $limitedPeerTX = $limitedPeer["tx"];
                            $limitedPeerRX = $limitedPeer["rx"];

                            if ($latest_tx > $limitedPeerTX) {
                                $new_tx = $latest_tx + $limitedPeerTX;
                            } else if ($latest_tx <= $limitedPeerTX) {
                                $new_tx = $limitedPeerTX;
                            }

                            if ($latest_rx > $limitedPeerRX) {
                                $new_rx = $latest_rx + $limitedPeerRX;
                            } else if ($latest_rx <= $limitedPeerRX) {
                                $new_rx = $limitedPeerRX;
                            }
                            
                            DB::table('server_peer_usages')->insert([
                                'server_id' => $sId,
                                'server_peer_id' => $limitedPeer[".id"],
                                'tx' => $new_tx,
                                'rx' => $new_rx,
                                'last_handshake' => $limitedPeer["last-handshake"] ?? null,
                                'created_at' => $now
                            ]);

                        }
                        array_push($message, "$sAddress: fetch successfull!");
                    } else {
                        array_push($message, "$sAddress: $remotePeers");
                    }
                }

                $resultMessage = implode("\r\n", $message);
                saveCronResult('storePeersUsages', $resultMessage);
                return $resultMessage;
            }

            $resultMessage = 'token mismatch!';
            saveCronResult('storePeersUsages', $resultMessage);
            return $resultMessage;
        } catch (\Exception $exception) {
            $resultMessage = $exception->getMessage();
            saveCronResult('storePeersUsages', $resultMessage);
            return $resultMessage;
        }
    }
}
