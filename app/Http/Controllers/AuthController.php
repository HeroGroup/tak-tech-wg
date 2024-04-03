<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

// This class handles user authentication and profile
class AuthController extends Controller
{
    // returns view for user to login
    public function login()
    {
        if (auth()->user()) {
            return redirect(route('dashboard'));
        }

        return view('auth.login');
    }

    // login attempt
    public function postLogin(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            if (Auth::attempt([...$credentials, 'is_active' => 1], $request->remember == "on" ? true : false)) {
                    
                // $this->saveLoginSession($request->ip(), $request->userAgent());

                $request->session()->regenerate();
                
                return redirect()->intended(RouteServiceProvider::HOME);
            }

            return back()->withErrors([
                'email' => 'Invalid Email or Password.',
            ])->onlyInput('email');
        } catch (\Exception $exception) {
            return back()->withErrors([
                'email' => $exception->getMessage(),
            ])->onlyInput('email');
        }
    }

    // logs out user
    public function logout(Request $request)
    {
        Auth::logout();
 
        $request->session()->invalidate();
     
        $request->session()->regenerateToken();
     
        return redirect(RouteServiceProvider::HOME);
    }

    // stores every login attempt with user ip and device
    public function saveLoginSession($request_ip, $requset_user_agent) 
    {
        // save login session
        $first_pos = strpos($requset_user_agent, '(');
        $second_pos = strpos($requset_user_agent, ';');
        $device = substr($requset_user_agent, $first_pos + 1, $second_pos - $first_pos - 1);
        
        LoginHistory::create([
            'user_id' => Auth::user()->id,
            'ip_address' => $request_ip,
            'device' => $device,
        ]);
    }

    // shows a view for user to change password
    public function changePassword() {
        return view('admin.changePassword');
    }
    
    // updates users password
    public function updatePassword(Request $request) {
        try {
            $user = User::find(auth()->user()->id);
            
            if (Hash::check($request->current_password, $user->password)) {
                if ($request->password == $request->password_confirmation) {
                    $user->password = Hash::make($request->password);
                    $user->save();

                    return back()->with('message', 'password updated successfully.')->with('type', 'success');
                } else {
                    return back()->with('message', 'password and confirm password does not match.')->with('type', 'danger');
                }
            } else {
                return back()->with('message', 'current password is incorrect.')->with('type', 'danger');
            }
        } catch (\Exception $exception) {
            return back()->with('message', $exception->getMessage())->with('type', 'danger');
        }
        
    }
}
