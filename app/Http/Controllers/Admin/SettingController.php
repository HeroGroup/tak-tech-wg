<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\WiregaurdController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

class SettingController extends Controller
{
    public function index()
    {
        try {
            $settings = DB::table('settings')->get(); 

            return view('admin.settings', compact('settings'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    public function addSetting(Request $request)
    {
        try {
            if (!$request->setting_key || !$request->setting_value) {
                return back()->with('message', 'Invalid credentials!')->with('type', 'danger');
            }
            if (DB::table('settings')->where('setting_key', $request->setting_key)->count() > 0) {
                return back()->with('message', 'This setting already exists!')->with('type', 'danger');
            }

            DB::table('settings')->insert([
                'setting_key' => $request->setting_key,
                'setting_value' => $request->setting_value
            ]);
    
            return back()->with('message', 'New setting added successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    public function updateSetting(Request $request)
    {
        try {
            DB::table('settings')->where('id', $request->id)->update([
                'setting_value' => $request->setting_value
            ]);
            return back()->with('message', 'Setting updated successfully')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

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
        // dd($remoteCounts['peers']);

        // enabled peers count
        $localEnabledPeersCount = DB::table('peers')->where('is_enabled', 1)->count();
        $remoteEnabledPeers = curl_general('GET', $sAddress . '/rest/interface/wireguard/peers?=disabled=no', '', false, 30);
        $remoteCounts['enabledPeers'] = is_array($remoteEnabledPeers) ? count($remoteEnabledPeers) : '-';

        // disabled peers count
        $localDisabledPeersCount = DB::table('peers')->where('is_enabled', 0)->count();
        $remoteDisabledPeers = curl_general('GET', $sAddress . '/rest/interface/wireguard/peers?=disabled=yes', '', false, 30);
        $remoteCounts['disabledPeers'] = is_array($remoteDisabledPeers) ? count($remoteDisabledPeers) : '-';

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

        return view('admin.servers.info', compact('server', 'remoteCounts', 'localInterfacesCount', 'localPeersCount', 'localEnabledPeersCount', 'localDisabledPeersCount'));
    }
    
    public function deleteSetting(Request $request)
    {
        try {
            DB::table('settings')->where('id', $request->id)->delete();
            return $this->success('Setting deleted successfully.');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function serversList()
    {
        try {
            $servers = DB::table('servers')->get();

            $infos = [];
            foreach ($servers as $server) {
                $sId = $server->id;
                $sAddress = $server->server_address;
                $infos[$sId]['address'] = $sAddress;
                $infos[$sId]['router_os_version'] = $server->router_os_version;
            }
        
            return view('admin.servers.list', compact('infos'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

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
                
                $remoteEnabledPeers = curl_general('GET', $sAddress . '/rest/interface/wireguard/peers?=disabled=no', '', false, 30);
                $infos[$sId]['enabledPeers'] = is_array($remoteEnabledPeers) ? count($remoteEnabledPeers) : '-';
                
                $remoteDisabledPeers = curl_general('GET', $sAddress . '/rest/interface/wireguard/peers?=disabled=yes', '', false, 30);
                $infos[$sId]['disabledPeers'] = is_array($remoteDisabledPeers) ? count($remoteDisabledPeers) : '-';

                $infos[$sId]['totalPeers'] = (is_array($remoteEnabledPeers) ? count($remoteEnabledPeers) : 0) + (is_array($remoteDisabledPeers) ? count($remoteDisabledPeers) : 0);
            }

            $localInterfaces = DB::table('interfaces')->count();
            $localEnabledPeers = DB::table('peers')->where('is_enabled', 1)->count();
            $localDisabledPeers = DB::table('peers')->where('is_enabled', 0)->count();
        
            return view('admin.servers.report', compact('infos', 'localInterfaces', 'localEnabledPeers', 'localDisabledPeers'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

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
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    
            return back()->with('message', 'New server added successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

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
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    
            return back()->with('message', 'Server updated successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

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

    /*
     * Here, we fetch interfacaes from selected server.
     * Then we would check if each exists in our local database.
     * If it does not exist, we create it.
     * Then we check if each exists in the server_interfaces table.
     * If it does not exists, we create it
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

    public function getPeers(Request $request)
    {
        try {
            $numberOfNewlyCreatedPeers = 0;
            $numberOfUpdatedPeers = 0;
    
            $remotePeers = curl_general('GET', $request->server_address . '/rest/interface/wireguard/peers', '', false, 30);
    
            if (is_array($remotePeers) && count($remotePeers) > 0) {
                foreach ($remotePeers as $remotePeer) {
                    $correspondingLocalPeer = DB::table('peers')
                                                ->where('client_address', $remotePeer['allowed-address']);
                    
                    if ($correspondingLocalPeer->count() > 0) {
                        $localPeerId = $correspondingLocalPeer->first()->id;
                    } else {
                        $interface = DB::table('interfaces')->where('name', $remotePeer['interface'])->first();
                        // create new peer
                        $localPeerId = DB::table('peers')->insertGetId([
                            'interface_id' => $interface->id,
                            'client_address' => $remotePeer['allowed-address'],
                            'comment' => $remotePeer['comment'] ?? '-',
                            'is_enabled' => $remotePeer['disabled'] == "false" ? 1 : 0,
                            'public_key' => $remotePeer['public-key'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
        
                        $numberOfNewlyCreatedPeers++;
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
                        }
                    } else {
                        // create new server_peer
                        DB::table('server_peers')->insert([
                            'server_id' => $request->id,
                            'peer_id' => $localPeerId,
                            'server_peer_id' => $remotePeer['.id']
                        ]);
                    }
                }
                return $this->success(
                    $numberOfNewlyCreatedPeers . 
                    ' peers created successfully. ' . 
                    $numberOfUpdatedPeers . 
                    ' peers updated successfully');
            } else {
                return $this->fail('Unable to fetch remote peers.');
            }
            
        } catch (\Exception $exception) {
            return $this->fail($exception->getLine() . ': ' . $exception->getMessage());
        }
    }

    public function syncInterfaces(Request $request)
    {
        // TODO: if remote value has extra interfaces, remove them
        $sId = $request->id;
        $sAddress = $request->server_address;
        $localInterfaces = DB::table('interfaces')->get();
        $remoteInterfaces = curl_general('GET',
            $sAddress . '/rest/interface/wireguard'
        );
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

    protected function syncPeersOnServer($sId, $saddress)
    {
        // TODO: depend on server version, add method is different
        try {
            $localPeers = DB::table('peers')->get();

            $remotePeers = curl_general('GET',
                $saddress . '/rest/interface/wireguard/peers',
                '',
                false,
                30 // timeout (s)
            );
            $remotePeersAllowedAddresses = array_column($remotePeers, 'allowed-address');
            $localPeersClientAddresses = array_column($localPeers->toArray(), 'client_address');
            
            // delete extra remote peers
            foreach ($remotePeers as $remotePeer) {
                $key = array_search($remotePeer['allowed-address'], $localPeersClientAddresses);
                if (! $key) {
                    curl_general(
                        'POST',
                        $saddress . '/rest/interface/wireguard/peers/remove',
                        json_encode(['.id', $remotePeer['.id']]),
                        true
                    );
                }
            }

            $numberOfFailedAttempts = 0;
            foreach ($localPeers as $localPeer) {
                $key = array_search($localPeer->client_address, $remotePeersAllowedAddresses);

                if ($key > 0) { // peer exists on remote
                    
                    // check id is correct
                    $server_peer = DB::table('server_peers')
                                    ->where('server_id', $sId)
                                    ->where('peer_id', $localPeer->id);

                    if ($server_peer->count() > 0) { // server_peer exists
                        if ($server_peer->first()->server_peer_id != $remotePeers[$key]['.id']) {
                            // .id is wrong in local DB
                            DB::table('server_peers')
                                ->where('server_id', $sId)
                                ->where('peer_id', $localPeer->id)
                                ->update(['server_peer_id' => $remotePeers[$key]['.id']]);
                        }
                    } else {
                        // create new server_peer
                        DB::table('server_peers')->insert([
                            'server_id' => $sId,
                            'peer_id' => $localPeer->id,
                            'server_peer_id' => $remotePeers[$key]['.id']
                        ]);
                    }

                    // check enabled
                    $remotePeerEnable = !(bool)$remotePeers[$key]['disabled'];
                    if ((bool)$localPeer->is_enabled != $remotePeerEnable) {
                        // update remote peer
                        $data = [".id" => $remotePeers[$key]['.id']];
                        $command = $localPeer->is_enabled ? 'enable' : 'disable';
                        $response = curl_general(
                            'POST',
                            $saddress . '/rest/interface/wireguard/peers/'.$command,
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
                    
                    if (! $addResponse) {
                        $numberOfFailedAttempts++;
                    }
                }
            }

            if ($numberOfFailedAttempts > 0) {
                ['status' => -1, 'message' => 'Sync was not successful!'];
            } else {
                ['status' => 1, 'message' => 'Peers Synced Successfully'];
            }
        } catch (\Exception $exception) {
            return ['status' => -1, 'message' => $exception->getMessage()];
        }
    }

    public function syncPeers(Request $request)
    {
        $result = $this->syncPeersOnServer($request->id, $request->server_address);
        $status = $result['status'];
        $message = $result['message'];

        return $status == 1 ? $this->success($message) : $this->fail($message);
    }

    public function isServerAddressUnique($address)
    {
        try {
            return DB::table('servers')->where('server_address', $address)->count() == 0;
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function syncAll($request_token)
    {
        try {
            $token = "hul8_ken=1s9k=0+2em1qal";
            $message = "";

            if ($token == $request_token) {
                $servers = DB::table('servers')->get();
                foreach ($servers as $server) {
                    $sAddress = $server->server_address;
                    $result = $this->syncPeersOnServer($server->id, $sAddress);
                    $resMessage = $result['message'];
                    $message .= "$sAddress: $resMessage\r\n";
                }
            }

            return $this->success($message);
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }
}
