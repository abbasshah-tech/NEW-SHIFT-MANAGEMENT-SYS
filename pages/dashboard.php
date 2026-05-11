<?php
// pages/dashboard.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../config/functions.php';

$role_id = $_SESSION['role_id'];

// If Employee, redirect to user dashboard (MUST be before any HTML output)
if ($role_id == 4) {
    header("Location: user_dashboard.php");
    exit();
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Add custom CSS for animations -->
<style>
    .widget-box {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-radius: 10px;
    }
    .widget-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .fade-in-up {
        animation: fadeInUp 0.5s ease-out forwards;
        opacity: 0;
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }
    .delay-5 { animation-delay: 0.5s; }
    .delay-6 { animation-delay: 0.6s; }
    .delay-7 { animation-delay: 0.7s; }
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 fw-bold">Admin Dashboard <span class="badge bg-success fs-6 ms-2" id="live-indicator"><i class="fas fa-circle fa-fade"></i> Live Updates</span></h1>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            
            <?php if ($role_id == 1 || $role_id == 2 || $role_id == 3): ?>
            <!-- 7 Widgets -->
            <div class="row">
                <div class="col-lg-3 col-6 fade-in-up delay-1">
                    <div class="small-box bg-info widget-box">
                        <div class="inner">
                            <h3 id="w-total-employees"><i class="fas fa-spinner fa-spin fs-4"></i></h3>
                            <p>Total Employees</p>
                        </div>
                        <div class="icon"><i class="fas fa-users"></i></div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6 fade-in-up delay-2">
                    <div class="small-box bg-success widget-box">
                        <div class="inner">
                            <h3 id="w-total-depts"><i class="fas fa-spinner fa-spin fs-4"></i></h3>
                            <p>Total Departments</p>
                        </div>
                        <div class="icon"><i class="fas fa-building"></i></div>
                    </div>
                </div>

                <div class="col-lg-3 col-6 fade-in-up delay-5">
                    <div class="small-box bg-primary widget-box">
                        <div class="inner">
                            <h3 id="w-active-shifts"><i class="fas fa-spinner fa-spin fs-4"></i></h3>
                            <p>Active Shifts</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6 fade-in-up delay-3">
                    <div class="small-box bg-warning widget-box">
                        <div class="inner">
                            <h3 id="w-shifts-today"><i class="fas fa-spinner fa-spin fs-4"></i></h3>
                            <p>Today's Shifts</p>
                        </div>
                        <div class="icon"><i class="fas fa-calendar-day"></i></div>
                    </div>
                </div>
            </div>

            <div class="row mt-2">
                <div class="col-lg-4 col-12 fade-in-up delay-6">
                    <div class="small-box bg-purple widget-box" style="background-color: #6f42c1; color: white;">
                        <div class="inner">
                            <h3><span id="w-attendance-rate"><i class="fas fa-spinner fa-spin fs-4"></i></span><sup style="font-size: 20px">%</sup></h3>
                            <p>Attendance Rate</p>
                        </div>
                        <div class="icon"><i class="fas fa-chart-pie"></i></div>
                    </div>
                </div>

                <div class="col-lg-4 col-12 fade-in-up delay-4">
                    <div class="small-box bg-danger widget-box">
                        <div class="inner">
                            <h3 id="w-pending-swaps"><i class="fas fa-spinner fa-spin fs-4"></i></h3>
                            <p>Pending Swap Requests</p>
                        </div>
                        <div class="icon"><i class="fas fa-exchange-alt"></i></div>
                    </div>
                </div>

                <div class="col-lg-4 col-12 fade-in-up delay-7">
                    <div class="small-box bg-dark widget-box">
                        <div class="inner">
                            <h3 id="w-system-status"><i class="fas fa-spinner fa-spin fs-4"></i></h3>
                            <p>System Status</p>
                        </div>
                        <div class="icon"><i class="fas fa-server"></i></div>
                    </div>
                </div>
            </div>
            <!-- /.row -->

            <!-- Charts Row -->
            <div class="row mt-4">
                <div class="col-lg-6 fade-in-up delay-4">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0">
                            <h3 class="card-title fw-bold"><i class="fas fa-chart-line text-primary me-2"></i> Weekly Shifts Trend</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="lineChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 fade-in-up delay-5">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0">
                            <h3 class="card-title fw-bold"><i class="fas fa-chart-pie text-success me-2"></i> Today's Attendance</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="pieChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </section>
</div>
<!-- /.content-wrapper -->

<?php include '../includes/footer.php'; ?>

<!-- Dashboard Scripts -->
<script>
$(function () {
    let lineChart, pieChart;
    
    function initCharts(data) {
        // Line Chart
        let lineChartCanvas = $('#lineChart').get(0).getContext('2d');
        let lineChartOptions = {
            maintainAspectRatio: false,
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { borderDash: [5, 5] }, beginAtZero: true }
            }
        };
        let lineChartData = {
            labels: data.chart_line.labels,
            datasets: [
                {
                    label: 'Scheduled Shifts',
                    backgroundColor: 'rgba(60,141,188,0.2)',
                    borderColor: 'rgba(60,141,188,1)',
                    pointRadius: 4,
                    pointColor: '#3b8bba',
                    pointStrokeColor: 'rgba(60,141,188,1)',
                    pointHighlightFill: '#fff',
                    pointHighlightStroke: 'rgba(60,141,188,1)',
                    data: data.chart_line.data,
                    fill: true,
                    tension: 0.4
                }
            ]
        };
        
        lineChart = new Chart(lineChartCanvas, {
            type: 'line',
            data: lineChartData,
            options: lineChartOptions
        });

        // Pie Chart
        let pieChartCanvas = $('#pieChart').get(0).getContext('2d');
        let pieData = {
            labels: data.chart_pie.labels,
            datasets: [
                {
                    data: data.chart_pie.data,
                    backgroundColor: ['#00a65a', '#f39c12', '#f56954'],
                }
            ]
        };
        let pieOptions = {
            maintainAspectRatio: false,
            responsive: true,
        };
        
        pieChart = new Chart(pieChartCanvas, {
            type: 'doughnut',
            data: pieData,
            options: pieOptions
        });
    }

    function updateCharts(data) {
        if (lineChart && pieChart) {
            lineChart.data.datasets[0].data = data.chart_line.data;
            lineChart.update();
            
            pieChart.data.datasets[0].data = data.chart_pie.data;
            pieChart.update();
        }
    }

    // Helper to animate count up
    function animateCount(id, endVal) {
        let el = document.getElementById(id);
        if(!el) return;
        // if it's not a number, just set text
        if(isNaN(endVal)) {
            el.innerHTML = endVal;
            return;
        }
        $({ countNum: parseInt(el.innerText) || 0 }).animate({
            countNum: endVal
        }, {
            duration: 1000,
            easing: 'swing',
            step: function() {
                el.innerText = Math.floor(this.countNum);
            },
            complete: function() {
                el.innerText = this.countNum;
            }
        });
    }

    function fetchDashboardData() {
        $.ajax({
            url: '../api/dashboard_data.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if(data.error) return;
                
                // Update Widgets with countup animation
                animateCount('w-total-employees', data.total_employees);
                animateCount('w-total-depts', data.total_depts);
                animateCount('w-active-shifts', data.active_shifts);
                animateCount('w-shifts-today', data.shifts_today);
                animateCount('w-attendance-rate', data.attendance_rate);
                animateCount('w-pending-swaps', data.pending_swaps);
                $('#w-system-status').text(data.system_status);

                if(!lineChart) {
                    initCharts(data);
                } else {
                    updateCharts(data);
                }
            },
            error: function() {
                console.error("Failed to fetch live updates");
            }
        });
    }

    // Initial fetch
    fetchDashboardData();

    // Poll every 10 seconds
    setInterval(fetchDashboardData, 10000);
});
</script>
