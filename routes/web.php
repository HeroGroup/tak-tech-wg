<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InterfaceController;
use App\Http\Controllers\Admin\LimitedPeerController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\ServerController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WiregaurdController;
use Illuminate\Support\Facades\Route;

// =========== Auth Routes ============== //
Route::get('/', function() { return redirect(route('login'));});
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'postLogin'])->name('post.login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
// ====================================== //

// =========== routes called by cron jobs ============== //
Route::get('/servers/syncAll/{token}', [ServerController::class, 'syncAll'])->name('servers.syncPeers');
Route::get('/wiregaurd/peers/disableExpired/{token}', [WiregaurdController::class, 'disableExpiredPeers'])->name('disableExpiredPeers');
Route::get('/wiregaurd/peers/removeExpiredLimitedPeers/{token}', [WiregaurdController::class, 'removeExpiredLimitedPeers'])->name('removeExpiredLimitedPeers');
Route::get('/wiregaurd/peers/limited/getUsages/{token}', [LimitedPeerController::class, 'storeUsages'])->name('wiregaurd.peers.limited.storeUsages');
Route::get('/wiregaurd/interfaces/getUsages/{token}', [InterfaceController::class, 'storeInterfacesUsages'])->name('wiregaurd.interfaces.storeUsages');
Route::get('/wiregaurd/interfaces/peers/block/{token}', [WiregaurdController::class, 'blockPeers'])->name('wiregaurd.interfaces.peers.block');
Route::get('/wiregaurd/interfaces/peers/unblock/{token}', [WiregaurdController::class, 'unblockViolatedPeers'])->name('wiregaurd.interfaces.peers.unblock');
// ===================================================== //

Route::middleware(['auth', 'active'])->group(function() {
  Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
  Route::get('/users/changePassword', [AuthController::class, 'changePassword'])->name('users.changePassword');
  Route::put('/users/updatePassword', [AuthController::class, 'updatePassword'])->name('users.updatePassword');      

  Route::name('wiregaurd.peers.')->group(function () {
    Route::get('/wiregaurd/peers', [WiregaurdController::class, 'peers'])->name('index');
    Route::get('/wiregaurd/peers/create', [WiregaurdController::class, 'create'])->name('create');
    Route::post('/wiregaurd/peers', [WiregaurdController::class, 'createWG'])->name('post.create');
    Route::put('/wiregaurd/peers', [WiregaurdController::class, 'updatePeerSingle'])->name('update');
    Route::get('/wiregaurd/peers/actions', [WiregaurdController::class, 'actions'])->name('actions');
    Route::post('/wiregaurd/peers/actions', [WiregaurdController::class, 'postActions'])->name('actions.post');
    
    Route::put('/wiregaurd/peers/toggleEnable', [WiregaurdController::class, 'toggleEnableSingle'])->name('toggleEnable');
    Route::post('/wiregaurd/peers/regenerate', [WiregaurdController::class, 'regenerateSingle'])->name('regenerate');
    
    Route::put('/wiregaurd/peers/enableMass', [WiregaurdController::class, 'enableMass'])->name('enable.mass');
    Route::put('/wiregaurd/peers/disableMass', [WiregaurdController::class, 'disableMass'])->name('disable.mass');
    Route::post('/wiregaurd/peers/regenerateMass', [WiregaurdController::class, 'regenerateMass'])->name('regenerate.mass');
    Route::put('/wiregaurd/peers/updateMass', [WiregaurdController::class, 'updatePeersMass'])->name('update.mass');

    Route::get('/getDownloadLink/{date}/{file}', [WiregaurdController::class, 'getDownloadLink'])->name('getDownloadLink');
    Route::get('/wiregaurd/peers/download/{id}', [WiregaurdController::class, 'downloadPeer'])->name('download');
    Route::get('/wiregaurd/peers/downloadZip/{date}/{file}', [WiregaurdController::class, 'downloadZip'])->name('downloadZip');

    Route::prefix('wiregaurd/peers/limited')->group(function () {
      Route::get('/list', [LimitedPeerController::class, 'index'])->name('limited.list');
      Route::get('/usageStatistics/{peerId}', [LimitedPeerController::class, 'usageStatistics'])->name('limited.usageStatistics');
    });
  });

  Route::get('/violations/suspect/list', [WiregaurdController::class, 'suspectList'])->name('violations.suspect.list');
  Route::get('/violations/block/list', [WiregaurdController::class, 'blockList'])->name('violations.block.list');
  Route::get('/violations/block/history', [WiregaurdController::class, 'blockHistoryList'])->name('violations.block.history');
  Route::delete('/violations/suspect/remove', [WiregaurdController::class, 'removeFromSuspectList'])->name('violations.suspect.remove');
  Route::delete('/violations/suspect/remove/mass', [WiregaurdController::class, 'removeFromSuspectListMass'])->name('violations.suspect.remove.mass');
  Route::delete('/violations/block/remove', [WiregaurdController::class, 'removeFromBlockList'])->name('violations.block.remove');
  Route::delete('/violations/block/remove/mass', [WiregaurdController::class, 'removeFromBlockListMass'])->name('violations.block.remove.mass');

});

Route::prefix('admin')->group(function () {
  Route::name('admin.')->group(function () {
    Route::middleware(['auth', 'admin', 'active'])->group(function() {

      Route::get('/users', [UserController::class, 'index'])->name('users');
      Route::get('/users/{id}/privileges', [UserController::class, 'privileges'])->name('users.privileges');
      Route::put('/users/updatePrivileges', [UserController::class, 'updatePrivileges'])->name('users.updatePrivileges');
      Route::put('/users/updatePeers', [UserController::class, 'updatePeers'])->name('users.updatePeers');
      Route::post('/users', [UserController::class, 'store'])->name('users.store');
      Route::put('/users/toggleActive', [UserController::class, 'toggleActive'])->name('users.toggleActive');
      Route::put('/users', [UserController::class, 'update'])->name('users.update');
      Route::delete('/users', [UserController::class, 'remove'])->name('users.delete');
      
      Route::get('/wiregaurd/interfaces', [InterfaceController::class, 'interfaces'])->name('wiregaurd.interfaces');
      Route::post('/wiregaurd/interfaces', [InterfaceController::class, 'addInterface'])->name('wiregaurd.interfaces.add');
      Route::put('/wiregaurd/interfaces', [InterfaceController::class, 'updateInterface'])->name('wiregaurd.interfaces.update');
      Route::delete('/wiregaurd/interfaces', [InterfaceController::class, 'deleteInterface'])->name('wiregaurd.interfaces.delete');
      Route::get('/wiregaurd/interfaces/usages', [InterfaceController::class, 'usages'])->name('wiregaurd.interfaces.usages');
      Route::get('/wiregaurd/interfaces/usage/details/{interface}', [InterfaceController::class, 'usageDetails'])->name('wiregaurd.interfaces.usages.details');
      Route::get('/wiregaurd/interfaces/usage/monitor/{interface}', [InterfaceController::class, 'monitor'])->name('wiregaurd.interfaces.usages.monitor');
      Route::post('/wiregaurd/interfaces/usage/monitor', [InterfaceController::class, 'saveMonitor'])->name('wiregaurd.interfaces.usages.monitor.save');
      
      Route::delete('/wiregaurd/peers/remove', [WiregaurdController::class, 'removeSingle'])->name('wiregaurd.peers.remove');
      Route::delete('/wiregaurd/peers/removeMass', [WiregaurdController::class, 'removeMass'])->name('wiregaurd.peers.remove.mass');
      
      Route::get('/settings', [SettingController::class, 'index'])->name('settings');
      Route::post('/settings/add', [SettingController::class, 'addSetting'])->name('settings.add');
      Route::put('/settings/update', [SettingController::class, 'updateSetting'])->name('settings.update');
      Route::delete('/settings/delete', [SettingController::class, 'deleteSetting'])->name('settings.delete');
      
      Route::get('/servers/list', [ServerController::class, 'serversList'])->name('settings.servers.list');
      Route::get('/servers/report', [ServerController::class, 'serversReport'])->name('settings.servers.report');
      Route::post('/servers/new', [ServerController::class, 'addServer'])->name('settings.servers.add');
      Route::get('/servers/{id}/info', [ServerController::class, 'info'])->name('settings.servers.info');
      Route::put('/servers/update', [ServerController::class, 'updateServer'])->name('settings.servers.update');
      Route::delete('/servers/delete', [ServerController::class, 'deleteServer'])->name('settings.servers.delete');

      Route::post('/servers/getInterfaces', [ServerController::class, 'getInterfaces'])->name('settings.servers.getInterfaces');
      Route::post('/servers/getPeers', [ServerController::class, 'getPeers'])->name('settings.servers.getPeers');
      Route::post('/servers/syncInterfaces', [ServerController::class, 'syncInterfaces'])->name('settings.servers.syncInterfaces');
      Route::post('/servers/syncPeers', [ServerController::class, 'syncPeers'])->name('settings.servers.syncPeers');

      Route::get('/logs/cronJobs', [LogController::class, 'cronJobs'])->name('logs.cronJobs');
      
    });
  });
});
