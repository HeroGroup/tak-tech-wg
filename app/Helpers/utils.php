<?php

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

require_once app_path('Helpers/phpqrcode/qrlib.php');

function createKeys()
{
  $keyGeneratorPath = app_path('Js_Helpers/keyGenerator.js');
  exec("node $keyGeneratorPath", $output); 
  $privateKey = $output[1];
  $publicKey = $output[2];
  $privateKey = str_replace("'", "", $output[1]);
  $privateKey = str_replace(",", "", $privateKey);
  $publicKey = str_replace("'", "", $output[2]);

  return [
    'public_key' => ltrim($publicKey), 
    'private_key' => ltrim($privateKey)
  ];
}

function curl_general($method, $url, $data=null, $withHeader=false, $timeout=3)
{
  $server_api_username = DB::table('settings')->where('setting_key', 'SERVERS_API_USERNAME')->first()->setting_value;
  $server_api_password = DB::table('settings')->where('setting_key', 'SERVERS_API_PASSWORD')->first()->setting_value;
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'http://' . $url);
  curl_setopt($ch, CURLOPT_USERPWD, $server_api_username . ":" . $server_api_password);
  if ($data) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  }
  if ($withHeader) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Content-Length: ' . strlen($data)]);
  }
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $timeout*1000);
  curl_setopt($ch, CURLOPT_FAILONERROR, true); // Required for HTTP error codes to be reported via our call to curl_error($ch)
  $out = curl_exec($ch);
  curl_close($ch);

  if (!$out || $out == null) {
    return curl_error($ch);
  }
  return json_decode($out, true);
}

function createConfFile($today, $time, $confFilePath, $privateKey, $caddress32, $cdns, $interfacePublicKey, $wgserveraddress, $interfaceListenPort) {
  if (! is_dir(resource_path("confs/$today"))) { 
      mkdir(resource_path("confs/$today")); 
  }

  if (! is_dir(resource_path("confs/$today/$time"))) {
      mkdir(resource_path("confs/$today/$time"));
  }

  $confFile = fopen($confFilePath, 'w');
        
  $content = "[Interface]\n";
  $content .= "PrivateKey = $privateKey\n";
  $content .= "Address = $caddress32\n";
  $content .= "DNS = $cdns\n\n";
  $content .= "[Peer]\n";
  $content .= "PublicKey = $interfacePublicKey\n"; // Interface's public key
  $content .= "AllowedIPs = 0.0.0.0/0, ::/0\n";
  $content .= "Endpoint = $wgserveraddress:$interfaceListenPort\n";

  fwrite($confFile, $content);
  fclose($confFile);

  return $content;
}

function createQRcode($content, $output)
{
  QRcode::png($content, $output, 'L', 10, 10);
}

function createZip($directory, $time)
{
  $zipArchive = new ZipArchive();

  $zipFile = "$directory/$time.zip";
  if ($zipArchive->open($zipFile, ZipArchive::CREATE) !== TRUE) {
    return ['status' => -1, 'message' => "Unable to open file."];
  }

  $folder = "$directory/";
  if (is_dir($folder)) {
    if ($f = opendir($folder)) {
        while (($file = readdir($f)) !== false) {
            if (is_file($folder . $file)) {
                if ($file != '' && $file != '.' && $file != '..') {
                    $zipArchive->addFile($folder . $file, $file);
                }
            } else {
                if (is_dir($folder . $file)) {
                    if ($file != '' && $file != '.' && $file != '..') {
                        $zipArchive->addEmptyDir($folder . $file);
                        $folder = $folder . $file . '/';
                        createZip($zipArchive, $folder);
                    }
                }
            }
        }
        closedir($f);
    } else {
      return ['status' => -1, 'message' => "Unable to open directory $folder"];
    }
  } else {
    return ['status' => -1, 'message' => "$folder is not a directory."];
  }
  $zipArchive->close();
  return ['status' => 1, 'file' => $zipFile];
}

function zipPeer($comment, $conf_file, $qrcode_file, $zipFileName)
{
  $zip = new ZipArchive();

  if ($zip->open($zipFileName, ZipArchive::CREATE)!==TRUE) {
      exit("cannot open <$zipFileName>\n");
  }
  $zip->addFile($conf_file, "$comment.conf");
  $zip->addFile($qrcode_file, "$comment.png");
  $zip->close();

  return $zip;
}

function saveCronResult($cronName, $cronResult)
{
  if(env('SHOULD_SAVE_CRON_RESULT')) {
    DB::table('cron_results')->insert([
      'cron_name' => $cronName,
      'cron_result' => $cronResult,
      'created_at' => date('Y-m-d H:i:s', time())
    ]);
  }
}

function logError($error)
{
  $now = time();
  $today = date('Y-m-d', $now);
  $time = date('H:i:s', $now);
    
  $line = $error->getLine();
  $message = $error->getMessage();
  $errorFile = $error->getFile();
  $content = "[$time] $line: $message IN FILE $errorFile \r\n";

  file_put_contents(base_path("/logs/errors/$today.log"), $content, FILE_APPEND | LOCK_EX);
}

function convertLastHandshakeToSeconds($input)
{
  $weeks = 0;
  $days = 0;
  $hours = 0;
  $minutes = 0;
  $seconds = 0;

  $w_position = strpos($input, 'w');
  if ($w_position) {
    $weeks = substr($input, 0, $w_position);
    $input = substr($input, $w_position+1);
  }

  $d_position = strpos($input, 'd');
  if ($d_position) {
    $days = substr($input, 0, $d_position);
    $input = substr($input, $d_position+1);
  }

  $h_position = strpos($input, 'h');
  if ($h_position) {
    $hours = substr($input, 0, $h_position);
    $input = substr($input, $h_position+1);
  }

  $m_position = strpos($input, 'm');
  if ($m_position) {
    $minutes = substr($input, 0, $m_position);
    $input = substr($input, $m_position+1);
  }

  $s_position = strpos($input, 's');
  if ($s_position) {
    $seconds = substr($input, 0, $s_position);
  }

  return ($weeks*7*24*60*60) + ($days*24*60*60) + ($hours*60*60) + ($minutes*60) + ($seconds);
}

function getPeerUsage($pId)
{
  // in order to prevent sql injection, first fetch the peer with eloquent
  $peer = DB::table('peers')->find($pId);

  $x = DB::table('peers')
    ->where('peers.id', $peer->id)
    ->join('server_peers', 'server_peers.peer_id', '=', 'peers.id')
    ->join('server_peer_usages', function(JoinClause $join) {
      $join->on('server_peer_usages.server_id', '=', 'server_peers.server_id')
        ->on('server_peer_usages.server_peer_id', '=', 'server_peers.server_peer_id');
    })
    ->selectRaw('`peers`.`id`, SUM(CAST(`server_peer_usages`.`tx` AS UNSIGNED)) AS TX, SUM(CAST(`server_peer_usages`.`rx` AS UNSIGNED)) AS RX')
    ->groupBy('peers.id')
    ->get();

  // $x = DB::raw(
  //     'SELECT `peers`.`id`, SUM(`server_peer_usages`.`tx`) AS TX, SUM(`server_peer_usages`.`rx`) AS RX 
  //      FROM `peers`, `server_peers`, `server_peer_usages` 
  //      WHERE `peers`.`id` = `server_peers`.`peer_id` 
  //      AND (`server_peers`.`server_id` = `server_peer_usages`.`server_id` AND 
  //           `server_peers`.`server_peer_id` = `server_peer_usages`.`server_peer_id`)
  //      AND `peers`.`id`=? 
  //      GROUP BY `peers`.`id`', [$peer->id]);

  
  // $sum_tx = 0;
  // $sum_rx = 0;
  // $servers = DB::table('servers')->get();
  // foreach ($servers as $server) {
  //     $sId = $server->id;
  //     $server_peer = DB::table('server_peers')
  //         ->where('server_id', $sId)
  //         ->where('peer_id', $pId)
  //         ->first();
  //     if ($server_peer) {
  //         $record = DB::table('server_peer_usages')
  //             ->where('server_id', $sId)
  //             ->where('server_peer_id', $server_peer->server_peer_id)
  //             ->orderBy('id', 'desc')
  //             ->first();
  //         $sum_tx += $record->tx ?? 0;
  //         $sum_rx += $record->rx ?? 0;
  //     }
  //   }
    
    $tx = isset($x[0]) ? round((($x[0]->TX ?? 0) / 1073741824), 2) : 0;
    $rx = isset($x[0]) ? round((($x[0]->RX ?? 0) / 1073741824), 2) : 0;
    $total_usage = $tx + $rx;

    return [
      'tx' => $tx,
      'rx' => $rx,
      'total_usage' => $total_usage
    ];
}

function storeUsage($sId, $pId, $tx, $rx, $last_handshake, $now)
{
  $x = DB::table('server_peer_usages')
    ->where('server_id', $sId)
    ->where('server_peer_id', $pId)
    ->selectRaw('`server_peer_usages`.`server_peer_id`, SUM(CAST(`server_peer_usages`.`tx` AS UNSIGNED)) AS TX, SUM(CAST(`server_peer_usages`.`rx` AS UNSIGNED)) AS RX')
    ->groupBy('server_peer_usages.server_peer_id')
    ->get();

  // $latest = DB::table('server_peer_usages')
  //   ->where('server_id', $sId)
  //   ->where('server_peer_id', $pId)
  //   ->orderBy('id', 'desc')
  //   ->first();

  $sum_tx = (int) (isset($x[0]) ? ($x[0]->TX ?? 0) : 0); // $latest ? $latest->tx : 0;
  $sum_rx = (int) (isset($x[0]) ? ($x[0]->RX ?? 0) : 0); // $latest ? $latest->rx : 0;

  $tx = (int) $tx;
  $rx = (int) $rx;
  
  $new_tx = ($sum_tx > $tx) ? $tx : $tx - $sum_tx;
  $new_rx = ($sum_rx > $rx) ? $rx : $rx - $sum_rx;

  DB::table('server_peer_usages')->insert([
      'server_id' => $sId,
      'server_peer_id' => $pId,
      'raw_tx' => $tx,
      'tx' => $new_tx,
      'raw_rx' => $rx,
      'rx' => $new_rx,
      'last_handshake' => $last_handshake ?? null,
      'created_at' => $now
  ]);
}