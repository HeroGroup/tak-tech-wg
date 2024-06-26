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
          <div class="com-sm-2" style="padding: 8px;">
            <a href="#" id="show-type" onclick="changeShowType()">
              @if($monitoring=='true') show all @else show only monitoring @endif
            </a>
          </div>
        </div>

        <x-sort :route="route('wiregaurd.interfaces.usages.monitor',$id)" :sorts="['comment' => 'Comment', 'client_address' => 'Client Address', 'total_usage' => 'Total Usage']" :sortBy="$sortBy" />
        
        <x-paginator :route="route('wiregaurd.interfaces.usages.monitor',$id)" :selectedCount="$selected_peers_count" :isLastPage="$isLastPage" />

        <table class="table table-striped">
          <thead>
            <th>row</th>
            <th>
              <input type="checkbox" id="chk-all" onclick="monitorAll(this.checked)" @if($selected_peers_count==count($peers)) checked="checked" @endif>
            </th>
            <th>comment</th>
            <th>Address</th>
            <th>Note</th>
            <th>Total Usage (GB)</th>
            <th>Enabled</th>
          </thead>
          <tbody>
            <?php $row=0; ?>
            @foreach($peers as $peer)
            <tr id="{{$peer->id}}">
              <td>{{++$row}}</td>
              <td>
                <input id="peer_{{$peer->id}}" type="checkbox" onclick="monitorPeer('{{$peer->id}}', this.checked)" class="chk-row" @if($peer->monitor) checked="checked" @endif>
              </td>
              <td>{{$peer->comment}}</td>
              <td>{{$peer->client_address}}</td>
              <td>{{$peer->note}}</td>
              <td>{{$peer->total_usage}}</td>
              <td>
                @if(auth()->user()->can_enable && auth()->user()->can_disable)
                <label class="switch">
                    <input type="checkbox" name="Enabled" id="enabled_{{$peer->id}}" @if($peer->is_enabled) checked @endif onchange="toggleEnable('{{$peer->id}}', '{{$peer->comment}}', this.checked)">
                    <span class="slider round"></span>
                </label>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
  var baseRoute = "{{route('wiregaurd.interfaces.usages.monitor',$id)}}";
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
      route: "{{route('wiregaurd.interfaces.usages.monitor.save')}}",
      formData,
      successCallback: turnOffLoader,
      failCallback: () => {
        rollback(peerId, checked);
      },
    };

    sendRequest(params);
  }
  function monitorAll(checked) {
    turnOnLoader();
    if (checked) {
      checkAll();
      var ids = checkedItems();
    } else {
      var ids = checkedItems();
      checkAll();
    }
    
    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'POST',
      'ids': JSON.stringify(ids),
      'checked': checked,
    });

    var params = {
      method: 'POST',
      route: "{{route('wiregaurd.interfaces.usages.monitor.save')}}",
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
    $(`#peer_${peerId}`).prop('checked', !checked);
  }
  function search() {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    urlParams.set('search', document.getElementById("search").value);
    urlParams.delete('page');

    window.location.href = `${baseRoute}?${urlParams.toString()}`;
  }
  function toggleEnable(id, comment, checked) {
    turnOnLoader();
    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'PUT',
      'id': id,
      'comment': comment,
      'status': checked ? 1 : 0
    });
    
    var params = {
      method: 'POST',
      route: "{{route('wiregaurd.peers.toggleEnable')}}",
      formData,
      successCallback: turnOffLoader,
      failCallback: function() {
        turnOffLoader();
        document.getElementById(`enabled_${id}`).checked = !checked;
      }
    };

    sendRequest(params);
  }
  function changeShowType() {
    var showOnlyMonitoring = "{{$monitoring}}";
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    urlParams.set('monitoring', showOnlyMonitoring == 'true' ? 'false' : 'true');
    urlParams.delete('page');

    window.location.href = `${baseRoute}?${urlParams.toString()}`;
  }
</script>
@endsection