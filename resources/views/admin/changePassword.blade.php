@extends('layouts.admin.main', ['pageTitle' => 'Change Password', 'active' => 'users'])
@section('content')
  <div class="row">
    <div class="col-lg-12">
      <div class="card shadow mb-4">
          <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-primary">Change Password</h6>
          </div>
          <div class="card-body">
            <form method="post" action="{{route('users.updatePassword')}}">
              @csrf
              <input type="hidden" name="_method" value="PUT" />
              <div class="form-group row">
                  <label for="current_password" class="col-sm-4 control-label">Current Password</label>
                  <div class="col-sm-8">
                      <input type="password" class="form-control" id="current_password" name="current_password" required>
                  </div>
              </div>
              <div class="form-group row">
                  <label for="password" class="col-sm-4 control-label">New Password</label>
                  <div class="col-sm-8">
                      <input type="password" class="form-control" id="password" name="password" required>
                  </div>
              </div>
              <div class="form-group row">
                  <label for="password_confirmation" class="col-sm-4 control-label">Confirm Password</label>
                  <div class="col-sm-8">
                      <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                  </div>
              </div>
              <div class="form-group row">
                  <div class="col-sm-12 text-right">
                      <button type="submit" class="btn btn-success">submit</button>
                  </div>
              </div>
            </form>
          </div>
      </div>
    </div>
  </div>
@endsection
