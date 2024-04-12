@extends('layouts.admin.main', ['pageTitle' => 'Peers Actions', 'active' => 'peers'])
@section('content')

<x-loader/>

<div class="card shadow mb-4">
  <form method= "POST" action="{{route('wiregaurd.peers.actions.post')}}" onsubmit="turnOnLoader()">
    @csrf
    <div class="card-body">
      <div class="form-group row">
        <label for="interface" class="col-sm-4 col-form-label">Wireguard Interface</label>
        <div class="col-sm-8">
          <select name="interface" id="interface" class="form-control" required>
            <option value="">select interface...</option>
            @foreach($interfaces as $key=>$value)
            <option value="{{$key}}">{{$value}}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="form-group row">
        <label for="type" class="col-sm-4 col-form-label">Type</label>
        <div class="col-sm-8">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="type" id="batchType" value="batch" checked>
            <label class="form-check-label" for="batchType">
              Batch
            </label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="type" id="randomType" value="random">
            <label class="form-check-label" for="randomType">
              Random
            </label>
          </div>
        </div>
      </div>

      <div id="batch-inputs" style="display:block;">
        <div class="form-group row">
        <label for="start" class="col-sm-4 col-form-label">Start Comment Number</label>
          <div class="col-sm-8">
            <input type="text" class="form-control" placeholder="2" name="start" id="start" />
          </div>
        </div>
        <div class="form-group row">
          <label for="end" class="col-sm-4 col-form-label">End Comment Number</label>
          <div class="col-sm-8">
            <input type="text" class="form-control" placeholder="20" name="end" id="end" />
          </div>
        </div>
      </div>

      <div id="random-inputs" style="display:none;">
        <div class="form-group row">
          <label for="random" class="col-sm-4 col-form-label">Random Comment Seprate with "-"</label>
          <div class="col-sm-8">
            <input type="text" class="form-control" placeholder="2-5-7-19" name="random" id="random" />
          </div>
        </div>
      </div>

      <div class="form-group row">
        <label for="comment" class="col-sm-4 col-form-label">Comment</label>
        <div class="col-sm-8">
          <input type="text" class="form-control" placeholder="Ali" name="comment" id="comment" required />
        </div>
      </div>

      <div class="form-group row">
        <label for="action" class="col-sm-4 col-form-label">Action</label>
        <div class="col-sm-8">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="action" id="enable_action" value="enable" checked>
            <label class="form-check-label" for="enable_action">
              Enable
            </label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="action" id="disable_action" value="disable">
            <label class="form-check-label" for="disable_action">
              Disable
            </label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="action" id="regenerate_action" value="regenerate">
            <label class="form-check-label" for="regenerate_action">
              Regenarate
            </label>
          </div>
          @if(auth()->user()->is_admin)
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="action" id="remove_action" value="remove">
            <label class="form-check-label" for="remove_action">
              Remove
            </label>
          </div>
          @endif
        </div>
      </div>

      <div class="form-group row">
        <div class="col-md-12 text-right">
          <button type="submit" class="btn btn-primary">Go!</button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
  $('input[type=radio][name=type]').click(function() {
    var val = $('input[type=radio][name=type]:checked').val();
    var batchInputs = document.getElementById('batch-inputs');
    var randomInputs = document.getElementById('random-inputs');
    if (val === 'batch') {
      batchInputs.style.display = "block";
      randomInputs.style.display = "none";
    }
    else if (val === 'random') {
      batchInputs.style.display = "none";
      randomInputs.style.display = "block";
    }
  });
</script>
@endsection