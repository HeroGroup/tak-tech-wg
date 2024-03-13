<?php

use phpseclib3\Math\BigInteger;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

require_once app_path('Helpers/phpqrcode/qrlib.php');
// import shz-peers-1402-12-21.rsc
function createKeysOld()
{
  $algorithms = ['PKCS1', 'PKCS8']; // 64 BYTES, 16 BYTES
  $bytes = [64, 16];
  $i = array_rand($algorithms);
  $algorithm = $algorithms[$i];

  $privateKey = RSA::createKey($bytes[$i]);
  $publicKey = $privateKey->getPublicKey();

  $privateKeyString = $privateKey->toString($algorithm);
  $publicKeyString = $publicKey->toString($algorithm);

  $privateKeyString = str_replace("-----BEGIN PRIVATE KEY-----", "", $privateKeyString);
  $privateKeyString = str_replace("-----END PRIVATE KEY-----", "", $privateKeyString);
  $privateKeyString = str_replace("-----BEGIN RSA PRIVATE KEY-----", "", $privateKeyString);
  $privateKeyString = str_replace("-----END RSA PRIVATE KEY-----", "", $privateKeyString);
  $privateKeyString = str_replace("\r\n", "", $privateKeyString);

  $publicKeyString = str_replace("-----BEGIN PUBLIC KEY-----", "", $publicKeyString);
  $publicKeyString = str_replace("-----END PUBLIC KEY-----", "", $publicKeyString);
  $publicKeyString = str_replace("-----BEGIN RSA PUBLIC KEY-----", "", $publicKeyString);
  $publicKeyString = str_replace("-----END RSA PUBLIC KEY-----", "", $publicKeyString);
  $publicKeyString = str_replace("\r\n", "", $publicKeyString);

  return [
      'public_key' => $publicKeyString, 
      'private_key' => $privateKeyString
  ];
}

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
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'http://' . $url);
  curl_setopt($ch, CURLOPT_USERPWD, env('SERVERS_API_USERNAME') . ":" . env('SERVERS_API_PASSWORD'));
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

function createQRcode($content, $output)
{
  QRcode::png(urlencode($content), $output, 'L', 10, 10);
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

function zipPeer($peer, $filename)
{
  $zip = new ZipArchive();
  $comment = $peer->comment;

  if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
      exit("cannot open <$filename>\n");
  }
  $zip->addFile($peer->conf_file, "$comment.conf");
  $zip->addFile($peer->qrcode_file, "$comment.png");
  $zip->close();

  return $zip;
}