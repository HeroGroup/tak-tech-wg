@extends('layouts.admin.main', ['pageTitle' => 'Monitor', 'active' => 'monitor'])
@section('content')

<div class="row">
  <div class="col-md-12">
    <div class="card shadow mb-4">
      <div class="card-body">
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
            <input type="text" name="search" id="search" class="form-control" placeholder="search comment, address, note" value="{{$search}}">
          </div>
          <div class="col-sm-1">
            <button type="button" class="btn btn-sm btn-primary mt-1" onclick="search()">search</button>
          </div>
        </div>

        <x-sort :route="route('wiregaurd.interfaces.usages.monitor.only')" :sorts="['comment' => 'Comment', 'client_address' => 'Client Address', 'total_usage' => 'Total Usage']" :sortBy="$sortBy" />
        
        <x-paginator :route="route('wiregaurd.interfaces.usages.monitor.only')" :selectedCount="0" :isLastPage="$isLastPage" />

        <table class="table table-striped">
          <thead>
            <th>row</th>
            <th>Interface</th>
            <th>comment</th>
            <th>Address</th>
            <th>Note</th>
            <th>Total Usage (GB)</th>
            <th>Enabled</th>
            <th></th>
          </thead>
          <tbody>
            <?php $row=0; ?>
            @foreach($peers as $peer)
            <tr id="{{$peer->id}}">
              <td>{{++$row}}</td>
              <td>{{$peer->name}}</td>
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
              <td>
                <a href="{{route('wiregaurd.peers.limited.usageStatistics', $peer->id)}}" target="_blank" class="text-success">
                  <i class="fa fa-fw fa-chart-line"></i> Usage Statistics
                </a>
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
  var baseRoute = "{{route('wiregaurd.interfaces.usages.monitor.only')}}";
  
  function searchInterface(val) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    urlParams.set('interface', val);
    urlParams.delete('page');

    window.location.href = `${baseRoute}?${urlParams.toString()}`;
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
</script>
@endsection