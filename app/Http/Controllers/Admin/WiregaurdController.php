<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

// This class is responsible for management of all wireguard interfaces
// and wiregaurd peers. All actions.
class WiregaurdController extends Controller
{
    // This function returns a view to create peers
    public function create()
    {
        $interfaces = DB::table('user_interfaces')
            ->where('user_id', auth()->user()->id)
            ->join('interfaces', 'interfaces.id', '=', 'user_interfaces.interface_id')
            ->pluck('name', 'interface_id')->toArray();
        
        $messageDuration = 10000;

        return view('admin.peers.create', compact('interfaces', 'messageDuration'));
    }

    // This function returns list of peers based on the accesibility of the user
    // It also performs search, filter and sort.
    public function peers(Request $request)
    {
        $peers = DB::table('peers')
            ->join('user_interfaces', 'peers.interface_id', '=', 'user_interfaces.interface_id')
            ->join('interfaces', 'peers.interface_id', '=', 'interfaces.id')
            ->select(['peers.*', 'interfaces.name'])
            ->where('user_interfaces.user_id', auth()->user()->id)
            ->where(function($query) {
                $query->whereRaw(
                    'user_interfaces.privilege="full" OR (user_interfaces.privilege="partial" AND peers.id IN (SELECT peer_id FROM user_peers where user_id=?))',
                    [auth()->user()->id]
                );
            });
        
        $interface = $request->query('wiregaurd');
        if ($interface && $interface != 'all' && $peers && $peers->count() > 0) {
            // $peers = array_filter($peers, function ($element) use ($interface) {
            //     return ($element->interface_id == $interface);
            // });
            $peers = $peers->where('peers.interface_id', $interface);
        }

        $comment = $request->query('comment');
        if ($comment && $peers && $peers->count() > 0) {
            // $peers = array_filter($peers, function ($element) use ($comment) {
            //     return str_contains($element['comment'], $comment) || str_contains($element['allowed-address'], $comment);;
            // });
            $peers = $peers->where(function (Builder $query) use ($comment) {
                                    $query->where('comment', 'like', '%'.$comment.'%')
                                        ->orWhere('client_address', 'like', '%'.$comment.'%')
                                        ->orWhere('note', 'like', '%'.$comment.'%');
                        });
        }

        $enabled = $request->query('enabled');
        if (in_array($enabled, ['0', '1']) && $peers && $peers->count() > 0) {
            $peers = $peers->where('is_enabled', (int)$enabled);
        }

        $peers = $peers->get();

        $now = time();
        foreach ($peers as $peer) {
            $peer->expires_in = '-1';

            if($peer->expire_days && $peer->activate_date_time) {
                $expire = $peer->expire_days;
                $diff = strtotime($peer->activate_date_time. " + $expire days") - $now;
                $peer->expires_in = $diff; // int
            }
        }

        if ($enabled == '2') { // expired
            $peers = $peers->where('expires_in', '<', 0)->where('expires_in', '!=', -1);
        }
        
        $sortBy = $request->query('sortBy');
        if ($sortBy && $peers && $peers->count() > 0) {
            $by = substr($sortBy, 0, strrpos($sortBy, '_'));
            $type = substr($sortBy, strrpos($sortBy, '_')+1);

            $peers = $peers->sortBy($by, SORT_NATURAL);
            
            if ($type == "desc") {
                $peers = $peers->reverse();
            }
        } else {
            $sortBy = "client_address_asc";
            $peers = $peers->sortBy('client_address', SORT_NATURAL);
        }
        
        $interfaces = DB::table('user_interfaces')
            ->where('user_id', auth()->user()->id)
            ->join('interfaces', 'interfaces.id', '=', 'user_interfaces.interface_id')
            ->pluck('name', 'interface_id')->toArray();
        
        $page = $request->query('page', 1);
        $take = $request->query('take', 50);
        if ($take == 'all') {
            // $peers = $peers->get();
            $isLastPage = true;
        } else {
            $skip = ($page - 1) * $take;
            $peers = $peers->skip($skip)->take($take);
            $isLastPage = (count($peers) < $take) ? true : false;
        }
        
        $messageDuration = 10000;
        return view('admin.peers.list', compact('peers', 'interface', 'comment', 'enabled', 'sortBy', 'interfaces', 'isLastPage', 'messageDuration'));
    }

    // This function adds peer to local database
    public function addLocalPeer($caddress, $interfaceId, $interfacePublicKey, $interfaceListenPort, $cdns, $wgserveraddress, $commentApply, $time, $oldId=0)
    {
        // check address is valid
        if (! filter_var($caddress, FILTER_VALIDATE_IP)) {
            return [
                'id' => 0,
                'message' => "IP $caddress is invalid."
            ];
        }
        
        // check not repetetive
        $caddress32 = "$caddress/32";
        $existingPeer = DB::table('peers')->where('client_address', $caddress32)->count();
        if ($existingPeer > 0) {
            return [
                'id' => 0,
                'message' => "Peer $caddress already exists."
            ];
        }
        
        $x = DB::table('allowed_addresses_restrictions')->where('allowed_address', $caddress)->first();
        $used_count = $x->used_count ?? 0;
        if ($x && $x->maximum_allowed <= $x->used_count) {
            return [
                'id' => 0,
                'message' => "Peer $caddress has reached limit!"
            ];
        }

        $keys = createKeys();
        $privateKey = $keys['private_key'];
        $publicKey = $keys['public_key'];
        
        $today = date('Y-m-d', $time);

        // creat conf file
        $confFilePath = resource_path("confs/$today/$time/$commentApply.conf");
        $content = createConfFile($today, $time, $confFilePath, $privateKey, $caddress32, $cdns, $interfacePublicKey, $wgserveraddress, $interfaceListenPort);
        
        // creat QR image
        $qrcodeFilePath = resource_path("confs/$today/$time/$commentApply.png");
        createQRcode($content, $qrcodeFilePath);

        $now = date('Y-m-d H:i:s');
        $newLocalPeerId = DB::table('peers')->insertGetId([
            'interface_id' => $interfaceId,
            'dns' => $cdns,
            'client_address' => $caddress32,
            'endpoint_address' => $wgserveraddress,
            'comment' => $commentApply,
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'is_enabled' => 1,
            // 'first_enabled' => auth()->user()->is_admin ? $now : null,
            'conf_file' => $confFilePath,
            'qrcode_file' => $qrcodeFilePath,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => auth()->user()->id
        ]);

        if ($oldId > 0) {
            DB::table('user_peers')
                ->where('peer_id', $oldId)
                ->update([
                    'peer_id' => $newLocalPeerId,
                    'created_at' => $now,
                ]);
        }

        DB::table('allowed_addresses_restrictions')
            ->where('allowed_address', $caddress)
            ->update([
                'used_count' => $used_count+1
            ]);

        return [
            'id' => $newLocalPeerId,
            'publicKey' => $publicKey
        ];
    }

