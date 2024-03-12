@extends('layouts.admin.main', ['pageTitle' => 'Download', 'active' => 'create_peers'])
@section('content')
<div class="row text-center">
  
    <a href="{{route('wiregaurd.peers.downloadZip', ['date' => $today, 'file' => $time])}}" class="btn btn-lg btn-success">Download Zip</a>
  
</div>
@endsection