@extends('layouts.admin.main', ['pageTitle' => 'Block List', 'active' => 'violations'])
@section('content')

<x-loader/>

<div>
  <a href="#" onclick="massDelete()" class="text-danger" style="text-decoration:none;">
    <i class="fa fa-times"></i> Remove From list
  </a>
</div>

<x-paginator :route="route('violations.block.list')" :selectedCount="0" :isLastPage="$isLastPage" />

<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <th>
        <input type="checkbox" id="chk-all" onclick="checkAll()">
      </th>
      <th>row</th>
      <th>Interface</th>
      <th>Peer</th>
      <th>Address</th>
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
        <td>{{$item->name}}</td>
        <td>{{$item->comment}}</td>
        <td>{{$item->client_address}}</td>
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
            <a href="#" onclick="destroy('{{route('violations.block.remove')}}','{{$item->peer_id}}','{{$item->peer_id}}')" class="text-danger">
                <i class="fa fa-times"></i> Remove From list
            </a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

<script>
  var baseRoute = "{{route('violations.block.list')}}";
  function search() {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    urlParams.set('search', document.getElementById("search").value);
    urlParams.delete('page');

    window.location.href = `${baseRoute}?${urlParams.toString()}`;
  }
  function massDelete() {
    var ids = checkedItems();
    if (ids.length == 0) {
      return;
    }

    turnOnLoader();
    
    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'DELETE',
      'ids': JSON.stringify(ids)
    });
    
    var params = {
      method: 'POST',
      route: "{{route('violations.block.remove.mass')}}",
      formData,
      successCallback: function() {
        ids.forEach(element => {
          document.getElementById(element).remove();
        });
        turnOffLoader();
      },
      failCallback: turnOffLoader,
    };

    sendRequest(params);
  }
</script>
@endsection