    // This function creates peer on remote router
    public function addRemotePeer($sId, $saddress, $caddress, $interfaceName, $cdns, $wgserveraddress, $commentApply, $publicKey, $localPeerId, $enabled=1)
    {
        $now = date('Y-m-d H:i:s');
        $data = [
			'interface' => $interfaceName,
			'allowed-address' => $caddress.'/32',
            // 'endpoint-address' => $wgserveraddress,
            // 'client-dns' => $cdns,
            'public-key' => $publicKey,
			'comment' => $commentApply,
        ];

        // if (in_array($enabled, [0, 1])) {
        //     $data['disabled'] = !(bool)$enabled;
        // } else if (! auth()->user()->is_admin) { // reseller user
        //     $data['disabled'] = true;
        // }

        // return new peer id
        $newRemotePeer = curl_general(
            'POST',
            $saddress . '/rest/interface/wireguard/peers/add',
            json_encode($data),
            true
        );

        if (is_array($newRemotePeer) && count($newRemotePeer) > 0 && $newRemotePeer['ret']) {
            DB::table('server_peers')->upsert(
                [
                    'server_id' => $sId,
                    'peer_id' => $localPeerId,
                    'server_peer_id' => $newRemotePeer['ret'],
                    'created_at' => $now
                ],
                ['server_id', 'peer_id'],
                ['server_peer_id']
            );

            return true;
        } else {
            return false;
        }
    }

    // This function is the entry point of creating a number of peers
    // random or batch
    public function createWG(Request $request)
    {
        try {
            // check user has access to this interface
            $userInterfaces = DB::table('user_interfaces')->where('user_id', auth()->user()->id)->where('interface_id', $request->wginterface);
            if ($userInterfaces->count() == 0) {
                return back()->with('message', 'You do not have access to this interface.')->with('type', 'danger');
            }

            $comment = $request->comment;
            $start = $request->start;
            $end = $request->end;
            $randoms = explode('-', $request->random);

            // check if wiregaurd is valid
            $interface = DB::table('interfaces')->find($request->wginterface);
            if (! $interface) {
                return back()->with("message", "Wireguard Interface Not Found")->with("type", "danger");
            }
            
            $interfaceId = $interface->id;
            $interfaceName = $interface->name;
            $interfacePublicKey = $interface->public_key;
            $interfaceListenPort = $interface->listen_port;
            $cdns = $request->dns ?? $interface->dns;
            $wgserveraddress = $request->endpoint ?? $interface->default_endpoint_address;
            $range = $request->range ?? $interface->ip_range;

            $message = '';
            $time = time();
            $numberOfCreatedPeers = 0;
            if ($request->type == 'batch') {
                for ($i = $start; $i <= $end; $i++)
                {
                    $caddress = $range . ($i + 1);
                    $commentApply = $comment . '-' . $i;
                    $result = $this->performOnAllServers($caddress, $interfaceId, $interfaceName, $interfacePublicKey, $interfaceListenPort, $range, $cdns, $wgserveraddress, $commentApply, $time);
                    $message .= $result['message'];
                    if ($result['status'] == 1) {
                        $numberOfCreatedPeers++;
                    }
                }
            } else {
                foreach ($randoms as $i)
                {
                    $caddress = $range . ($i + 1);
                    $commentApply = $comment . '-' . $i;
                    $result = $this->performOnAllServers($caddress, $interfaceId, $interfaceName, $interfacePublicKey, $interfaceListenPort, $range, $cdns, $wgserveraddress, $commentApply, $time);
                    $message .= $result['message'];
                    if ($result['status'] == 1) {
                        $numberOfCreatedPeers++;
                    }
                }
            }

            if ($numberOfCreatedPeers > 0) {
                $today = date('Y-m-d', $time);
                $zipResult = createZip(resource_path("confs/$today/$time/"), $time);
                
                if ($zipResult['status'] == 1) {
                    return $this->getDownloadLink($today, $time);
                } else {
                    return back()->with('message', $x['message'])->with('type', 'success');
                }
            } else {
                return back()->with('message', $message)->with('type', 'danger');
            }
            
            // return back()->with('message', $message)->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getLine() . ': ' . $exception->getMessage())->with('type', 'danger');
        }
    }

    // This function perfomrs the add action on all remote routers
    private function performOnAllServers($caddress, $interfaceId, $interfaceName, $interfacePublicKey, $interfaceListenPort, $range, $cdns, $wgserveraddress, $comment, $time, $oldId=0)
    {
        $newLocalPeer = $this->addLocalPeer($caddress, $interfaceId, $interfacePublicKey, $interfaceListenPort, $cdns, $wgserveraddress, $comment, $time, $oldId);
        
        if ($newLocalPeer['id'] > 0) { // local successfull
            $message = "Local inserted successfully.\r\n";

            $servers = DB::table('servers')
                ->where('router_os_version', '>=', '7.12.1')
                ->where('router_os_version', 'not like', '%beta%')
                ->get();
        
            foreach ($servers as $server) {
                $sId = $server->id;
                $saddress = $server->server_address;

                $message .= $saddress . ' peer ' . $comment;

                $addRemoteResponse = $this->addRemotePeer($sId, $saddress, $caddress, $interfaceName, $cdns, $wgserveraddress, $comment, $newLocalPeer['publicKey'], $newLocalPeer['id']);
                if ($addRemoteResponse) {
                    $message .= " successful.\r\n";
                } else {
                    $message .= " failed.\r\n";
                }
            }

            return ['status' => 1, 'message' => $message];
        } else {
            return ['status' => -1, 'message' => $newLocalPeer['message']];
        }
    }

    // Actions
    // regenerate action
    protected function regenerate($id, $time)
    {
        try {
            $message = $this->removeRemote($id)['message'] . "\r\n";

            $peer = DB::table('peers')->find($id);
            $caddress = substr($peer->client_address,0,-3);
            $interface = DB::table('interfaces')->find($peer->interface_id);
            $interfaceId = $peer->interface_id;
            $interfaceName = $interface->name;
            $interfacePublicKey = $interface->public_key;
            $interfaceListenPort = $interface->listen_port;
            $range = $interface->ip_range;
            $cdns = $request->dns ?? $interface->dns;
            $wgserveraddress = $peer->endpoint_address ?? $interface->default_endpoint_address;
            $commentApply = $peer->comment;

            $removeResult = $this->removeLocal($id, 'regenerate');

            $message .= $removeResult ? "Local removed successfully\r\n" : "Unable to remove local peer $commentApply. \r\n";
            

            $result = $this->performOnAllServers($caddress, $interfaceId, $interfaceName, $interfacePublicKey, $interfaceListenPort, $range, $cdns, $wgserveraddress, $commentApply, $time, $id);
            $message .= $result['message'];
            
            return $this->success($message);
        } catch (\Exception $exception) {
            return $this->fail($exception->getLine() . ': ' . $exception->getMessage());
        }
    }
    // regenerates one peer only
    public function regenerateSingle(Request $request)
    {
        $time = time();
        return $this->regenerate($request->id, $time);
    }

