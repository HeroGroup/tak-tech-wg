@extends('layouts.admin.main', ['pageTitle' => 'Limited Peers', 'active' => 'limited-peers'])
@section('content')

<x-loader/>

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
      </div>
    </div>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <th>row</th>
      <th>Comment</th>
      <th>Interface</th>
      <th>Address</th>
      <th>Note</th>
      <th>Expire</th>
      <th>TX (GB)</th>
      <th>RX (GB)</th>
      <th>Total Usage (GB)</th>
      <th>Allowed Traffic (GB)</th>
      <th>Actions</th>
    </thead>
    <tbody>
      <?php $row = 0; $nowTime = time(); $nowDateTime = new DateTime(); ?>
      @foreach($limitedPeers as $peer)
      <tr id="{{$peer->id}}">
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
              $expires_on = new DateTime($peer->activate_date_time);
              $expires_on->add(new DateInterval("P$expire"."D"));
              $time_left = $expires_on->diff($nowDateTime);
              $days_left = $time_left->m*30 + $time_left->d;
              $hours_left = $time_left->h;
              $minutes_left = $time_left->i;
              // $seconds_left = $time_left->s;
              $time_left_to_show = "";
              if ($days_left > 0) {
                $time_left_to_show .= "$days_left days ";
              }
              if ($hours_left > 0) {
                $time_left_to_show .= "$hours_left hours ";
              }
              if ($time_left_to_show == "" && $minutes_left > 0) {
                $time_left_to_show .= "$minutes_left minutes ";
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
        <td>{{$peer->total_usage}}</td>
        <td>{{$peer->peer_allowed_traffic_GB ?? $peer->allowed_traffic_GB}}</td>
        <td>
          <a href="" class="btn btn-sm btn-circle btn-info" data-toggle="modal" data-target="#edit-peer-modal-{{$peer->id}}" title="Edit Allowed Traffic">
            <i class="fa fa-fw fa-pen"></i>
          </a>
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
                        <form method="post" action="{{route('wiregaurd.peers.limited.update')}}" onsubmit="turnOnLoader()">
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
</script>

@endsection