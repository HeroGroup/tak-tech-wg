@extends('layouts.admin.main', ['pageTitle' => 'Download', 'active' => 'peers'])
@section('content')
<div class="row text-center">
  
    <a href="{{route('admin.wiregaurd.peers.export.download.data', $time)}}" class="btn btn-lg btn-success">Download Data</a>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <a href="{{route('admin.wiregaurd.peers.export.download.files', $time)}}" class="btn btn-lg btn-info">Download Files</a>
  
</div>
@endsection