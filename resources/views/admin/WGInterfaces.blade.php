@extends('layouts.admin.main', ['pageTitle' => 'Wiregaurd Interfaces', 'active' => 'interfaces'])
@section('content')

<x-loader/>

<a href="#" class="btn btn-primary btn-icon-split mb-4" data-toggle="modal" data-target="#new-interface-modal">
    <span class="icon text-white-50">
        <i class="fas fa-plus"></i>
    </span>
    <span class="text">Add Wiregaurd Interface</span>
</a>

<div class="table-responsive">
  <table class="table table-striped" id="dataTable">
    <thead>
      <th>Row</th>
      <th>Name</th>
      <th>Default Endpoint</th>
      <th>DNS</th>
      <th>IP Range</th>
      <th>MTU</th>
      <th>Listen port</th>
      <th>Peers</th>
      <th>Actions</th>
    </thead>
    <tbody>
    <?php $row = 0; ?>
    @foreach($interfaces as $interface)
      <tr id="{{$interface->id}}">
        <td>{{++$row}}</td>
        <td>{{$interface->name}}</td>
        <td>{{$interface->default_endpoint_address}}</td>
        <td>{{$interface->dns}}</td>
        <td>{{$interface->ip_range}}</td>
        <td>{{$interface->mtu}}</td>
        <td>{{$interface->listen_port}}</td>
        <td>
            <a href="/wiregaurd/peers?wiregaurd={{$interface->id}}">
                {{\Illuminate\Support\Facades\DB::table('peers')->where('interface_id', $interface->id)->count()}}
            </a>
        </td>
        <td>
          <a href="#" class="btn btn-info btn-circle btn-sm" data-toggle="modal" data-target="#edit-interface-modal-{{$interface->id}}" title="Edit">
                <i class="fas fa-pen"></i>
            </a>
            &nbsp;

            <!-- Edit interface Modal -->
            <div class="modal fade" id="edit-interface-modal-{{$interface->id}}" tabindex="-1" role="dialog" aria-labelledby="editinterfaceModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editinterfaceModalLabel">Edit interface</h5>
                            <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form method="post" action="{{route('admin.wiregaurd.interfaces.update')}}" onsubmit="turnOnLoader()">
                                @csrf
                                <input type="hidden" name="_method" value="PUT">
                                <input type="hidden" name="id" value="{{$interface->id}}">
                                <div class="form-group row mb-4">
                                    <div class="col-md-12">
                                        <label for="default_endpoint_address">Default Endpoint</label>
                                        <input class="form-control" name="default_endpoint_address" value="{{$interface->default_endpoint_address}}" placeholder="s1.yourdomain.com">
                                    </div>
                                </div>
                                <div class="form-group row mb-4">
                                    <div class="col-md-12">
                                        <label for="dns">Wiregaurd DNS</label>
                                        <input class="form-control" name="dns" value="{{$interface->dns}}" placeholder="192.168.200.1">
                                    </div>
                                </div>
                                <div class="form-group row mb-4">
                                    <div class="col-md-12">
                                        <label for="ip_range">IPv4 Address Range</label>
                                        <input class="form-control" name="ip_range" value="{{$interface->ip_range}}" placeholder="192.168.200.">
                                    </div>
                                </div>
                                <div class="form-group row mb-4">
                                    <div class="col-md-12">
                                        <label for="mtu">MTU</label>
                                        <input class="form-control" name="mtu" value="{{$interface->mtu}}" placeholder="1440">
                                    </div>
                                </div>
                                <div class="form-group row mb-4">
                                    <div class="col-md-12">
                                        <label for="listen_port">listen port</label>
                                        <input class="form-control" name="listen_port" value="{{$interface->listen_port}}" placeholder="">
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
            <a href="#" class="btn btn-danger btn-circle btn-sm" title="Delete" onclick="destroy('{{route('admin.wiregaurd.interfaces.delete')}}','{{$interface->id}}','{{$interface->id}}')">
                <i class="fas fa-trash"></i>
            </a>
            &nbsp;
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>

<div class="modal fade" id="new-interface-modal" tabindex="-1" role="dialog" aria-labelledby="newinterfaceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newinterfaceModalLabel">Add new interface</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="{{route('admin.wiregaurd.interfaces.add')}}" onsubmit="turnOnLoader()">
                    @csrf
                    <div class="form-group row mb-4">
                        <div class="col-md-12">
                            <label for="name">Interface Name</label>
                            <input class="form-control" name="name" placeholder="WG-S" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-12">
                            <label for="default_endpoint_address">Default Endpoint</label>
                            <input class="form-control" name="default_endpoint_address" placeholder="s1.yourdomain.com" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-12">
                            <label for="dns">Wiregaurd DNS</label>
                            <input class="form-control" name="dns" placeholder="192.168.200.1" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-12">
                            <label for="ip_range">IPv4 Address Range</label>
                            <input class="form-control" name="ip_range" placeholder="192.168.200." required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-12">
                            <label for="mtu">MTU</label>
                            <input class="form-control" name="mtu" placeholder="1440" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-12">
                            <label for="listen_port">listen port</label>
                            <input class="form-control" name="listen_port" required>
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