@extends('layouts.admin.main', ['pageTitle' => 'Suspect List', 'active' => 'violations'])
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
      <th>Number Of Violations</th>
      <th>Actions</th>
    </thead>
    <tbody>
      <?php $row = 0; ?>
      @foreach($list as $item)
      <tr id="{{$item->peer_id}}">
        <td>
          <input type="checkbox" class="chk-row">
        </td>
        <td>{{++$row}}</td>
        <td>{{$item->comment}}</td>
        <td>{{$item->name}}</td>
        <td>{{$item->number_of_violations}}</td>
        <td>
            <a href="#" onclick="destroy('{{route('admin.violations.suspect.remove')}}','{{$item->peer_id}}','{{$item->peer_id}}')" class="text-danger">
                <i class="fa fa-times"></i> Remove From list
            </a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection