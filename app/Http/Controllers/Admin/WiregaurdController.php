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

        return view('admin.WGCreate', compact('interfaces', 'messageDuration'));
    }

    // This function returns list of peers based on the accesibility of the user
    // It also performs search, filter and sort.
    public function peers(Request $request)
    {
        $peers = DB::table('peers')
            ->join('user_interfaces', 'peers.interface_id', '=', 'user_interfaces.interface_id')
            ->join('interfaces', 'peers.interface_id', '=', 'interfaces.id')
            ->select(['peers.*', 'interfaces.name'])
            ->where('user_interfaces.user_id', auth()->user()->id);
        
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
        }
        
        $interfaces = DB::table('user_interfaces')
            ->where('user_id', auth()->user()->id)
            ->join('interfaces', 'interfaces.id', '=', 'user_interfaces.interface_id')
            ->pluck('name', 'interface_id')->toArray();
        
        $messageDuration = 10000;
        return view('admin.WGPeers', compact('peers', 'interface', 'comment', 'enabled', 'sortBy', 'interfaces', 'messageDuration'));
    }

    // This function adds peer to local database
    public function addLocalPeer($caddress, $interfaceId, $interfacePublicKey, $interfaceListenPort, $cdns, $wgserveraddress, $commentApply, $time)
    {
        // check not repetetive
        $caddress32 = "$caddress/32";
        $existingPeer = DB::table('peers')->where('client_address', $caddress32)->count();
        if ($existingPeer > 0) {
            return [
                'id' => 0,
                'message' => "Peer $caddress already exists."
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

        return [
            'id' => $newLocalPeerId,
            'publicKey' => $publicKey
        ];
    }

    // This function creates peer on remote router
    public function addRemotePeer($sId, $saddress, $caddress, $interfaceName, $cdns, $wgserveraddress, $commentApply, $publicKey, $localPeerId, $enabled=1)
    {
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
            DB::table('server_peers')->insert([
                'server_id' => $sId,
                'peer_id' => $localPeerId,
                'server_peer_id' => $newRemotePeer['ret']
            ]);

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
            // check user has access
            $userInterfaces = DB::table('user_interfaces')->where('user_id', auth()->user()->id)->where('interface_id', $request->wginterface);
            if ($userInterfaces->count() == 0) {
                return back()->with('message', 'You do not have access to this interface.')->with('type', 'danger');
            }

            $comment = $request->comment;
            $start = $request->start;
            $end = $request->end;
            $randoms = explode('-', $request->random);

            // check is wiregaurd is valid
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
    private function performOnAllServers($caddress, $interfaceId, $interfaceName, $interfacePublicKey, $interfaceListenPort, $range, $cdns, $wgserveraddress, $comment, $time)
    {
        $newLocalPeer = $this->addLocalPeer($caddress, $interfaceId, $interfacePublicKey, $interfaceListenPort, $cdns, $wgserveraddress, $comment, $time);
        
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

            $removeResult = $this->removeLocal($id);

            $message .= $removeResult ? "Local removed successfully\r\n" : "Unable to remove local peer $commentApply. \r\n";
            

            $result = $this->performOnAllServers($caddress, $interfaceId, $interfaceName, $interfacePublicKey, $interfaceListenPort, $range, $cdns, $wgserveraddress, $commentApply, $time);
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
    private function toggleEnable($id, $status)
    {
        try {
            // toggle on local DB
            $peer = DB::table('peers')->find($id);
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
            return ['status' => -1, 'message' => $exception->getLine() . ': ' . $exception->getMessage()];
        }
    }

    // This function removes a peer on our local databse
    public function removeLocal($id)
    {
        try {
            DB::table('server_peers')->where('peer_id', $id)->delete();
            
            // delete associated conf and qr file
            $peer = DB::table('peers')->find($id);
            if ($peer->conf_file && file_exists($peer->conf_file)) {
                unlink($peer->conf_file);
            }
            if ($peer->qrcode_file && file_exists($peer->qrcode_file)) {
                unlink($peer->qrcode_file);
            }

            DB::table('peers')->where('id', $id)->delete();

            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    // This function performs the action of remove to one peer only
    public function removeSingle(Request $request)
    {
        try {
            $peerId = $request->id;
            $message = $this->removeRemote($peerId)['message'] . "\r\n";
            $message .= $this->removeLocal($peerId) ? "removed from local\r\n" : "failed to remove from local\r\n";
            
            return $this->success($message);
        } catch (\Exception $exception) {
            return $this->fail($exception->getLine() . ': ' . $exception->getMessage());
        }
    }

    // This function performs the action of remove to a number of selected peers
    public function removeMass(Request $request)
    {
        $ids = json_decode($request->ids);
        foreach ($ids as $peerId) {
            $this->removeRemote($peerId);
            $this->removeLocal($peerId);
        }

        return $this->success('Selected items removed successfully.');
    }

    // This function updates the attributes of a peer
    protected function updatePeer($id, $dns, $endpoint_address, $note, $expire_days, $activate_date, $activate_time, $peer_allowed_traffic_GB, $today, $time, $mass=false)
    {
        try {
            $peer = DB::table('peers')->find($id);
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
        $result = $this->updatePeer($request->id, $request->dns, $request->endpoint_address, $request->note, $request->expire_days, $request->activate_date, $request->activate_time, $request->peer_allowed_traffic_GB, $today, $time);

        $peer = DB::table('peers')->where('id', $request->id)->first();

        if ($request->comment != $peer->comment) {
            $newComment = $request->comment;
            // update comment on local
            DB::table('peers')->where('id', $request->id)->update([
                'comment' => $newComment
            ]);
            // update on remote as well
            // loop on all servers to update on remote
            $server_peers = DB::table('server_peers')->where('peer_id', $request->id)->get();
            foreach ($server_peers as $server_peer) {
                $sAddress = DB::table('servers')->find($server_peer->server_id)->server_address;
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
                $result = $this->updatePeer($id, $request->dns, $request->endpoint_address, null, $request->expire_days, $request->activate_date, $request->activate_time, $request->peer_allowed_traffic_GB, $today, $time, true);
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
                            // $this->removeLocal($peerId);
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
                    
                $servers = DB::table('servers')->get();
                foreach($limitedPeers as $limitedPeer) {
                    $peerId = $limitedPeer->id;
                    $limit = $limitedPeer->peer_allowed_traffic_GB ?? $limitedPeer->allowed_traffic_GB;
                    $usage = 0;
                    foreach ($servers as $server) {
                        $sId = $server->id;
                        $server_peer = DB::table('server_peers')
                            ->where('server_id', $sId)
                            ->where('peer_id', $peerId)
                            ->first();
                        if ($server_peer) {
                            $record = DB::table('server_peer_usages')
                                ->where('server_id', $sId)
                                ->where('server_peer_id', $server_peer->server_peer_id)
                                ->orderBy('id', 'desc')
                                ->first();

                            $usage += $record->tx ?? 0;
                            $usage += $record->rx ?? 0;
                        }
                    }
array_push($removed, $limitedPeer->comment . ' , ' . round(($usage / 1073741824), 2), $limit);


                    // if (round($usage / 1073741824) > $limit) { // GB
                        // remove peer
                        // $this->removeRemote($peerId);
                        // $this->removeLocal($peerId);
                        // array_push($removed, $limitedPeer->comment);
                    // }
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
}
