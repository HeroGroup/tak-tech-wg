<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

// This class handles actions related to interfaces
class InterfaceController extends Controller
{
    // return list of interfaces
    public function interfaces()
    {
        $interfaces = DB::table('interfaces')->get();
        return view('admin.interfaces.list', compact('interfaces'));
    }

    // add an interface in local db and all remote routers
    // it also add ip address on remote router as well
    public function addInterface(Request $request)
    {
        try {
            $servers = DB::table('servers')
                ->where('router_os_version', '>=', '7.12.1')
                ->where('router_os_version', 'not like', '%beta%')
                ->get();
            
            $keys = createKeys();
            $privateKey = $keys['private_key'];
            $publicKey = $keys['public_key'];
            $now = date('Y-m-d H:i:s');

            // insert on local DB
            $newInterfaceId = DB::table('interfaces')->insertGetId([
                'name' => $request->name,
                'default_endpoint_address' => $request->default_endpoint_address,
                'dns' => $request->dns,
                'ip_range' => $request->ip_range,
                'mtu' => $request->mtu,
                'listen_port' => $request->listen_port,
                'iType' => $request->iType,
                'allowed_traffic_GB' => $request->allowed_traffic_GB,
                'public_key' => $publicKey,
                'private_key' => $privateKey,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            $message = `Local: OK!\r\n`;

            // give access to all superadmins
            $superAdmins = DB::table('users')->where('user_type', UserType::SUPERADMIN->value)->get();
            foreach ($superAdmins as $superAdmin) {
                DB::table('user_interfaces')->insert([
                    'user_id' => $superAdmin->id,
                    'interface_id' => $newInterfaceId,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            }

            foreach ($servers as $server) {
                $sAddress = $server->server_address;
                $sId = $server->id;

                // add remote interface
                $res = curl_general('POST', 
                    $sAddress . '/rest/interface/wireguard/add',
                    json_encode([
                        'name' => $request->name,
                        'private-key' => $privateKey,
                        'mtu' => $request->mtu,
                        'listen-port' => $request->listen_port,
                        'comment' => $request->allowed_traffic_GB
                    ]),
                    true
                );

                if ($res && is_array($res) && count($res) > 0 && isset($res['ret'])) {
                    $message .= `$sAddress: OK!\r\n`;
                    $newRemoteInterface = $res['ret'];
                    // add remote ip address
                    curl_general('POST', 
                        $sAddress . '/rest/ip/address/add',
                        json_encode([
                            'address' => $request->ip_range.'1/24',
                            'interface' => $request->name,
                        ]),
                        true
                    );
                    
                    DB::table('server_interfaces')->insert([
                        'server_id' => $sId,
                        'interface_id' => $newInterfaceId,
                        'server_interface_id' => $newRemoteInterface
                    ]);
                } else {
                    $message .= `$sAddress: failed! \r\n`;
                }
            }
            return back()->with('message', $message)->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // update interface attributes
    public function updateInterface(Request $request)
    {
        try {
            DB::table('interfaces')->where('id', $request->id)->update([
                'default_endpoint_address' => $request->default_endpoint_address,
                'dns' => $request->dns,
                'ip_range' => $request->ip_range,
                'mtu' => $request->mtu,
                'listen_port' => $request->listen_port,
                'iType' => $request->iType,
                'allowed_traffic_GB' => $request->allowed_traffic_GB,
            ]);

            // TODO: update on remote (mtu, listen_port)
            // TODO: update ip address on remote

            return back()->with('message', 'Interface updated successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // deletes an interface both on local and remote
    public function deleteInterface(Request $request)
    {
        try {
            // delete interface locally
            DB::table('interfaces')->delete($request->id);
            
            $server_interfaces = DB::table('server_interfaces')->where('interface_id', $request->id)->get();
            // delete interfaces on remote servers
            foreach ($server_interfaces as $server_interface) {
                $server = DB::table('servers')->find($server_interface->server_id);
                $data = [".id" => $server_interface->server_interface_id];
                $response = curl_general(
                    'POST',
                    $server->server_address . '/rest/interface/wireguard/remove',
                    json_encode($data),
                    true
                );
                // TODO: delete ip address as well
            }
            DB::table('server_interfaces')->where('interface_id', $request->id)->delete();
            DB::table('user_interfaces')->where('interface_id', $request->id)->delete();

            return $this->success('Interface removed successfully.');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function storeInterfacesUsages($request_token)
    {
        try {
            if ($request_token == env('STORE_INTERFACES_USAGES_TOKEN')) {
                $message = [];
                $now = date('Y-m-d H:i:s', time());
                $localInterfaces = DB::table('interfaces')->pluck('name', 'id')->toArray();

                $servers = DB::table('servers')->get();
                foreach($servers as $server) {
                    $sId = $server->id;
                    $sAddress = $server->server_address;
                    $remoteInterfaces = curl_general('GET', $sAddress . '/rest/interface', '', false);

                    if (is_array($remoteInterfaces) && count($remoteInterfaces) > 0) {
                        $validInterfaces = array_filter($remoteInterfaces, function($elm) use ($localInterfaces) {
                            return in_array($elm['name'], $localInterfaces);
                        });
                        $inserted = 0;
                        foreach ($validInterfaces as $validInterface) {
                            $latest = DB::table('server_interface_usages')
                                ->where('server_id', $sId)
                                ->where('server_interface_id', $validInterface[".id"])
                                ->orderBy('id', 'desc')
                                ->first();
                            
                            $latest_tx = $latest ? $latest->tx : 0;
                            $latest_rx = $latest ? $latest->rx : 0;
                            
                            $remoteInterfaceTX = $validInterface["tx-byte"];
                            $remoteInterfaceRX = $validInterface["rx-byte"];

                            if ($remoteInterfaceTX >= $latest_tx) {
                                $new_tx = $remoteInterfaceTX;
                            } else { // ($remoteInterfaceTX < $latest_tx) 
                                $new_tx = $latest_tx + $remoteInterfaceTX;
                            }

                            if ($latest_rx > $remoteInterfaceRX) {
                                $new_rx = $latest_rx + $remoteInterfaceRX;
                            } else if ($latest_rx <= $remoteInterfaceRX) {
                                $new_rx = $remoteInterfaceRX;
                            }
                            
                            DB::table('server_interface_usages')->insert([
                                'server_id' => $sId,
                                'server_interface_id' => $validInterface[".id"],
                                'tx' => $new_tx,
                                'rx' => $new_rx,
                                'created_at' => $now
                            ]);
                            $inserted++;
                        }
                        $cnt = count($validInterfaces);
                        array_push($message, "$sAddress: $cnt fetch successfull! $inserted inserted.");
                    } else {
                        array_push($message, "$sAddress: $remoteInterfaces");
                    }
                }
                $resultMessage = implode("\r\n", $message);
                saveCronResult('store-interfaces-usages', $resultMessage);
                return $resultMessage;
            }
            $resultMessage = 'token mismatch!';
            saveCronResult('store-interfaces-usages', $resultMessage);
            return $resultMessage;
        } catch(\Exception $exception) {
            $resultMessage = $exception->getLine() . ': ' . $exception->getMessage();
            saveCronResult('store-interfaces-usages', $resultMessage);
            return $resultMessage;
        }
        
    }

    public function usages()
    {
        $interfaces = DB::table('interfaces')->select(['id', 'name'])->get();
        $servers = DB::table('servers')->get();
        foreach($interfaces as $interface) {
            for($i = 0; $i < 6; $i++) {
                $sum_tx = 0;
                $sum_rx = 0;
                foreach($servers as $server) {
                    // find corresponding server_interface_id
                    $server_interface = DB::table('server_interfaces')
                        ->where('server_id', $server->id)
                        ->where('interface_id', $interface->id)
                        ->first();

                    if($server_interface) {
                        $server_interface_usage = DB::table('server_interface_usages')
                            ->where('server_id', $server->id)
                            ->where('server_interface_id', $server_interface->server_interface_id)
                            ->orderBy('id', 'desc')
                            ->skip($i)
                            ->first();

                        if ($server_interface_usage) {
                            $sum_tx += round(($server_interface_usage->tx / 1073741824), 2);
                            $sum_rx += round(($server_interface_usage->rx / 1073741824), 2);
                        }
                    }
                }

                $interface->usages[$i] = $sum_tx + $sum_rx;
            }
        }
        $interfaces = $interfaces->toArray();

        $interfaces_json = json_encode($interfaces);

        return view('admin.interfaces.usages', compact('interfaces', 'interfaces_json'));
    }
}
