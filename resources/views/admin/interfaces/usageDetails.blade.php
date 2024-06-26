@extends('layouts.admin.main', ['pageTitle' => $interfaceName . ' Usage (Last 3 hours)', 'active' => 'interfaces'])
@section('content')
<div class="row">
  @foreach($output as $server => $value)
  <div class="col-lg-12">
        <div class="card shadow mb-4">
            <!-- Card Header - Dropdown -->
            <div
                class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">{{$server}}</h6>
            </div>
            <!-- Card Body -->
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="{{$server}}-chart"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<script src="/vendor/chart.js/Chart.min.js"></script>
<script>
  var usageJson = "{{$output_json}}";
  var interfaces = JSON.parse(usageJson.replace(/&quot;/g,'"'));
  var servers = Object.keys(interfaces);

  // loop on all interfacaes and create charts
  for(var i = 0; i < servers.length; i++) {
    var ctx = document.getElementById(`${servers[i]}-chart`);
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
          data: interfaces[servers[i]].reverse(), // [0, 10000, 5000, 15000, 10000, 20000, 15000, 25000, 20000, 30000, 25000, 40000],
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