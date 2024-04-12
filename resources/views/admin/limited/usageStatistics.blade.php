@extends('layouts.admin.main', ['pageTitle' => 'Peer ' . $peer->comment . ' Statistics', 'active' => 'peers'])
@section('content')
<div class="row">
    <div class="col-lg-12">
        <!-- Peer Usages -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{$peer->comment}}</h6>
            </div>
            <div class="card-body">
                <div class="chart-bar">
                    <canvas id="peerUsagesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/vendor/chart.js/Chart.min.js"></script>
<script>
    var peerUsagesString = "{{$peer_usages}}";
    var peerUsages = JSON.parse(peerUsagesString.replace(/&quot;/g,'"'));

    var ctx = document.getElementById("peerUsagesChart");
    var peerUsagesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(peerUsages),
            datasets: [{
                label: "Usages",
                backgroundColor: "#4e73df",
                hoverBackgroundColor: "#2e59d9",
                borderColor: "#4e73df",
                data: Object.values(peerUsages),
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
                gridLines: {
                    display: false,
                    drawBorder: false
                },
                maxBarThickness: 25,
            }],
            yAxes: [{
                ticks: {
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