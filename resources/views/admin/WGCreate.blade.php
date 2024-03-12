@extends('layouts.admin.main', ['pageTitle' => 'Create Wiregaurd Peers', 'active' => 'create_peers'])
@section('content')

<x-loader/>

<div class="card shadow mb-4">
  <form method= "POST" action="{{route('wiregaurd.peers.post.create')}}" onsubmit="turnOnLoader()">
    @csrf
    <div class="card-body">
      <div class="form-group row">
        <label for="wginterface" class="col-sm-4 col-form-label">Wireguard Interface</label>
        <div class="col-sm-8">
          <select name="wginterface" id="wginterface" class="form-control" required>
            <option value="">select interface...</option>
            @foreach($interfaces as $key=>$value)
            <option value="{{$key}}">{{$value}}</option>
            @endforeach
          </select>
        </div>
      </div>

        <!-- <div class="form-group row">
          <label for="endpoint" class="col-sm-4 col-form-label">EndPoint Address</label>
          <div class="col-sm-8">
            <input type="text" class="form-control" placeholder="s1.yourdomain.com" name="endpoint" id="endpoint" required>
          </div>
        </div>


        <div class="form-group row">
        <label for="dns" class="col-sm-4 col-form-label">Wireguard DNS</label>
          <div class="col-sm-8">
            <input type="text" class="form-control" placeholder="192.168.200.1" name="dns" id="dns" required>
          </div>
        </div>


        <div class="form-group row">
          <label for="range" class="col-sm-4 col-form-label">IPv4 Address Range</label>
          <div class="col-sm-8">
            <input type="text" class="form-control" placeholder="192.168.200." name="range" id="range" required>
          </div>
        </div> -->

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
          <label for="start" class="col-sm-4 col-form-label">Start Comment Number (IP will Set Comment+1)</label>
            <div class="col-sm-8">
              <input type="text" class="form-control" placeholder="2" name="start" id="start" />
            </div>
          </div>
          <div class="form-group row">
            <label for="end" class="col-sm-4 col-form-label">End Comment Number (IP will Set Comment+1)</label>
            <div class="col-sm-8">
              <input type="text" class="form-control" placeholder="20" name="end" id="end" />
            </div>
          </div>
        </div>

        <div id="random-inputs" style="display:none;">
          <div class="form-group row">
            <label for="random" class="col-sm-4 col-form-label">Random Comment Seprate with "-" (IP will Set Comment+1)</label>
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
          <div class="col-md-12 text-right">
            <button type="submit" class="btn btn-primary">Create Wireguard  Peers</button>
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