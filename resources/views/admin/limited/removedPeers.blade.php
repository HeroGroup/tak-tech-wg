@extends('layouts.admin.main', ['pageTitle' => 'Limited Databse', 'active' => 'peers'])
@section('content')

<div class="row mb-4">
  <div class="col-sm-2">
    <select name="interface" class="form-control" onchange="searchInterface(this.value)">
      <option value="all">All Interfaces</option>
      @foreach($limitedInterfaces as $key=>$value)
      <option value="{{$key}}" @if($key==$interface) selected @endif>{{$value}}</option>
      @endforeach
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

<x-paginator :route="route('wiregaurd.peers.limited.removedPeers')" :selectedCount="0" :isLastPage="$isLastPage" />

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
      <th>Removed</th>
      <th>TX (GB)</th>
      <th>RX (GB)</th>
      <th>Total (GB)</th>
      <th>Allowed Traffic (GB)</th>
      <th></th>
    </thead>
    <tbody>
      <?php $row = 0; $nowTime = time(); $nowDateTime = new DateTime(); ?>
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
        <td>
          <a href="{{route('wiregaurd.peers.limited.usageStatistics', $peer->id)}}" target="_blank" class="text-success">
            <i class="fa fa-fw fa-chart-line"></i>
          </a>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>

<script>
  var baseRoute = "{{route('wiregaurd.peers.limited.removedPeers')}}";
  function searchBase(set={}) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    urlParams.delete('page');

    var params = Object.keys(set);
    params.forEach(key => {
      urlParams.set(key, set[key]);
    });

    window.location.href = `${baseRoute}?${urlParams.toString()}`;
  }
  function clearAllFilters() {
    searchBase();
  }
  function sortResult(sortBy) {
    searchBase({ 'sortBy': sortBy });
  }
  function searchInterface(val, clear=false) {
    searchBase({ 'interface': val });
  }
  function search() {
    searchBase({ 'comment': document.getElementById("search-comment").value });
  }
</script>

@endsection