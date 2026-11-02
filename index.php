<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "Kahoot! | Make Learning Awesome!";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>

/* Animations */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(35px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInRight {
    from { opacity: 0; transform: translateX(40px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes floatPhone {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-14px); }
}

/* Hero */
.hero-section {
    background: linear-gradient(160deg, #46178F 0%, #280a5c 100%);
    min-height: 560px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    padding: 70px 0 50px;
}
.hero-text {
    opacity: 0;
    animation: fadeInUp 0.7s ease-out forwards;
}
.hero-text .hero-title {
    animation-delay: 0.1s;
}
.hero-text .hero-desc {
    animation: fadeInUp 0.7s ease-out 0.25s forwards;
    opacity: 0;
}
.hero-text .hero-actions {
    animation: fadeInUp 0.7s ease-out 0.4s forwards;
    opacity: 0;
}

.hero-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 2.8rem;
    font-weight: 800;
    line-height: 1.15;
    color: #fff;
    margin-bottom: 16px;
}
.hero-title .accent {
    color: #ffca28;
}
.hero-desc {
    color: #c8b8e0;
    font-size: 1.05rem;
    line-height: 1.7;
    max-width: 480px;
    margin-bottom: 28px;
}
.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

/* Hero buttons — simple, flat */
.btn-hero-primary {
    display: inline-block;
    padding: 11px 26px;
    background: #fff;
    color: #46178F;
    font-weight: 700;
    font-size: 0.93rem;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    transition: opacity 0.2s;
}
.btn-hero-primary:hover {
    opacity: 0.9;
    color: #46178F;
    text-decoration: none;
}
.btn-hero-secondary {
    display: inline-block;
    padding: 11px 26px;
    background: transparent;
    color: #fff;
    font-weight: 700;
    font-size: 0.93rem;
    border-radius: 8px;
    border: 2px solid rgba(255,255,255,0.35);
    text-decoration: none;
    transition: border-color 0.2s;
    cursor: pointer;
}
.btn-hero-secondary:hover {
    border-color: rgba(255,255,255,0.7);
    color: #fff;
    text-decoration: none;
}

/* Phone mockup */
.phone-wrap {
    animation: fadeInRight 0.8s ease-out 0.3s forwards;
    opacity: 0;
    text-align: center;
}
.phone-container {
    position: relative;
    display: inline-block;
    animation: floatPhone 4s ease-in-out infinite;
}
.phone-frame {
    position: relative;
    z-index: 1;
    max-height: 460px;
    filter: drop-shadow(0 20px 40px rgba(0,0,0,0.4));
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
}

/* Steps */
.steps-section {
    background: #fff;
    padding: 70px 0;
}
.section-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 1.8rem;
    font-weight: 800;
    color: #1a1a2e;
    margin-bottom: 6px;
}
.section-sub {
    color: #6b7280;
    font-size: 0.95rem;
    margin-bottom: 40px;
}
.step-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
}
@media (max-width: 767px) {
    .step-row { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .step-row { grid-template-columns: 1fr; }
}
.step-item {
    text-align: center;
    padding: 20px 12px;
    opacity: 0;
    animation: fadeInUp 0.5s ease-out forwards;
}
.step-item:nth-child(1) { animation-delay: 0.1s; }
.step-item:nth-child(2) { animation-delay: 0.2s; }
.step-item:nth-child(3) { animation-delay: 0.3s; }
.step-item:nth-child(4) { animation-delay: 0.4s; }

.step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #46178F;
    color: #fff;
    font-family: 'Montserrat', sans-serif;
    font-size: 1rem;
    font-weight: 800;
    margin-bottom: 14px;
}
.step-item h5 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 700;
    font-size: 1rem;
    color: #1a1a2e;
    margin-bottom: 6px;
}
.step-item p {
    color: #6b7280;
    font-size: 0.85rem;
    line-height: 1.6;
    margin: 0;
}

