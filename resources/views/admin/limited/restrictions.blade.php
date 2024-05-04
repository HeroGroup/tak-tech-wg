@extends('layouts.admin.main', ['pageTitle' => 'Restrictions', 'active' => 'peers'])
@section('content')
<div class="row mb-4">
  <div class="col-sm-2">
    <select name="interface" class="form-control" onchange="searchInterface(this.value)">
      @foreach($limitedInterfaces as $key=>$value)
      <option value="{{$key}}" @if($key==$interface) selected @endif>{{$value}}</option>
      @endforeach
    </select>
  </div>
  <div class="col-sm-4">
    <input type="text" name="search" id="search" class="form-control" placeholder="search address" value="{{$search}}">
  </div>
  <div class="col-sm-1">
    <button type="button" class="btn btn-sm btn-primary mt-1" onclick="search()">search</button>
  </div>
</div>
<x-paginator :route="route('admin.wiregaurd.peers.restrictions')" :selectedCount="0" :isLastPage="$isLastPage" />

<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <th>
        <input type="checkbox" id="chk-all" onclick="checkAll()">
      </th>
      <th>row</th>
      <th>Address</th>
      <th>Used</th>
      <th>Maximum</th>
      <th></th>
    </thead>
    <tbody>
      <?php $row = 0; ?>
      @foreach($addresses as $address)
      <tr id="{{$address->id}}">
        <td>
              <input type="checkbox" class="chk-row">
        </td>
        <td>{{++$row}}</td>
        <td>{{$address->allowed_address}}</td>
        <td>{{$address->used_count}}</td>
        <td>{{$address->maximum_allowed}}</td>
        <td>
          <a href="#" class="text-info" data-toggle="modal" data-target="#edit-address-modal-{{$address->id}}">
            <i class="fa fa-pen"></i> Edit
          </a>
          <!-- Edit address Modal -->
          <div class="modal fade" id="edit-address-modal-{{$address->id}}" tabindex="-1" role="dialog" aria-labelledby="editAddressModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
              <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAddressModalLabel">Edit Address {{$address->allowed_address}}</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                  <form method="post" action="{{route('admin.wiregaurd.peers.restrictions.update')}}" onsubmit="turnOnLoader()">
                      @csrf
                      <input type="hidden" name="_method" value="PUT">
                      <input type="hidden" name="id" value="{{$address->id}}">
                      <div class="form-group row mb-4">
                          <div class="col-md-12">
                              <label for="maximum_allowed">Maximum Allowed</label>
                              <input class="form-control" name="maximum_allowed" value="{{$address->maximum_allowed}}" required>
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
  var baseRoute = "{{route('admin.wiregaurd.peers.restrictions')}}";
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
  function searchInterface(val, clear=false) {
    searchBase({ 'interface': val });
  }
  function search() {
    searchBase({ 'search': document.getElementById("search").value });
  }
</script>
@endsection