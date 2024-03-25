<?php

namespace App\Http\Controllers\Admin;

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

            return view('admin.users.index', compact('users', 'interfaces', 'userTypes'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // saves new user
    public function store(Request $request)
    {
        try {
            $userId = DB::table('users')->insertGetId([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => $request->user_type,
            ]);

            $userInterfaces = $request->interfaces;
            if ($userInterfaces && count($userInterfaces) > 0) {
                $this->createUserInterfaces($userInterfaces, $userId);
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

            DB::table('users')->where('id', $userId)->update($update);

            $userInterfaces = $request->user_interfaces;
            if ($userInterfaces && count($userInterfaces) > 0) {
                DB::table('user_interfaces')->where('user_id', $userId)->delete();

                $this->createUserInterfaces($userInterfaces, $userId);
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
}