    // regenerates a number of peers and returns new qrcodes and config files
    public function regenerateMass(Request $request)
    {
        $time = time();
        $ids = json_decode($request->ids);
        foreach ($ids as $id) {
            $this->regenerate($id, $time);
        }

        $today = date('Y-m-d', $time);
        $zipResult = createZip(resource_path("confs/$today/$time/"), $time);
                
        if ($zipResult['status'] == 1) {
            return $this->success('Selected items regenerated successfully.', [
                'route' => route('wiregaurd.peers.getDownloadLink', ['date' => $today, 'file' => $time])
            ]);
        } else {
            return $this->fail($zipResult['message']);
        }
    }

    // This function changes the enablity status of the peer
    public function toggleEnable($id, $status)
    {
        try {
            // toggle on local DB
            $peer = DB::table('peers')->find($id);
            if (!$peer) {
                return $this->fail("invalid peer!");
            }
            $update = ['is_enabled' => $status];
            if ($status == 1 && (! $peer->first_enabled)) {
                $update['first_enabled'] = date('Y-m-d H:i:s');
            }
            
            DB::table('peers')->where('id', $id)->update($update);

            // loop on servers and perform action
            $server_peers = DB::table('server_peers')->where('peer_id', $id)->get();

            foreach ($server_peers as $server_peer) {
                $spID = $server_peer->server_peer_id;
                $data = [".id" => $spID];
                $command = $status ? 'enable' : 'disable';
                $saddress = DB::table('servers')->find($server_peer->server_id)->server_address;
                $response = curl_general(
                    'POST',
                    $saddress . '/rest/interface/wireguard/peers/'.$command,
                    json_encode($data),
                    true
                );
            }
            return $this->success(($status == 1 ? 'Enabled' : 'Disabled') . ' successfully!');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    // toggles the status of one peer only
    public function toggleEnableSingle(Request $request)
    {
        return $this->toggleEnable($request->id, $request->status);
    }

    // toggles the statuses of a number of selected peers to enable
    public function enableMass(Request $request)
    {
        $ids = json_decode($request->ids);
        foreach ($ids as $id) {
            $this->toggleEnable($id, 1);
        }

        return $this->success('Selected items enabled successfully.');
    }

    // toggles the statuses of a number of selected peers to disable
    public function disableMass(Request $request)
    {
        $ids = json_decode($request->ids);
        foreach ($ids as $id) {
            $this->toggleEnable($id, 0);
        }

        return $this->success('Selected items disabled successfully.');
    }

    // This function removes a peer on remote router
    public function removeRemote($peerId)
    {
        try {
            $server_peers = DB::table('server_peers')
                ->where('peer_id', $peerId)
                ->join('servers', 'servers.id', '=', 'server_peers.server_id')
                ->select(['server_peers.*', 'servers.server_address'])
                ->get();

            $message = [];
            foreach ($server_peers as $server_peer) {
                $saddress = $server_peer->server_address;
                $response = curl_general(
                    'POST',
                    $saddress . '/rest/interface/wireguard/peers/remove',
                    json_encode([".id" => $server_peer->server_peer_id]),
                    true
                );
                
                if($response == []) {
                    array_push($message, "$saddress: removed successfully!");
                } else {
                    array_push($message, "$saddress: failed to remove!");
                }
            }
            
            return ['status' => 1, 'message' => implode("\r\n", $message)];
        } catch (\Exception $exception) {
            logError($exception);
            return ['status' => -1, 'message' => $exception->getLine() . ': ' . $exception->getMessage()];
        }
    }

    // This function removes a peer on our local databse
    public function removeLocal($id, $removeReason)
    {
        try {
            $peer = DB::table('peers')->find($id);
            if ($peer) {
                $now = date('Y-m-d H:i:s', time());
                $user = auth()->user()->id ?? 0;

                DB::table('removed_peers')->insert([
                    'peer_id' => $id,
                    'interface_id' => $peer->interface_id,
                    'client_address' => $peer->client_address,
                    'comment' => $peer->comment,
                    'note' => $peer->note,
                    'expire_days' => $peer->expire_days,
                    'activate_date_time' => $peer->activate_date_time,
                    'remove_reason' => $removeReason,
                    'removed_at' => $now,
                    'removed_by' => $user
                ]);

                $server_peers = DB::table('server_peers')->where('peer_id', $id)->get();
                foreach ($server_peers as $server_peer) {
                    DB::table('removed_server_peers')->insert([
                        'server_id' => $server_peer->server_id,
                        'peer_id' => $server_peer->peer_id,
                        'server_peer_id' => $server_peer->server_peer_id,
                        'total_tx' => $server_peer->total_tx,
                        'total_rx' => $server_peer->total_rx,
                        'removed_at' => $now,
                        'removed_by' => $user
                    ]);
                }

                // delete server_peers
                DB::table('server_peers')->where('peer_id', $id)->delete();
    
                // delete associated conf and qr file
                if ($peer->conf_file && file_exists($peer->conf_file)) {
                    unlink($peer->conf_file);
                }
                if ($peer->qrcode_file && file_exists($peer->qrcode_file)) {
                    unlink($peer->qrcode_file);
                }
    
                DB::table('peers')->where('id', $id)->delete();
    
                return true;
            } else {
                return false;
            }
        } catch (\Exception $exception) {
            logError($exception);
            return false;
        }
    }

    // This function performs the action of remove to one peer only
    public function removeSingle(Request $request)
    {
        try {
            if (!auth()->user()->can_remove) {
                return $this->fail('You do not have access to remove peers!.');
            }

            $peerId = $request->id;
            $message = $this->removeRemote($peerId)['message'] . "\r\n";
            $message .= $this->removeLocal($peerId, 'manual-single') ? "removed from local\r\n" : "failed to remove from local\r\n";
            
            return $this->success($message);
        } catch (\Exception $exception) {
            return $this->fail($exception->getLine() . ': ' . $exception->getMessage());
        }
    }

    // This function performs the action of remove to a number of selected peers
    public function removeMass(Request $request)
    {
        try {
            if (!auth()->user()->can_remove) {
                return $this->fail('You do not have access to remove peers!.');
            }

            $ids = json_decode($request->ids);
            foreach ($ids as $peerId) {
                $this->removeRemote($peerId);
                $this->removeLocal($peerId, 'manual-mass');
            }

            return $this->success('Selected items removed successfully.');
        } catch (\Exception $exception) {
            return $this->fail($exception->getLine() . ': ' . $exception->getMessage());
        }
    }

    // This function updates the attributes of a peer
    protected function updatePeer($id, $dns, $endpoint_address, $note, $expire_days, $activate_date, $activate_time, $peer_allowed_traffic_GB, $max_allowed_connections, $today, $time, $mass=false)
    {
        try {
            $peer = DB::table('peers')->find($id);
            if (! $peer) {
                return ['status' => -1, 'message' => 'Invalid peer!'];
            }
            $interface = DB::table('interfaces')->find($peer->interface_id);
            $update = [
                'dns' => $dns,
                'endpoint_address' => $endpoint_address,
            ];

            if ($peer->dns != $dns || $peer->endpoint_address != $endpoint_address) {
                $cdns = $dns ?? $interface->dns;
                $wgserveraddress = $endpoint_address ?? $interface->default_endpoint_address;
                $commentApply = $peer->comment;

                // remove config and qr files
                if ($peer->conf_file && file_exists($peer->conf_file)) {
                    unlink($peer->conf_file);
                }
                if ($peer->qrcode_file && file_exists($peer->qrcode_file)) {
                    unlink($peer->qrcode_file);
                }

                $time = time();
                $today = date('Y-m-d', $time);

                // regenerate config file
                $confFilePath = resource_path("confs/$today/$time/$commentApply.conf");
                $content = createConfFile($today, $time, $confFilePath, $peer->private_key, $peer->client_address, $cdns, $interface->public_key, $wgserveraddress, $interface->listen_port);
                
                // creat QR image
                $qrcodeFilePath = resource_path("confs/$today/$time/$commentApply.png");
                createQRcode($content, $qrcodeFilePath);

                // update record with new values
                $update['conf_file'] = $confFilePath;
                $update['qrcode_file'] = $qrcodeFilePath;

            } 
            if ($note != $peer->note) {
                $update['note'] = $note;
            }

            $activate_d = $peer->activate_date_time ? substr($peer->activate_date_time, 0, 10) : date('Y-m-d', $time);
            if ($activate_date && $activate_date != $activate_d) {
                $activate_d = $activate_date;
            }

            $activate_t = $peer->activate_date_time ? substr($peer->activate_date_time, 11, 5) : date('H:i:s', $time);
            if ($activate_time && $activate_time != $activate_t) {
                $activate_t = $activate_time;
            }
            if ($mass) { // update mass
                if ($expire_days) {
                    $update['expire_days'] = $expire_days;
                }

                if ($activate_date || $activate_time || $expire_days) { 
                    // user may want to only change activate_date_time
                    // However, peer should have expire
                    if ($expire_days || $peer->expire_days) {
                        $update['activate_date_time'] = "$activate_d $activate_t";
                    }
                }
            } else { // update single
                // active_date_time without expire is useless
                if ($expire_days) {
                    $update['expire_days'] = $expire_days;
                    $update['activate_date_time'] = "$activate_d $activate_t";
                } else {
                    $update['expire_days'] = null;
                    $update['activate_date_time'] = null;
                }
            }

            if ($peer_allowed_traffic_GB) {
                $update['peer_allowed_traffic_GB'] = $peer_allowed_traffic_GB;
            }

            if ($max_allowed_connections) {
                $update['max_allowed_connections'] = $max_allowed_connections;
            }

            DB::table('peers')->where('id', $id)->update($update);
            
            return ['status' => 1, 'message' => 'Peer updated successully'];
        } catch (\Exception $exception) {
            return ['status' => -1, 'message' => $exception->getLine() . ': ' . $exception->getMessage()];
        }
    }

    // This function performs the action of update to one peer only
    public function updatePeerSingle(Request $request)
    {
        $time = time();
        $today = date('Y-m-d', $time);
        $result = $this->updatePeer($request->id, $request->dns, $request->endpoint_address, $request->note, $request->expire_days, $request->activate_date, $request->activate_time, $request->peer_allowed_traffic_GB, $request->max_allowed_connections, $today, $time);

        $peer = DB::table('peers')->find($request->id);

        if (! $peer) {
            return back()->with('message', 'invalid peer')->with('type', 'danger');
        }
        
        if ($request->comment != $peer->comment) {
            $newComment = $request->comment;

            // check if comment is not in use
            $existingComment = DB::table('peers')->where('interface_id', $peer->interface_id)->where('comment', $newComment)->count();
            if ($existingComment > 0) {
                return back()->with('message', 'This comment is already in use!')->with('type', 'danger');
            }

            // update comment on local
            DB::table('peers')->where('id', $request->id)->update([
                'comment' => $newComment
            ]);

            // loop on all servers to update on remote
            $server_peers = DB::table('server_peers')
                ->where('peer_id', $request->id)
                ->join('servers', 'servers.id', '=', 'server_peers.server_id')
                ->select(['server_peers.*', 'servers.server_address'])
                ->get();
            foreach ($server_peers as $server_peer) {
                $sAddress = $server_peer->server_address;
                $data = [".id" => $server_peer->server_peer_id, "comment" => $newComment];
                curl_general(
                    'POST',
                    $sAddress . '/rest/interface/wireguard/peers/set',
                    json_encode($data),
                    true
                );
            }
        }
        return back()->with('message', $result['message'])->with('type', $result['status'] == 1 ? 'success' : 'danger');
    }
    
    // This function performs the action of update to a number of selected peers
    public function updatePeersMass(Request $request)
    {
        $time = time();
        $today = date('Y-m-d', $time);
        $message = '';
        try {
            $ids = json_decode($request->ids);
            foreach($ids as $id) {
                $result = $this->updatePeer($id, $request->dns, $request->endpoint_address, null, $request->expire_days, $request->activate_date, $request->activate_time, $request->peer_allowed_traffic_GB, $request->max_allowed_connections, $today, $time, true);
                $message .= $result['message'] . "\r\n";
            }
            
            return $this->success($message);
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    // downloads a zip file
    public function downloadZip($date, $file)
    {
        return response()->download(resource_path("confs/$date/$file/$file.zip"));
    }

    // return a view with a download link
    public function getDownloadLink($today, $time) {
        return view('admin.download', compact('today', 'time'));
    }

    // downloads the qr and config of one peer only
    public function downloadPeer($id)
    {
        $peer = DB::table('peers')->find($id);
        $comment = $peer->comment;
        $zipFileName =  resource_path("confs/$comment.zip");
        
        if ($peer->conf_file && $peer->qrcode_file) {
            zipPeer($comment, $peer->conf_file, $peer->qrcode_file, $zipFileName);
        } else {
            if ($peer->private_key) {
                $interface = DB::table('interfaces')->find($peer->interface_id);
                $cdns = $peer->dns ?? $interface->dns;
                $wgserveraddress = $peer->endpoint_address ?? $interface->default_endpoint_address;
                
                // create conf and qr
                $time = time();
                $today = date('Y-m-d', $time);

                // generate config file
                $confFilePath = resource_path("confs/$today/$time/$comment.conf");
                $content = createConfFile($today, $time, $confFilePath, $peer->private_key, $peer->client_address, $cdns, $interface->public_key, $wgserveraddress, $interface->listen_port);
                    
                // creat QR image
                $qrcodeFilePath = resource_path("confs/$today/$time/$comment.png");
                createQRcode($content, $qrcodeFilePath);

                DB::table('peers')->where('id', $id)->update([
                    'conf_file' => $confFilePath,
                    'qrcode_file' => $qrcodeFilePath
                ]);

                zipPeer($comment, $confFilePath, $qrcodeFilePath, $zipFileName);
            }
        }
        

        return response()->download($zipFileName);
    }

    // This function disables all peers that has been expired
    public function disableExpiredPeers($request_token)
    {
        try {
            $token = env('DISABLE_EXPIRED_PEERS_TOKEN');

            if ($request_token == $token) {
                $disabled = [];
                $removed = [];
                $peers = DB::table('peers')
                    ->whereNotNull('expire_days')
                    ->whereNotNull('activate_date_time')
                    ->where('is_enabled', 1)
                    ->join('interfaces', 'interfaces.id' ,'=', 'peers.interface_id')
                    ->select(['peers.*', 'interfaces.iType'])
                    ->get();
                $now = time();
                foreach ($peers as $peer) {
                    $expire = $peer->expire_days;
                    $diff = strtotime($peer->activate_date_time. " + $expire days") - $now;

                    if ($diff <= 0) {
                        $peerId = $peer->id;
                        // if peer is unlimited, disable peer
                        // if ($peer->iType == "unlimited") {
                            $this->toggleEnable($peerId, 0);
                            array_push($disabled, $peer->comment);
                        // } else { // if peer is limited, remove peer
                            // $this->removeRemote($peerId);
                            // $this->removeLocal($peerId, 'date-expired');
                            // array_push($removed, $peer->comment);
                        // }
                    }
                }

                if ((count($disabled) > 0)/* || (count($removed) > 0)*/) {
                    $message = implode("\r\n", $disabled) . ' disabled successfully!';
                    // $message .= implode("\r\n", $removed) . ' removed successfully!';
                    saveCronResult('disable-expired-peers', $message);
                    return $message;
                } else {
                    $message = 'nothing to disabled!';
                    saveCronResult('disable-expired-peers', $message);
                    return $message;
                }
            } else {
                $message = 'token mismatch!';
                saveCronResult('disable-expired-peers', $message);
                return $message;
            }
        } catch(\Exception $exception) {
            $message = $exception->getMessage();
            saveCronResult('disable-expired-peers', $message);
            return $message;
        }
    }

    // remove expired limited peers
    public function removeExpiredLimitedPeers($request_token)
    {
        try {
            if ($request_token == env('REMOVE_EXPIRED_LIMITED_PEERS_TOKEN')) {
                $removed = [];
                $limitedPeers = DB::table('peers')
                    ->join('interfaces', 'interfaces.id', '=', 'peers.interface_id')
                    ->where('interfaces.iType', 'limited')
                    ->select(['peers.*', 'interfaces.allowed_traffic_GB'])
                    ->get();
                    
                // $servers = DB::table('servers')->get();
                foreach($limitedPeers as $limitedPeer) {
                    $peerId = $limitedPeer->id;
                    $limit = $limitedPeer->peer_allowed_traffic_GB ?? $limitedPeer->allowed_traffic_GB;
                    
                    $usage = getPeerUsage($peerId)['total_usage'];
                    
                    // foreach ($servers as $server) {
                    //     $sId = $server->id;
                    //     $server_peer = DB::table('server_peers')
                    //         ->where('server_id', $sId)
                    //         ->where('peer_id', $peerId)
                    //         ->first();
                    //     if ($server_peer) {
                    //         $record = DB::table('server_peer_usages')
                    //             ->where('server_id', $sId)
                    //             ->where('server_peer_id', $server_peer->server_peer_id)
                    //             ->orderBy('id', 'desc')
                    //             ->first();

                    //         $usage += $record->tx ?? 0;
                    //         $usage += $record->rx ?? 0;
                    //     }
                    // }

                    if ($usage > $limit) {
                        // remove peer
                        $this->removeRemote($peerId);
                        if (! $this->removeLocal($peerId, 'reach-limit')) {
                            $this->toggleEnable($peerId, 0);
                        }
                        array_push($removed, $limitedPeer->comment);
                    }
                }

                if (count($removed) > 0) {
                    $message = implode("\r\n", $removed) . ' removed successfully!';
                    saveCronResult('remove-expired-limited-peers', $message);
                    return $message;
                } else {
                    $message = 'nothing to remove!';
                    saveCronResult('remove-expired-limited-peers', $message);
                    return $message;
                }
            } else {
                $message = 'token mismatch!';
                saveCronResult('remove-expired-limited-peers', $message);
                return $message;
            }
        } catch(\Exception $exception) {
            $message = $exception->getMessage();
            saveCronResult('remove-expired-limited-peers', $message);
            return $message;
        }
    }

    // this function checks for peers that are violating maximum allowed number of connections
    // and pu them to suspect list
    // after a defined violation count, system blocks those peers
    public function blockPeers($request_token)
    {
        try {
            if ($request_token == env('LOOK_FOR_VIOLATIONS_TOKEN')) {
                $should_block = DB::table('settings')->where('setting_key', 'IS_BLOCK_UNBLOCK_ACTIVE')->first()->setting_value;
                $now = date('Y-m-d H:i:s', time());
                $suspected = [];
                $blocked = [];
                $message = '';
                // select only unlimited peers
                $peers = DB::table('peers')
                    ->where('is_enabled', 1)
                    ->join('interfaces', 'interfaces.id', '=', 'peers.interface_id')
                    ->where('interfaces.iType', 'unlimited')
                    ->where('interfaces.exclude_from_block', 0)
                    ->select(['peers.*'])
                    ->get();

                $handshake_period_seconds = DB::table('settings')->where('setting_key', 'HANDSHAKE_PERIOD_SECONDS')->first()->setting_value;
                $max_number_of_violations = DB::table('settings')->where('setting_key', 'MAX_NUMBER_OF_VIOLATIONS')->first()->setting_value;
                foreach ($peers as $peer) {
                    $peerId = $peer->id;
                    $server_peers = DB::table('server_peers')->where('peer_id', $peerId)->get();
                    $number_of_active_connections = 0;
                    // convert last_handshake to seconds
                    foreach ($server_peers as $server_peer) {
                        if ($server_peer->last_handshake) {
                            $last_handshake_seconds = convertLastHandshakeToSeconds($server_peer->last_handshake);
                            if ($last_handshake_seconds < $handshake_period_seconds) {
                                $number_of_active_connections++;
                            }
                        }
                    }
                    $max = $peer->max_allowed_connections ?? 1;
                    if ($max != -1 && $number_of_active_connections > $max) { // $max: -1 => unlimited
                        // add peer to suspect list
                        DB::table('suspect_list')->insert([
                            'peer_id' => $peerId,
                            'created_at' => $now
                        ]);

                        array_push($suspected, $peer->comment);

                        $cnt = DB::table('suspect_list')->where('peer_id', $peerId)->count();

                        if ($cnt > $max_number_of_violations) {
                            // make peer disable
                            if ($should_block && ($should_block=='true' || $should_block=='yes')) {
                                $this->toggleEnable($peerId, 0);
                            }

                            DB::table('block_list')->insert([
                                'peer_id' => $peerId,
                                'created_at' => $now
                            ]);

                            // remove from suspect_list
                            DB::table('suspect_list')->where('peer_id', $peerId)->delete();
                                
                            array_push($blocked, $peer->comment);
                        }
                    }
                }

                if (count($suspected) > 0) {
                    $message .= implode(", ", $suspected) . ' went into suspect list.';
                }
                if (count($blocked) > 0) {
                    $message .= implode(", ", $blocked) . ' blocked (disabled) due to violation.';
                }

                if (count($suspected) == 0 && count($blocked) == 0){
                    $message = 'no violations were detected!';
                    saveCronResult('block-peers', $message);
                    return $message;
                } else {
                    saveCronResult('block-peers', $message);
                    return $message;
                }
            } else {
                $message = 'token mismatch!';
                saveCronResult('block-peers', $message);
                return $message;
            }
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            saveCronResult('block-peers', $message);
            return $message;
        }
    }

    // unblocks blocked peers after a certain defined amount of time
    public function unblockViolatedPeers($request_token)
    {
        try {
            if ($request_token == env('UNBLOCK_PEERS_TOKEN')) {
                $should_unblock = DB::table('settings')->where('setting_key', 'IS_BLOCK_UNBLOCK_ACTIVE')->first()->setting_value;
                $blocked = DB::table('block_list')->whereNull('unblocked_at')->get();
                $unblock_after = DB::table('settings')->where('setting_key', 'UNBLOCK_AFTER_MINUTES')->first()->setting_value;
                $now = time();
                $unblocked_at = date('Y-m-d H:i:s', $now);
                $unblocked = [];
                foreach($blocked as $item) {
                    $diff = $now - strtotime($item->created_at. " + $unblock_after minutes");
                    if ($diff > 0) {
                        // unblock peer
                        $peerId = $item->peer_id;
                        $peer = DB::table('peers')->find($peerId);
                        
                        // make peer enable
                        if ($should_unblock && ($should_unblock=='true' || $should_unblock=='yes')) {
                            $this->toggleEnable($peerId, 1);
                        }
                        
                        // remove from block_list
                        DB::table('block_list')
                            ->where('peer_id', $peerId)
                            ->whereNull('unblocked_at')
                            ->update([
                                'unblocked_at' => $unblocked_at
                            ]);
                        
                        array_push($unblocked, $peer->comment);
                    }
                }

                if (count($unblocked) > 0) {
                    $message = implode("\r\n", $unblocked) . ' unblocked (enabled) successfully!';
                    saveCronResult('unblock-peers', $message);
                    return $message;
                } else {
                    $message = 'nothing to unblock!';
                    saveCronResult('unblock-peers', $message);
                    return $message;
                }
            } else {
                $message = 'token mismatch!';
                saveCronResult('unblock-peers', $message);
                return $message;
            }
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            saveCronResult('unblock-peers', $message);
            return $message;
        }
    }

    // returns the list of suspected peers
    public function suspectList(Request $request)
    {
        $list = DB::table('peers')
            ->join('user_interfaces', 'peers.interface_id', '=', 'user_interfaces.interface_id')
            ->join('interfaces', 'peers.interface_id', '=', 'interfaces.id')
            ->where('user_interfaces.user_id', auth()->user()->id)
            ->where(function($query) {
                $query->whereRaw(
                    'user_interfaces.privilege="full" OR (user_interfaces.privilege="partial" AND peers.id IN (SELECT peer_id FROM user_peers where user_id=?))',
                    [auth()->user()->id]
                );
            })
            ->join('suspect_list', 'suspect_list.peer_id', '=', 'peers.id')
            ->selectRaw('count(*) as number_of_violations, suspect_list.peer_id, peers.comment, peers.client_address, peers.note, interfaces.name')
            ->groupBy('suspect_list.peer_id');

        $search = $request->query('search');
        if ($search && $list && $list->count() > 0) {
            $list = $list->where(function (Builder $query) use ($search) {
                $query->where('comment', 'like', '%'.$search.'%')
                    ->orWhere('client_address', 'like', '%'.$search.'%')
                    ->orWhere('note', 'like', '%'.$search.'%');
            });
        }

        $list = $list->get();

        $sortBy = $request->query('sortBy');
        if ($sortBy && $list && $list->count() > 0) {
            $by = substr($sortBy, 0, strrpos($sortBy, '_'));
            $type = substr($sortBy, strrpos($sortBy, '_')+1);

            $list = $list->sortBy($by, SORT_NATURAL);
                
            if ($type == "desc") {
                $list = $list->reverse();
            }
        } else {
            $sortBy = "client_address_asc";
            $list = $list->sortBy('client_address', SORT_NATURAL);
        }

        $page = $request->query('page', 1);
        $take = $request->query('take', 50);
        if ($take == 'all') {
            // $list = $list->get();
            $isLastPage = true;
        } else {
            $skip = ($page - 1) * $take;
            $list = $list->skip($skip)->take($take);
            $isLastPage = (count($list) < $take) ? true : false;
        }
        
        return view('admin.violations.suspect', compact('list', 'search', 'sortBy', 'isLastPage'));
    }

    // returns the list of blocked peers
    public function blockList(Request $request)
    {
        $list = DB::table('peers')
            ->join('user_interfaces', 'peers.interface_id', '=', 'user_interfaces.interface_id')
            ->join('interfaces', 'peers.interface_id', '=', 'interfaces.id')
            ->where('user_interfaces.user_id', auth()->user()->id)
            ->where(function($query) {
                $query->whereRaw(
                    'user_interfaces.privilege="full" OR (user_interfaces.privilege="partial" AND peers.id IN (SELECT peer_id FROM user_peers where user_id=?))',
                    [auth()->user()->id]
                );
            })
            ->join('block_list', 'block_list.peer_id', '=', 'peers.id')
            ->whereNull('block_list.unblocked_at')
            ->select(['block_list.*', 'peers.comment', 'peers.client_address', 'peers.note', 'interfaces.name']);
        
        $search = $request->query('search');
        if ($search && $list && $list->count() > 0) {
            $list = $list->where(function (Builder $query) use ($search) {
                $query->where('comment', 'like', '%'.$search.'%')
                    ->orWhere('client_address', 'like', '%'.$search.'%')
                    ->orWhere('note', 'like', '%'.$search.'%');
            });
        }

        $list = $list->get();

        $sortBy = $request->query('sortBy');
        if ($sortBy && $list && $list->count() > 0) {
            $by = substr($sortBy, 0, strrpos($sortBy, '_'));
            $type = substr($sortBy, strrpos($sortBy, '_')+1);

            $list = $list->sortBy($by, SORT_NATURAL);
                
            if ($type == "desc") {
                $list = $list->reverse();
            }
        } else {
            $sortBy = "client_address_asc";
            $list = $list->sortBy('client_address', SORT_NATURAL);
        }     

        $page = $request->query('page', 1);
        $take = $request->query('take', 50);
        if ($take == 'all') {
            // $list = $list->get();
            $isLastPage = true;
        } else {
            $skip = ($page - 1) * $take;
            $list = $list->skip($skip)->take($take);
            $isLastPage = (count($list) < $take) ? true : false;
        }

        return view('admin.violations.block', compact('list', 'search', 'sortBy', 'isLastPage'));
    }
    
    // returns the history of blocked peers
    public function blockHistoryList(Request $request)
    {
        $list = DB::table('peers')
            ->join('user_interfaces', 'peers.interface_id', '=', 'user_interfaces.interface_id')
            ->join('interfaces', 'peers.interface_id', '=', 'interfaces.id')
            ->where('user_interfaces.user_id', auth()->user()->id)
            ->where(function($query) {
                $query->whereRaw(
                    'user_interfaces.privilege="full" OR (user_interfaces.privilege="partial" AND peers.id IN (SELECT peer_id FROM user_peers where user_id=?))',
                    [auth()->user()->id]
                );
            })
            ->join('block_list', 'block_list.peer_id', '=', 'peers.id')
            ->whereNotNull('block_list.unblocked_at')
            ->select(['block_list.*', 'peers.comment', 'peers.client_address', 'peers.note', 'interfaces.name'])
            ->orderBy('id', 'asc');
        
        $search = $request->query('search');
        if ($search && $list && $list->count() > 0) {
            $list = $list->where(function (Builder $query) use ($search) {
                $query->where('comment', 'like', '%'.$search.'%')
                    ->orWhere('client_address', 'like', '%'.$search.'%')
                    ->orWhere('note', 'like', '%'.$search.'%');
            });
        }

        $list = $list->get();

        $sortBy = $request->query('sortBy');
        if ($sortBy && $list && $list->count() > 0) {
            $by = substr($sortBy, 0, strrpos($sortBy, '_'));
            $type = substr($sortBy, strrpos($sortBy, '_')+1);

            $list = $list->sortBy($by, SORT_NATURAL);
                
            if ($type == "desc") {
                $list = $list->reverse();
            }
        } else {
            $sortBy = "client_address_asc";
            $list = $list->sortBy('client_address', SORT_NATURAL);
        }

        $page = $request->query('page', 1);
        $take = $request->query('take', 50);
        if ($take == 'all') {
            // $list = $list->get();
            $isLastPage = true;
        } else {
            $skip = ($page - 1) * $take;
            $list = $list->skip($skip)->take($take);
            $isLastPage = (count($list) < $take) ? true : false;
        }
        return view('admin.violations.blockHistory', compact('list', 'search', 'sortBy', 'isLastPage'));
    }

    // manually extracts a peer from suspect list
    public function removeFromSuspectList(Request $request)
    {
        try {
            $peerId = $request->id;
            DB::table('suspect_list')->where('peer_id', $peerId)->delete();

            return $this->success('Removed from suspect list!');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function removeFromSuspectListMass(Request $request)
    {
        try {
            $ids = json_decode($request->ids);
            DB::table('suspect_list')->whereIn('peer_id', $ids)->delete();
            return $this->success('Selected items were removed successfully from suspect list.');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
        
    }

    // manually extracts a peer from block list
    public function removeFromBlockList(Request $request)
    {
        try {
            $should_unblock = DB::table('settings')->where('setting_key', 'IS_BLOCK_UNBLOCK_ACTIVE')->first()->setting_value;
            $peerId = $request->id;
            DB::table('block_list')
                ->where('peer_id', $peerId)
                ->update([
                    'unblocked_at' => date('Y-m-d H:i:s', time())
                ]);

            // enable peer
            if ($should_unblock && ($should_unblock=='true' || $should_unblock=='yes')) {
                $this->toggleEnable($peerId, 1);
            }

            return $this->success('Removed from block list!');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function removeFromBlockListMass(Request $request)
    {
        try {
            $should_unblock = DB::table('settings')->where('setting_key', 'IS_BLOCK_UNBLOCK_ACTIVE')->first()->setting_value;
            $ids = json_decode($request->ids);
            DB::table('block_list')
                ->whereIn('peer_id', $ids)
                ->update([
                    'unblocked_at' => date('Y-m-d H:i:s', time())
                ]);

            if ($should_unblock && ($should_unblock=='true' || $should_unblock=='yes')) {
                foreach ($ids as $peerId) {
                    $this->toggleEnable($peerId, 1);
                }
            }

            return $this->success('Selected items were removed successfully from block list.');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
        
    }

    // returns a view for doing actions on peers
    public function actions()
    {
        $interfaces = DB::table('user_interfaces')
            ->where('user_id', auth()->user()->id)
            ->where('privilege', 'full')
            ->join('interfaces', 'interfaces.id', '=', 'user_interfaces.interface_id')
            ->pluck('name', 'interface_id')->toArray();

        return view('admin.peers.actions', compact('interfaces'));
    }

    // this function pre-handles different actions on peers
    public function postActions(Request $request)
    {
        try {
            // check user has access to this interface
            $userInterfaces = DB::table('user_interfaces')->where('user_id', auth()->user()->id)->where('interface_id', $request->interface);
            if ($userInterfaces->count() == 0) {
                return back()->with('message', 'You do not have access to this interface.')->with('type', 'danger');
            }

            $comment = $request->comment;
            $start = $request->start;
            $end = $request->end;
            $randoms = explode('-', $request->random);
            $action = $request->action;

            // check if wiregaurd is valid
            $interface = DB::table('interfaces')->find($request->interface);
            if (! $interface) {
                return back()->with('message', 'Wireguard Interface Not Found')->with('type', 'danger');
            }
            
            $interfaceId = $interface->id;
            $time = time();

            if ($request->type == 'batch') {
                for ($i = $start; $i <= $end; $i++)
                {
                    $commentApply = $comment . '-' . $i;
                    $this->performActionOnPeer($interfaceId, $commentApply, $action, $time);
                }
            } else {
                foreach ($randoms as $i)
                {
                    $commentApply = $comment . '-' . $i;
                    $this->performActionOnPeer($interfaceId, $commentApply, $action, $time);
                }
            }

            if ($action=='regenerate') {
                $today = date('Y-m-d', $time);
                $zipResult = createZip(resource_path("confs/$today/$time/"), $time);
                    
                if ($zipResult['status'] == 1) {
                    return redirect(route('wiregaurd.peers.getDownloadLink', ['date' => $today, 'file' => $time]));
                } else {
                    return back()->with('message', $zipResult['message'])->with('type', 'danger');
                }
            }

            return back()->with('message', 'success!')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getLine() . ': ' . $exception->getMessage())->with('type', 'danger');
        }
    }

    // this function handles different actions on peers
    public function performActionOnPeer($interfaceId, $comment, $action, $time)
    {
        $peer = DB::table('peers')
            ->where('interface_id', $interfaceId)
            ->where('comment', $comment)
            ->first();

        if ($peer) {
            $peerId = $peer->id;
            switch ($action) {
                case 'enable':
                    $this->toggleEnable($peerId, 1);
                    break;
                case 'disable':
                    $this->toggleEnable($peerId, 0);
                    break;
                case 'regenerate':
                    $this->regenerate($peerId, $time);
                    break;
                case 'remove':
                    $this->removeRemote($peerId);
                    $this->removeLocal($peerId, 'manual-batch');
                    break;
                default:
                    # code...
                    break;
            }
        }
    }

    // This is temporary function to fill database with empty allowed addresses restrictions
    public function fill()
    {
        try {
            $limitedInterfaces = DB::table('interfaces')->where('iType', 'limited')->get();
        
            foreach ($limitedInterfaces as $interface) {
                $ip_range = $interface->ip_range;
                $insert_data = [];
                for ($i=1; $i<=254; $i++) {
                    $address = $ip_range.$i;
                    array_push($insert_data, [
                        'allowed_address' => $address,
                        'used_count' => DB::table('peers')->where('client_address', $address.'/32')->count(),
                        'maximum_allowed' => 1,
                    ]);
                }

                DB::table('allowed_addresses_restrictions')->insert($insert_data);
            }

            return 'Complete!';
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    // This function returns all möglich(possible) peers of each interface,
    // with associated restrictions
    public function restrictions(Request $request)
    {
        try {
            $limitedInterfaces = DB::table('interfaces')
                ->where('iType', 'limited')
                ->join('user_interfaces', 'user_interfaces.interface_id', '=', 'interfaces.id')
                ->where('user_interfaces.user_id', auth()->user()->id)
                ->select(['interfaces.*'])
                ->pluck('name', 'id')
                ->toArray();
            
            $interface = $request->query('interface', 1);
            $_interface = DB::table('interfaces')->find($interface);

            if (! $_interface) {
                return back()->with('message', 'invalid interface')->with('type', 'danger');
            }

            $addresses = DB::table('allowed_addresses_restrictions')
                ->where('allowed_address', 'like', $_interface->ip_range.'%');

            $search = $request->query('search');
            if ($search && $addresses && $addresses->count() > 0) {
                $addresses = $addresses->where('allowed_address', 'like', '%'.$search.'%');
            }

            $addresses = $addresses->get();
        
            $page = $request->query('page', 1);
            $take = $request->query('take', 50);
            if ($take == 'all') {
                $isLastPage = true;
            } else {
                $skip = ($page - 1) * $take;
                $addresses = $addresses->skip($skip)->take($take);
                $isLastPage = (count($addresses) < $take) ? true : false;
            }

            $messageDuration = 1000;

            return view('admin.limited.restrictions', compact('limitedInterfaces', 'interface', 'search', 'addresses', 'isLastPage', 'messageDuration'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // updates restrictions of one peer only (maximum number of creation of each allowed address)
    public  function updateRestrictions(Request $request)
    {
        try {
            DB::table('allowed_addresses_restrictions')
                ->where('id', $request->id)
                ->update([
                    'maximum_allowed' => $request->maximum_allowed
                ]);
            
            return back()->with('message', 'success')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // updates restrictions of peers (maximum number of creation of each allowed address)
    public  function updateRestrictionsMass(Request $request)
    {
        try {
            $ids = json_decode($request->ids);
            DB::table('allowed_addresses_restrictions')
                ->whereIn('id', $ids)
                ->update([
                    'maximum_allowed' => $request->maximum_allowed
                ]);
            
            return $this->success('success');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function suspectDetails(Request $request, $peerId)
    {
        try {
            $details = DB::table('server_peers')
                ->where('peer_id', $peerId)
                ->join('servers', 'servers.id', '=', 'server_peers.server_id')
                ->join('peers', 'peers.id', '=', 'server_peers.peer_id')
                ->select(['server_peers.*', 'peers.comment', 'servers.server_address', 'servers.alias'])
                ->orderBy('server_id', 'asc')
                ->get();

            foreach ($details as $item) {
                $item->last_handshake_seconds = convertLastHandshakeToSeconds($item->last_handshake);
            }

            return view('admin.violations.details', compact('details'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    public function clearSuspectList($request_token)
    {
        try {
            if ($request_token == env('CLEAR_SUSPECT_LIST_TOKEN')) {
                DB::table('suspect_list')->delete();

                $message = 'suspect list cleared successfully!';
                saveCronResult('clear-suspect-list', $message);
                return $message;
            } else {
                $message = 'token mismatch!';
                saveCronResult('clear-suspect-list', $message);
                return $message;
            }
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            saveCronResult('clear-suspect-list', $message);
            return $message;
        }
    }
}
