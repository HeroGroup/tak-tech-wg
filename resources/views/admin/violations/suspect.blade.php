@extends('layouts.admin.main', ['pageTitle' => 'Suspect List', 'active' => 'violations'])
@section('content')

<x-loader/>

<div class="row mb-4">
  <div class="col-sm-4">
    <input type="text" name="search" id="search" class="form-control" placeholder="search comment, address, note" value="{{$search}}">
  </div>
  <div class="col-sm-1">
    <button type="button" class="btn btn-sm btn-primary mt-1" onclick="search()">search</button>
  </div>
</div>

<div>
  <a href="#" onclick="massDelete()" class="text-danger" style="text-decoration:none;">
    <i class="fa fa-times"></i> Remove From list
  </a>
</div>

<x-paginator :route="route('violations.suspect.list')" :selectedCount="0" :isLastPage="$isLastPage" />

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
        <td>{{$item->name}}</td>
        <td>{{$item->comment}}</td>
        <td>{{$item->client_address}}</td>
        <td>{{$item->number_of_violations}}</td>
        <td>
            <a href="#" onclick="destroy('{{route('violations.suspect.remove')}}','{{$item->peer_id}}','{{$item->peer_id}}')" class="text-danger">
                <i class="fa fa-times"></i> Remove From list
            </a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

<script>
  var baseRoute = "{{route('violations.suspect.list')}}";
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
      route: "{{route('violations.suspect.remove.mass')}}",
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