<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// This class is responsible for management of the settings of the panel
class SettingController extends Controller
{
    // returns list of software settings
    public function index()
    {
        try {
            $settings = DB::table('settings')->get(); 

            return view('admin.settings', compact('settings'));
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
    }

    // add new setting
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

    // update existing setting
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

    // delete setting
    public function deleteSetting(Request $request)
    {
        try {
            DB::table('settings')->where('id', $request->id)->delete();
            return $this->success('Setting deleted successfully.');
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }
}
