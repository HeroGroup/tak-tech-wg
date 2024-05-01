@extends('layouts.admin.main', ['pageTitle' => $server->server_address, 'active' => 'servers'])
@section('content')

<x-loader/>

<div class="row">
  <div class="col-lg-6">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Server Details</h6>
        <div class="dropdown no-arrow show">
            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink" x-placement="bottom-end" style="position: absolute; transform: translate3d(-158px, 19px, 0px); top: 0px; left: 0px; will-change: transform;">
                <div class="dropdown-header">Actions:</div>
                <a class="dropdown-item" href="#" onclick="syncInterfaces('{{$server->id}}', '{{$server->server_address}}')">Sync remote Interfaces with DB</a>
                <a class="dropdown-item" href="#" onclick="syncPeers('{{$server->id}}', '{{$server->server_address}}')">Sync remote Peers with DB</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#" onclick="getInterfaces('{{$server->id}}', '{{$server->server_address}}')">Get Interfaces from remote</a>
                <a class="dropdown-item" href="#" onclick="getPeers('{{$server->id}}', '{{$server->server_address}}')">Get Peers from remote</a>
            </div>
        </div>
      </div>
      
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <th>Parameter</th>
              <th>Local Value</th>
              <th>Remote Value</th>
            </thead>
            <tbody>
              <tr>
                <td>Interfaces</td>
                <td>{{$localInterfacesCount}}</td>
                <td @if($localInterfacesCount == $remoteCounts['interfaces']) class="text-success" @else class="text-danger" @endif>
                  {{$remoteCounts['interfaces']}}
                </td>
              </tr>
              <tr>
                <td>Peers</td>
                <td>{{$localPeersCount}}</td>
                <td @if($localPeersCount == $remoteCounts['peers']) class="text-success" @else class="text-danger" @endif>
                  {{$remoteCounts['peers']}}
                </td>
              </tr>
              <tr>
                <td>Enabled Peers</td>
                <td>{{$localEnabledPeersCount}}</td>
                <td @if($localEnabledPeersCount == $remoteCounts['enabledPeers']) class="text-success" @else class="text-danger" @endif>
                  {{$remoteCounts['enabledPeers']}}
                </td>
              </tr>
              <tr>
                <td>Disabled Peers</td>
                <td>{{$localDisabledPeersCount}}</td>
                <td @if($localDisabledPeersCount == $remoteCounts['disabledPeers']) class="text-success" @else class="text-danger" @endif>
                  {{$remoteCounts['disabledPeers']}}
                </td>
              </tr>
              <tr>
                <td>Queues</td>
                <td>-</td>
                <td class="text-danger">
                  {{$remoteCounts['queues']}}
                </td>
              </tr>
              <tr>
                <td>NATs</td>
                <td>-</td>
                <td class="text-danger">
                  {{$remoteCounts['NATs']}}
                </td>
              </tr>
              <tr>
                <td>Mangles</td>
                <td>-</td>
                <td class="text-danger">
                  {{$remoteCounts['mangles']}}
                </td>
              </tr>
              <tr>
                <td>IP Addresses</td>
                <td>-</td>
                <td class="text-danger">
                  {{$remoteCounts['IPAddresses']}}
                </td>
              </tr>
              <tr>
                <td>Routes</td>
                <td>-</td>
                <td class="text-danger">
                  {{$remoteCounts['routes']}}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Remote Duplicates</h6>
      </div>
      <div class="card-body">
      @if(count($duplicates) > 0)
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <th>Allowed Address</th>
              <th>Interface</th>
              <th>Comment</th>
              <th>Remove</th>
            </thead>
            <tbody>
                @foreach($duplicates as $duplicate)
                  <?php $id=$duplicate['.id']; ?>
                  <tr id="{{$id}}">
                    <td>{{$duplicate['allowed-address']}}</td>
                    <td>{{$duplicate['interface']}}</td>
                    <td>{{$duplicate['comment']}}</td>
                    <td>
                      <a href="#" class="text-danger" onclick="destroy('{{route('admin.settings.servers.delete.duplicate')}}','{{$id}}','{{$id}}',{'sAddress':'{{$server->server_address}}'})">
                        <i class="fa fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                @endforeach
            </tbody>
          </table>
        </div>
        @else
          <h6>No duplicates found!</h6>
        @endif
      </div>
    </div>
  </div>
</div>

<script>
  function getInterfaces(id, address) {
    turnOnLoader();
    sendRequest({
      method: 'POST',
      route: "{{route('admin.settings.servers.getInterfaces')}}",
      formData: createFormData({
        '_token': '{{csrf_token()}}',
        'id': id,
        'server_address': address
      }),
      successCallback: turnOffLoader,
      failCallback: turnOffLoader
    });
  }
  function getPeers(id, address) {
    turnOnLoader();
    sendRequest({
      method: 'POST',
      route: "{{route('admin.settings.servers.getPeers')}}",
      formData: createFormData({
        '_token': '{{csrf_token()}}',
        'id': id,
        'server_address': address
      }),
      successCallback: turnOffLoader,
      failCallback: turnOffLoader
    });
  }
  function syncInterfaces(id, address) {
    turnOnLoader();
    sendRequest({
        method: 'POST',
        route: "{{route('admin.settings.servers.syncInterfaces')}}",
        formData: createFormData({
            '_token': '{{csrf_token()}}',
            'id': id,
            'server_address': address
      }),
      successCallback: turnOffLoader,
      failCallback: turnOffLoader
      });
  }
  function syncPeers(id, address) {
    turnOnLoader();
    sendRequest({
      method: 'POST',
      route: "{{route('admin.settings.servers.syncPeers')}}",
      formData: createFormData({
        '_token': '{{csrf_token()}}',
        'id': id,
        'server_address': address
      }),
      successCallback: turnOffLoader,
      failCallback: turnOffLoader
    });
  }
</script>
@endsection