@extends('layouts.admin.main', ['pageTitle' => 'Cron Jobs Logs', 'active' => 'logs'])
@section('content')
<div class="row">
    <div class="col-md-3">
        <select name="type" class="form-control" onchange="searchCronJobs(this.value)">
            <option value="all" @if($type=="all") selected @endif>All cron jobs</option>
            <option value="store-interfaces-usages" @if($type=="store-interfaces-usages") selected @endif>store interfaces usages</option>
            <option value="store-peers-usages" @if($type=="store-peers-usages") selected @endif>store peers usages</option>
            <option value="syncAll" @if($type=="syncAll") selected @endif>sync all</option>
            <option value="disable-expired-peers" @if($type=="disable-expired-peers") selected @endif>disable expired peers</option>
            <option value="remove-limited-expired-peers" @if($type=="remove-limited-expired-peers") selected @endif>remove limited expired peers</option>
        </select>
    </div>
</div>
<div class="row">
  @foreach($jobs as $job)
  <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6>{{$job->cron_name}}</h6>
                <h6>{{$job->created_at}}</h6>
            </div>
            <div class="card-body">
                <div>{{$job->cron_result}}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<script>
    function searchCronJobs(val) {
        var url = "{{route('admin.logs.cronJobs')}}";
        window.location.href = url + `?type=${val}`;
    }
</script>
@endsection