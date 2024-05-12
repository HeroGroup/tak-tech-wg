<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

class StoreUsagesAndLastHandshakes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $sId, 
        public $sAddress,
        public $now,
    ){ }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sId = $this->sId;
        $sAddress = $this->sAddress;
        $now = $this->now;

        $limitedInterfaces = DB::table('interfaces')
            ->where('iType', 'limited')
            ->pluck('name', 'id')
            ->toArray();

        $unlimitedInterfaces = DB::table('interfaces')
            ->where('iType', 'unlimited')
            ->pluck('name', 'id')
            ->toArray();
            
        $peersToMonitor = DB::table('peers')
            ->where('monitor', 1)
            ->join('server_peers', 'server_peers.peer_id', '=', 'peers.id')
            ->where('server_id', $sId)
            ->pluck('server_peer_id')
            ->toArray();
    
        $remotePeers = curl_general('GET', "$sAddress/rest/interface/wireguard/peers", '', false, 30);

        DB::table('server_peers')
            ->where('server_id', $sId)
            ->update([
                'last_handshake' => null,
                'last_handshake_updated_at' => $now
            ]);
        
        if (is_array($remotePeers)) {
            // filter limited interfaces
            $peersToStore = array_filter($remotePeers, function($elm) use ($limitedInterfaces, $peersToMonitor) {
                return in_array($elm['interface'], $limitedInterfaces) || in_array($elm['.id'], $peersToMonitor);
            });

            foreach ($peersToStore as $peer) {
                storeUsage($sId, $peer[".id"], $peer["tx"], $peer["rx"], $peer["last-handshake"] ?? null, $now);
            }

            // filter unlimited interfaces
            $unlimitedPeers = array_filter($remotePeers, function($elm) use ($unlimitedInterfaces) {
                return in_array($elm['interface'], $unlimitedInterfaces);
            });
                        
            // store last-handshake for all unlimited peers
            foreach ($unlimitedPeers as $unlimitedPeer) {
                DB::table('server_peers')
                    ->where('server_id', $sId)
                    ->where('server_peer_id', $unlimitedPeer[".id"])
                    ->update([
                        'last_handshake' => $unlimitedPeer["last-handshake"] ?? null,
                    ]);
            }
        }
    }
}
