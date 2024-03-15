<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

class WiregaurdController extends Controller
{
    public function create()
    {
        $interfaces = DB::table('user_interfaces')
            ->where('user_id', auth()->user()->id)
            ->join('interfaces', 'interfaces.id', '=', 'user_interfaces.interface_id')
            ->pluck('name', 'interface_id')->toArray();
        
        $messageDuration = 10000;

        return view('admin.WGCreate', compact('interfaces', 'messageDuration'));
    }
    public function peers(Request $request)
    {
        $peers = DB::table('peers')
            ->join('user_interfaces', 'peers.interface_id', '=', 'user_interfaces.interface_id')
            ->select('peers.*')
            ->where('user_interfaces.user_id', auth()->user()->id);
        
        $interface = $request->query('wiregaurd');
        if ($interface && $peers && $peers->count() > 0) {
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
                                        ->orWhere('client_address', 'like', '%'.$comment.'%');
                        });
        }

        $peers = $peers->get();
        
        $interfaces = DB::table('user_interfaces')
            ->where('user_id', auth()->user()->id)
            ->join('interfaces', 'interfaces.id', '=', 'user_interfaces.interface_id')
            ->pluck('name', 'interface_id')->toArray();
        
        $messageDuration = 10000;
        return view('admin.WGPeers', compact('peers', 'interface', 'comment', 'interfaces', 'messageDuration'));
    }
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
        
        $today = date('Y-m-d', time());
        if (! is_dir(resource_path("confs/$today"))) { 
            mkdir(resource_path("confs/$today")); 
        }

        if (! is_dir(resource_path("confs/$today/$time"))) {
            mkdir(resource_path("confs/$today/$time"));
        }
        
        $confFilePath = resource_path("confs/$today/$time/$commentApply.conf");
        $qrcodeFilePath = resource_path("confs/$today/$time/$commentApply.jpg");

        // create .conf file
        $confFile = fopen($confFilePath, 'w');
        
        $content = "[Interface]\n";
        $content .= "PrivateKey = $privateKey\n";
        $content .= "Address = $caddress32\n";
        $content .= "DNS = $cdns\n\n";
        $content .= "[Peer]\n";
        $content .= "PublicKey = $interfacePublicKey\n"; // Interface's public key
        $content .= "AllowedIPs = 0.0.0.0/0, ::/0\n";
        $content .= "Endpoint = $wgserveraddress:$interfaceListenPort\n";

        fwrite($confFile, $content);
        fclose($confFile);

        // creat QR image
        createQRcode($content, $qrcodeFilePath);

        $newLocalPeerId = DB::table('peers')->insertGetId([
            'interface_id' => $interfaceId,
            'dns' => $cdns,
            'client_address' => $caddress32,
            'endpoint_address' => $wgserveraddress,
            'comment' => $commentApply,
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'conf_file' => $confFilePath,
            'qrcode_file' => $qrcodeFilePath,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_by' => auth()->user()->id
        ]);

        return [
            'id' => $newLocalPeerId,
            'publicKey' => $publicKey
        ];
    }
    public function addRemotePeer($sId, $saddress, $caddress, $interfaceName, $cdns, $wgserveraddress, $commentApply, $publicKey, $localPeerId)
    {
        $data = [
			'interface' => $interfaceName,
			'allowed-address' => $caddress.'/32',
            // 'endpoint-address' => $wgserveraddress,
            // 'client-dns' => $cdns,
            'public-key' => $publicKey,
			'comment' => $commentApply,
        ];

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
    protected function regenerate($id, $time)
    {
        try {
            $message = '';
            // loop on servers and perform action
            $server_peers = DB::table('server_peers')->where('peer_id', $id)->get();

            foreach ($server_peers as $server_peer) {
                $saddress = DB::table('servers')->find($server_peer->server_id)->server_address;
                $message .= ("$saddress: " . ($this->removeRemote($saddress, $server_peer->server_peer_id) ? "success\r\n" : "fail\r\n"));
            }

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

    public function regenerateSingle(Request $request)
    {
        $time = time();
        return $this->regenerate($request->id, $time);
    }

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

    private function toggleEnable($id, $status)
    {
        try {
            // toggle on local DB
            DB::table('peers')->where('id', $id)->update(['is_enabled' => $status]);

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

    public function toggleEnableSingle(Request $request)
    {
        return $this->toggleEnable($request->id, $request->status);
    }

    public function enableMass(Request $request)
    {
        $ids = json_decode($request->ids);
        foreach ($ids as $id) {
            $this->toggleEnable($id, 1);
        }

        return $this->success('Selected items enabled successfully.');
    }

    public function disableMass(Request $request)
    {
        $ids = json_decode($request->ids);
        foreach ($ids as $id) {
            $this->toggleEnable($id, 0);
        }

        return $this->success('Selected items disabled successfully.');
    }

    public function removeRemote($saddress, $id)
    {
        try {
            $data = [".id" => $id];
            $response = curl_general(
                'POST',
                $saddress . '/rest/interface/wireguard/peers/remove',
                json_encode($data),
                true
            );
            if($response == []) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }
    }

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

    public function removeSingle(Request $request)
    {
        try {
            $message = '';
            
            $server_peers = DB::table('server_peers')->where('peer_id', $request->id)->get();
            foreach ($server_peers as $server_peer) {
                $saddress = DB::table('servers')->find($server_peer->server_id)->server_address;
                $removeResponse = $this->removeRemote($saddress, $server_peer->server_peer_id);
                $message .= $saddress . ($removeResponse ? " succcess\r\n" : " fail\r\n");
            }
        
            $message .= $this->removeLocal($request->id) ? "removed from local\r\n" : "failed to remove from local\r\n";
            
            return $this->success($message);
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function removeMass(Request $request)
    {
        $ids = json_decode($request->ids);
        foreach ($ids as $id) {
            $server_peers = DB::table('server_peers')->where('peer_id', $id)->get();
            foreach ($server_peers as $server_peer) {
                $server = 
                $saddress = DB::table('servers')->find($server_peer->server_id)->server_address;
                $this->removeRemote($saddress, $server_peer->server_peer_id);
                DB::table('server_peers')->where('peer_id', $server_peer->peer_id)->where('server_id', $server_peer->server_id)->delete();
            }
            DB::table('peers')->where('id', $id)->delete();
        }

        return $this->success('Selected items removed successfully.');
    }

    public function updatePeer(Request $request)
    {
        // update dns and endpoint_address
        try {
            DB::table('peers')->where('id', $request->id)->update([
                'dns' => $request->dns,
                'endpoint_address' => $request->endpoint_address
            ]);
            
            return back()->with('message', 'Peer updated successully')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }
    
    public function updatePeersMass(Request $request)
    {
        // update dns and endpoint_address
        try {
            $ids = json_decode($request->ids);
            DB::table('peers')->whereIn('id', $ids)->update([
                'dns' => $request->dns,
                'endpoint_address' => $request->endpoint_address
            ]);
            
            return $this->success('Peers updated successully');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function downloadZip($date, $file)
    {
        return response()->download(resource_path("confs/$date/$file/$file.zip"));
    }

    public function getDownloadLink($today, $time) {
        return view('admin.download', compact('today', 'time'));
    }

    public function downloadPeer($id)
    {
        $peer = DB::table('peers')->find($id);
        $comment = $peer->comment;
        $filename =  resource_path("confs/$comment.zip");
        zipPeer($peer, $filename);

        return response()->download($filename);
    }
}
