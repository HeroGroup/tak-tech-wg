@extends('layouts.admin.main', ['pageTitle' => 'Details', 'active' => 'violations'])
@section('content')
<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <th>server</th>
      <th>last handshake</th>
      <th>checked time</th>
    </thead>
    <tbody>
      @foreach($details as $item)
      <tr>
        <td>{{$item->server_address}} ({{$item->alias}})</td>
        <td>{{$item->last_handshake}}</td>
        <td>{{$item->last_handshake_updated_at}}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection