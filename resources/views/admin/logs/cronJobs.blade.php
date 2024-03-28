@extends('layouts.admin.main', ['pageTitle' => 'Cron Jobs Logs', 'active' => 'logs'])
@section('content')
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
@endsection