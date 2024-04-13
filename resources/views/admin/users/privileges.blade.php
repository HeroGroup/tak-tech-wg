@extends('layouts.admin.main', ['pageTitle' => $user->email . ' Privileges', 'active' => 'users'])
@section('content')

<div class="row">
  <div class="col-md-6">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Peers</h6>
      </div>
      <div class="card-body">
        <table id="dataTable" class="table table-striped">
          
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Privileges</h6>
      </div>
      <div class="card-body"></div>
    </div>
  </div>
</div>

@endsection