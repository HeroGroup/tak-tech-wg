<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/utils.php');

// This controller handles Epport peers actions
class ExportController extends Controller
{
    // return a page to do export
    public function export(Request $request)
    {
        $interfaces = DB::table('interfaces')->pluck('name', 'id')->toArray();
        $interface = $request->query('interface');
        if ($interface && $interface != 'all') {
            $peers = DB::table('peers')->where('interface_id', $interface);
        } else {
            $peers = DB::table('peers');
        }

        $search = $request->query('search');
        if ($search && $peers && $peers->count() > 0) {
            $peers = $peers->where(function (Builder $query) use ($search) {
                $query->where('comment', 'like', '%'.$search.'%')
                    ->orWhere('client_address', 'like', '%'.$search.'%')
                    ->orWhere('note', 'like', '%'.$search.'%');
                });
        }

        $page = $request->query('page', 1);
        $take = $request->query('take', 50);
        if ($take == 'all') {
            $peers = $peers->get();
            $isLastPage = true;
        } else {
            $skip = ($page - 1) * $take;
            $peers = $peers->skip($skip)->take($take)->get();
            $isLastPage = (count($peers) < $take) ? true : false;
        }
        
        return view('admin.peers.export', compact('interfaces', 'interface', 'search', 'isLastPage', 'peers'));
    }

    // this method creates a csv for data, and a zip file for qrcodes and conf files
    public function exportDataAndFiles(Request $request)
    {
        try {
            $now = time();
            $csvFile = resource_path("confs/$now.csv");
            $headers = ['id', 'is_enabled', 'note', 'expire_days', 'qrcode_file', 'conf_file'];
            $ids = json_decode($request->ids);
            $peers = DB::table('peers')
                ->whereNotNull('qrcode_file')
                ->whereNotNull('conf_file')
                ->whereIn('id', $ids)
                ->get($headers)
                ->toArray();
            
            createCSV($headers, $peers, $csvFile);

            $files = [];
            $zipFile = resource_path("confs/$now.zip");
            $ids = json_decode($request->ids);
            $peers = DB::table('peers')
                ->whereNotNull('qrcode_file')
                ->whereNotNull('conf_file')
                ->whereIn('id', $ids)
                ->get();
            
            foreach ($peers as $peer) {
                array_push($files, $peer->conf_file);
                array_push($files, $peer->qrcode_file);
            }

            zipFiles($files, $zipFile);

            return $this->success('Data exported successfully.', [
                'route' => route('admin.wiregaurd.peers.export.getDownloadLinks', ['time' => $now])
            ]);
        } catch (\Exception $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    // return a view with download links
    public function getDownloadLinks($time) {
        return view('admin.peers.downloadExports', compact('time'));
    }

    // reutrn a view to show download links
    public function downloadData($file)
    {
        $target_file = resource_path("confs/$file.csv");
        
        if (file_exists($target_file)) {
            return response()->download($target_file);
        } else {
            return back()->with('message', 'no file to download!')->with('type', 'danger');
        }
    }

    // downlod corrsponding data (csv) and qrcode and conf files (zip)
    public function downloadFiles($file)
    {
        $target_file = resource_path("confs/$file.zip");

        if (file_exists($target_file)) {
            return response()->download($target_file);
        } else {
            return back()->with('message', 'no file to download!')->with('type', 'danger');
        }
    }
}
