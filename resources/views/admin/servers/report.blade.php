@extends('layouts.admin.main', ['pageTitle' => 'Servers Report', 'active' => 'servers_report'])
@section('content')

<x-loader/>
<div class="table-responsive" style="padding-bottom: 50px;">
  <table class="table table-striped">
    <thead>
      <th>Server Address</th>
      <th>Router OS Version</th>
      <th>Interfaces</th>
      <th>Total Peers</th>
      <th>Enabled Peers</th>
      <th>Disabled Peers</th>
    </thead>
    <tbody>
      @foreach($infos as $id => $server)
      <tr id="{{$id}}">
        <td>{{$server['address']}}</td>
        <td>{{$server['router_os_version']}}</td>
        <td>{{$server['interfaces']}}</td>
        <td>{{$server['enabledPeers'] + $server['disabledPeers']}}</td>
        <td>{{$server['enabledPeers']}}</td>
        <td>{{$server['disabledPeers']}}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

@endsection