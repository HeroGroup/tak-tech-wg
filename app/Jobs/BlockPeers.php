<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

class BlockPeers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(){ }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $should_block = DB::table('settings')->where('setting_key', 'IS_BLOCK_UNBLOCK_ACTIVE')->first()->setting_value;
        $now = date('Y-m-d H:i:s', time());
        $suspected = [];
        $blocked = [];
        $message = '';
        // select only unlimited peers
        $peers = DB::table('peers')
            ->where('is_enabled', 1)
            ->join('interfaces', 'interfaces.id', '=', 'peers.interface_id')
            ->where('interfaces.iType', 'unlimited')
            ->where('interfaces.exclude_from_block', 0)
            ->select(['peers.*'])
            ->get();

        $handshake_period_seconds = DB::table('settings')->where('setting_key', 'HANDSHAKE_PERIOD_SECONDS')->first()->setting_value;
        $max_number_of_violations = DB::table('settings')->where('setting_key', 'MAX_NUMBER_OF_VIOLATIONS')->first()->setting_value;
        foreach ($peers as $peer) {
            $peerId = $peer->id;
            $server_peers = DB::table('server_peers')->where('peer_id', $peerId)->get();
            $number_of_active_connections = 0;
            // convert last_handshake to seconds
            foreach ($server_peers as $server_peer) {
                if ($server_peer->last_handshake) {
                    $last_handshake_seconds = convertLastHandshakeToSeconds($server_peer->last_handshake);
                    if ($last_handshake_seconds < $handshake_period_seconds) {
                        $number_of_active_connections++;
                    }
                }
            }
            $max = $peer->max_allowed_connections ?? 1;
            if ($max != -1 && $number_of_active_connections > $max) { // $max: -1 => unlimited
                // add peer to suspect list
                DB::table('suspect_list')->insert([
                    'peer_id' => $peerId,
                    'created_at' => $now
                ]);

                array_push($suspected, $peer->comment);

                $cnt = DB::table('suspect_list')->where('peer_id', $peerId)->count();

                if ($cnt > $max_number_of_violations) {
                    // make peer disable
                    if ($should_block && ($should_block=='true' || $should_block=='yes')) {
                        // $this->toggleEnable($peerId, 0);
                    }

                    DB::table('block_list')->insert([
                        'peer_id' => $peerId,
                        'created_at' => $now
                    ]);

                    // remove from suspect_list
                    DB::table('suspect_list')->where('peer_id', $peerId)->delete();
                        
                    array_push($blocked, $peer->comment);
                }
            }
        }

        if (count($suspected) > 0) {
            $message .= implode(", ", $suspected) . ' went into suspect list.';
        }
        if (count($blocked) > 0) {
            $message .= implode(", ", $blocked) . ' blocked (disabled) due to violation.';
        }

        if (count($suspected) == 0 && count($blocked) == 0){
            $message = 'no violations were detected!';
            saveCronResult('block-peers', $message);
        } else {
            saveCronResult('block-peers', $message);
        }
    }
}
