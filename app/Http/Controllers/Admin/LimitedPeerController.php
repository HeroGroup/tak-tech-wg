<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
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
            ->join('user_interfaces', 'user_interfaces.interface_id', '=', 'interfaces.id')
            ->where('user_interfaces.user_id', auth()->user()->id)
            ->pluck('name', 'interfaces.id')
            ->toArray();
            
        $interface = $request->query('interface');
        if ($interface && $interface != 'all') {
            $limitedPeers = DB::table('peers')
                ->where('interface_id', $interface)
                ->join('interfaces', 'interfaces.id', '=', 'peers.interface_id')
                ->select(['peers.*', 'interfaces.name', 'interfaces.allowed_traffic_GB']);
        } else {
            $limitedInterfacesKeys = array_keys($limitedInterfaces);
            $limitedPeers = DB::table('peers')
                ->whereIn('interface_id', $limitedInterfacesKeys)
                ->join('interfaces', 'interfaces.id', '=', 'peers.interface_id')
                ->select(['peers.*', 'interfaces.name', 'interfaces.allowed_traffic_GB']);
        }

        $comment = $request->query('comment');
        if ($comment && $limitedPeers && $limitedPeers->count() > 0) {
            $limitedPeers = $limitedPeers->where(function (Builder $query) use ($comment) {
                $query->where('comment', 'like', '%'.$comment.'%')
                    ->orWhere('client_address', 'like', '%'.$comment.'%')
                    ->orWhere('note', 'like', '%'.$comment.'%');
            });
        }

        $enabled = $request->query('enabled');
        if (in_array($enabled, ['0', '1']) && $limitedPeers && $limitedPeers->count() > 0) {
            $limitedPeers = $limitedPeers->where('is_enabled', (int)$enabled);
        }

        $limitedPeers = $limitedPeers->get();

        $servers = DB::table('servers')->get();
        $now = time();
        foreach($limitedPeers as $peer) {
            $pId = $peer->id;
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
                        ->first();
                    $sum_tx += $record->tx ?? 0;
                    $sum_rx += $record->rx ?? 0;
                }
                
            }
            
            $peer->tx = round(($sum_tx / 1073741824), 2);
            $peer->rx = round(($sum_rx / 1073741824), 2);
            $peer->total_usage = $peer->tx + $peer->rx;

            $peer->expires_in = '-1';

            if($peer->expire_days && $peer->activate_date_time) {
                $expire = $peer->expire_days;
                $diff = strtotime($peer->activate_date_time. " + $expire days") - $now;
                $peer->expires_in = $diff; // int
            }
        }

        if ($enabled == '2') { // expired
            $limitedPeers = $limitedPeers->where('expires_in', '<', 0)->where('expires_in', '!=', -1);
        }

        $sortBy = $request->query('sortBy');
        if ($sortBy && $limitedPeers && $limitedPeers->count() > 0) {
            $by = substr($sortBy, 0, strrpos($sortBy, '_'));
            $type = substr($sortBy, strrpos($sortBy, '_')+1);

            $limitedPeers = $limitedPeers->sortBy($by, SORT_NATURAL);
            
            if ($type == "desc") {
                $limitedPeers = $limitedPeers->reverse();
            }
        } else {
            $sortBy = "client_address_asc";
        }

        $lastUpdate = '';
        if (isset($record)) {
            if(date('Y-m-d', $now) == substr($record->created_at, 0, 10)) {
                $time = substr($record->created_at, 11, 5);
                $lastUpdate = "Today $time";
            } else {
                $lastUpdate = substr($record->created_at, 0, 16);
            }
        }

        return view('admin.limited.index', compact('limitedInterfaces', 'interface', 'limitedPeers', 'lastUpdate', 'comment', 'enabled', 'sortBy'));
    }

    // This functions runs periodically and stores tx, rx and last-handshake of limited peers only
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
                saveCronResult('store-peers-usages', $resultMessage);
                return $resultMessage;
            }

            $resultMessage = 'token mismatch!';
            saveCronResult('store-peers-usages', $resultMessage);
            return $resultMessage;
        } catch (\Exception $exception) {
            $resultMessage = $exception->getMessage();
            saveCronResult('store-peers-usages', $resultMessage);
            return $resultMessage;
        }
    }

    // This functions runs periodically and stores last_handshake for unlimited peers only
    public function storeLastHandshakes($request_token) 
    {
        try {
            if ($request_token == env('STORE_PEERS_LAST_HANDSHAKES_TOKEN')) {
                set_time_limit(600); // 10 minutes
                $message = [];
                $now = date('Y-m-d H:i:s', time());
                
                $unlimitedInterfaces = DB::table('interfaces')
                    ->where('iType', 'unlimited')
                    ->pluck('name', 'id')
                    ->toArray();

                $servers = DB::table('servers')->get();
                foreach($servers as $server) {
                    $sId = $server->id;
                    $sAddress = $server->server_address;
                    $remotePeers = curl_general('GET', "$sAddress/rest/interface/wireguard/peers", '', false, 30);
                    if (is_array($remotePeers) && count($remotePeers) > 0) {
                        // filter unlimited interfaces
                        $unlimitedPeers = array_filter($remotePeers, function($elm) use ($unlimitedInterfaces) {
                            return in_array($elm['interface'], $unlimitedInterfaces);
                        });

                        // store last-handshake for all unlimited peers
                        $remote_peers_count = count($unlimitedPeers);
                        for ($i=0; $i<$remote_peers_count; $i++) {
                            DB::table('server_peers')
                                ->where('server_id', $sId)
                                ->where('server_peer_id', $remotePeers[$i][".id"])
                                ->update([
                                    'last_handshake' => $remotePeers[$i]["last-handshake"] ?? null,
                                    'last_handshake_updated_at' => $now
                                ]);
                        }

                        array_push($message, "$sAddress: last handshakes fetched successfully!");
                    } else {
                        array_push($message, "$sAddress: $remotePeers");
                    }
                }

                $resultMessage = implode("\r\n", $message);
                saveCronResult('store-peers-last-handshakes', $resultMessage);
                return $resultMessage;
            }

            $resultMessage = 'token mismatch!';
            saveCronResult('store-peers-last-handshakes', $resultMessage);
            return $resultMessage;
        } catch (\Exception $exception) {
            $resultMessage = $exception->getMessage();
            saveCronResult('store-peers-last-handshakes', $resultMessage);
            return $resultMessage;
        }
    }

    // this functions shows usage of a peer daily
    public function usageStatistics($peerId)
    {
        $peer = DB::table('peers')->find($peerId);
        if (! $peer) {
            return back()->with('message', 'invalid peer')->with('type', 'danger');
        }

        $server_peers = DB::table('server_peers')->where('peer_id', $peer->id)->get();
        $result = [];
        $days = [];
        foreach($server_peers as $server_peer) {
            $res = DB::table('server_peer_usages')
                ->where('server_id', $server_peer->server_id)
                ->where('server_peer_id', $server_peer->server_peer_id)
                ->selectRaw('(MAX(CAST(`server_peer_usages`.`tx` AS UNSIGNED)) - MIN(CAST(`server_peer_usages`.`tx` AS UNSIGNED)) + MAX(CAST(`server_peer_usages`.`rx` AS UNSIGNED)) - MIN(CAST(`server_peer_usages`.`rx` AS UNSIGNED))) / 1073741824 AS TOTAL_USAGE, SUBSTR(`server_peer_usages`.`created_at`, 1, 10) AS DAY')
                ->groupByRaw('DAY')
                ->get();

            $days = array_merge($days, array_column($res->toArray(), 'DAY'));
            $result[$server_peer->id] = $res;
        }

        $peer_usages = [];
        foreach ($days as $day) {
            $sum_day = 0;
            foreach ($result as $key => $value) {
                foreach ($value as $elm) {
                    if ($elm->DAY == $day) {
                        $sum_day += $elm->TOTAL_USAGE;
                    }
                }
            }
            if ($sum_day > 0) {
                $peer_usages[$day] = $sum_day;
            }
        }

        $peer_usages = json_encode($peer_usages);

        return view('admin.limited.usageStatistics', compact('peer', 'peer_usages'));
    }
}
