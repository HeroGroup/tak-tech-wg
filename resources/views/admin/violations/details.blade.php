@extends('layouts.admin.main', ['pageTitle' => $details[0]->comment.' Details', 'active' => 'violations'])
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
        <td>
          @if($item->last_handshake)
          {{$item->last_handshake}} {{($item->last_handshake_seconds)}}
          @else
          wait for update
          @endif
        </td>
        <td>{{$item->last_handshake_updated_at}}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection