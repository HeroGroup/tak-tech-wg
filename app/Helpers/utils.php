<?php

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
  DB::table('cron_results')->insert([
    'cron_name' => $cronName,
    'cron_result' => $cronResult,
    'created_at' => date('Y-m-d H:i:s', time())
  ]);
}