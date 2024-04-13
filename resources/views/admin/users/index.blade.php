@extends('layouts.admin.main', ['pageTitle' => 'Users', 'active' => 'users'])
@section('content')

<x-loader/>

<a href="#" class="btn btn-primary btn-icon-split mb-4" data-toggle="modal" data-target="#new-user-modal">
    <span class="icon text-white-50">
        <i class="fas fa-plus"></i>
    </span>
    <span class="text">Create User</span>
</a>

<div class="table-responsive">
  <table class="table table-striped" id="dataTable">
    <thead>
      <th>Name</th>
      <th>Email</th>
      <th>Type</th>
      <th>Interface Access</th>
      <th>Peers</th>
      <th>Active</th>
      <th>Actions</th>
    </thead>
    <tbody>
      @foreach($users as $user)
      <tr id="{{$user->id}}">
        <?php 
          $userInterfaces = \Illuminate\Support\Facades\DB::table('user_interfaces')->where('user_id', $user->id)->join('interfaces', 'user_interfaces.interface_id', '=', 'interfaces.id')->get();
          $x = array_column($userInterfaces->toArray(), 'interface_id');
          $user_privileges = DB::table('user_privileges')->where('user_id', $user->id)->pluck('action')->toArray();
        ?>
        <td>{{$user->name}}</td>
        <td>{{$user->email}}</td>
        <td>{{$user->user_type}}</td>
        <td>
          @if($user->is_admin)
          All
          @else
          <ul style="list-style-type:none; padding: 0">
            @foreach ($userInterfaces as $userInterface)
            <li>{{$userInterface->name}}</li>
            @endforeach
          </ul>
          @endif
        </td>
        <td>{{\Illuminate\Support\Facades\DB::table('peers')->whereIn('interface_id', array_unique($x))->count()}}</td>
        <td>
          <label class="switch">
            <input type="checkbox" name="is_active_{{$user->id}}" id="is_active_{{$user->id}}" @if($user->is_active) checked @endif onchange="toggleActive('{{$user->id}}', this.checked)">
            <span class="slider round"></span>
          </label>
        </td>
        <td>
          <a href="#" class="text-info" data-toggle="modal" data-target="#edit-user-modal-{{$user->id}}" title="Edit">
            <i class="fa fa-fw fa-pen"></i>
          </a>
          <a href="#" onclick="destroy('{{route('admin.users.delete')}}','{{$user->id}}','{{$user->id}}')" class="text-danger" title="Remove">
                <i class="fa fa-fw fa-trash"></i>
            </a>
          <!-- Edit user Modal -->
          <div class="modal fade" id="edit-user-modal-{{$user->id}}" tabindex="-1" role="dialog" aria-labelledby="edituserModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg" role="document">
                  <div class="modal-content">
                      <div class="modal-header">
                          <h5 class="modal-title" id="edituserModalLabel">Edit user</h5>
                          <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">×</span>
                          </button>
                      </div>
                      <div class="modal-body">
                          <form method="post" action="{{route('admin.users.update')}}" onsubmit="turnOnLoader()">
                              @csrf
                              <input type="hidden" name="_method" value="PUT">
                              <input type="hidden" name="id" value="{{$user->id}}">
                              <div class="form-group row mb-4">
                                  <div class="col-md-6">
                                      <label for="name">Name</label>
                                      <input class="form-control" name="name" value="{{$user->name}}" required>
                                  </div>
                                  <div class="col-md-6">
                                      <label for="email">Email</label>
                                      <input class="form-control" name="email" value="{{$user->email}}" required>
                                  </div>
                              </div>
                              <div class="form-group row mb-4">
                                  <div class="col-md-6">
                                      <label for="password">Password</label>
                                      <input class="form-control" name="password">
                                  </div>
                                  <div class="col-md-6">
                                    <label for="user_type">User Type</label>
                                    <select name="user_type" class="form-control">
                                      @foreach ($userTypes as $key => $value)
                                      <option value="{{$key}}" @if($user->user_type==$key) selected @endif>{{$value}}</option>
                                      @endforeach
                                    </select>  
                                  </div>
                              </div>
                              <div class="form-group row mb-4">
                                <div class="col-md-12">
                                  <label for="user_interfaces">Access Interfaces</label>
                                  <select name="user_interfaces[]" id="user_interfaces" class="form-control" multiple>
                                    @foreach ($interfaces as $key => $value)
                                    <option value="{{$key}}" @if(in_array($key, array_column($userInterfaces->toArray(), 'id'))) selected @endif>{{$value}}</option>
                                    @endforeach
                                  </select>
                                </div>
                              </div>
                              <div class="form-group row mb-4">
                                <div class="col-md-12">
                                  <label>Priviliges</label>
                                  <br>
                                  @foreach ($privileges as $key => $value)
                                    <input type="checkbox" name="privileges[]" value="{{$key}}" @if(in_array($key, $user_privileges)) checked="checked" @endif/> {{$value}} &nbsp;
                                  @endforeach
                                </div>
                              </div>
                              <div class="form-group row mb-4">
                                  <div class="col-md-12 text-center">
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
<div class="modal fade" id="new-user-modal" tabindex="-1" role="dialog" aria-labelledby="newuserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newuserModalLabel">Add new user</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="{{route('admin.users.store')}}" onsubmit="turnOnLoader()">
                    @csrf
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="name">Name</label>
                            <input type="text" class="form-control" name="name" value="{{old('name')}}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" name="email" value="{{old('email')}}" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6">
                          <label for="user_type">User Type</label>
                          <select name="user_type" id="user_type" class="form-control">
                            @foreach ($userTypes as $key => $value)
                            <option value="{{$key}}">{{$value}}</option> <!--  @if($user->user_type==$key) selected @endif -->
                            @endforeach
                          </select>  
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-12">
                        <label for="interfaces">Access Interfaces</label>
                        <select name="interfaces[]" id="interfaces" class="form-control" multiple>
                          @foreach ($interfaces as $key => $value)
                          <option value="{{$key}}">{{$value}}</option>
                          @endforeach
                        </select>  
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                      <div class="col-md-12">
                        <label>Priviliges</label>
                        <br>
                        @foreach ($privileges as $key => $value)
                          <input type="checkbox" name="privileges[]" value="{{$key}}" /> {{$value}} &nbsp;
                        @endforeach
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
<script>
  function toggleActive(userId, checked) {
    var formData = createFormData({
      '_token': '{{csrf_token()}}',
      '_method': 'PUT',
      'id': userId,
      'status': checked ? 1 : 0
    });
    
    var params = {
      method: 'POST',
      route: "{{route('admin.users.toggleActive')}}",
      formData,
      failCallback: function() {
        document.getElementById(`is_active_${id}`).checked = !checked;
      }
    };

    sendRequest(params);
  }
</script>
@endsection