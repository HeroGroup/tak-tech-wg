<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Privileges;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// This class handles user related actions
class UserController extends Controller
{
    // returns list of users with access
    public function index()
    {
        try {
            $users = User::all(); // DB::table('users')->get();
            $interfaces = DB::table('interfaces')->pluck('name', 'id')->toArray();
            $userTypes = UserType::forSelect();
            $privileges = array_column(Privileges::cases(), 'name', 'value');

            return view('admin.users.index', compact('users', 'interfaces', 'userTypes', 'privileges'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // saves new user
    public function store(Request $request)
    {
        try {
            $now = date('Y-m-d H:i:s');
            $userId = User::insertGetId([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => $request->user_type,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            $userInterfaces = $request->interfaces;
            if ($userInterfaces && count($userInterfaces) > 0) {
                $this->createUserInterfaces($userInterfaces, $userId);
            }

            $userPrivileges = $request->privileges;
            if ($userPrivileges && count($userPrivileges) > 0) {
                $this->createUserPrivileges($userPrivileges, $userId);
            }

            return back()->with('message', 'User was created successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // update existing user
    public function update(Request $request)
    {
        try {
            $userId = $request->id;
            $update = [
                'name' => $request->name,
                'email' => $request->email,
                'user_type' => $request->user_type,
            ];

            if ($request->password) {
                $update['password'] = Hash::make($request->password);
            }

            User::find($userId)->update($update);
            
            DB::table('user_interfaces')->where('user_id', $userId)->delete();
            DB::table('user_privileges')->where('user_id', $userId)->delete();

            $userInterfaces = $request->user_interfaces;
            if ($userInterfaces && count($userInterfaces) > 0) {
                $this->createUserInterfaces($userInterfaces, $userId);
            }

            $userPrivileges = $request->privileges;
            if ($userPrivileges && count($userPrivileges) > 0) {
                $this->createUserPrivileges($userPrivileges, $userId);
            }

            return back()->with('message', 'User updated successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // connect user to accessed interfaces
    protected function createUserInterfaces($userInterfaces, $userId)
    {
        foreach($userInterfaces as $key => $value) {
            DB::table('user_interfaces')->insert([
                'user_id' => $userId,
                'interface_id' => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    // connect user to accessed privileges
    protected function createUserPrivileges($userPrivileges, $userId)
    {
        foreach($userPrivileges as $key => $value) {
            DB::table('user_privileges')->insert([
                'user_id' => $userId,
                'action' => $value,
            ]);
        }
    }

    // makes user active or inactive
    public function toggleActive(Request $request)
    {
        try {
            DB::table('users')->where('id', $request->id)->update(['is_active' => $request->status]);

            return $this->success('User updated!');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    // soft deletes user
    public function remove(Request $request)
    {
        try {
            User::where('id', $request->id)->delete();
    
            return $this->success('User deleted successfully.');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    public function privileges($id)
    {
        $user = User::find($id);
        $privileges = array_column(Privileges::cases(), 'name', 'value');
        $user_privileges = DB::table('user_privileges')->where('user_id', $id)->pluck('action')->toArray();
        
        $peers = DB::table('peers')
            ->join('user_interfaces', 'user_interfaces.interface_id', '=', 'peers.interface_id')
            ->where('user_interfaces.user_id', $id)
            ->join('interfaces', 'interfaces.id', '=', 'peers.interface_id')
            ->select(['peers.*', 'interfaces.name'])
            ->get();

        $user_peers = DB::table('user_peers')->where('user_id', $id)->pluck('peer_id')->toArray();

        return view('admin.users.privileges', compact('user', 'privileges', 'user_privileges', 'peers', 'user_peers'));
    }

    public function updatePrivileges(Request $request)
    {
        try {
            DB::table('user_privileges')->where('user_id', $request->user_id)->delete();
            $privileges = $request->privileges;
            $now = date('Y-m-d H:i:s');

            foreach($privileges as $privilege) {
                DB::table('user_privileges')->insert([
                    'user_id' => $request->user_id,
                    'action' => $privilege,
                    'created_at' => $now,
                ]);
            }

            return back()->with('message', 'User privileges updated successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    public function updatePeers(Request $request)
    {
        try {
            $userId = $request->user_id;
            DB::table('user_peers')->where('user_id', $userId)->delete();
            $peers = $request->user_peers;
            $now = date('Y-m-d H:i:s');
            
            foreach($peers as $peer) {
                DB::table('user_peers')->insert([
                    'user_id' => $userId,
                    'peer_id' => $peer,
                    'created_at' => $now,
                ]);
            }

            $interfaces = DB::table('peers')
                ->whereIn('id', $request->user_peers)
                ->pluck('interface_id')
                ->toArray();
            $interfaces = array_unique($interfaces);
            DB::table('user_interfaces')
                ->where('user_id', $userId)
                ->whereIn('interface_id', $interfaces)
                ->update([
                    'privilege' => 'partial'
                ]);

            return back()->with('message', 'User peers updated successfully.')->with('type', 'success');
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }
}
