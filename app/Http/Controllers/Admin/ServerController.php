<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\WiregaurdController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

// This class is responsible for management of servers (microtik routers)
// A server is a Microtik router
class ServerController extends Controller
{
    /*
    This function shows the information about a router.
    like: number of interfaces, peers, enabled and disabled peers,
    ip addresses, mangels, etc.
    */
    public function info($id)
    {
        $server = DB::table('servers')->find($id);
        $sAddress = $server->server_address;
        $remoteCounts = [];
        
        // interfaces count
        $localInterfacesCount = DB::table('interfaces')->count();
        $remoteInterfaces = curl_general('GET', $sAddress . '/rest/interface/wireguard');
        $remoteCounts['interfaces'] = is_array($remoteInterfaces) ? count($remoteInterfaces) : '-';

        // total peers count
        $localPeersCount = DB::table('peers')->count();
        $remotePeers = curl_general('GET', $sAddress . '/rest/interface/wireguard/peers', '', false, 30);
        $remoteCounts['peers'] = is_array($remotePeers) ? count($remotePeers) : '-';

        $duplicates = $this->findDuplicates($remotePeers);

        $disabledArrayCounts = is_array($remotePeers) ? 
            array_count_values(array_column($remotePeers, 'disabled')) : 
            ['false' => '-', 'true' => '-'];

        // enabled peers count
        $localEnabledPeersCount = DB::table('peers')->where('is_enabled', 1)->count();
        $remoteCounts['enabledPeers'] = $disabledArrayCounts['false'] ?? '-';

        // disabled peers count
        $localDisabledPeersCount = DB::table('peers')->where('is_enabled', 0)->count();
        $remoteCounts['disabledPeers'] = $disabledArrayCounts['true'] ?? '-';

        $remoteQueues = curl_general('GET', $sAddress . '/rest/queue/simple');
        $remoteCounts['queues'] = is_array($remoteQueues) ? count($remoteQueues) : '-';

        $remoteNats = curl_general('GET', $sAddress . '/rest/ip/firewall/nat');
        $remoteCounts['NATs'] = is_array($remoteNats) ? count($remoteNats) : '-';

        $remoteMangles = curl_general('GET', $sAddress . '/rest/ip/firewall/mangle');
        $remoteCounts['mangles'] = is_array($remoteMangles) ? count($remoteMangles) : '-';

        $remoteIps = curl_general('GET', $sAddress . '/rest/ip/address');
        $remoteCounts['IPAddresses'] = is_array($remoteIps) ? count($remoteIps) : '-';

        $remoteRoutes = curl_general('GET', $sAddress . '/rest/ip/route');
        $remoteCounts['routes'] = is_array($remoteRoutes) ? count($remoteRoutes) : '-';

        return view('admin.servers.info', compact('server', 'remoteCounts', 'localInterfacesCount', 'localPeersCount', 'localEnabledPeersCount', 'localDisabledPeersCount', 'duplicates'));
    }

