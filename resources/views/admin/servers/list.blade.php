@extends('layouts.admin.main', ['pageTitle' => 'Servers', 'active' => 'servers'])
@section('content')

<x-loader/>

<a href="#" class="btn btn-primary btn-icon-split mb-4" data-toggle="modal" data-target="#new-server-modal">
    <span class="icon text-white-50">
        <i class="fas fa-plus"></i>
    </span>
    <span class="text">Add Server</span>
</a>

<div class="table-responsive" style="padding-bottom: 50px;">
  <table class="table table-striped">
    <thead>
      <th>Server Address</th>
      <th>Router OS Version</th>
      <th>Actions</th>
    </thead>
    <tbody>
      @foreach($infos as $id => $server)
      <tr id="{{$id}}">
        <td>{{$server['address']}}</td>
        <td>{{$server['router_os_version']}}</td>
        <td>
        <div class="dropdown no-arrow show">
            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink" x-placement="bottom-end" style="position: absolute; transform: translate3d(-158px, 19px, 0px); top: 0px; left: 0px; will-change: transform;">
                <div class="dropdown-header">Actions:</div>
                <a href="#" class="dropdown-item text-info" data-toggle="modal" data-target="#edit-server-modal-{{$id}}">
                    <i class="fa fa-pen"></i> Edit
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{route('admin.settings.servers.info',$id)}}" class="dropdown-item text-warning">
                    <i class="fa fa-info-circle"></i> Server Details
                </a>
                <a href="#" onclick="" class="dropdown-item text-success">
                    <i class="fa fa-sync"></i> Sync
                <div class="dropdown-divider"></div>
                <a href="#" onclick="destroy('{{route('admin.settings.servers.delete')}}','{{$id}}','{{$id}}')" class="dropdown-item text-danger">
                    <i class="fa fa-trash"></i> Remove
                </a>
            </div>
        </div>
        <!-- Edit server Modal -->
        <div class="modal fade" id="edit-server-modal-{{$id}}" tabindex="-1" role="dialog" aria-labelledby="editserverModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editserverModalLabel">Edit server</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="{{route('admin.settings.servers.update')}}" onsubmit="turnOnLoader()">
                            @csrf
                            <input type="hidden" name="_method" value="PUT">
                            <input type="hidden" name="id" value="{{$id}}">
                            <div class="form-group row mb-4">
                                <div class="col-md-12">
                                    <label for="server_address">Server Address</label>
                                    <input class="form-control" name="server_address" value="{{$server['address']}}" placeholder="217.60.254.2" required>
                                </div>
                            </div>
                            <div class="form-group row mb-4">
                                <div class="col-md-12">
                                    <label for="router_os_version">Router OS Version</label>
                                    <input class="form-control" name="router_os_version" value="{{$server['router_os_version']}}" placeholder="7.12beta" required>
                                </div>
                            </div>
                            <div class="form-group row mb-4">
                                <div class="col-md-12" style="text-align:center;">
                                    <input type="submit" class="btn btn-success" value="Save and close" />
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div class="modal fade" id="new-server-modal" tabindex="-1" role="dialog" aria-labelledby="newserverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newserverModalLabel">Add new server</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="{{route('admin.settings.servers.add')}}" onsubmit="turnOnLoader()">
                    @csrf
                    <div class="form-group row mb-4">
                        <div class="col-md-12">
                            <label for="server_address">Server Address</label>
                            <input class="form-control" name="server_address" value="{{old('server_address')}}" placeholder="217.60.254.2" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-12">
                            <label for="router_os_version">Router Version</label>
                            <input class="form-control" name="router_os_version" value="{{old('router_os_version')}}" placeholder="7.12beta" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-12" style="text-align:center;">
                            <input type="submit" class="btn btn-success" value="Save and close" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection