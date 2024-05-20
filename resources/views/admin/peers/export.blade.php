@extends('layouts.admin.main', ['pageTitle' => 'Export', 'active' => 'peers'])
@section('content')

<x-loader />

<div class="row mb-4">
  <div class="col-sm-2">
    <select name="interface" class="form-control" onchange="searchInterface(this.value)">
      <option value="all">All Interfaces</option>
      @foreach($interfaces as $key=>$value)
      <option value="{{$key}}" @if($key==$interface) selected @endif>{{$value}}</option>
      @endforeach
    </select>
  </div>
  <div class="col-sm-4">
    <input type="text" name="search" id="search" class="form-control" placeholder="search address, comment, note" value="{{$search}}">
  </div>
  <div class="col-sm-1">
    <button type="button" class="btn btn-sm btn-primary mt-1" onclick="search()">search</button>
  </div>
</div>

<div class="mb-4">
  <a href="#" onclick="exportPeers()" class="text-info" style="text-decoration:none;">
    <i class="fa fa-fw fa-file-export"></i>
    <span>Export Data</span>
  </a>
</div>

<x-paginator :route="route('admin.wiregaurd.peers.export')" :selectedCount="0" :isLastPage="$isLastPage" />

<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <th>
        <input type="checkbox" id="chk-all" onclick="checkAll()">
      </th>
      <th>row</th>
      <th>Address</th>
      <th>Comment</th>
    </thead>
    <tbody>
      <?php $row = 0; ?>
      @foreach($peers as $peer)
      <tr id="{{$peer->id}}">
        <td>
              <input type="checkbox" class="chk-row">
        </td>
        <td>{{++$row}}</td>
        <td>{{$peer->client_address}}</td>
        <td>{{$peer->comment}}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
<script>
  var baseRoute = "{{route('admin.wiregaurd.peers.export')}}";
  function searchBase(set={}) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    urlParams.delete('page');

    var params = Object.keys(set);
    params.forEach(key => {
      urlParams.set(key, set[key]);
    });

    window.location.href = `${baseRoute}?${urlParams.toString()}`;
  }
  function searchInterface(val, clear=false) {
    searchBase({ 'interface': val });
  }
  function search() {
    searchBase({ 'search': document.getElementById("search").value });
  }
  function exportPeers() {
    var ids = checkedItems();
    if (ids.length == 0) {
      return;
    }

    turnOnLoader();

    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      'ids': JSON.stringify(ids),
    });

    var params = {
      method: 'POST',
      route: "{{route('admin.wiregaurd.peers.export.data')}}",
      formData,
      successCallback: turnOffLoader,
      failCallback: turnOffLoader,
    };

    sendRequest(params);
  }
</script>
@endsection