    // This function returns the list of all servers
    public function serversList()
    {
        try {
            $servers = DB::table('servers')->get();
        
            return view('admin.servers.list', compact('servers'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // This function shows a brief of all servers with number of interfaces and peers
    public function serversReport()
    {
        try {
            $servers = DB::table('servers')->get();

            $infos = [];
            foreach ($servers as $server) {
                $sId = $server->id;
                $sAddress = $server->server_address;
                $infos[$sId]['address'] = $sAddress;
                $infos[$sId]['router_os_version'] = $server->router_os_version;
                
                $remoteInterfaces = curl_general('GET', $sAddress . '/rest/interface/wireguard');
                $infos[$sId]['interfaces'] = is_array($remoteInterfaces) ? count($remoteInterfaces) : '-';
                
                $remotePeers = curl_general('GET', $sAddress . '/rest/interface/wireguard/peers', '', false, 30);
                $disabledArrayCounts = is_array($remotePeers) ? 
                    array_count_values(array_column($remotePeers, 'disabled')) : 
                    ['false' => '-', 'true' => '-'];
                
                $infos[$sId]['totalPeers'] = is_array($remotePeers) ? count($remotePeers) : '-';
                $infos[$sId]['enabledPeers'] = $disabledArrayCounts['false'] ?? '-';
                $infos[$sId]['disabledPeers'] = $disabledArrayCounts['true'] ?? '-';
            }

            $localInterfaces = DB::table('interfaces')->count();
            $localEnabledPeers = DB::table('peers')->where('is_enabled', 1)->count();
            $localDisabledPeers = DB::table('peers')->where('is_enabled', 0)->count();
        
            return view('admin.servers.report', compact('infos', 'localInterfaces', 'localEnabledPeers', 'localDisabledPeers'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // this function adds a new server (router)
    public function addServer(Request $request)
    {
        try {
            $newAddress = $request->server_address;
            if (!$newAddress || ! filter_var($newAddress, FILTER_VALIDATE_IP)) {
                return back()->with('message', 'invalid server address')->with('type', 'danger');
            }

            if (! $this->isServerAddressUnique($newAddress)) {
                return back()->with('message', 'This server address alreadey exists!')->with('type', 'danger');
            }
    
            DB::table('servers')->insert([
                'server_address' => $newAddress,
                'router_os_version' => $request->router_os_version,
                'alias' => $request->alias,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    
            return back()->with('message', 'New server added successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // this function updates the attributes of a server
    public function updateServer(Request $request)
    {
        try {
            $newAddress = $request->server_address;
            $server = DB::table('servers')->find($request->id);
            if ($server->server_address != $newAddress) {
                if (!$newAddress || ! filter_var($newAddress, FILTER_VALIDATE_IP)) {
                    return back()->with('message', 'Invalid server address!')->with('type', 'danger');
                }
                if (! $this->isServerAddressUnique($newAddress)) {
                    return back()->with('message', 'This server address alreadey exists!')->with('type', 'danger');
                }
            }

            DB::table('servers')->where('id', $request->id)->update([
                'server_address' => $newAddress,
                'router_os_version' => $request->router_os_version,
                'alias' => $request->alias,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    
            return back()->with('message', 'Server updated successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // This function deletes a server with all it's interfaces and peers
    public function deleteServer(Request $request)
    {
        try {
            DB::table('server_interfaces')->where('server_id', $request->id)->delete();
            DB::table('server_peers')->where('server_id', $request->id)->delete();
            DB::table('servers')->where('id', $request->id)->delete();
    
            return $this->success('Server deleted successfully.');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    protected function findDuplicates($remotePeers)
    {
        if (!is_array($remotePeers))
        {
            return [];
        }

        $duplicates = [];
        $remoteDuplicates = array_count_values(array_column($remotePeers, 'allowed-address'));
        foreach ($remoteDuplicates as $key => $value) {
            if ($value > 1) {
                array_push(
                    $duplicates, 
                    ...array_filter($remotePeers, function($elm) use($key) {
                        return $key == $elm['allowed-address'];
                    })
                );
            }
        }

        return $duplicates;
    }

    protected function removeSingleDuplicate($sAddress, $id)
    {
        try {
            return curl_general(
                'POST',
                $sAddress . '/rest/interface/wireguard/peers/remove',
                json_encode([".id" => $id]),
                true
            );
        } catch (\Exception $exception) {
            //
        }
    }

    // with use of this method, we remove duplicate addresses on remote servers
    public function removeDuplicate(Request $request)
    {
        try {
            $sAddress = $request->sAddress;
            $id = $request->id;
            $response = $this->removeSingleDuplicate($sAddress, $id);
            
            if($response == []) {
                return $this->success('success');
            } else {
                return $this->fail($responce);
            }
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    /*
     * Here, we fetch interfacaes from selected server.
     * Then we would check if each exists in our local database.
     * If it does not exist, we create it.
     * Then we check if each exists in the server_interfaces table.
     * If it does not exists, we create it.
     * if it does exist but the related id is not right, we would update the id
    */
    public function getInterfaces(Request $request)
    {
        try {
            $numberOfNewlyCreatedInterfaces = 0;
            $numberOfUpdatedInterfaces = 0;
    
            $remoteInterfaces = curl_general('GET', $request->server_address . '/rest/interface/wireguard');
    
            if (is_array($remoteInterfaces) && count($remoteInterfaces) > 0) {
                foreach ($remoteInterfaces as $remoteInterface) {
                    $correspondingLocalInterface = DB::table('interfaces')
                                                    ->where('name', $remoteInterface['name']);
                    
                    if ($correspondingLocalInterface->count() > 0) {
                        $localInterfaceId = $correspondingLocalInterface->first()->id;
                    } else {
                        // create new interface
                        $localInterfaceId = DB::table('interfaces')->insertGetId([
                            'name' => $remoteInterface['name'],
                            'public_key' => $remoteInterface['public-key'],
                            'private_key' => $remoteInterface['private-key'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
        
                        $numberOfNewlyCreatedInterfaces++;
                    }
        
                    $server_interface = DB::table('server_interfaces')
                        ->where('server_id', $request->id)
                        ->where('interface_id', $localInterfaceId);
        
                    if ($server_interface->count() > 0) {
                        // control *id
                        if ($server_interface->first()->server_interface_id != $remoteInterface['.id']) {
                            // update *id
                            DB::table('server_interfaces')
                                ->where('server_id', $request->id)
                                ->where('interface_id', $localInterfaceId)
                                ->update(['server_interface_id' => $remoteInterface['.id']]);
        
                            $numberOfUpdatedInterfaces++;
                        }
                    } else {
                        // create new server_interface
                        DB::table('server_interfaces')->insert([
                            'server_id' => $request->id,
                            'interface_id' => $localInterfaceId,
                            'server_interface_id' => $remoteInterface['.id']
                        ]);
                    }
                }
                return $this->success(
                    $numberOfNewlyCreatedInterfaces . 
                    ' interfaces created successfully. ' . 
                    $numberOfUpdatedInterfaces . 
                    ' interfaces updated successfully');
            } else {
                return $this->fail('Unable to fetch remote interfaces.');
            }
            
        } catch (\Exception $exception) {
            return $this->fail($exception->getLine() . ': ' . $exception->getMessage());
        }
    }

    /*
     * Here, we fetch peers from selected server.
     * Then we would check if each exists in our local database.
     * If it does not exist, we create it.
     * Then we check if each exists in the server_peers table.
     * If it does not exists, we create it.
     * if it does exist but the related id is not right, we would update the id
    */
    public function getPeers(Request $request)
    {
        try {
            $messages = [];
            $numberOfNewlyCreatedPeers = 0;
            $numberOfUpdatedPeers = 0;
            $now = date('Y-m-d H:i:s');
    
            $remotePeers = curl_general('GET', $request->server_address . '/rest/interface/wireguard/peers', '', false, 30);
    
            if (is_array($remotePeers) && count($remotePeers) > 0) {
                foreach ($remotePeers as $remotePeer) {
                    $remotePeerAllowedAddress = $remotePeer['allowed-address'];
                    $interfaceName = $remotePeer['interface'];

                    $correspondingLocalPeer = DB::table('peers')
                                                ->where('client_address', $remotePeerAllowedAddress);
                    
                    if ($correspondingLocalPeer->count() > 0) {
                        $localPeerId = $correspondingLocalPeer->first()->id;
                    } else {
                        $interface = DB::table('interfaces')->where('name', $interfaceName)->first();
                        // create new peer
                        $localPeerId = DB::table('peers')->insertGetId([
                            'interface_id' => $interface->id,
                            'client_address' => $remotePeerAllowedAddress,
                            'comment' => $remotePeer['comment'] ?? '-',
                            'is_enabled' => $remotePeer['disabled'] == "false" ? 1 : 0,
                            'public_key' => $remotePeer['public-key'],
                            'created_at' => $now,
                            'updated_at' => $now
                        ]);
        
                        $numberOfNewlyCreatedPeers++;
                        $x = substr($remotePeerAllowedAddress, 0, -3);
                        array_push($messages, "$x created on $interfaceName");
                    }
        
                    $server_peer = DB::table('server_peers')
                        ->where('server_id', $request->id)
                        ->where('peer_id', $localPeerId);
        
                    if ($server_peer->count() > 0) {
                        // control *id
                        if ($server_peer->first()->server_peer_id != $remotePeer['.id']) {
                            // update *id
                            DB::table('server_peers')
                                ->where('server_id', $request->id)
                                ->where('peer_id', $localPeerId)
                                ->update(['server_peer_id' => $remotePeer['.id']]);
        
                            $numberOfUpdatedPeers++;
                            $x = substr($remotePeerAllowedAddress, 0, -3);
                            array_push($messages, "$x id updated on $interfaceName");
                        }
                    } else {
                        // create new server_peer
                        DB::table('server_peers')->insert([
                            'server_id' => $request->id,
                            'peer_id' => $localPeerId,
                            'server_peer_id' => $remotePeer['.id'],
                            'created_at' => $now
                        ]);
                    }
                }
                $y = implode("\r\n", $messages);
                return $this->success("$numberOfNewlyCreatedPeers peers created successfully. $numberOfUpdatedPeers peers updated successfully. Details: $y");
            } else {
                return $this->fail('Unable to fetch remote peers.');
            }
            
        } catch (\Exception $exception) {
            return $this->fail($exception->getLine() . ': ' . $exception->getMessage());
        }
    }

    /* in this function we sync interfaces across all routers.
       if an interface does not exist in a router, we create it.
       and also coresponding ip address
    */
    public function syncInterfaces(Request $request)
    {
        // TODO: if remote value has extra interfaces, remove them
        $sId = $request->id;
        $sAddress = $request->server_address;
        $localInterfaces = DB::table('interfaces')->get();
        $remoteInterfaces = curl_general('GET',
            $sAddress . '/rest/interface/wireguard'
        );
        if (!is_array($remoteInterfaces)) {
            return $this->fail($remoteInterfaces);
        }
        $remoteInterfaceNames = array_column($remoteInterfaces, 'name');
        
        foreach ($localInterfaces as $localInterface) {
            $localInterfaceId = $localInterface->id;
            $localInterfaceName = $localInterface->name;
            $key = array_search($localInterfaceName, $remoteInterfaceNames);
            if ($key > 0) { // interface exists on remote
                // check id is correct
                $remoteInterfaceId = $remoteInterfaces[$key]['.id'];
                $server_interface = DB::table('server_interfaces')
                                        ->where('server_id', $sId)
                                        ->where('interface_id', $localInterfaceId);

                if ($server_interface->count() > 0) { // server_interface exists
                    if ($server_interface->first()->server_interface_id != $remoteInterfaceId) {
                        // .id is wrong in local DB
                        DB::table('server_interfaces')
                            ->where('server_id', $sId)
                            ->where('interface_id', $localInterfaceId)
                            ->update(['server_interface_id' => $remoteInterfaceId]);
                    }
                } else {
                    // create new server_interface
                    DB::table('server_interfaces')->insert([
                        'server_id' => $sId,
                        'interface_id' => $localInterfaceId,
                        'server_interface_id' => $remoteInterfaceId
                    ]);
                }
            } else { // interface does not exist on remote
                // create interface on remote and return .id
                $res = curl_general('POST', 
                    $sAddress . '/rest/interface/wireguard/add',
                    json_encode([
                        'name' => $localInterfaceName, 
                        'private-key' => $localInterface->private_key, 
                        'mtu' => $localInterface->mtu,
                        'listen-port' => $localInterface->listen_port
                    ]),
                    true
                );

                // add ip address as well
                curl_general('POST', 
                    $sAddress . '/rest/ip/address/add',
                    json_encode([
                        'address' => $localInterface->ip_range.'1/24',
                        'interface' => $localInterfaceName,
                    ]),
                    true
                );

                if ($res && is_array($res) && count($res) > 0 && isset($res['ret'])) {
                    $remoteInterfaceId = $res['ret'];
                } else {
                    $remoteInterface = curl_general('GET', $sAddress . '/rest/interface/wireguard?name='.$localInterfaceName);
                    $remoteInterfaceId = $remoteInterface[0]['.id'];
                }
                
                DB::table('server_interfaces')->insert([
                    'server_id' => $sId,
                    'interface_id' => $localInterfaceId,
                    'server_interface_id' => $remoteInterfaceId
                ]);
            }
        }

        return $this->success('Sync OK!');
    }

    /* 
        In this sync peer function, we do the following:
        first check if a peer exists on remote. if not we create it.
        if a peer already exists on remote we check that it's id is correct in our local databse.
        then check if the enabled status is correct on remote, if not we fix it.
        then check if the comment is equal in the remote, if not we fix it.
    */
    protected function syncPeersOnServer($sId, $saddress)
    {
        // TODO: depend on server version, add method is different
        try {
            $messages = [];
            $now = date('Y-m-d H:i:s');
            $localPeers = DB::table('peers')->get();

            $remotePeers = curl_general('GET',
                $saddress . '/rest/interface/wireguard/peers',
                '',
                false,
                30 // timeout (s)
            );

            if(is_array($remotePeers)) {
                // find duplicates
                $duplicates = $this->findDuplicates($remotePeers);
                
                // remove duplicates
                foreach ($duplicates as $duplicate) {
                    $this->removeSingleDuplicate($saddress, $duplicate[".id"]);
                }

                // check if server is already synced by number of enabled and disabled peers
                $remotePeersTotalCount = count($remotePeers);
                $disabledArray = array_column($remotePeers, 'disabled');
                $disabledArrayCounts = array_count_values($disabledArray);
                $enabledRemotePeersCount = $disabledArrayCounts['false'] ?? '-';
                $disabledRemotePeersCount = $disabledArrayCounts['true'] ?? '-';

                $localEnabledPeersCount = DB::table('peers')->where('is_enabled', 1)->count();
                $localDisabledPeersCount = DB::table('peers')->where('is_enabled', 0)->count();

                if ($localEnabledPeersCount == $enabledRemotePeersCount && $localDisabledPeersCount == $disabledRemotePeersCount) {
                    return ['status' => 1, 'message' => 'Server is already synced!'];
                }
                
                $remotePeersAllowedAddresses = array_column($remotePeers, 'allowed-address');
                $localPeersClientAddresses = array_column($localPeers->toArray(), 'client_address');
                
                // delete extra remote peers
                foreach ($remotePeers as $remotePeer) {
                    $key = array_search($remotePeer['allowed-address'], $localPeersClientAddresses);
                    if (! $key) {
                        $res = curl_general(
                            'POST',
                            $saddress . '/rest/interface/wireguard/peers/remove',
                            json_encode(['.id' => $remotePeer['.id']]),
                            true
                        );

                        $address = substr($remotePeer['allowed-address'], 0 , -3);
                        $resMessage = is_array($res) ? implode('-', $res) : $res;
                        array_push($messages, "$saddress, $address remove response: $resMessage");
                    }
                }

                $numberOfFailedAttempts = 0;
                foreach ($localPeers as $localPeer) {
                    $key = array_search($localPeer->client_address, $remotePeersAllowedAddresses);

                    if ($key > 0) { // peer exists on remote
                        $remotePeerId = $remotePeers[$key]['.id'];
                        // DB::table('server_peers')->upsert(
                        //     [
                        //         'server_id' => $sId,
                        //         'peer_id' => $localPeer->id,
                        //         'server_peer_id' => $remotePeerId,
                        //         'created_at' => $now,
                        //         'updated_at' => $now
                        //     ],
                        //     ['server_id', 'peer_id'],
                        //     ['server_peer_id', 'updated_at']
                        // );
                        
                        // check id is correct
                        $server_peer = DB::table('server_peers')
                                        ->where('server_id', $sId)
                                        ->where('peer_id', $localPeer->id)
                                        ->first();

                        if ($server_peer) { // server_peer exists
                            if ($server_peer->server_peer_id != $remotePeerId) {
                                // .id is wrong in local DB
                                DB::table('server_peers')
                                    ->where('server_id', $sId)
                                    ->where('peer_id', $localPeer->id)
                                    ->update([
                                        'server_peer_id' => $remotePeerId, 
                                        'updated_at' => $now
                                    ]);
                            }
                        } else {
                            // create new server_peer
                            DB::table('server_peers')->insert([
                                'server_id' => $sId,
                                'peer_id' => $localPeer->id,
                                'server_peer_id' => $remotePeerId,
                                'created_at' => $now
                            ]);
                        }

                        // check enabled
                        $remotePeerDisabledStatus = $remotePeers[$key]['disabled'];
                        $localPeerDisabledStatus = $localPeer->is_enabled ? "false" : "true";
                        if ($localPeerDisabledStatus != $remotePeerDisabledStatus) {
                            // update remote peer
                            $data = [".id" => $remotePeerId];
                            $command = $localPeerDisabledStatus == "false" ? 'enable' : 'disable';
                            $response = curl_general(
                                'POST',
                                $saddress . '/rest/interface/wireguard/peers/'.$command,
                                json_encode($data),
                                true
                            );
                            $address = substr($remotePeers[$key]['allowed-address'], 0, -3);
                            $responseMessage = is_array($response) ? implode('-', $response) : $response;
                            array_push($messages, "$saddress, Peer $address $command response: $responseMessage");
                        }

                        // check comment
                        $remotePeerComment = $remotePeers[$key]['comment'];
                        $localPeerComment = $localPeer->comment;
                        if ($remotePeerComment != $localPeerComment) {
                            $data = [".id" => $remotePeerId, "comment" => $localPeerComment];
                            curl_general(
                                'POST',
                                $saddress . '/rest/interface/wireguard/peers/set',
                                json_encode($data),
                                true
                            );
                        }
                    } else { // peer does not exist on remote
                        // create peer on remote
                        $interface = DB::table('interfaces')->find($localPeer->interface_id);
                        $caddress = substr($localPeer->client_address,0,-3);
                        $interfaceName = $interface->name;
                        $cdns = $localPeer->dns ?? $interface->dns;
                        $wgserveraddress = $localPeer->endpoint_address ?? $interface->default_endpoint_address;

                        $wg = new WiregaurdController();
                        $addResponse = $wg->addRemotePeer($sId, $saddress, $caddress, $interfaceName, $cdns, $wgserveraddress, $localPeer->comment, $localPeer->public_key, $localPeer->id, $localPeer->is_enabled);
                        array_push($messages, "$saddress, Add Peer $caddress response: $addResponse");
                        if (! $addResponse) {
                            $numberOfFailedAttempts++;
                        }
                    }
                }

                return ['status' => 1, 'message' => implode("\r\n", $messages)];

                if ($numberOfFailedAttempts > 0) {
                    return ['status' => -1, 'message' => 'Sync was not successful!'];
                } else {
                    return ['status' => 1, 'message' => 'Peers Synced Successfully'];
                }
            } else {
                return ['status' => -1, 'message' => "$remotePeers"];
            }
            
            
        } catch (\Exception $exception) {
            return ['status' => -1, 'message' => $exception->getLine() . ': ' . $exception->getMessage()];
        }
    }

    // Sync peers on a single server
    public function syncPeers(Request $request)
    {
        $result = $this->syncPeersOnServer($request->id, $request->server_address);
        $status = $result['status'];
        $message = $result['message'];

        return $status == 1 ? $this->success($message) : $this->fail($message);
    }

    // this function checks that an ip address be unique
    public function isServerAddressUnique($address)
    {
        try {
            return DB::table('servers')->where('server_address', $address)->count() == 0;
        } catch (\Exception $ex) {
            return false;
        }
    }

    // sync peers on all servers via a cron job
    public function syncAll($request_token)
    {
        try {
            $token = env('SYNC_ALL_TOKEN');
            $message = "";

            if ($token == $request_token) {
                $servers = DB::table('servers')->get();
                foreach ($servers as $server) {
                    $sAddress = $server->server_address;
                    $result = $this->syncPeersOnServer($server->id, $sAddress);
                    $resMessage = $result['message'];
                    $message .= "$sAddress: $resMessage\r\n";
                }

                saveCronResult('syncAll', $message);
                
                return $this->success($message);
            }

            saveCronResult('syncAll', 'token mismatch!');
            return $this->fail('token mismatch!');
        } catch (\Exception $exception) {
            $message = $exception->getLine() . ': ' . $exception->getMessage();
            saveCronResult('syncAll', $message);
            return $this->fail($message);
        }
    }
}
