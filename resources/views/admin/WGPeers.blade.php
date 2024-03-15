@extends('layouts.admin.main', ['pageTitle' => 'List of Peers', 'active' => 'peers'])
@section('content')

<x-loader/>

<a href="{{route('wiregaurd.peers.create')}}" class="btn btn-primary btn-icon-split mb-4">
    <span class="icon text-white-50">
        <i class="fas fa-plus"></i>
    </span>
    <span class="text">Create Wiregaurd Peers</span>
</a>

<div class="row">
  <div class="col-sm-4">
    <select name="wginterface" class="form-control" onchange="searchInterface(this.value)">
      <option value="">select interface...</option>
      @foreach($interfaces as $key=>$value)
      <option value="{{$key}}" @if($key==$interface) selected @endif>{{$value}}</option>
      @endforeach
    </select>
  </div>
  <div class="col-sm-2">
    <a href="#" onclick="searchInterface('', true)">clear</a>
  </div>
  <div class="col-sm-4">
    <input type="text" name="search-comment" id="search-comment" class="form-control" placeholder="search by comment or peer" value="{{$comment}}">
  </div>
  <div class="col-sm-2">
    <button type="button" class="btn btn-sm btn-primary" onclick="search()">search</button>
  </div>
</div>

<div class="mb-4">
  <a href="#" data-toggle="modal" data-target="#edit-peers-mass-modal" title="Edit" class="text-info" style="text-decoration:none;">
    <i class="fa fa-fw fa-pen"></i>
    <span>Edit</span>
  </a>
  &nbsp; &nbsp; &nbsp; &nbsp;
  <a href="#" onclick="toggleEnableMass(1)" title="Enable" class="text-success" style="text-decoration:none;">
    <i class="fa fa-fw fa-toggle-on"></i>
    <span>Enable</span>
  </a>
  &nbsp; &nbsp; &nbsp; &nbsp;
  <a href="#" onclick="toggleEnableMass(0)" title="Disable" class="text-warning" style="text-decoration:none;">
    <i class="fa fa-fw fa-toggle-off"></i>
    <span>Disable</span>
  </a>
  &nbsp; &nbsp; &nbsp; &nbsp;
  <a href="#" onclick="regenerateMass()" title="regenerate" class="text-info" style="text-decoration:none;">
    <i class="fa fa-fw fa-sync"></i>
    <span>regenerate</span>
  </a>
  @if(auth()->user()->is_admin)
  &nbsp; &nbsp; &nbsp; &nbsp;
  <a href="#" onclick="massDelete()" class="text-danger" style="text-decoration:none;">
    <i class="fas fa-trash"></i>
    <span>Delete</span>
  </a>
  @endif
</div>

<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <th>
        <input type="checkbox" id="chk-all" onclick="checkAll()">
      </th>
      <th>row</th>
      <th>Comment</th>
      <th>Interface</th>
      <th>Peer</th>
      <th>Enabled</th>
      <th>Actions</th>
    </thead>
    <tbody>
      <?php $row = 0; ?>
    @foreach($peers as $peer)
      <tr id="{{$peer->id}}">
        <td>
          <input type="checkbox" class="chk-row">
        </td>
        <td>{{++$row}}</td>
        <td>{{$peer->comment}}</td>
        <td>{{\Illuminate\Support\Facades\DB::table('interfaces')->find($peer->interface_id)->name}}</td>
        <td>{{$peer->client_address}}</td>
        <td>
            <!-- <span> Disable </span> -->
            <label class="switch">
                <input type="checkbox" name="Enabled" id="enabled_{{$peer->id}}" @if($peer->is_enabled) checked @endif onchange="toggleEnable('{{$peer->id}}', '{{$peer->comment}}', this.checked)">
                <span class="slider round"></span>
            </label>
            <!-- <span> Enable </span> -->
        </td>
        <td>
          @if($peer->conf_file && $peer->qrcode_file)
          <a href="{{route('wiregaurd.peers.download',$peer->id)}}" class="btn btn-sm btn-success btn-circle">
            <i class="fa fa-fw fa-download"></i>
          </a>
          &nbsp;
          @endif
          <a href="#" class="btn btn-sm btn-info btn-circle" data-toggle="modal" data-target="#edit-peer-modal-{{$peer->id}}" title="edit">
            <i class="fa fa-fw fa-pen"></i>
          </a>
          &nbsp;
          <!-- Edit peer Modal -->
          <div class="modal fade" id="edit-peer-modal-{{$peer->id}}" tabindex="-1" role="dialog" aria-labelledby="editpeerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editpeerModalLabel">Edit peer</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="{{route('wiregaurd.peers.update')}}" onsubmit="turnOnLoader()">
                            @csrf
                            <input type="hidden" name="_method" value="PUT">
                            <input type="hidden" name="id" value="{{$peer->id}}">
                            <div class="form-group row mb-4">
                                <div class="col-md-12">
                                    <label for="endpoint_address">Endpoint Address</label>
                                    <input class="form-control" name="endpoint_address" value="{{$peer->endpoint_address}}" placeholder="s1.yourdomain.com">
                                </div>
                            </div>
                            <div class="form-group row mb-4">
                                <div class="col-md-12">
                                    <label for="dns">DNS</label>
                                    <input class="form-control" name="dns" value="{{$peer->dns}}" placeholder="192.168.200.1">
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
          <a href="#" onclick="regenerateSingle('{{$peer->id}}')" class="btn btn-sm btn-primary btn-circle" title="regenerate">
            <i class="fa fa-fw fa-sync"></i>
          </a>
          @if(auth()->user()->is_admin)
          &nbsp;
          <a href="#" class="btn btn-sm btn-danger btn-circle" title="Delete" onclick="destroy('{{route('admin.wiregaurd.peers.remove')}}','{{$peer->id}}','{{$peer->id}}')">
            <i class="fas fa-trash"></i>
          </a>
          @endif
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
{{ $peers->links() }}

