@extends('layouts.admin.main', ['pageTitle' => 'Block List', 'active' => 'violations'])
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
      <th>Peer</th>
      <th>Interface</th>
      <th>Blocked Time</th>
      <th>Actions</th>
    </thead>
    <tbody>
      <?php $row = 0; $now = time(); ?>
      @foreach($list as $item)
      <tr id="{{$item->peer_id}}">
        <td>
          <input type="checkbox" class="chk-row">
        </td>
        <td>{{++$row}}</td>
        <td>{{$item->comment}}</td>
        <td>{{$item->name}}</td>
        <td>
          <?php
            $diff = $now - strtotime($item->created_at);
            $days_passed = $diff / 86400;
            $hours_passed = $diff / 3600;
            $minutes_passed = $diff / 60;
            $time_passed = "";
            if ((int) $days_passed > 0) {
              $days_passed_round = round($days_passed);
              $time_passed = "$days_passed_round days ago";
            } else if ((int) $hours_passed > 0) {
              $hours_passed_round = round($hours_passed);
              $time_passed = "$hours_passed_round hours ago";
            } else if ((int) $minutes_passed > 0) {
              $minutes_passed_round = round($minutes_passed);
              $time_passed = "$minutes_passed_round minutes ago";
            } else {
              $time_passed = "$diff seconds ago";
            }
          ?>
          {{$time_passed}}
        </td>
        <td>
            <a href="#" onclick="destroy('{{route('admin.violations.block.remove')}}','{{$item->peer_id}}','{{$item->peer_id}}')" class="text-danger">
                <i class="fa fa-times"></i> Remove From list
            </a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection