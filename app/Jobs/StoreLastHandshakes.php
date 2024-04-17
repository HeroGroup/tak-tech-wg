<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

class StoreLastHandshakes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public $sId, public $sAddress, public $unlimitedInterfaces ){ }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sId = $this->sId;
        $sAddress = $this->sAddress;
        $unlimitedInterfaces = $this->unlimitedInterfaces;

        $remotePeers = curl_general('GET', "$sAddress/rest/interface/wireguard/peers", '', false, 30);
        if (is_array($remotePeers)) {
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
