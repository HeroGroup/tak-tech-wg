<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\StoreLastHandshakes;
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

        // $limitedPeers = $limitedPeers->get();
        $page = $request->query('page', 1);
        $take = $request->query('take', 50);
        if ($take == 'all') {
            $limitedPeers = $limitedPeers->get();
            $isLastPage = true;
        } else {
            $skip = ($page - 1) * $take;
            $limitedPeers = $limitedPeers->skip($skip)->take($take)->get();
            $isLastPage = (count($limitedPeers) < $take) ? true : false;
        }
        
        $now = time();
        foreach($limitedPeers as $peer) {
            $usages = getPeerUsage($peer->id);

            $peer->tx = $usages['tx'];
            $peer->rx = $usages['rx'];
            $peer->total_usage = $usages['total_usage'];
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

        return view('admin.limited.index', compact('limitedInterfaces', 'interface', 'limitedPeers', 'lastUpdate', 'comment', 'enabled', 'sortBy', 'isLastPage'));
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

                $unlimitedInterfaces = DB::table('interfaces')
                    ->where('iType', 'unlimited')
                    ->pluck('name', 'id')
                    ->toArray();

                DB::table('server_peers')->update([
                    'last_handshake' => null,
                    'last_handshake_updated_at' => $now
                ]);
                
                $servers = DB::table('servers')->get();
                foreach($servers as $server) {
                    $sId = $server->id;
                    $sAddress = $server->server_address;
                    StoreLastHandshakes::dispatch($sId, $sAddress, $unlimitedInterfaces);
                    $remotePeers = curl_general('GET', "$sAddress/rest/interface/wireguard/peers", '', false, 30);
                    $peersToMonitor = DB::table('peers')
                        ->where('monitor', 1)
                        ->join('server_peers', 'server_peers.peer_id', '=', 'peers.id')
                        ->where('server_id', $sId)
                        ->pluck('server_peer_id')
                        ->toArray();
                    
                    if (is_array($remotePeers) && count($remotePeers) > 0) {
                        // filter limited interfaces
                        $peersToStore = array_filter($remotePeers, function($elm) use ($limitedInterfaces, $peersToMonitor) {
                            return in_array($elm['interface'], $limitedInterfaces) || in_array($elm['.id'], $peersToMonitor);
                        });

                        foreach ($peersToStore as $peer) {
                            $latest = DB::table('server_peer_usages')
                                ->where('server_id', $sId)
                                ->where('server_peer_id', $peer[".id"])
                                ->orderBy('id', 'desc')
                                ->first();
                            
                            $latest_tx = $latest ? $latest->tx : 0;
                            $latest_rx = $latest ? $latest->rx : 0;
                            
                            $limitedPeerTX = $peer["tx"];
                            $limitedPeerRX = $peer["rx"];

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
                                'server_peer_id' => $peer[".id"],
                                'tx' => $new_tx,
                                'rx' => $new_rx,
                                'last_handshake' => $peer["last-handshake"] ?? null,
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

    // this functions shows usage of a peer daily
    public function usageStatistics($peerId)
    {
        $peer = DB::table('peers')->find($peerId);
        if (! $peer) {
            $peer = DB::table('removed_peers')->find($peerId);
            if (! $peer) {
                return back()->with('message', 'invalid peer')->with('type', 'danger');
            }
        }

        $server_peers = DB::table('server_peers')->where('peer_id', $peer->id)->get();
        if (count($server_peers) == 0) {
            $server_peers = DB::table('removed_server_peers')->where('peer_id', $peer->peer_id)->get();
        }

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

    // This function returns the amount of traffic used by selected removed peer
    public function removedPeersUsages(Request $request)
    {
        $limitedInterfaces = DB::table('interfaces')
            ->where('iType', 'limited')
            ->join('user_interfaces', 'user_interfaces.interface_id', '=', 'interfaces.id')
            ->where('user_interfaces.user_id', auth()->user()->id)
            ->pluck('name', 'interfaces.id')
            ->toArray();
            
        $interface = $request->query('interface');
        if ($interface && $interface != 'all') {
            $limitedPeers = DB::table('removed_peers')
                ->where('interface_id', $interface)
                ->join('interfaces', 'interfaces.id', '=', 'removed_peers.interface_id')
                ->select(['removed_peers.*', 'interfaces.name', 'interfaces.allowed_traffic_GB']);
        } else {
            $limitedInterfacesKeys = array_keys($limitedInterfaces);
            $limitedPeers = DB::table('removed_peers')
                ->whereIn('interface_id', $limitedInterfacesKeys)
                ->join('interfaces', 'interfaces.id', '=', 'removed_peers.interface_id')
                ->select(['removed_peers.*', 'interfaces.name', 'interfaces.allowed_traffic_GB']);
        }

        $comment = $request->query('comment');
        if ($comment && $limitedPeers && $limitedPeers->count() > 0) {
            $limitedPeers = $limitedPeers->where(function (Builder $query) use ($comment) {
                $query->where('comment', 'like', '%'.$comment.'%')
                    ->orWhere('client_address', 'like', '%'.$comment.'%')
                    ->orWhere('note', 'like', '%'.$comment.'%');
            });
        }

        // $limitedPeers = $limitedPeers->get();
        $page = $request->query('page', 1);
        $take = $request->query('take', 50);
        if ($take == 'all') {
            $limitedPeers = $limitedPeers->get();
            $isLastPage = true;
        } else {
            $skip = ($page - 1) * $take;
            $limitedPeers = $limitedPeers->skip($skip)->take($take)->get();
            $isLastPage = (count($limitedPeers) < $take) ? true : false;
        }
        
        $now = time();
        foreach($limitedPeers as $peer) {
            $usages = getPeerUsage($peer->peer_id);

            $peer->tx = $usages['tx'];
            $peer->rx = $usages['rx'];
            $peer->total_usage = $usages['total_usage'];

            $peer->expires_in = '-1';

            if($peer->expire_days && $peer->activate_date_time) {
                $expire = $peer->expire_days;
                $diff = strtotime($peer->activate_date_time. " + $expire days") - $now;
                $peer->expires_in = $diff; // int
            }
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

        return view('admin.limited.removedPeers', compact('limitedInterfaces', 'interface', 'limitedPeers', 'comment', 'sortBy', 'isLastPage'));
    }
}
