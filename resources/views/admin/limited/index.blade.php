@extends('layouts.admin.main', ['pageTitle' => 'Limited Peers (Last update: ' . $lastUpdate . ')', 'active' => 'peers'])
@section('content')

<x-loader/>
@if(auth()->user()->can_create)
<a href="{{route('wiregaurd.peers.create')}}" class="btn btn-primary btn-icon-split mb-4">
    <span class="icon text-white-50">
        <i class="fas fa-plus"></i>
    </span>
    <span class="text">Create Wiregaurd Peers</span>
</a>
@endif
<div class="row mb-4">
  <div class="col-sm-2">
    <select name="interface" class="form-control" onchange="searchInterface(this.value)">
      <option value="all">All Interfaces</option>
      @foreach($limitedInterfaces as $key=>$value)
      <option value="{{$key}}" @if($key==$interface) selected @endif>{{$value}}</option>
      @endforeach
    </select>
  </div>
  <div class="col-sm-2">
    <select name="peer-status" class="form-control" onchange="searchEnabled(this.value)">
      <option value="all" @if($enabled=="all") selected @endif>All Statuses</option>
      <option value="1" @if($enabled=="1") selected @endif>Enabled</option>
      <option value="0" @if($enabled=="0") selected @endif>Disabled</option>
      <option value="2" @if($enabled=="2") selected @endif>Expired</option>
    </select>
  </div>
  <div class="col-sm-4">
    <input type="text" name="search-comment" id="search-comment" class="form-control" placeholder="search comment, address, note" value="{{$comment}}">
  </div>
  <div class="col-sm-1">
    <button type="button" class="btn btn-sm btn-primary mt-1" onclick="search()">search</button>
  </div>
  
  <div class="col-sm-2" style="padding-top:5px;">
    <a href="#" onclick="clearAllFilters()">clear all filters</a>
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
        <a href="#" onclick="sortResult('expires_in_asc')" class="btn sort-btn @if($sortBy=='expires_in_asc') btn-dark @endif" id="sort_expires_in_asc">Expire <i class="fa fa-sort-amount-down-alt"></i></a>
        <a href="#" onclick="sortResult('expires_in_desc')" class="btn sort-btn @if($sortBy=='expires_in_desc') btn-dark @endif" id="sort_expires_in_desc">Expire <i class="fa fa-sort-amount-down"></i></a>
        <a href="#" onclick="sortResult('expires_in_asc')" class="btn sort-btn @if($sortBy=='total_usage_asc') btn-dark @endif" id="sort_total_usage_asc">Usage <i class="fa fa-sort-amount-down-alt"></i></a>
        <a href="#" onclick="sortResult('total_usage_desc')" class="btn sort-btn @if($sortBy=='total_usage_desc') btn-dark @endif" id="sort_total_usage_desc">Usage <i class="fa fa-sort-amount-down"></i></a>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-12">
    @if(auth()->user()->can_edit)
    <a href="#" data-toggle="modal" data-target="#edit-peers-mass-modal" title="Edit" class="text-info" style="text-decoration:none;">
      <i class="fa fa-fw fa-pen"></i>
      <span>Edit</span>
    </a>
    &nbsp; &nbsp; &nbsp; &nbsp;
    @endif
    @if(auth()->user()->can_enable)
    <a href="#" onclick="toggleEnableMass(1)" title="Enable" class="text-success" style="text-decoration:none;">
      <i class="fa fa-fw fa-toggle-on"></i>
      <span>Enable</span>
    </a>
    &nbsp; &nbsp; &nbsp; &nbsp;
    @endif
    @if(auth()->user()->can_disable)
    <a href="#" onclick="toggleEnableMass(0)" title="Disable" class="text-warning" style="text-decoration:none;">
      <i class="fa fa-fw fa-toggle-off"></i>
      <span>Disable</span>
    </a>
    &nbsp; &nbsp; &nbsp; &nbsp;
    @endif
    @if(auth()->user()->can_regenerate)
    <a href="#" onclick="regenerateMass()" title="regenerate" class="text-info" style="text-decoration:none;">
      <i class="fa fa-fw fa-sync"></i>
      <span>regenerate</span>
    </a>
    @endif
    @if(auth()->user()->can_remove)
    &nbsp; &nbsp; &nbsp; &nbsp;
    <a href="#" onclick="massDelete()" class="text-danger" style="text-decoration:none;">
      <i class="fas fa-trash"></i>
      <span>Delete</span>
    </a>
    @endif
  </div>
</div>

<x-paginator :route="route('wiregaurd.peers.limited.list')" :selectedCount="0" :isLastPage="$isLastPage" />

<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <th>
        <input type="checkbox" id="chk-all" onclick="checkAll()">
      </th>
      <th>row</th>
      <th>Comment</th>
      <th>Interface</th>
      <th>Address</th>
      <th>Note</th>
      <th>Expire</th>
      <th>TX (GB)</th>
      <th>RX (GB)</th>
      <th>Total (GB)</th>
      <th>Allowed Traffic (GB)</th>
      <th>Enabled</th>
      <th>Actions</th>
    </thead>
    <tbody>
      <?php $row = 0; $nowTime = time(); ?>
      @foreach($limitedPeers as $peer)
      <tr id="{{$peer->id}}">
        <td>
          <input type="checkbox" class="chk-row">
        </td>
        <td>{{++$row}}</td>
        <td>{{$peer->comment}}</td>
        <td>{{$peer->name}}</td>
        <td>{{$peer->client_address}}</td>
        <td>{{$peer->note}}</td>
        <td>
        @if($peer->expire_days && $peer->activate_date_time)
          <?php 
            $expire = $peer->expire_days;
            $diff = strtotime($peer->activate_date_time. " + $expire days") - $nowTime; 
            if ($diff > 0) {
              $total_left = explode('.', round($diff / (60 * 60 * 24), 2));
              $days_left = $total_left[0] ?? 0;
              $hours_left = floor(($total_left[1] ?? 0) / 100 * 24);
              $time_left_to_show = "";
              if ($days_left > 0) {
                $time_left_to_show .= "$days_left days ";
              }
              if ($hours_left > 0) {
                $time_left_to_show .= "$hours_left hours";
              }
            }
          ?>
          @if($diff > 0)
            @if($days_left > 15)
            <div class="badge badge-success">{{$time_left_to_show}}</div>
            @elseif($days_left > 8)
            <div class="badge badge-info">{{$time_left_to_show}}</div>
            @else
            <div class="badge badge-warning">{{$time_left_to_show}}</div>
            @endif
          @else
            <div class="badge badge-danger">expired</div>
          @endif
          @else
          -
          @endif
        </td>
        <td>{{$peer->tx}}</td>
        <td>{{$peer->rx}}</td>
        <td>
          <?php 
            $total = $peer->total_usage; 
            $max = $peer->peer_allowed_traffic_GB ?? $peer->allowed_traffic_GB;
          ?>
          @if($total >= $max)
            <span class="badge badge-danger">{{$total}}</span>
          @elseif($total >= $max*0.75)
            <span class="badge badge-warning">{{$total}}</span>
          @else
            <span class="badge badge-info">{{$total}}</span>
          @endif
        </td>
        <td>{{$max}}</td>
        <td style="font-size: 10px;">
          @if(auth()->user()->can_enable || auth()->user()->can_disable)
          <label class="switch">
            <input type="checkbox" name="Enabled" id="enabled_{{$peer->id}}" @if($peer->is_enabled) checked @endif onchange="toggleEnable('{{$peer->id}}', '{{$peer->comment}}', this.checked)">
            <span class="slider round"></span>
          </label>
          @endif
        </td>
        <td>
          <button class="btn">
            <div class="dropdown no-arrow show">
              <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                  <i class="fas fa-ellipsis-h fa-fw text-gray-700"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink" x-placement="bottom-end" style="position: absolute; transform: translate3d(-158px, 19px, 0px); top: 0px; left: 0px; will-change: transform;">
                  <div class="dropdown-header">Actions</div>
                  <a href="{{route('wiregaurd.peers.limited.usageStatistics', $peer->id)}}" target="_blank" class="dropdown-item text-warning">
                    <i class="fa fa-fw fa-chart-line"></i> Usage Statistics
                  </a>
                  @if(auth()->user()->can_edit)
                  <a href="#" class="dropdown-item text-info" data-toggle="modal" data-target="#edit-peer-modal-{{$peer->id}}">
                    <i class="fa fa-fw fa-pen"></i> Edit
                  </a>
                  @endif
                  @if(auth()->user()->can_regenerate)
                  <a href="#" onclick="regenerateSingle('{{$peer->id}}')" class="dropdown-item text-primary">
                    <i class="fa fa-fw fa-sync"></i> Regenerate
                  </a>
                  @endif
                  @if(auth()->user()->can_remove)
                  <a href="#" class="dropdown-item text-danger" title="Delete" onclick="destroy('{{route('wiregaurd.peers.remove')}}','{{$peer->id}}','{{$peer->id}}')">
                    <i class="fas fa-trash"></i> Remove
                  </a>
                  @endif
              </div>
            </div>
          </button>
          @if($peer->conf_file && $peer->qrcode_file)
          <a href="{{route('wiregaurd.peers.download',$peer->id)}}" class="btn btn-circle btn-sm btn-success" title="Download config">
            <i class="fa fa-fw fa-download"></i>
          </a>
          @endif
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
                                <div class="col-md-6">
                                    <label for="peer_allowed_traffic_GB">Allowed Traffic (GB)</label>
                                    <input type="number" step="0.5" class="form-control" name="peer_allowed_traffic_GB" value="{{$peer->peer_allowed_traffic_GB}}">
                                </div>
                            </div>
                            <div class="form-group row mb-4">
                                <div class="col-md-6">
                                    <label for="endpoint_address">Endpoint Address</label>
                                    <input class="form-control" name="endpoint_address" value="{{$peer->endpoint_address}}" placeholder="s1.yourdomain.com">
                                </div>
                                <div class="col-md-6">
                                    <label for="dns">DNS</label>
                                    <input class="form-control" name="dns" value="{{$peer->dns}}" placeholder="192.168.200.1">
                                </div>
                            </div>
                            <div class="form-group row mb-4">
                                <div class="col-md-6">
                                    <label for="comment">Comment</label>
                                    <input class="form-control" name="comment" value="{{$peer->comment}}">
                                </div>
                                <div class="col-md-6">
                                    <label for="note">Note</label>
                                    <input class="form-control" name="note" value="{{$peer->note}}">
                                </div>
                            </div>
                            <div class="form-group row mb-4">
                                <div class="col-md-6">
                                    <label for="expire_days">Expires after (days)</label>
                                    <input type="number" class="form-control" name="expire_days" value="{{$peer->expire_days}}">
                                </div>
                                <div class="col-md-3">
                                    <label for="activate_date">Starting From Date</label>
                                    <input type="date" class="form-control" name="activate_date" value="{{substr($peer->activate_date_time,0,10)}}">
                                </div>
                                <div class="col-md-3">
                                    <label for="activate_time">Starting From Time</label>
                                    <input type="time" class="form-control" name="activate_time" value="{{substr($peer->activate_date_time,11,5)}}">
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

