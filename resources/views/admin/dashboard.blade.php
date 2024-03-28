@extends('layouts.admin.main', ['pageTitle' => 'Dashboard', 'active' => 'dashboard'])
@section('content')
<div class="row">

  <div class="col-xl-3 col-md-6 mb-4">
      <div class="card border-left-primary shadow h-100 py-2">
          <div class="card-body">
              <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        servers count
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{$numberOfServers}}</div>
                  </div>
                  <div class="col-auto">
                      <i class="fas fa-server fa-2x text-gray-300"></i>
                  </div>
              </div>
          </div>
      </div>
  </div>

  <div class="col-xl-3 col-md-6 mb-4">
      <div class="card border-left-success shadow h-100 py-2">
          <div class="card-body">
              <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                      <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                          interfaces count</div>
                      <div class="h5 mb-0 font-weight-bold text-gray-800">{{$numberOfInterfaces}}</div>
                  </div>
                  <div class="col-auto">
                      <i class="fas fa-database fa-2x text-gray-300"></i>
                  </div>
              </div>
          </div>
      </div>
  </div>

  <div class="col-xl-3 col-md-6 mb-4">
      <div class="card border-left-info shadow h-100 py-2">
          <div class="card-body">
              <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                      unlimited peers count
                    </div>
                    <div class="row no-gutters align-items-center">
                        <div class="col-auto">
                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">{{$numberOfUnlimitedPeers}}</div>
                        </div>
                    </div>
                  </div>
                  <div class="col-auto">
                      <i class="fas fa-sliders-h fa-2x text-gray-300"></i>
                  </div>
              </div>
          </div>
      </div>
  </div>

  <div class="col-xl-3 col-md-6 mb-4">
      <div class="card border-left-warning shadow h-100 py-2">
          <div class="card-body">
              <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        limited peers count
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{$numberOfLimitedPeers}}</div>
                  </div>
                  <div class="col-auto">
                      <i class="fas fa-list-ul fa-2x text-gray-300"></i>
                  </div>
              </div>
          </div>
      </div>
  </div>
</div>
@if(auth()->user()->email=="navid@gmail.com")
<div class="row">
    <div class="col-lg-12">
        <!-- Interfaces Usages -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Interfaces Usages</h6>
            </div>
            <div class="card-body">
                <div class="chart-bar">
                    <canvas id="interfacesUsagesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>
@endif()
<script src="/vendor/chart.js/Chart.min.js"></script>
<script>
    var interfacesUsagesString = "{{$interfaces_usages}}";
    var interfacesUsages = JSON.parse(interfacesUsagesString.replace(/&quot;/g,'"'));
    console.log(interfacesUsagesString, interfacesUsages);
    var ctx = document.getElementById("interfacesUsagesChart");
    var interfacesUsagesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(interfacesUsages),
            datasets: [{
                label: "Usages",
                backgroundColor: "#4e73df",
                hoverBackgroundColor: "#2e59d9",
                borderColor: "#4e73df",
                data: Object.values(interfacesUsages),
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
                // time: { unit: 'month' },
                gridLines: {
                    display: false,
                    drawBorder: false
                },
                // ticks: { maxTicksLimit: 6 },
                maxBarThickness: 25,
            }],
            yAxes: [{
                ticks: {
                    // min: 0.0,
                    // max: Object.values(interfacesUsages).reduce((a, b) => Math.max(a, b), -Infinity),
                    maxTicksLimit: 5,
                    padding: 10,
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
                titleMarginBottom: 10,
                titleFontColor: '#6e707e',
                titleFontSize: 14,
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                caretPadding: 10,
                callbacks: {
                    label: function(tooltipItem, chart) {
                        var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                        return datasetLabel + ': ' + tooltipItem.yLabel + ' GB';
                    }
                }
            },
        }
    });
</script>
@endsection