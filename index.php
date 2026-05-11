<?php
// index.php (Landing Page)
session_start();

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role_id'] == 4) {
        header("Location: pages/user_dashboard.php");
    } else {
        header("Location: pages/dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SHIFT Pro - Management System</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Animate CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            color: #333;
            overflow-x: hidden;
        }
        .navbar {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .navbar-brand {
            font-weight: 700;
            color: #2c3e50 !important;
            font-size: 1.5rem;
        }
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 80px; /* Offset for navbar */
        }
        .hero-text h1 {
            font-size: 3.5rem;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }
        .hero-text p {
            font-size: 1.25rem;
            color: #555;
            margin-bottom: 2rem;
        }
        .btn-custom {
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        .btn-primary-custom {
            background-color: #3498db;
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        .btn-primary-custom:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.6);
            color: white;
        }
        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            text-align: center;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-10px);
        }
        .feature-icon {
            font-size: 2.5rem;
            color: #3498db;
            margin-bottom: 1rem;
        }
        .footer {
            background-color: #2c3e50;
            color: white;
            padding: 2rem 0;
            text-align: center;
        }
        @media (max-width: 768px) {
            .hero-text h1 { font-size: 2.5rem; }
            .hero-section { text-align: center; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top animate__animated animate__fadeInDown">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-clock text-primary"></i> SHIFT Pro</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="#features">Features</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-primary-custom" href="login.php">Login to Portal</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-text animate__animated animate__fadeInLeft">
                    <h1>Smart Shift & Roster Management</h1>
                    <p>Streamline your workforce scheduling, track attendance effortlessly, and manage swap requests in real-time with our intelligent portal.</p>
                    <a href="login.php" class="btn btn-primary-custom me-3"><i class="fas fa-sign-in-alt"></i> Get Started</a>
                    <a href="#features" class="btn btn-outline-dark btn-custom">Learn More</a>
                </div>
                <div class="col-lg-6 mt-5 mt-lg-0 text-center animate__animated animate__fadeInRight">
                    <!-- Placeholder for Hero Image, using a FontAwesome representation for now -->
                    <div style="font-size: 15rem; color: #3498db; opacity: 0.8; animation: pulse 2s infinite;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5 animate__animated animate__fadeInUp">
                <h2 class="fw-bold">Powerful Features</h2>
                <p class="text-muted">Everything you need to manage your team effectively</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                    <div class="feature-card">
                        <i class="fas fa-sync-alt feature-icon"></i>
                        <h4 class="fw-bold">Dynamic Rostering</h4>
                        <p class="text-muted">Assign shifts easily and generate comprehensive duty rosters with a few clicks.</p>
                    </div>
                </div>
                <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <div class="feature-card">
                        <i class="fas fa-exchange-alt feature-icon"></i>
                        <h4 class="fw-bold">Shift Swaps</h4>
                        <p class="text-muted">Empower employees to request shift swaps, subject to manager approval.</p>
                    </div>
                </div>
                <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                    <div class="feature-card">
                        <i class="fas fa-chart-pie feature-icon"></i>
                        <h4 class="fw-bold">Live Analytics</h4>
                        <p class="text-muted">Monitor attendance and shift distribution in real-time with beautiful charts.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> SHIFT Pro Management System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
