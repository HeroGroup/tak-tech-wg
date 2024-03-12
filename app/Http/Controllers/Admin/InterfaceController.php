<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

class InterfaceController extends Controller
{
    public function interfaces()
    {
        $interfaces = DB::table('interfaces')->get();
        return view('admin.WGInterfaces', compact('interfaces'));
    }

    public function addInterface(Request $request)
    {
        try {
            $message = '';
            $servers = DB::table('servers')
                ->where('router_os_version', '>=', '7.12.1')
                ->where('router_os_version', 'not like', '%beta%')
                ->get();
            // insert on first remote servers
            $server = $servers[0];
            $sAddress = $server->server_address;
            $sId = $server->id;

            curl_general('POST', 
                $sAddress . '/rest/interface/wireguard/add',
                json_encode([
                    'name' => $request->name,
                    'mtu' => $request->mtu,
                    'listen-port' => $request->listen_port
                ]),
                true);

            // fetch public and private key
            $newRemoteInterface = curl_general('GET', $sAddress . '/rest/interface/wireguard?name='.$request->name);

            // ip/address/add address=10.20.130.1/24 interface=WG-OM
            curl_general('POST', 
                $sAddress . '/rest/ip/address/add',
                json_encode([
                    'address' => $request->ip_range.'1/24',
                    'interface' => $request->name,
                ]),
                true);

            if (is_array($newRemoteInterface) && count($newRemoteInterface) > 0) {
                // $newRemoteInterface = $addInterfaceResponse['ret'];
                $message .= $sAddress . ': OK!\n\r';
                $public_key = $newRemoteInterface[0]['public-key'];
                $private_key = $newRemoteInterface[0]['private-key'];
                // insert on local DB
                $newInterfaceId = DB::table('interfaces')->insertGetId([
                    'name' => $request->name,
                    'default_endpoint_address' => $request->default_endpoint_address,
                    'dns' => $request->dns,
                    'ip_range' => $request->ip_range,
                    'mtu' => $request->mtu,
                    'listen_port' => $request->listen_port,
                    'public_key' => $public_key,
                    'private_key' => $private_key
                ]);

                // give access to all superadmins
                $superAdmins = DB::table('users')->where('user_type', \App\Enums\UserType::SUPERADMIN->value)->get();
                foreach ($superAdmins as $superAdmin) {
                    DB::table('user_interfaces')->insert([
                        'user_id' => $superAdmin->id,
                        'interface_id' => $newInterfaceId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }

                DB::table('server_interfaces')->insert([
                    'server_id' => $sId,
                    'interface_id' => $newInterfaceId,
                    'server_interface_id' => $newRemoteInterface[0]['.id']
                ]);

                // insert on all remote servers
                for($i=1; $i<count($servers); $i++) {
                    $server= $servers[$i];
                    $sAddress = $server->server_address;
                    $sId = $server->id;

                    curl_general('POST', 
                        $sAddress . '/rest/interface/wireguard/add',
                        json_encode([
                            'name' => $request->name,
                            // 'public-key' => $public_key,
                            'private-key' => $private_key,
                            'mtu' => $request->mtu,
                            'listen-port' => $request->listen_port
                        ]),
                        true);

                    curl_general('POST', 
                        $sAddress . '/rest/ip/address/add',
                        json_encode([
                            'address' => $request->ip_range.'1/24',
                            'interface' => $request->name,
                        ]),
                        true);

                    $newRemoteInterface = curl_general('GET', $sAddress . '/rest/interface/wireguard?name='.$request->name);
                    if (count($newRemoteInterface) > 0) {
                        DB::table('server_interfaces')->insert([
                            'server_id' => $sId,
                            'interface_id' => $newInterfaceId,
                            'server_interface_id' => $newRemoteInterface[0]['.id']
                        ]);
                        $message .= $sAddress . ': OK!\n\r';
                    } else {
                        $message .= $sAddress . ' did not respond.\n\r';
                    }
                }
                return back()->with('message', $message)->with('type', 'success');
            } else {
                return back()->with('message', 'Unable to create interface on remote.')->with('type', 'danger');
            }
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage)->with('type', 'danger');
        }
    }

    public function updateInterface(Request $request)
    {
        try {
            DB::table('interfaces')->where('id', $request->id)->update([
                'default_endpoint_address' => $request->default_endpoint_address,
                'dns' => $request->dns,
                'ip_range' => $request->ip_range,
                'mtu' => $request->mtu,
                'listen_port' => $request->listen_port,
            ]);

            return back()->with('message', 'Interface updated successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

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
            $server_interfaces = DB::table('server_interfaces')->where('interface_id', $request->id)->delete();

            return $this->success('Interface removed successfully.');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }
}
