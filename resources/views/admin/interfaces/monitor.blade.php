@extends('layouts.admin.main', ['pageTitle' => 'Monitor Interface ' . $interfaceName . ' Peers', 'active' => 'interfaces'])
@section('content')

<x-loader/>

<div class="row">
  <div class="col-md-12">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Peers to Monitor</h6>
      </div>
      <div class="card-body">
        <div class="row mb-4">
          <div class="col-sm-4">
            <input type="text" name="search" id="search" class="form-control" placeholder="search comment, address, note" value="{{$search}}">
          </div>
          <div class="col-sm-1">
            <button type="button" class="btn btn-sm btn-primary mt-1" onclick="search()">search</button>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-sm-12">
            <div class="card">
              <div class="card-body" style="padding:5px 10px;">
                <label>Sort By: </label>
                <a href="#" onclick="sortResult('comment_asc')" class="btn sort-btn @if($sortBy=='comment_asc') btn-dark @endif" id="sort_comment_asc">Comment <i class="fa fa-sort-amount-down-alt"></i></a>
                <a href="#" onclick="sortResult('comment_desc')" class="btn sort-btn @if($sortBy=='comment_desc') btn-dark @endif" id="sort_comment_desc">Comment <i class="fa fa-sort-amount-down"></i></a>
                <a href="#" onclick="sortResult('client_address_asc')" class="btn sort-btn @if($sortBy=='client_address_asc') btn-dark @endif" id="sort_client_address_asc">Address <i class="fa fa-sort-amount-down-alt"></i></a>
                <a href="#" onclick="sortResult('client_address_desc')" class="btn sort-btn @if($sortBy=='client_address_desc') btn-dark @endif" id="sort_client_address_desc">Address <i class="fa fa-sort-amount-down"></i></a>
                <a href="#" onclick="sortResult('total_usage_asc')" class="btn sort-btn @if($sortBy=='total_usage_asc') btn-dark @endif" id="sort_total_usage_asc">Total Usage <i class="fa fa-sort-amount-down-alt"></i></a>
                <a href="#" onclick="sortResult('total_usage_desc')" class="btn sort-btn @if($sortBy=='total_usage_desc') btn-dark @endif" id="sort_total_usage_desc">Total Usage <i class="fa fa-sort-amount-down"></i></a>
              </div>
            </div>
          </div>
        </div>
        <div style="font-size: 14px;">
          <span id="number-of-selected-items">{{$selected_peers_count}}</span> items are selected.
        </div>
          <table class="table table-striped">
            <thead>
              <th>
                <input type="checkbox" id="chk-all" onclick="monitorAll(this.checked)" @if($selected_peers_count==count($peers)) checked="checked" @endif>
              </th>
              <th>comment</th>
              <th>Address</th>
              <th>Note</th>
              <th>Total Usage</th>
            </thead>
            <tbody>
              @foreach($peers as $peer)
              <tr>
                <td>
                  <input id="{{$peer->id}}" type="checkbox" onclick="monitorPeer('{{$peer->id}}', this.checked)" class="chk-row" @if($peer->monitor) checked="checked" @endif>
                </td>
                <td>{{$peer->comment}}</td>
                <td>{{$peer->client_address}}</td>
                <td>{{$peer->note}}</td>
                <td>{{$peer->total_usage}}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
      </div>
    </div>
  </div>
</div>
<script>
  function monitorPeer(peerId, checked) {
    turnOnLoader();
    
    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'POST',
      'peerId': peerId,
      'checked': checked,
      'interfaceId': '{{$id}}'
    });

    var params = {
      method: 'POST',
      route: "{{route('admin.wiregaurd.interfaces.usages.monitor.save')}}",
      formData,
      successCallback: turnOffLoader,
      failCallback: () => {
        rollback(peerId, checked);
      },
    };

    sendRequest(params);
  }
  function monitorAll(checked) {
    checkAll();
    turnOnLoader();
    
    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'POST',
      'interfaceId': '{{$id}}',
      'checked': checked,
    });

    var params = {
      method: 'POST',
      route: "{{route('admin.wiregaurd.interfaces.usages.monitor.save')}}",
      formData,
      successCallback: turnOffLoader,
      failCallback: () => {
        checkAll();
        turnOffLoader();
      }
    };

    sendRequest(params);
  }
  function rollback(peerId, checked) {
    turnOffLoader();
    $(`#${peerId}`).prop('checked', !checked);
  }
  function search() {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    var sortBy = urlParams.get('sortBy');
    var search = document.getElementById("search").innerHTML;
    
    window.location.href = "{{route('admin.wiregaurd.interfaces.usages.monitor',$id)}}" + 
                `?search=${search || ''}` +  
                `&sortBy=${sortBy || ''}`;
  }
  function sortResult(sortBy) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    var search = urlParams.get('search');
    
    window.location.href = "{{route('admin.wiregaurd.interfaces.usages.monitor',$id)}}" + 
                `?search=${search || ''}` +  
                `&sortBy=${sortBy}`;
  }
</script>
@endsection