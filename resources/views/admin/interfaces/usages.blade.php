@extends('layouts.admin.main', ['pageTitle' => 'Interfaces Usages (Last 3 hours)', 'active' => 'interfaces'])
@section('content')
@if(auth()->user()->is_admin)
<div class="row mb-4">
  <div class="col-md-6">
    <a href="#" class="btn btn-dark">
      <i class="fa fa-fw fa-columns"></i>
    </a>
    <a href="{{route('admin.wiregaurd.interfaces')}}" class="btn">
      <i class="fa fa-fw fa-list"></i>
    </a>
  </div>
  <div class="col-md-6 text-right">
    <a href="#" class="btn btn-primary btn-icon-split" data-toggle="modal" data-target="#new-interface-modal">
      <span class="icon text-white-50">
        <i class="fas fa-plus"></i>
      </span>
      <span class="text">Add Wiregaurd Interface</span>
    </a>
  </div>
</div>
@endif
<div class="row">
  @foreach($interfaces as $interface)
  <div class="col-md-12">
        <div class="card shadow mb-4">
            <!-- Card Header - Dropdown -->
            <div
                class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">{{$interface->name}}</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                        aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Actions</div>
                        @if(auth()->user()->isAdmin)
                        <a class="dropdown-item text-info" href="#" data-toggle="modal" data-target="#edit-interface-modal-{{$interface->id}}"><i class="fas fa-pen"></i> Edit</a>
                        <a class="dropdown-item text-warning" href="{{route('admin.wiregaurd.interfaces.usages.details',$interface->id)}}"><i class="fas fa-info-circle"></i> Details</a>
                        @endif
                        <a class="dropdown-item text-success" href="{{route('wiregaurd.interfaces.usages.monitor',$interface->id)}}"><i class="fas fa-tv"></i> Monitor</a>
                        @if(auth()->user()->isAdmin)
                        <a class="dropdown-item text-danger" href="#" onclick="destroy('{{route('admin.wiregaurd.interfaces.delete')}}','{{$interface->id}}','{{$interface->id}}')"><i class="fas fa-trash"></i> Remove</a>
                        @endif
                    </div>
                </div>
            </div>
            <!-- Card Body -->
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="row mb-3">
                    <label class="col-sm-6 col-form-label">Default Endpoint</label>
                    <div class="col-sm-6">
                      <input class="form-control" type="text" value="{{$interface->default_endpoint_address}}" aria-label="Disabled input" disabled readonly>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <label class="col-sm-6 col-form-label">DNS</label>
                    <div class="col-sm-6">
                      <input class="form-control" type="text" value="{{$interface->dns}}" aria-label="Disabled input" disabled readonly>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <label class="col-sm-6 col-form-label">IP Range</label>
                    <div class="col-sm-6">
                      <input class="form-control" type="text" value="{{$interface->ip_range}}" aria-label="Disabled input" disabled readonly>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <label class="col-sm-6 col-form-label">Listen port</label>
                    <div class="col-sm-6">
                      <input class="form-control" type="text" value="{{$interface->listen_port}}" aria-label="Disabled input" disabled readonly>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <label class="col-sm-6 col-form-label">iType</label>
                    <div class="col-sm-6">
                      <input class="form-control" type="text" value="{{$interface->iType}}" aria-label="Disabled input" disabled readonly>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <label class="col-sm-6 col-form-label">Allowed Traffic (GB)</label>
                    <div class="col-sm-6">
                      <input class="form-control" type="text" value="{{$interface->allowed_traffic_GB}}" aria-label="Disabled input" disabled readonly>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="chart-area">
                      <canvas id="{{$interface->name}}-chart"></canvas>
                  </div>
                </div>
              </div>
            </div>
        </div>
    </div>
    <!-- Edit interface Modal -->
    <div class="modal fade" id="edit-interface-modal-{{$interface->id}}" tabindex="-1" role="dialog" aria-labelledby="editinterfaceModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editinterfaceModalLabel">Edit interface</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="{{route('admin.wiregaurd.interfaces.update')}}" onsubmit="turnOnLoader()">
                    @csrf
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="id" value="{{$interface->id}}">
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="name">Name</label>
                            <input class="form-control" name="name" value="{{$interface->name}}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="default_endpoint_address">Default Endpoint</label>
                            <input class="form-control" name="default_endpoint_address" value="{{$interface->default_endpoint_address}}" placeholder="s1.yourdomain.com">
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="dns">Wiregaurd DNS</label>
                            <input class="form-control" name="dns" value="{{$interface->dns}}" placeholder="192.168.200.1">
                        </div>
                        <div class="col-md-6">
                            <label for="ip_range">IPv4 Address Range</label>
                            <input class="form-control" name="ip_range" value="{{$interface->ip_range}}" placeholder="192.168.200.">
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="mtu">MTU</label>
                            <input class="form-control" name="mtu" value="{{$interface->mtu}}" placeholder="1440">
                        </div>
                        <div class="col-md-6">
                            <label for="listen_port">listen port</label>
                            <input class="form-control" name="listen_port" value="{{$interface->listen_port}}">
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="iType">iType</label>
                            <select name="iType">
                                <option key="unlimited" @if($interface->iType=='unlimited') selected @endif>unlimited</option>
                                <option key="limited" @if($interface->iType=='limited') selected @endif>limited</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="allowed_traffic_GB">Allowed Traffic (GB)</label>
                            <input type="number" class="form-control" name="allowed_traffic_GB" value="{{$interface->allowed_traffic_GB}}" step="0.5">
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <input type="checkbox" name="exclude_from_block" id="exclude_from_block" @if($interface->exclude_from_block) checked="checked" @endif>
                            <label for="exclude_from_block">Exclude From Blocking Peers</label>
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
    @endforeach
</div>

<div class="modal fade" id="new-interface-modal" tabindex="-1" role="dialog" aria-labelledby="newinterfaceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newinterfaceModalLabel">Add new interface</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="{{route('admin.wiregaurd.interfaces.add')}}" onsubmit="turnOnLoader()">
                    @csrf
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="name">Interface Name</label>
                            <input class="form-control" name="name" placeholder="WG-S" required>
                        </div>
                        <div class="col-md-6">
                            <label for="default_endpoint_address">Default Endpoint</label>
                            <input class="form-control" name="default_endpoint_address" placeholder="s1.yourdomain.com" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="dns">Wiregaurd DNS</label>
                            <input class="form-control" name="dns" placeholder="192.168.200.1" required>
                        </div>
                        <div class="col-md-6">
                            <label for="ip_range">IPv4 Address Range</label>
                            <input class="form-control" name="ip_range" placeholder="192.168.200." required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="mtu">MTU</label>
                            <input class="form-control" name="mtu" placeholder="1440" required>
                        </div>
                        <div class="col-md-6">
                            <label for="listen_port">listen port</label>
                            <input class="form-control" name="listen_port" required>
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <label for="iType">iType</label>
                            <select name="iType">
                                <option key="unlimited">unlimited</option>
                                <option key="limited">limited</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="allowed_traffic_GB">Allowed Traffic (GB)</label>
                            <input type="number" class="form-control" name="allowed_traffic_GB" step="0.5">
                        </div>
                    </div>
                    <div class="form-group row mb-4">
                        <div class="col-md-6">
                            <input type="checkbox" name="exclude_from_block" id="exclude_from_block" >
                            <label for="exclude_from_block">Exclude From Blocking Peers</label>
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
<script src="/vendor/chart.js/Chart.min.js"></script>
<script>
  var interfacesJson = "{{$interfaces_json}}";
  var interfaces = JSON.parse(interfacesJson.replace(/&quot;/g,'"'));
  // loop on all interfacaes and create charts
  for(var i = 0; i < interfaces.length; i++) {
    var ctx = document.getElementById(`${interfaces[i].name}-chart`);
    var myLineChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: ["3 hours ago", "2.5 hours ago", "2 hours ago", "1.5 hours ago", "1 hour ago", "30 minutes ago"],
        datasets: [{
          label: "Usages",
          lineTension: 0.3,
          backgroundColor: "rgba(78, 115, 223, 0.05)",
          borderColor: "rgba(78, 115, 223, 1)",
          pointRadius: 3,
          pointBackgroundColor: "rgba(78, 115, 223, 1)",
          pointBorderColor: "rgba(78, 115, 223, 1)",
          pointHoverRadius: 3,
          pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
          pointHoverBorderColor: "rgba(78, 115, 223, 1)",
          pointHitRadius: 10,
          pointBorderWidth: 2,
          data: interfaces[i].usages.reverse(), // [0, 10000, 5000, 15000, 10000, 20000, 15000, 25000, 20000, 30000, 25000, 40000],
        }],
      },
      options: {
        maintainAspectRatio: false,
        layout: {
          padding: {
            left: 10,
            right: 25,
            top: 25,
            bottom: 0
          }
        },
        scales: {
          xAxes: [{
            // time: { unit: 'date' },
            gridLines: {
              display: false,
              drawBorder: false
            },
            // ticks: { maxTicksLimit: 7 }
          }],
          yAxes: [{
            ticks: {
              // maxTicksLimit: 5,
              padding: 10,
              // Include a dollar sign in the ticks
              callback: function(value, index, values) {
                return value + ' GB';
              }
            },
            gridLines: {
              color: "rgb(234, 236, 244)",
              zeroLineColor: "rgb(234, 236, 244)",
              drawBorder: false,
              borderDash: [2],
              zeroLineBorderDash: [2]
            }
          }],
        },
        legend: {
          display: false
        },
        tooltips: {
          backgroundColor: "rgb(255,255,255)",
          bodyFontColor: "#858796",
          titleMarginBottom: 10,
          titleFontColor: '#6e707e',
          titleFontSize: 14,
          borderColor: '#dddfeb',
          borderWidth: 1,
          xPadding: 15,
          yPadding: 15,
          displayColors: false,
          intersect: false,
          mode: 'index',
          caretPadding: 10,
          callbacks: {
            label: function(tooltipItem, chart) {
              var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
              return datasetLabel + ': ' + tooltipItem.yLabel + ' GB';
            }
          }
        }
      }
    });
  }
</script>
@endsection