/* Video */
.video-section {
    background: #f5f3fa;
    padding: 70px 0;
}
.video-wrapper {
    max-width: 860px;
    margin: 0 auto;
    border-radius: 14px;
    overflow: hidden;
    background: #000;
    box-shadow: 0 12px 40px rgba(0,0,0,0.12);
}
.video-wrapper video {
    width: 100%;
    display: block;
    outline: none;
}

/* Features */
.features-section {
    background: #fff;
    padding: 70px 0;
}
.feat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px;
}
@media (max-width: 767px) {
    .feat-grid { grid-template-columns: 1fr; }
}
.feat-card {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 26px 22px;
    transition: box-shadow 0.2s;
}
.feat-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
}
.feat-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
    position: relative;
    top: -1px;
}
.feat-card h5 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 700;
    font-size: 0.95rem;
    color: #1a1a2e;
    margin-bottom: 8px;
    display: inline;
}
.feat-card p {
    color: #6b7280;
    font-size: 0.85rem;
    line-height: 1.65;
    margin: 10px 0 0;
}

/* CTA */
.cta-section {
    background: #46178F;
    padding: 56px 0;
    text-align: center;
}
.cta-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 8px;
}
.cta-desc {
    color: #c8b8e0;
    font-size: 0.95rem;
    margin-bottom: 24px;
    max-width: 420px;
    margin-left: auto;
    margin-right: auto;
}
.cta-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
}
.btn-cta-white {
    display: inline-block;
    padding: 11px 28px;
    background: #fff;
    color: #46178F;
    font-weight: 700;
    font-size: 0.93rem;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    transition: opacity 0.2s;
}
.btn-cta-white:hover {
    opacity: 0.9;
    color: #46178F;
    text-decoration: none;
}
.btn-cta-outline {
    display: inline-block;
    padding: 11px 28px;
    background: transparent;
    color: #fff;
    font-weight: 700;
    font-size: 0.93rem;
    border-radius: 8px;
    border: 2px solid rgba(255,255,255,0.35);
    text-decoration: none;
    transition: border-color 0.2s;
    cursor: pointer;
}
.btn-cta-outline:hover {
    border-color: rgba(255,255,255,0.7);
    color: #fff;
    text-decoration: none;
}

@media (max-width: 991px) {
    .hero-section { padding: 50px 0 40px; min-height: auto; }
    .hero-title { font-size: 2.2rem; }
    .phone-frame { max-height: 360px; }
}
@media (max-width: 767px) {
    .hero-section { padding: 40px 0 30px; }
    .hero-title { font-size: 1.8rem; }
    .phone-frame { max-height: 300px; }
}
</style>

<!-- HERO -->
<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 text-center text-lg-left mb-5 mb-lg-0">
                <div class="hero-text">
                    <h1 class="hero-title">
                        Make learning<br><span class="accent">awesome!</span>
                    </h1>
                    <p class="hero-desc">
                        Host real-time quiz competitions in your classroom or with friends. Create questions, share a PIN, and play together instantly.
                    </p>
                    <div class="hero-actions justify-content-center justify-content-lg-start">
                        <a href="<?= BASE_URL ?>modules/player/join.php" class="btn-hero-primary">
                            Join a Game
                        </a>
                        <?php if (empty($_SESSION['id_creator'])): ?>
                            <button type="button" class="btn-hero-secondary" data-toggle="modal" data-target="#signUpModal">
                                Sign Up Free
                            </button>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn-hero-secondary">
                                My Kahoots
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="phone-wrap">
                    <div class="phone-container">
                        <img src="<?= BASE_URL ?>assets/img/phone.png" alt="Kahoot on mobile" class="phone-frame">
                        <video class="phone-video" autoplay muted loop playsinline>
                            <source src="<?= BASE_URL ?>assets/video/home-mobile.mp4" type="video/mp4">
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HOW IT WORKS -->
<div class="steps-section">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title">How It Works</h2>
            <p class="section-sub">Four steps to a live quiz session</p>
        </div>
        <div class="step-row">
            <div class="step-item">
                <div class="step-num">1</div>
                <h5>Create a Quiz</h5>
                <p>Write your questions, set time limits, and add images or videos.</p>
            </div>
            <div class="step-item">
                <div class="step-num">2</div>
                <h5>Host a Room</h5>
                <p>Start a game and share the 6-digit PIN with your players.</p>
            </div>
            <div class="step-item">
                <div class="step-num">3</div>
                <h5>Play Live</h5>
                <p>Players answer on their phones while scores update in real time.</p>
            </div>
            <div class="step-item">
                <div class="step-num">4</div>
                <h5>See Results</h5>
                <p>Leaderboard reveals the top scorers with a podium celebration.</p>
            </div>
        </div>
    </div>
