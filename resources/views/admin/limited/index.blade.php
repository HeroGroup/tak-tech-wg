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
      <th>TX</th>
      <th>RX</th>
      <th>Total Usage</th>
    </thead>
    <tbody>
      <?php $row = 0; $nowTime = time(); $nowDateTime = new DateTime(); ?>
      @foreach($limitedPeers as $peer)
      <tr id="{{$peer->id}}">
        <td>{{++$row}}</td>
        <td>{{$peer->comment}}</td>
        <td>{{\Illuminate\Support\Facades\DB::table('interfaces')->find($peer->interface_id)->name}}</td>
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
      </tr>
    @endforeach
    </tbody>
  </table>
</div>

<script>
  function searchInterface(val, clear=false) {
    var queryString = window.location.search;

    var url = "{{route('wiregaurd.peers.limited.list')}}";
    if(clear) {
      window.location.href = url;
    } else {
      window.location.href = url + `?interface=${val}`;
      // $('#dataTable').DataTable().column(2).search(`^${val}$`, true).draw();
    }
  }
</script>

@endsection