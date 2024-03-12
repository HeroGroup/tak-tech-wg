<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InterfaceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WiregaurdController;
use Illuminate\Support\Facades\Route;

Route::get('/', function() { return redirect(route('login'));});
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'postLogin'])->name('post.login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'active'])->group(function() {
  Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
  Route::get('/users/changePassword', [AuthController::class, 'changePassword'])->name('users.changePassword');
  Route::put('/users/updatePassword', [AuthController::class, 'updatePassword'])->name('users.updatePassword');      

  Route::name('wiregaurd.peers.')->group(function () {
    Route::get('/wiregaurd/peers', [WiregaurdController::class, 'peers'])->name('index');
    Route::get('/wiregaurd/peers/create', [WiregaurdController::class, 'create'])->name('create');
    Route::post('/wiregaurd/peers', [WiregaurdController::class, 'createWG'])->name('post.create');
    Route::put('/wiregaurd/peers', [WiregaurdController::class, 'updatePeer'])->name('update');
    
    Route::put('/wiregaurd/peers/toggleEnable', [WiregaurdController::class, 'toggleEnableSingle'])->name('toggleEnable');
    Route::post('/wiregaurd/peers/regenerate', [WiregaurdController::class, 'regenerateSingle'])->name('regenerate');
    
    Route::put('/wiregaurd/peers/enableMass', [WiregaurdController::class, 'enableMass'])->name('enable.mass');
    Route::put('/wiregaurd/peers/disableMass', [WiregaurdController::class, 'disableMass'])->name('disable.mass');
    Route::post('/wiregaurd/peers/regenerateMass', [WiregaurdController::class, 'regenerateMass'])->name('regenerate.mass');
    Route::put('/wiregaurd/peers/updateMass', [WiregaurdController::class, 'updatePeersMass'])->name('update.mass');
  });
});

Route::prefix('admin')->group(function () {
  Route::name('admin.')->group(function () {
    Route::middleware(['auth', 'admin', 'active'])->group(function() {

      Route::get('/users', [UserController::class, 'index'])->name('users');
      Route::post('/users', [UserController::class, 'store'])->name('users.store');
      Route::put('/users/toggleActive', [UserController::class, 'toggleActive'])->name('users.toggleActive');
      Route::put('/users', [UserController::class, 'update'])->name('users.update');
      
      Route::get('/wiregaurd/interfaces', [InterfaceController::class, 'interfaces'])->name('wiregaurd.interfaces');
      Route::post('/wiregaurd/interfaces', [InterfaceController::class, 'addInterface'])->name('wiregaurd.interfaces.add');
      Route::put('/wiregaurd/interfaces', [InterfaceController::class, 'updateInterface'])->name('wiregaurd.interfaces.update');
      Route::delete('/wiregaurd/interfaces', [InterfaceController::class, 'deleteInterface'])->name('wiregaurd.interfaces.delete');
      
      Route::delete('/wiregaurd/peers/remove', [WiregaurdController::class, 'removeSingle'])->name('wiregaurd.peers.remove');
      Route::delete('/wiregaurd/peers/removeMass', [WiregaurdController::class, 'removeMass'])->name('wiregaurd.peers.remove.mass');
  
      
      Route::get('/settings', [SettingController::class, 'index'])->name('settings');
      Route::post('/settings/add', [SettingController::class, 'addSetting'])->name('settings.add');
      Route::put('/settings/update', [SettingController::class, 'updateSetting'])->name('settings.update');
      Route::delete('/settings/delete', [SettingController::class, 'deleteSetting'])->name('settings.delete');
      
      Route::get('/servers/list', [SettingController::class, 'serversList'])->name('settings.servers.list');
      Route::post('/servers/new', [SettingController::class, 'addServer'])->name('settings.servers.add');
      Route::get('/servers/{id}/info', [SettingController::class, 'info'])->name('settings.servers.info');
      Route::put('/servers/update', [SettingController::class, 'updateServer'])->name('settings.servers.update');
      Route::delete('/servers/delete', [SettingController::class, 'deleteServer'])->name('settings.servers.delete');

      Route::post('/servers/getInterfaces', [SettingController::class, 'getInterfaces'])->name('settings.servers.getInterfaces');
      Route::post('/servers/getPeers', [SettingController::class, 'getPeers'])->name('settings.servers.getPeers');
      Route::post('/servers/syncInterfaces', [SettingController::class, 'syncInterfaces'])->name('settings.servers.syncInterfaces');
      Route::post('/servers/syncPeers', [SettingController::class, 'syncPeers'])->name('settings.servers.syncPeers');
    });
  });
});