<!-- Edit peers Mass Modal -->
<div class="modal fade" id="edit-peers-mass-modal" tabindex="-1" role="dialog" aria-labelledby="editPeersMassModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editPeersMassModalLabel">Edit Peers</h5>
        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row mb-4">
            <div class="col-md-6">
                <label for="mass_peer_allowed_traffic_GB">Allowed Traffic (GB)</label>
                <input type="number" step="0.5" class="form-control" name="mass_peer_allowed_traffic_GB" id="mass_peer_allowed_traffic_GB">
            </div>
        </div>
        <div class="form-group row mb-4">
          <div class="col-md-6">
            <label for="mass_endpoint_address">Endpoint Address</label>
            <input class="form-control" name="mass_endpoint_address" id="mass_endpoint_address">
          </div>
          <div class="col-md-6">
            <label for="mass_dns">DNS</label>
            <input class="form-control" name="mass_dns" id="mass_dns">
          </div>
        </div>
        <div class="form-group row mb-4">
          <div class="col-md-6">
            <label for="mass_expire_days">Expires after (days)</label>
            <input class="form-control" name="mass_expire_days" id="mass_expire_days">
          </div>
          <div class="col-md-3">
            <label for="mass_activate_date">Starting From Date</label>
            <input type="date" class="form-control" name="mass_activate_date" id="mass_activate_date">
          </div>
          <div class="col-md-3">
            <label for="mass_activate_time">Starting From Time</label>
            <input type="time" class="form-control" name="mass_activate_time" id="mass_activate_time">
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
  function clearAllFilters() {
    window.location.href = "{{route('wiregaurd.peers.limited.list')}}";
  }
  function sortResult(sortBy) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    var comment = urlParams.get('comment');
    var interface = urlParams.get('interface');
    var enabled = urlParams.get('enabled');
    
    window.location.href = "{{route('wiregaurd.peers.limited.list')}}" + 
                `?interface=${interface || ''}` + 
                `&comment=${comment || ''}` + 
                `&enabled=${enabled || ''}` + 
                `&sortBy=${sortBy}`;
  }
  function searchInterface(val, clear=false) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    var comment = urlParams.get('comment');
    var enabled = urlParams.get('enabled');
    var sortBy = urlParams.get('sortBy');

    var url = "{{route('wiregaurd.peers.limited.list')}}" + `?comment=${comment || ''}` + `&enabled=${enabled || 'all'}` + `&sortBy=${sortBy || ''}`;
    if(clear) {
      window.location.href = url;
    } else {
      window.location.href = url + `&interface=${val}`;
      // $('#dataTable').DataTable().column(2).search(`^${val}$`, true).draw();
    }
  }
  function search() {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    var interface = urlParams.get('interface');
    var enabled = urlParams.get('enabled');
    var sortBy = urlParams.get('sortBy');
    var comment = document.getElementById("search-comment");

    window.location.href = "{{route('wiregaurd.peers.limited.list')}}" + `?interface=${interface || ''}` + `&comment=${comment.value}` + `&enabled=${enabled || 'all'}` + `&sortBy=${sortBy || ''}`;
  }
  function searchEnabled(val) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    var comment = urlParams.get('comment');
    var interface = urlParams.get('interface');
    var sortBy = urlParams.get('sortBy');
    
    window.location.href = "{{route('wiregaurd.peers.limited.list')}}" + `?interface=${interface || ''}` + `&comment=${comment || ''}` + `&enabled=${val}` + `&sortBy=${sortBy || ''}`;
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
      route: "{{route('wiregaurd.peers.remove.mass')}}",
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
    var ids = checkedItems();
    if (ids.length == 0) {
      return;
    }

    turnOnLoader();

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
    var ids = checkedItems();
    if (ids.length == 0) {
      return;
    }

    turnOnLoader();

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

    var ids = checkedItems();
    if (ids.length == 0) {
      return;
    }

    turnOnLoader();
    
    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'PUT',
      'ids': JSON.stringify(ids),
      'dns': document.getElementById('mass_dns').value,
      'endpoint_address': document.getElementById('mass_endpoint_address').value,
      'expire_days': document.getElementById('mass_expire_days').value,
      'activate_date': document.getElementById('mass_activate_date').value,
      'activate_time': document.getElementById('mass_activate_time').value,
      'peer_allowed_traffic_GB': document.getElementById('mass_peer_allowed_traffic_GB').value,
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