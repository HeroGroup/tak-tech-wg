@extends('layouts.admin.main', ['pageTitle' => 'Block History', 'active' => 'violations'])
@section('content')

<div style="font-size: 14px;">
  <span id="number-of-selected-items">0</span> items are selected.
</div>

<div class="table-responsive">
  <table class="table table-striped" id="dataTable">
    <thead>
      <th>
        <input type="checkbox" id="chk-all" onclick="checkAll()">
      </th>
      <th>row</th>
      <th>Interface</th>
      <th>Peer</th>
      <th>Address</th>
      <th>Blocked Time</th>
      <th>Unblocked Time</th>
    </thead>
    <tbody>
      <?php $row = 0; $now = time(); ?>
      @foreach($list as $item)
      <tr id="{{$item->peer_id}}">
        <td>
          <input type="checkbox" class="chk-row">
        </td>
        <td>{{++$row}}</td>
        <td>{{$item->name}}</td>
        <td>{{$item->comment}}</td>
        <td>{{$item->client_address}}</td>
        <td>{{substr($item->created_at, 0, 16)}}</td>
        <td>{{substr($item->unblocked_at, 0, 16)}}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection