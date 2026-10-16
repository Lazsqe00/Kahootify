<?php
/**
 * TRANG CHỦ BÀI TẬP NHÓM (BTN) - KAHOOT CLONE
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "Kahoot! | Make Learning Awesome!";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
/* ================= HERO ANIMATIONS ================= */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInLeft {
    from { opacity: 0; transform: translateX(-50px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes fadeInRight {
    from { opacity: 0; transform: translateX(50px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes floatPhone {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-18px); }
}
@keyframes floatSlow {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-12px) rotate(5deg); }
}
@keyframes pulseGlow {
    0%, 100% { box-shadow: 0 0 20px rgba(255,202,40,0.3); }
    50% { box-shadow: 0 0 40px rgba(255,202,40,0.6); }
}
@keyframes spinSlow {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.7); }
    to { opacity: 1; transform: scale(1); }
}
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
@keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ================= HERO SECTION ================= */
.hero-section {
    background: linear-gradient(135deg, #46178F 0%, #1a0a3e 50%, #2d1066 100%);
    min-height: 600px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    padding: 80px 0 60px;
}

/* Floating geometric shapes background */
.hero-shape {
    position: absolute;
    border-radius: 50%;
    opacity: 0.08;
}
.hero-shape-1 {
    width: 300px; height: 300px;
    background: #ffca28;
    top: -80px; left: -80px;
    animation: floatSlow 8s ease-in-out infinite;
}
.hero-shape-2 {
    width: 200px; height: 200px;
    background: #e21b3c;
    bottom: -60px; right: 10%;
    animation: floatSlow 6s ease-in-out infinite 1s;
}
.hero-shape-3 {
    width: 150px; height: 150px;
    background: #1368ce;
    top: 20%; right: -40px;
    animation: floatSlow 7s ease-in-out infinite 2s;
}
.hero-shape-4 {
    width: 100px; height: 100px;
    background: #26890c;
    bottom: 20%; left: 5%;
    animation: floatSlow 5s ease-in-out infinite 0.5s;
}
/* Small decorative triangles & squares */
.hero-deco {
    position: absolute;
    opacity: 0.12;
}
.deco-triangle {
    width: 0; height: 0;
    border-left: 25px solid transparent;
    border-right: 25px solid transparent;
    border-bottom: 43px solid #e21b3c;
    top: 15%; left: 15%;
    animation: spinSlow 20s linear infinite;
}
.deco-diamond {
    width: 35px; height: 35px;
    background: #1368ce;
    transform: rotate(45deg);
    top: 60%; left: 20%;
    animation: floatSlow 9s ease-in-out infinite 3s;
}
.deco-circle {
    width: 20px; height: 20px;
    border-radius: 50%;
    background: #26890c;
    top: 30%; right: 15%;
    animation: floatSlow 6s ease-in-out infinite 1.5s;
}
.deco-square {
    width: 28px; height: 28px;
    background: #d89e00;
    top: 70%; right: 25%;
    animation: spinSlow 15s linear infinite reverse;
}

/* Hero text animations */
.hero-badge {
    animation: fadeInUp 0.8s ease-out both;
    animation-delay: 0.2s;
}
.hero-title {
    animation: fadeInUp 0.8s ease-out both;
    animation-delay: 0.4s;
}
.hero-desc {
    animation: fadeInUp 0.8s ease-out both;
    animation-delay: 0.6s;
}
.hero-buttons {
    animation: fadeInUp 0.8s ease-out both;
    animation-delay: 0.8s;
}

/* ================= PHONE MOCKUP WITH VIDEO ================= */
.phone-mockup-wrapper {
    animation: fadeInRight 1s ease-out both;
    animation-delay: 0.5s;
    position: relative;
    padding: 20px;
}
.phone-mockup-container {
    position: relative;
    display: inline-block;
    animation: floatPhone 4s ease-in-out infinite;
}
.phone-frame {
    position: relative;
    z-index: 1;
    max-height: 480px;
    filter: drop-shadow(0 20px 40px rgba(0,0,0,0.45));
    pointer-events: none;
    display: block;
}
.phone-video {
    position: absolute;
    z-index: 2;
    top: 4.8%;
    left: 6.8%;
    width: 86.4%;
    height: 90.4%;
    object-fit: cover;
    border-radius: 36px;
    box-shadow: inset 0 0 15px rgba(0,0,0,0.3);
}
/* Glow ring around phone */
.phone-glow {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 380px; height: 380px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,202,40,0.2) 0%, transparent 70%);
    animation: pulseGlow 3s ease-in-out infinite;
    z-index: 0;
}

/* Floating interactive badges */
.floating-badge {
    position: absolute;
    z-index: 4;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    color: #333;
    padding: 10px 16px;
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
    transition: transform 0.3s ease;
}
.floating-badge:hover {
    transform: scale(1.05);
}
.badge-top-right {
    top: 15%;
    right: -25px;
    animation: floatSlow 5s ease-in-out infinite;
}
.badge-bottom-left {
    bottom: 12%;
    left: -25px;
    animation: floatSlow 6s ease-in-out infinite 1s;
}
.badge-icon {
    font-size: 1.8rem;
    line-height: 1;
}
.badge-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-sub {
    font-size: 0.95rem;
    font-weight: 800;
    color: #46178F;
}
.live-pill {
    position: absolute;
    top: 10%;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3;
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 6px;
    letter-spacing: 0.5px;
    pointer-events: none;
}
.live-dot {
    width: 8px;
    height: 8px;
    background: #e21b3c;
    border-radius: 50%;
    display: inline-block;
    animation: pulse 1s infinite;
}

/* ================= HOW IT WORKS ================= */
.how-it-works {
    background: #fff;
    padding: 80px 0;
}
.step-card {
    text-align: center;
    padding: 30px 20px;
    position: relative;
    opacity: 0;
    animation: fadeInUp 0.6s ease-out forwards;
}
.step-card:nth-child(1) { animation-delay: 0.2s; }
.step-card:nth-child(2) { animation-delay: 0.4s; }
.step-card:nth-child(3) { animation-delay: 0.6s; }
.step-card:nth-child(4) { animation-delay: 0.8s; }

.step-number {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #46178F, #7b3fc4);
    color: #fff;
    font-family: 'Montserrat', sans-serif;
    font-size: 1.5rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(70,23,143,0.3);
}
.step-icon {
    font-size: 2.5rem;
    margin-bottom: 15px;
}
.step-connector {
    position: absolute;
    top: 60px;
    right: -30px;
    color: #ccc;
    font-size: 1.5rem;
    z-index: 2;
}

/* ================= STATS SECTION ================= */
.stats-section {
    background: linear-gradient(135deg, #46178F 0%, #2d1066 100%);
    padding: 60px 0;
    color: #fff;
}
.stat-item {
    text-align: center;
    padding: 20px;
    opacity: 0;
    animation: countUp 0.6s ease-out forwards;
}
.stat-item:nth-child(1) { animation-delay: 0.1s; }
.stat-item:nth-child(2) { animation-delay: 0.3s; }
.stat-item:nth-child(3) { animation-delay: 0.5s; }
.stat-item:nth-child(4) { animation-delay: 0.7s; }

.stat-number {
    font-family: 'Montserrat', sans-serif;
    font-size: 2.8rem;
    font-weight: 900;
    color: #ffca28;
    text-shadow: 0 2px 10px rgba(255,202,40,0.3);
}
.stat-label {
    font-size: 1rem;
    font-weight: 500;
    color: #d0c0e8;
    margin-top: 5px;
}

/* ================= FEATURE CARDS (ENHANCED) ================= */
.feature-cards-section {
    padding: 80px 0;
    background: #f8f9fa;
}
.feature-card-enhanced {
    border-radius: 16px;
    transition: transform 0.3s cubic-bezier(0.25,0.46,0.45,0.94), box-shadow 0.3s ease;
    overflow: hidden;
}
.feature-card-enhanced:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15) !important;
}
.feature-card-enhanced .card-body {
    position: relative;
    z-index: 1;
}
.feature-card-enhanced::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(180deg, transparent 50%, rgba(0,0,0,0.1) 100%);
    z-index: 0;
}
.feature-icon-ring {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s ease, background 0.3s ease;
}
.feature-card-enhanced:hover .feature-icon-ring {
    transform: scale(1.1) rotate(5deg);
    background: rgba(255,255,255,0.35);
}

/* ================= VIDEO SHOWCASE SECTION (HOME.MP4) ================= */
.video-showcase-section {
    background: linear-gradient(180deg, #f8f9fa 0%, #ede8f7 50%, #f8f9fa 100%);
    padding: 90px 0;
    position: relative;
}
.video-cinema-frame {
    max-width: 920px;
    margin: 0 auto 40px;
    background: #1e1e24;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(70, 23, 143, 0.22);
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.video-window-header {
    background: #2b2b36;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.window-dots {
    display: flex;
    gap: 8px;
}
.window-dots .dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}
.window-dots .dot.red { background: #ff5f56; }
.window-dots .dot.yellow { background: #ffbd2e; }
.window-dots .dot.green { background: #27c93f; }
.window-title {
    color: #b0a8c4;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.cinema-video-container {
    position: relative;
    background: #000;
}
.cinema-video {
    width: 100%;
    max-height: 520px;
    display: block;
    object-fit: contain;
    outline: none;
}
.video-feature-pill {
    background: #fff;
    border-radius: 14px;
    padding: 24px 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    transition: transform 0.25s, box-shadow 0.25s;
    height: 100%;
}
.video-feature-pill:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(70, 23, 143, 0.12);
}

/* ================= CTA SECTION ================= */
.cta-section {
    background: linear-gradient(135deg, #26890c 0%, #1a6207 100%);
    padding: 80px 0;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.cta-section::before {
    content: '';
    position: absolute;
    top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: radial-gradient(circle at 30% 70%, rgba(255,255,255,0.05) 0%, transparent 50%);
}
.cta-btn {
    padding: 18px 40px;
    font-size: 1.2rem;
    border-radius: 50px;
    font-weight: 700;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}
.cta-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

/* ================= RESPONSIVE ================= */
@media (max-width: 991px) {
    .hero-section { padding: 50px 0 40px; min-height: auto; }
    .phone-frame { max-height: 350px; }
    .phone-glow { width: 260px; height: 260px; }
    .step-connector { display: none; }
}
@media (max-width: 767px) {
    .hero-section { padding: 40px 0 30px; }
    .phone-frame { max-height: 300px; }
    .phone-glow { width: 220px; height: 220px; }
    .stat-number { font-size: 2rem; }
}
</style>

<!-- ================= HERO BANNER ================= -->
<div class="hero-section text-white">
    <!-- Floating shapes background -->
    <div class="hero-shape hero-shape-1"></div>
    <div class="hero-shape hero-shape-2"></div>
    <div class="hero-shape hero-shape-3"></div>
    <div class="hero-shape hero-shape-4"></div>
    <div class="hero-deco deco-triangle"></div>
    <div class="hero-deco deco-diamond"></div>
    <div class="hero-deco deco-circle"></div>
    <div class="hero-deco deco-square"></div>

    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 text-center text-lg-left mb-5 mb-lg-0">

                <h1 class="hero-title font-weight-bold mb-3" style="font-size: 3rem; line-height: 1.15;">
                    Make learning<br>
                    <span style="background: linear-gradient(135deg, #ffca28, #ff9800); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">awesome!</span>
                </h1>
                <p class="hero-desc lead mb-4" style="color: #d0c0e8; max-width: 520px; font-size: 1.1rem; line-height: 1.7;">
                    Real-time interactive quiz platform. Create engaging questions, host exciting competitions, and celebrate the winners!
                </p>

                <div class="hero-buttons d-flex flex-wrap justify-content-center justify-content-lg-start" style="gap: 15px;">
                    <a href="<?= BASE_URL ?>modules/player/join.php" class="btn btn-kahoot btn-kahoot-green btn-lg px-4 py-3" style="font-size: 1rem;">
                        <i class="fas fa-play mr-2"></i> JOIN GAME ROOM
                    </a>

                    <?php if (empty($_SESSION['id_creator'])): ?>
                        <button type="button" class="btn btn-outline-light btn-lg px-4 py-3 font-weight-bold" style="border-width: 2px;" data-toggle="modal" data-target="#signUpModal">
                            <i class="fas fa-user-plus mr-2"></i> Sign Up for Free
                        </button>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-warning btn-lg px-4 py-3 font-weight-bold text-dark">
                            <i class="fas fa-th-list mr-2"></i> Manage My Kahoots
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Phone mockup with video -->
            <div class="col-lg-6 text-center">
                <div class="phone-mockup-wrapper">
                    <div class="phone-mockup-container">
                        <div class="phone-glow"></div>
                        
                        <!-- Floating Badge: Top 1 Leaderboard -->
                        <div class="floating-badge badge-top-right">
                            <span class="badge-icon">🏆</span>
                            <div>
                                <div class="badge-title">Top 1 Leaderboard</div>
                                <div class="badge-sub">+1,450 pts</div>
                            </div>
                        </div>

                        <!-- Floating Badge: PIN Game -->
                        <div class="floating-badge badge-bottom-left">
                            <span class="badge-icon">⚡</span>
                            <div>
                                <div class="badge-title">Game PIN</div>
                                <div class="badge-sub" style="color: #26890c;">849 201</div>
                            </div>
                        </div>

                        <!-- Live pill indicator -->
                        <div class="live-pill">
                            <span class="live-dot"></span> LIVE GAME
                        </div>

                        <img src="<?= BASE_URL ?>assets/img/phone.png" alt="Kahoot Mobile App" class="phone-frame">
                        <video class="phone-video" autoplay muted loop playsinline webkit-playsinline="true">
                            <source src="<?= BASE_URL ?>assets/video/home-mobile.mp4" type="video/mp4">
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= HOW IT WORKS ================= -->
<div class="how-it-works">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="font-weight-bold text-dark" style="font-size: 2.2rem;">How It Works</h2>
            <p class="text-muted" style="max-width: 500px; margin: 0 auto;">Only 4 simple steps to start an exciting trivia challenge!</p>
        </div>

        <div class="row">
            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-icon">📝</div>
                    <h5 class="font-weight-bold">Create Quiz</h5>
                    <p class="text-muted small">Compose multiple-choice questions with 4 answers, customize timers and point values.</p>
                    <span class="step-connector d-none d-lg-block"><i class="fas fa-chevron-right"></i></span>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-icon">🎮</div>
                    <h5 class="font-weight-bold">Host Game</h5>
                    <p class="text-muted small">The system generates a 6-digit game PIN. Share it for everyone to join.</p>
                    <span class="step-connector d-none d-lg-block"><i class="fas fa-chevron-right"></i></span>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-icon">⚡</div>
                    <h5 class="font-weight-bold">Compete</h5>
                    <p class="text-muted small">Players submit answers on their phones while scores update in real time on the big screen.</p>
                    <span class="step-connector d-none d-lg-block"><i class="fas fa-chevron-right"></i></span>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <div class="step-number">4</div>
                    <div class="step-icon">🏆</div>
                    <h5 class="font-weight-bold">Podium</h5>
                    <p class="text-muted small">Dynamic leaderboard animations, top 3 podium celebration, and confetti fireworks!</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= STATS ================= -->
<div class="stats-section">
    <div class="container">
        <div class="row">
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number">4</div>
                    <div class="stat-label">Classic Colors</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number">6</div>
                    <div class="stat-label">Game PIN Digits</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number">∞</div>
                    <div class="stat-label">Quizzes Allowed</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number">Real-time</div>
                    <div class="stat-label">Live Result Sync</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= 4 TÍNH NĂNG NỔI BẬT ================= -->
<div class="feature-cards-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="font-weight-bold text-dark" style="font-size: 2.2rem;">Classic 4-Color Experience</h2>
            <p class="text-muted">Experience next-level interactive learning and real-time multiplayer competition</p>
        </div>

        <div class="row">
            <!-- Khối 1: Đỏ - Xác thực & Tổng quan -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100 border-0 shadow-sm text-white feature-card-enhanced" style="background: linear-gradient(145deg, #e21b3c, #b3142e); border-radius: 16px;">
                    <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-3 feature-icon-ring d-inline-flex align-items-center justify-content-center">
                                <span style="font-size: 2.2rem;">🔺</span>
                            </div>
                            <h4 class="font-weight-bold mb-2">Dashboard</h4>
                            <p class="badge badge-light text-dark font-weight-bold mb-3 px-3 py-1">Secure Management</p>
                            <p class="small text-light" style="line-height: 1.6;">Register, log in, manage personal account, change password, and view analytics overview.</p>
                        </div>
                        <div>
                            <a href="<?= BASE_URL ?>modules/auth/dashboard.php" class="btn btn-light btn-sm font-weight-bold mt-3 text-danger px-3 py-2 shadow-sm rounded-pill">
                                Go to Dashboard &raquo;
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Khối 2: Xanh dương - Bộ đề Quiz -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100 border-0 shadow-sm text-white feature-card-enhanced" style="background: linear-gradient(145deg, #1368ce, #0c4da0); border-radius: 16px;">
                    <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-3 feature-icon-ring d-inline-flex align-items-center justify-content-center">
                                <span style="font-size: 2.2rem;">🔷</span>
                            </div>
                            <h4 class="font-weight-bold mb-2">Quiz Creator</h4>
                            <p class="badge badge-light text-dark font-weight-bold mb-3 px-3 py-1">Flexible Builder</p>
                            <p class="small text-light" style="line-height: 1.6;">Create rich quizzes, visually edit questions, customize time limits (10s/20s/30s/60s), and set scores.</p>
                        </div>
                        <div>
                            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-light btn-sm font-weight-bold mt-3 text-primary px-3 py-2 shadow-sm rounded-pill">
                                Manage Quizzes &raquo;
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Khối 3: Vàng - Host Máy chiếu & Bảng xếp hạng -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100 border-0 shadow-sm text-white feature-card-enhanced" style="background: linear-gradient(145deg, #d89e00, #a97c00); border-radius: 16px;">
                    <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-3 feature-icon-ring d-inline-flex align-items-center justify-content-center">
                                <span style="font-size: 2.2rem;">🟡</span>
                            </div>
                            <h4 class="font-weight-bold mb-2">Host Screen</h4>
                            <p class="badge badge-light text-dark font-weight-bold mb-3 px-3 py-1">Real-Time Live Play</p>
                            <p class="small text-light" style="line-height: 1.6;">Instant room hosting with 6-digit PIN, synchronized countdowns, live score jumps, and auto-transitions.</p>
                        </div>
                        <div>
                            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-light btn-sm font-weight-bold mt-3 px-3 py-2 shadow-sm rounded-pill" style="color: #8f6700 !important;">
                                Host a Game &raquo;
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Khối 4: Xanh lá - Giao diện Player -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100 border-0 shadow-sm text-white feature-card-enhanced" style="background: linear-gradient(145deg, #26890c, #1a6207); border-radius: 16px;">
                    <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-3 feature-icon-ring d-inline-flex align-items-center justify-content-center">
                                <span style="font-size: 2.2rem;">🟩</span>
                            </div>
                            <h4 class="font-weight-bold mb-2">Player Experience</h4>
                            <p class="badge badge-light text-dark font-weight-bold mb-3 px-3 py-1">Cross-Device Friendly</p>
                            <p class="small text-light" style="line-height: 1.6;">Join instantly via PIN and nickname, responsive 4-color buttons, and seamless multi-tab support.</p>
                        </div>
                        <div>
                            <a href="<?= BASE_URL ?>modules/player/join.php" class="btn btn-light btn-sm font-weight-bold mt-3 text-success px-3 py-2 shadow-sm rounded-pill">
                                Join Game Now &raquo;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- ================= VIDEO SHOWCASE (HOME.MP4) ================= -->
<div class="video-showcase-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge badge-pill badge-primary px-3 py-2 font-weight-bold mb-3" style="font-size: 0.85rem; background: linear-gradient(135deg, #46178F, #7b3fc4);">
                🎬 LIVE EXPERIENCE
            </span>
            <h2 class="font-weight-bold text-dark" style="font-size: 2.2rem;">See Kahoot! Bring Learning to Life</h2>
            <p class="text-muted" style="max-width: 620px; margin: 0 auto;">Discover how Kahoot transforms ordinary lessons into vibrant, interactive knowledge arenas.</p>
        </div>

        <!-- Khung chiếu video dạng macOS Cinema Window -->
        <div class="video-cinema-frame">
            <div class="video-window-header">
                <div class="window-dots">
                    <span class="dot red"></span>
                    <span class="dot yellow"></span>
                    <span class="dot green"></span>
                </div>
                <div class="window-title">
                    <i class="fas fa-play-circle mr-1 text-warning"></i> Kahoot! Classroom Experience - Demo Video
                </div>
                <div class="d-none d-md-block" style="width: 50px;"></div>
            </div>
            <div class="cinema-video-container">
                <video class="cinema-video" controls autoplay muted loop playsinline poster="<?= BASE_URL ?>assets/img/banner.png">
                    <source src="<?= BASE_URL ?>assets/video/home.mp4" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>

        <!-- 3 Điểm nhấn giá trị trải nghiệm -->
        <div class="row text-center mt-4">
            <div class="col-md-4 mb-4">
                <div class="video-feature-pill">
                    <div class="mb-3" style="font-size: 2.5rem;">🎯</div>
                    <h5 class="font-weight-bold text-dark">100% Student Engagement</h5>
                    <p class="text-muted small mb-0">Every participant answers directly from their phone or browser without installing any app.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="video-feature-pill">
                    <div class="mb-3" style="font-size: 2.5rem;">⚡</div>
                    <h5 class="font-weight-bold text-dark">Real-Time Scoring & Feedback</h5>
                    <p class="text-muted small mb-0">Instant vote analytics, speed-based scoring, and automatic leaderboard updates after every round.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="video-feature-pill">
                    <div class="mb-3" style="font-size: 2.5rem;">🏆</div>
                    <h5 class="font-weight-bold text-dark">Learn Through Play</h5>
                    <p class="text-muted small mb-0">Fosters healthy learning competition, sparks excitement, and reinforces knowledge retention through gamified trivia.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= CTA SECTION ================= -->
<div class="cta-section text-center">
    <div class="container position-relative" style="z-index: 1;">
        <h2 class="font-weight-bold mb-3" style="font-size: 2.2rem;">Ready to get started?</h2>
        <p class="lead mb-4" style="color: rgba(255,255,255,0.85); max-width: 500px; margin: 0 auto 30px;">
            Create a free quiz or join an exciting live challenge with your friends right now!
        </p>
        <div class="d-flex flex-wrap justify-content-center" style="gap: 20px;">
            <a href="<?= BASE_URL ?>modules/player/join.php" class="btn btn-light text-success cta-btn">
                <i class="fas fa-play mr-2"></i> Join Game Now
            </a>
            <?php if (empty($_SESSION['id_creator'])): ?>
                <button type="button" class="btn btn-outline-light cta-btn" data-toggle="modal" data-target="#signUpModal">
                    <i class="fas fa-user-plus mr-2"></i> Create Account
                </button>
            <?php else: ?>
                <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn btn-outline-light cta-btn">
                    <i class="fas fa-plus-circle mr-2"></i> Create New Quiz
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