<!-- Edit peers Mass Modal -->
<div class="modal fade" id="edit-peers-mass-modal" tabindex="-1" role="dialog" aria-labelledby="editPeersMassModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editPeersMassModalLabel">Edit peer</h5>
        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row mb-4">
          <div class="col-md-12">
            <label for="mass_endpoint_address">Endpoint Address</label>
            <input class="form-control" name="mass_endpoint_address" id="mass_endpoint_address" placeholder="s1.yourdomain.com">
          </div>
        </div>
        <div class="form-group row mb-4">
          <div class="col-md-12">
            <label for="mass_dns">DNS</label>
            <input class="form-control" name="mass_dns" id="mass_dns" placeholder="192.168.200.1">
          </div>
        </div>
        <div class="form-group row mb-4">
            <div class="col-md-12" style="text-align:center;">
                <button onclick="updateMass()" class="btn btn-success">Save and close</button>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  window.addEventListener('load', function () {
    turnOffLoader();
  });
  function searchInterface(val, clear=false) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    var comment = urlParams.get('comment');
    if(clear) {
      window.location.href = "{{route('wiregaurd.peers.index')}}" + `?comment=${comment || ''}`;
    } else {
      window.location.href = "{{route('wiregaurd.peers.index')}}" + `?wiregaurd=${val}` + `&comment=${comment || ''}`;
      // $('#dataTable').DataTable().column(2).search(`^${val}$`, true).draw();
    }
  }
  function search() {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    var wiregaurd = urlParams.get('wiregaurd');
    var comment = document.getElementById("search-comment");
    window.location.href = "{{route('wiregaurd.peers.index')}}" + `?wiregaurd=${wiregaurd || ''}` + `&comment=${comment.value}`;
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
  function checkAll() {
    $('.chk-row:checkbox').prop('checked', $('#chk-all').prop('checked'));
  }
  function checkedItems() {
    var ids = [];
    var x = $('.chk-row:checkbox:checked');
    for (var i=0; i<x.length;i++) {
      ids.push(x[i].parentElement.parentElement.id);
    }
    
    if (ids.length == 0) {
      Swal.fire({
          position: 'top-end',
          icon: 'warning',
          title: 'No items are selected',
          showConfirmButton: false,
          timer: 1500
        });
    }

    return ids;
  }
  function massDelete() {
    turnOnLoader();
    var ids = checkedItems();
    if (ids.length == 0) {
      return;
    }

    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'DELETE',
      'ids': JSON.stringify(ids)
    });
    
    var params = {
      method: 'POST',
      route: "{{route('admin.wiregaurd.peers.remove.mass')}}",
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
  function toggleEnableMass(status) {
    turnOnLoader();
    var ids = checkedItems();
    if (ids.length == 0) {
      return;
    }

    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'PUT',
      'ids': JSON.stringify(ids)
    });

    var params = {
      method: 'POST',
      route: status ? "{{route('wiregaurd.peers.enable.mass')}}" : "{{route('wiregaurd.peers.disable.mass')}}",
      formData,
      successCallback: reloadWithTimeout,
      failCallback: turnOffLoader,
    };

    sendRequest(params);
  }
  function regenerateSingle(id) {
    turnOnLoader();
    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      'id': id
    });
    
    var params = {
      method: 'POST',
      route: "{{route('wiregaurd.peers.regenerate')}}",
      formData,
      successCallback: reloadWithTimeout,
      failCallback: turnOffLoader,
    };

    sendRequest(params);
  }
  function regenerateMass() {
    turnOnLoader();
    var ids = checkedItems();
    if (ids.length == 0) {
      return;
    }

    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      'ids': JSON.stringify(ids)
    });

    var params = {
      method: 'POST',
      route: "{{route('wiregaurd.peers.regenerate.mass')}}",
      formData,
      // successCallback: reloadWithTimeout,
      failCallback: turnOffLoader,

    };

    sendRequest(params);
  }
  function updateMass() {
    turnOnLoader();
    var ids = checkedItems();
    if (ids.length == 0) {
      return;
    }

    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'PUT',
      'ids': JSON.stringify(ids),
      'dns': document.getElementById('mass_dns').value,
      'endpoint_address': document.getElementById('mass_endpoint_address').value,
    });

    var params = {
      method: 'POST',
      route: "{{route('wiregaurd.peers.update.mass')}}",
      formData,
      successCallback: reloadWithTimeout,
      failCallback: turnOffLoader,
    };

    sendRequest(params);
  }
  function reloadWithTimeout() {
    setTimeout(function() {
      location.reload();
    }, 
    2000);
  }
</script>
@endsection