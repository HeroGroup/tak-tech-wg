<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use phpseclib3\Math\BigInteger;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

require_once app_path('Helpers/utils.php');
require_once app_path('Helpers/phpqrcode/qrlib.php');

class WiregaurdController extends Controller
{
    public function createKeys()
    {
        $privateKey = RSA::createKey(16);
        $publicKey = $privateKey->getPublicKey();

        
        $privateKeyString = $privateKey->toString('PKCS8');
        $publicKeyString = $publicKey->toString('PKCS8');
        
        $privateKeyString = str_replace("-----BEGIN PRIVATE KEY-----", "", $privateKeyString);
        $privateKeyString = str_replace("-----END PRIVATE KEY-----", "", $privateKeyString);
        $privateKeyString = str_replace("\r\n", "", $privateKeyString);
        
        $publicKeyString = str_replace("-----BEGIN PUBLIC KEY-----", "", $publicKeyString);
        $publicKeyString = str_replace("-----END PUBLIC KEY-----", "", $publicKeyString);
        $publicKeyString = str_replace("\r\n", "", $publicKeyString);


        return [
            'public_key' => $publicKeyString, 
            'private_key' => $privateKeyString
        ];
    }
    public function create()
    {
        $interfaces = DB::table('interfaces')->pluck('name', 'id')->toArray();
        $messageDuration = 10000;

        return view('admin.WGCreate', compact('interfaces', 'messageDuration'));
    }
    public function peers(Request $request)
    {
        $peers = DB::table('peers');
        
        $interface = $request->query('wiregaurd');
        if ($interface && $peers && $peers->count() > 0) {
            // $peers = array_filter($peers, function ($element) use ($interface) {
            //     return ($element->interface_id == $interface);
            // });
            $peers = $peers->where('interface_id', $interface);
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
        
        $interfaces = DB::table('interfaces')->pluck('name', 'id')->toArray();
        
        $messageDuration = 10000;
        return view('admin.WGPeers', compact('peers', 'interface', 'comment', 'interfaces', 'messageDuration'));
    }
    public function addPeer($saddress, $caddress, $remoteInterfaceId, $cdns, $wgserveraddress, $comment, $privateKey, $publicKey)
    {
        try {
            $confsPath = DB::table('settings')->where('setting_key', 'CONFS_PATH')->first()->setting_value;
            $confsDirectory = DB::table('settings')->where('setting_key', 'CONFS_DIRECTORY')->first()->setting_value;

            
            // add peer on remote
            $this->addRemotePeerWithoutComment($saddress, $caddress, $remoteInterfaceId, $cdns, $wgserveraddress, $privateKey, $publicKey);

            $recentPeer = curl_general('GET', $saddress . '/rest/interface/wireguard/peers?allowed-address='.$caddress.'/32');
            if (!$recentPeer) {
                // dump($recentPeer);
                return false;
            }
            $this->applyComment($saddress, $caddress, $comment, $recentPeer[0]['.id'], []);
            $this->createConfFileAndUpload($saddress, $caddress, $comment, $confsPath,  $confsDirectory);

            // get all files
            $files = curl_general(
                'POST',
                $saddress . '/rest/file/getall'
            );

            $filesNames = array_column($files, 'name', '.id');

            // remove source file
            $filename = $caddress.".conf"; // source file
            $this->removeUnnecessaryFilesOnRemote($saddress, $filename, $filesNames);
            // $this->createQRCode($comment, $confsPath,  $confsDirectory);
            // createZip($confsDirectory);

            return $recentPeer[0]['.id'];
        } catch (\Exception $exception) {
            return $exception->getLine().': '.$exception->getMessage();
        }
    }
    public function createWG(Request $request)
    {
        $confsPath = DB::table('settings')->where('setting_key', 'CONFS_PATH')->first()->setting_value;
        $confsDirectory = DB::table('settings')->where('setting_key', 'CONFS_DIRECTORY')->first()->setting_value;

        $interfaceId = $request->wginterface;
        $wgserveraddress = $request->endpoint;
        $cdns = $request->dns;
        $comment = $request->comment;
        $range = $request->range;
        $start = $request->start;
        $end = $request->end;
        $randoms = explode('-', $request->random);

        // validate incoming request
        // if(! (filter_var($wgserveraddress, FILTER_VALIDATE_IP)) && !(preg_match('/([a-zA-Z0-9\-_]+\.)?[a-zA-Z0-9\-_]+\.[a-zA-Z]{2,5}/',$wgserveraddress))) {
        //     return back()->with("message", "EndPoint Address is Wrong.")->with("type", "danger");
        // }

        // if(! filter_var($cdns, FILTER_VALIDATE_IP)) {
        //     return back()->with("message", "Wireguard DNS Address is Wrong")->with("type", "danger");
        // }

        // if(filter_var($range, FILTER_VALIDATE_IP)) {
        //     return back()->with("message", "IPv4 Address Range Address is Wrong")->with("type", "danger");
        // }

        // check is wiregaurd is valid
        $interface = DB::table('interfaces')->find($interfaceId);
        if (! $interface) {
            return back()->with("message", "Wireguard Interface Not Found")->with("type", "danger");
        }

        // perform action on first server in order to fetch public keys
        $servers = DB::table('servers')
            ->where('router_os_version', '>=', '7.12.1')
            ->where('router_os_version', 'not like', '%beta%')
            ->get();
        // $server = $servers[3];
        
        $saddress = $server->server_address;
        $server_interface = DB::table('server_interfaces')
                ->where('interface_id', $interfaceId)
                ->where('server_id', $server->id)
                ->first();

        $remoteInterfaceId = $server_interface->server_interface_id;

        if ($request->type == 'batch') {
            $batch = $this->addPeersBatch(
                $saddress,
                $interfaceId,
                $interface->name,
                // $remoteInterfaceId,
                $interface->dns,
                $interface->default_endpoint_address,
                $comment,
                $interface->ip_range,
                $start,
                $end,
                $confsPath,
                $confsDirectory);
        } else {
            $batch = $this->addPeersRandom(
                $saddress,
                $interfaceId,
                $interface->name,
                $remoteInterfaceId,
                $interface->dns,
                $interface->default_endpoint_address,
                $comment,
                $interface->ip_range,
                $randoms,
                $confsPath,
                $confsDirectory);
        }

        if ($batch > 0) {
            // loop on all servers and sync server peers with local DB
            $recentPeers = DB::table('peers')->where('batch', $batch)->get();
            if (count($recentPeers) > 0) {
                $message = $saddress . ': OK!\r\n';
                for ($i=0; $i<count($servers)-1; $i++) {
                    $server = $servers[$i];
                    $saddress = $server->server_address;
                    
                    $remoteInterfaceId = DB::table('server_interfaces')
                        ->where('interface_id', $interfaceId)
                        ->where('server_id', $server->id)
                        ->first()
                        ->server_interface_id;
    
                    foreach ($recentPeers as $recentPeer)
                    {
                        $this->addRemotePeerWithoutComment($saddress, substr($recentPeer->client_address,0,-3), $remoteInterfaceId, $cdns, $wgserveraddress, $recentPeer->private_key, $recentPeer->public_key);
                    }
                    // get all peers from server
                    $allPeers = curl_general('POST', $saddress . '/rest/interface/wireguard/peers/getall');
                    $allowed_addresses = array_column($allPeers, 'allowed-address', '.id');
    
                    foreach ($recentPeers as $recentPeer)
                    {
                        $this->applyComment($saddress, substr($recentPeer->client_address,0,-3), $recentPeer->comment, 0, $allowed_addresses);
                    }
    
                    $message .= $saddress . ': OK!\r\n';
                }
                // return back()->with("message", $message)->with("type", "success");
            } else {
                // return back()->with("message", "Unable to create peers!")->with("type", "danger");
            }
        } else {
            // return back()->with("message", "Unable to create peers!")->with("type", "danger");
        }
        
              
    }
    public function addRemotePeerWithComment($saddress, $caddress, $interfaceName, $cdns, $wgserveraddress, $commentApply, $privateKey, $publicKey, $generatePresharedKey="no") {
        $data = [
			'interface' => $interfaceName,
			'allowed-address' => $caddress.'/32',
            // 'endpoint-address' => $wgserveraddress,
            // 'client-dns' => $cdns,
			// 'private-key' => $privateKey,
            'public-key' => $publicKey,
			'comment' => $commentApply,
        ];

        // return new peer id
        return curl_general(
            'POST',
            $saddress . '/rest/interface/wireguard/peers/add',
            json_encode($data),
            true
        );
    }
    public function addRemotePeerWithoutComment($saddress, $caddress, $remoteInterfaceId, $cdns, $wgserveraddress, $privateKey, $publicKey, $generatePresharedKey="no")
    {
        $filename = $caddress.".conf";
        $data = [
            ".id" => $remoteInterfaceId,
			"client-dns" => $cdns,
			"client-address" => $caddress,
			"endpoint-address" => $wgserveraddress,
			"file" => $filename,
			"generate-preshared-key" => "no",
            'private-key' => $privateKey,
            'public-key' => $publicKey
        ];
        
        dump($saddress, $data, curl_general(
            'POST',
            $saddress . '/rest/interface/wireguard/wg-add-client',
            json_encode($data),
            true
        ));
    }
    public function applyComment($saddress, $caddress, $comment, $peerId, $allowed_addresses)
    {
        // Set comment for recently created peers
        if ((! $peerId) || $peerId == 0) { 
            $peerId = array_search($caddress.'/32', $allowed_addresses); 
        }

        if ($peerId) {
            $data = [".id" => $peerId, "comment" => $comment];
            curl_general(
                'POST',
                $saddress . '/rest/interface/wireguard/peers/set',
                json_encode($data),
                true
            );

            return true;
        } else {
            return false;
        }
    }
    public function createConfFileAndUpload($saddress, $caddress, $commentApply, $confsPath,  $confsDirectory)
    {
        $filename = $caddress.".conf";
        $filenameonserver = $commentApply.".conf";
        $ftpaddress = DB::table('settings')->where('setting_key', 'FTP_ADDRESS')->first()->setting_value;
        $ftpuser = DB::table('settings')->where('setting_key', 'FTP_USER')->first()->setting_value;
        $ftppassword = DB::table('settings')->where('setting_key', 'FTP_PASSWORD')->first()->setting_value;
        $dstpath = $confsPath . $confsDirectory . $filenameonserver;
        
        $data = [
            "mode" => "ftp",
            "upload" => "yes",
            "address" => $ftpaddress,
            "user" => $ftpuser ,
            "password" => $ftppassword,
            "src-path" => $filename,
            "dst-path" => $dstpath,
            "keep-result" => "yes",
            "check-certificate" => "no",
        ];

        // copy config file from server to destinationftp server
        $fetch = curl_general(
            'POST',
            $saddress . '/rest/tool/fetch',
            json_encode($data),
            true
        );
    }
    public function removeUnnecessaryFilesOnRemote($saddress, $filename, $filesNames)
    {
        // remove source file
        $fileId = array_search($filename, $filesNames);
        if ($fileId) {
            $data = [".id" => $fileId];
            curl_general(
                'POST',
                $saddress . '/rest/file/remove',
                json_encode($data),
                true
            );
        }
    }
    public function createQRCode($comment, $confsPath,  $confsDirectory)
    {
        // creating QRcode
        $filenameonserver = $comment.".conf";
        $filen = $confsPath . $confsDirectory . $filenameonserver;
        $imageName = $comment.".jpg";
        $imagePath = $confsDirectory . $imageName ;
        deleteLineInFile($filen, "ListenPort");
        $f = fopen($filen, 'r');
        if ($f)
        {
            $contents = fread($f, filesize($filen));
            fclose($f);
            $encoded = urlencode($contents);
            $ecc = 'L';
            $pixel_Size = 10;
            $frame_Size = 10;
            // Generates QR Code and Stores it in directory given
            QRcode::png($contents, $imagePath, $ecc, $pixel_Size, $frame_Size);
        }
    }
    public function addPeersBatch($sId, $saddress, $interfaceId, $interfaceName, $remoteInterfaceId, $cdns, $wgserveraddress, $comment, $range, $start, $end, $confsPath, $confsDirectory)
    {
        // Create all peers
        for ($i = $start; $i <= $end; $i++)
        {
            $caddress = $range . ($i + 1);
            $commentApply = $comment . '-' . $i;
            $keys = $this->createKeys();
            $privateKey = $keys['private_key'];
            $publicKey = $keys['public_key'];

            $newLocalPeer = DB::table('peers')->insertGetId([
                'interface_id' => $interfaceId,
                'dns' => $cdns,
                'client_address' => $caddress,
                'endpoint_address' => $wgserveraddress,
                'generated_preshared_key' => 0,
                'comment' => $commentApply,
                'private_key' => $privateKey,
                'public_key' => $publicKey,
                'is_enabled' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $newRemotePeer = $this->addRemotePeerWithComment($saddress, $caddress, $interfaceName, $cdns, $wgserveraddress, $commentApply, $privateKey, $publicKey);
            dump($newRemotePeer);

            if (is_array($newRemotePeer) && count($newRemotePeer) > 0 && $newRemotePeer[0]['ret']) {
                DB::table('server_peers')->insert([
                    'server_id' => $sId,
                    'peer_id' => $newLocalPeer->id,
                    'server_peer_id' => $newRemotePeer[0]['ret']
                ]);
            }

            // create conf file and set source url
            // create qr and set source url
        }
        
        // get all peers from server
        // $allPeers = curl_general(
        //     'POST',
        //     $saddress . '/rest/interface/wireguard/peers/getall'
        // );

        // $allowed_addresses = array_column($allPeers, 'allowed-address', '.id');
        // $public_keys = array_column($allPeers, 'public-key', '.id');
        
        // $batch = DB::table('peers')->max('batch') ?? 0;
        // $newBatch = $batch + 1;
        // for ($i = $start; $i <= $end; $i++)
        // {
        //     $caddress = $range . ($i + 1);
        //     $commentApply = $comment . '-' . $i;

        //     $remoteId = array_search($caddress.'/32', $allowed_addresses);

        //     if ($remoteId) {
        //         // insert peer in local DB
        //         DB::table('peers')->insert([
        //             'interface_id' => $interfaceId,
        //             'dns' => $cdns,
        //             'client_address' => $caddress,
        //             'endpoint_address' => $wgserveraddress,
        //             'generated_preshared_key' => 0,
        //             'comment' => $commentApply,
        //             'public_key' => $public_keys[$remoteId],
        //             'is_enabled' => true,
        //             'batch' => $newBatch,
        //             'created_at' => date('Y-m-d H:i:s'),
        //             'updated_at' => date('Y-m-d H:i:s')
        //         ]);

        //         $this->applyComment($saddress, $caddress, $commentApply, 0, $allowed_addresses);
        //         $this->createConfFileAndUpload($saddress, $caddress, $commentApply, $confsPath,  $confsDirectory);
        //     }
        // }
            
        // // get all files
        // $files = curl_general(
        //     'POST',
        //     $saddress . '/rest/file/getall'
        // );

        // $filesNames = array_column($files, 'name', '.id');

        // for ($i = $start; $i <= $end; $i++)
        // {
        //     $caddress = $range . ($i + 1);
        //     $filename = $caddress.".conf"; // source file
        //     $commentApply = $comment . '-' . $i;
        //     $this->removeUnnecessaryFilesOnRemote($saddress, $filename, $filesNames);
        //     // $this->createQRCode($commentApply, $confsPath,  $confsDirectory);
        // }

        // // creating zip file
        // // createZip($confsDirectory);
        
        // return $newBatch; // redirect(route('wiregaurd.peers.index'));
    }
    public function addPeersRandom($saddress, $interfaceId, $interfaceName, $remoteInterfaceId, $cdns, $wgserveraddress, $comment, $range, $randoms, $confsPath, $confsDirectory)
    {
        // Create all peers
        foreach ($randoms as $i)
        {
            $caddress = $range . ($i + 1);
            $commentApply = $comment . '-' . $i;
            $keys = $this->createKeys();
            $this->addRemotePeerWithoutComment($saddress, $caddress, $remoteInterfaceId, $cdns, $wgserveraddress, $keys['private_key'], $keys['public_key']);
        }
        
        // get all peers from server
        $allPeers = curl_general(
            'POST',
            $saddress . '/rest/interface/wireguard/peers/getall'
        );

        $allowed_addresses = array_column($allPeers, 'allowed-address', '.id');
        $public_keys = array_column($allPeers, 'public-key', '.id');
        
        $batch = DB::table('peers')->max('batch') ?? 0;
        $newBatch = $batch + 1;
        foreach ($randoms as $i)
        {
            $caddress = $range . ($i + 1);
            $commentApply = $comment . '-' . $i;

            $remoteId = array_search($caddress.'/32', $allowed_addresses);

            if ($remoteId) {
                // insert peer in local DB
                DB::table('peers')->insert([
                    'interface_id' => $interfaceId,
                    'dns' => $cdns,
                    'client_address' => $caddress.'/32',
                    'endpoint_address' => $wgserveraddress,
                    'generate_preshared_key' => 0,
                    'comment' => $commentApply,
                    'public_key' => $public_keys[$remoteId],
                    'is_enabled' => true,
                    'batch' => $newBatch,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $this->applyComment($saddress, $caddress, $commentApply, 0, $allowed_addresses);
                $this->createConfFileAndUpload($saddress, $caddress, $commentApply, $confsPath,  $confsDirectory);
            }
        }
            
        // get all files
        $files = curl_general(
            'POST',
            $saddress . '/rest/file/getall'
        );

        $filesNames = array_column($files, 'name', '.id');

        foreach ($randoms as $i)
        {
            $caddress = $range . ($i + 1);
            $filename = $caddress.".conf"; // source file

            $this->removeUnnecessaryFilesOnRemote($saddress, $filename, $filesNames);
            // $this->createQRCode($comment, $confsPath,  $confsDirectory);            
        }

        // createZip($confsDirectory);
        return $newBatch; // redirect(route('wiregaurd.peers.index'));
    }

    // Actions
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

    public function regenerate($id)
    {
        try {
            // loop on servers and perform action
            $server_peers = DB::table('server_peers')->where('peer_id', $id)->get();

            foreach ($server_peers as $server_peer) {
                $spID = $server_peer->server_peer_id;
                $saddress = DB::table('servers')->find($server_peer->server_id)->server_address;
                // remove
                $removeResponse = $this->remove($saddress, $id);
                if ($removeResponse->status == 1) {
                    // fetch peer information
                    $peer = DB::table('peers')->find($id);
                    
                    // add
                    $caddress = substr($peer->client_address,0,-3);
                    $remoteInterfaceId = DB::table('server_interfaces')
                                        ->where('interface_id', $peer->interface_id)
                                        ->server_interface_id;
                    $cdns = $peer->dns;
                    $wgserveraddress = $peer->endpoint_address;
                    
                    $addResponse = $this->addPeer($saddress, $caddress, $remoteInterfaceId, $cdns, $wgserveraddress, $comment);
                    
                    // update server_peer_id (new .id) in server_peers_table
                    $remotePeers = curl_general('GET',
                        $saddress . '/rest/interface/wireguard/peers'
                    );
                    $remotePeersAllowedAddresses = array_column($remotePeers, 'allowed-address');
                    $key = array_search($peer->client_address, $remotePeersAllowedAddresses);
                    $server_peer->update(['server_peer_id' => $remotePeers[$key]['.id']]);
                }
            }
            
            $this->success('Peer regenerated successfully!');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    public function remove($saddress, $id)
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
                return $this->success('Removed successfully!', ['extra' => $saddress . ' Removed. \r\n']);
            } else {
                return $this->fail($response, ['extra' => $saddress . ' Failed. \r\n']);
            }
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function toggleEnableSingle(Request $request)
    {
        return $this->toggleEnable($request->id, $request->status);
    }

    public function regenerateSingle(Request $request)
    {
        return $this->regenerate($request->id);
    }

    public function removeSingle(Request $request)
    {
        try {
            $message = '';
            
            $server_peers = DB::table('server_peers')->where('peer_id', $request->id)->get();
            foreach ($server_peers as $server_peer) {
                $saddress = DB::table('servers')->find($server_peer->server_id)->server_address;
                $this->remove($saddress, $server_peer->server_peer_id);
                $server_peer->delete();
                $message .= 'removed from '.$saddress.'\r\n';
            }

            // remove from local DB
            DB::table('peers')->where('id', $request->id)->delete();
            
            $message = 'Removed from local DB. \r\n';

            return $this->success($message);
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function regenerateMass(Request $request)
    {
        $ids = json_decode($request->ids);
        foreach ($ids as $id) {
            $this->regenerate($id);
        }

        return $this->success('Selected items regenerated successfully.');
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

    public function removeMass(Request $request)
    {
        $ids = json_decode($request->ids);
        foreach ($ids as $id) {
            $server_peers = DB::table('server_peers')->where('peer_id', $id)->get();
            $saddress = DB::table('servers')->find($server_peer->server_id)->server_address;
            $this->remove($saddress, $server_peer->server_peer_id);
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
}