</div>

<!-- VIDEO -->
<div class="video-section">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title">See It in Action</h2>
            <p class="section-sub">A quick look at a live Kahoot session</p>
        </div>
        <div class="video-wrapper">
            <video controls autoplay muted loop playsinline poster="<?= BASE_URL ?>assets/img/banner.png">
                <source src="<?= BASE_URL ?>assets/video/home.mp4" type="video/mp4">
            </video>
        </div>
    </div>
</div>

<!-- FEATURES -->
<div class="features-section">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title">What You Can Do</h2>
            <p class="section-sub">Everything you need for interactive quizzes</p>
        </div>
        <div class="feat-grid">
            <div class="feat-card">
                <div>
                    <span class="feat-dot" style="background: #e21b3c;"></span>
                    <h5>Quiz Builder</h5>
                </div>
                <p>Create multiple-choice questions with images, videos, and custom timers. Supports Classic, Wager, and Puzzle game modes.</p>
            </div>
            <div class="feat-card">
                <div>
                    <span class="feat-dot" style="background: #1368ce;"></span>
                    <h5>Live Hosting</h5>
                </div>
                <p>Generate a game room with a unique PIN. Control the pace, see live answer counts, and advance questions from your screen.</p>
            </div>
            <div class="feat-card">
                <div>
                    <span class="feat-dot" style="background: #26890c;"></span>
                    <h5>Play on Any Device</h5>
                </div>
                <p>Players join from their phone or laptop browser — no app install needed. Just enter the PIN and a nickname to start.</p>
            </div>
            <div class="feat-card">
                <div>
                    <span class="feat-dot" style="background: #d89e00;"></span>
                    <h5>Real-Time Scores</h5>
                </div>
                <p>Points are calculated by speed and accuracy. Streak bonuses reward consecutive correct answers. Leaderboards update live.</p>
            </div>
            <div class="feat-card">
                <div>
                    <span class="feat-dot" style="background: #46178F;"></span>
                    <h5>Reports & Review</h5>
                </div>
                <p>After each game, view detailed answer breakdowns and player rankings. Save questions for later review and study.</p>
            </div>
            <div class="feat-card">
                <div>
                    <span class="feat-dot" style="background: #e21b3c;"></span>
                    <h5>Puzzle Mode</h5>
                </div>
                <p>A hidden image is divided into tiles. Correct answers reveal pieces. Players race to guess the secret picture first.</p>
            </div>
        </div>
    </div>
</div>

<!-- CTA -->
<div class="cta-section">
    <div class="container">
        <h2 class="cta-title">Ready to play?</h2>
        <p class="cta-desc">Create a quiz or jump into a live game right now.</p>
        <div class="cta-actions">
            <a href="<?= BASE_URL ?>modules/player/join.php" class="btn-cta-white">Join a Game</a>
            <?php if (empty($_SESSION['id_creator'])): ?>
                <button type="button" class="btn-cta-outline" data-toggle="modal" data-target="#signUpModal">
                    Create Account
                </button>
            <?php else: ?>
                <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn-cta-outline">
                    Create New Quiz
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
