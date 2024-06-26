@extends('layouts.admin.main', ['pageTitle' => 'Wiregaurd Interfaces', 'active' => 'interfaces'])
@section('content')

<x-loader/>
@if(auth()->user()->is_admin)
<div class="row mb-4">
  <div class="col-md-6">
    <a href="{{route('wiregaurd.interfaces.usages')}}" class="btn">
      <i class="fa fa-fw fa-columns"></i>
    </a>
    <a href="#" class="btn btn-dark">
      <i class="fa fa-fw fa-list"></i>
    </a>
  </div>
  <div class="col-md-6 text-right">
    <a href="#" class="btn btn-primary btn-icon-split" data-toggle="modal" data-target="#new-interface-modal">
      <span class="icon text-white-50">
        <i class="fas fa-plus"></i>
      </span>
      <span class="text">Add Wiregaurd Interface</span>
    </a>
  </div>
</div>
@endif

<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <th>Row</th>
      <th>Name</th>
      <th>Default Endpoint</th>
      <th>DNS</th>
      <th>IP Range</th>
      <th>Listen port</th>
      <th>iType</th>
      <th>Allowed Traffic (GB)</th>
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
        <td>{{$interface->listen_port}}</td>
        <td>{{$interface->iType}}</td>
        <td>{{$interface->allowed_traffic_GB}}</td>
        <td>
            <a href="/wiregaurd/peers?wiregaurd={{$interface->id}}">
                {{\Illuminate\Support\Facades\DB::table('peers')->where('interface_id', $interface->id)->count()}}
            </a>
        </td>
        <td>
            <div class="dropdown no-arrow show">
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                    <i class="fas fa-ellipsis-h fa-fw text-gray-700"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink" x-placement="bottom-end" style="position: absolute; transform: translate3d(-158px, 19px, 0px); top: 0px; left: 0px; will-change: transform;">
                    <div class="dropdown-header">Actions</div>
                    <a href="#" class="dropdown-item text-info" data-toggle="modal" data-target="#edit-interface-modal-{{$interface->id}}">
                        <i class="fas fa-pen"></i> Edit
                    </a>
                    <a href="{{route('wiregaurd.interfaces.usages.monitor',$interface->id)}}" class="dropdown-item text-success">
                        <i class="fas fa-tv"></i> Monitor
                    </a>
                    <a href="#" class="dropdown-item text-danger" onclick="destroy('{{route('admin.wiregaurd.interfaces.delete')}}','{{$interface->id}}','{{$interface->id}}')">
                        <i class="fas fa-trash"></i> Remove
                    </a>
                </div>
            </div>
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
                                    <div class="col-md-6">
                                        <label for="name">Name</label>
                                        <input class="form-control" name="name" value="{{$interface->name}}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="default_endpoint_address">Default Endpoint</label>
                                        <input class="form-control" name="default_endpoint_address" value="{{$interface->default_endpoint_address}}" placeholder="s1.yourdomain.com">
                                    </div>
                                </div>
                                <div class="form-group row mb-4">
                                    <div class="col-md-6">
                                        <label for="dns">Wiregaurd DNS</label>
                                        <input class="form-control" name="dns" value="{{$interface->dns}}" placeholder="192.168.200.1">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ip_range">IPv4 Address Range</label>
                                        <input class="form-control" name="ip_range" value="{{$interface->ip_range}}" placeholder="192.168.200.">
                                    </div>
                                </div>
                                <div class="form-group row mb-4">
                                    <div class="col-md-6">
                                        <label for="mtu">MTU</label>
                                        <input class="form-control" name="mtu" value="{{$interface->mtu}}" placeholder="1440">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="listen_port">listen port</label>
                                        <input class="form-control" name="listen_port" value="{{$interface->listen_port}}">
                                    </div>
                                </div>
                                <div class="form-group row mb-4">
                                    <div class="col-md-6">
                                        <label for="iType">iType</label>
                                        <select name="iType">
                                            <option key="unlimited" @if($interface->iType=='unlimited') selected @endif>unlimited</option>
                                            <option key="limited" @if($interface->iType=='limited') selected @endif>limited</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="allowed_traffic_GB">Allowed Traffic (GB)</label>
                                        <input type="number" class="form-control" name="allowed_traffic_GB" value="{{$interface->allowed_traffic_GB}}" step="0.5">
                                    </div>
                                </div>
                                <div class="form-group row mb-4">
                                    <div class="col-md-6">
                                        <input type="checkbox" name="exclude_from_block" id="exclude_from_block" @if($interface->exclude_from_block) checked="checked" @endif>
                                        <label for="exclude_from_block">Exclude From Blocking Peers</label>
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
                        <div class="col-md-6">
                            <label for="name">Interface Name</label>
                            <input class="form-control" name="name" placeholder="WG-S" required>
                        </div>
                        <div class="col-md-6">
                            <label for="default_endpoint_address">Default Endpoint</label>
                            <input class="form-control" name="default_endpoint_address" placeholder="s1.yourdomain.com" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="dns">Wiregaurd DNS</label>
                            <input class="form-control" name="dns" placeholder="192.168.200.1" required>
                        </div>
                        <div class="col-md-6">
                            <label for="ip_range">IPv4 Address Range</label>
                            <input class="form-control" name="ip_range" placeholder="192.168.200." required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="mtu">MTU</label>
                            <input class="form-control" name="mtu" placeholder="1440" required>
                        </div>
                        <div class="col-md-6">
                            <label for="listen_port">listen port</label>
                            <input class="form-control" name="listen_port" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="iType">iType</label>
                            <select name="iType">
                                <option key="unlimited">unlimited</option>
                                <option key="limited">limited</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="allowed_traffic_GB">Allowed Traffic (GB)</label>
                            <input type="number" class="form-control" name="allowed_traffic_GB" step="0.5">
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <input type="checkbox" name="exclude_from_block" id="exclude_from_block" >
                            <label for="exclude_from_block">Exclude From Blocking Peers</label>
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