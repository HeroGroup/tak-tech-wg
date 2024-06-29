<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    public function activatePeer(Request $request)
    {
        try {
            $token = $request->token;
            $peer_id = $request->peer_id;

            if ($token != env('SITE_TOKEN')) {
                return $this->fail("invalid token!");
            }

            if (!$peer_id) {
                return $this->fail("invalid peer id!");
            }

            $peer = DB::table('peers')->find($peer_id);

            if (!$peer) {
                return $this->fail("invalid peer!");
            }

            DB::table('peers')
                ->where('id', $peer_id)
                ->update([
                    'activated_at' => date('Y-m-d H:i:s', time())
                ]);

            return $this->success("Ok");
        } catch (\Exception $excption) {
            return $this->fail($excption->getMessage());
        }
    }

    public function toggleEnable(Request $request)
    {
        try {
            $token = $request->token;
            $peer_id = $request->peer_id;
            $status = $request->status;

            if ($token != env('SITE_TOKEN')) {
                return $this->fail("invalid token");
            }

            if (!$peer_id) {
                return $this->fail("invalid peer id");
            }

            if (! in_array($status, ["0", "1"])) {
                return $this->fail("invalid status");
            }

            $wg = new WiregaurdController();
            return $wg->toggleEnable($peer_id, $status);
        } catch (\Exception $excption) {
            return $this->fail($excption->getMessage());
        }
    }

    public function renewPeer(Request $request)
    {
        try {
            $token = $request->token;
            $peer_id = $request->peer_id;
            $add_days = $request->add_days;

            if ($token != env('SITE_TOKEN')) {
                return $this->fail("invalid token!");
            }

            if (!$peer_id) {
                return $this->fail("invalid peer id!");
            }

            if (!$add_days) {
                return $this->fail("invalid add days!");
            }

            $peer = DB::table('peers')->find($peer_id);

            if (!$peer) {
                return $this->fail("invalid peer!");
            }

            $current_expire_days = $peer->expire_days ?? 0;
            DB::table('peers')
                ->where('id', $peer_id)
                ->update([
                    'expire_days' => $current_expire_days + $add_days
                ]);

            return $this->success("Ok");
        } catch (\Exception $excption) {
            return $this->fail($excption->getMessage());
        }
    }
}
