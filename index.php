<?php
/**
 * index.php
 * ---------------------------------------------------------------
 * Public landing page for TrashWise.
 * Logged-in users are redirected to the dashboard.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_path_for(current_role()));
}

$pageTitle = 'Home';
$pageCss   = ['index.css'];
$pageJs    = ['index.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container hero__inner">
        <div class="hero__content">
            <p class="hero__eyebrow">Community Waste Management</p>
            <h1 class="hero__title">
                Smarter waste reporting for a cleaner
                <span class="hero__highlight">Minglanilla</span>.
            </h1>
            <p class="hero__text">
                TrashWise helps residents report waste concerns, request collection,
                and stay informed. Staff and administrators get the tools they need
                to respond, monitor, and plan — all in one organized platform.
            </p>
            <div class="hero__actions">
                <a class="btn btn-primary btn-lg" href="<?= e(BASE_URL) ?>/register.php">Get Started</a>
                <a class="btn btn-outline btn-lg" href="<?= e(BASE_URL) ?>/login.php">Log In</a>
            </div>
        </div>

        <div class="hero__art" aria-hidden="true">
            <div class="hero__card hero__card--stat">
                <span class="hero__card-label">Report a concern</span>
                <span class="hero__card-value">In 3 steps</span>
            </div>
            <div class="hero__card hero__card--stat">
                <span class="hero__card-label">Categories</span>
                <span class="hero__card-value">4 waste types</span>
            </div>
            <div class="hero__card hero__card--stat">
                <span class="hero__card-label">Coverage</span>
                <span class="hero__card-value">19 barangays</span>
            </div>
        </div>
    </div>
</section>

<section class="section features">
    <div class="container">
        <h2 class="section-title">What you can do with TrashWise</h2>
        <p class="section-subtitle">
            Everything residents, staff, and administrators need to keep the
            community clean and organized.
        </p>

        <div class="grid grid-3">
            <article class="card card--neumorphic feature-card">
                <div class="feature-card__icon" aria-hidden="true">📝</div>
                <h3 class="card-title">Report Waste Concerns</h3>
                <p class="card-text">Submit reports for overflowing bins, illegal dumping, littering, and improper disposal — complete with photos and location.</p>
            </article>

            <article class="card card--neumorphic feature-card">
                <div class="feature-card__icon" aria-hidden="true">🚛</div>
                <h3 class="card-title">Request Collection</h3>
                <p class="card-text">Request waste pickup for accumulated waste and preferred collection dates through a simple form.</p>
            </article>

            <article class="card card--neumorphic feature-card">
                <div class="feature-card__icon" aria-hidden="true">📍</div>
                <h3 class="card-title">Find Disposal Locations</h3>
                <p class="card-text">View designated disposal facilities and collection points across Minglanilla on an interactive map.</p>
            </article>

            <article class="card card--neumorphic feature-card">
                <div class="feature-card__icon" aria-hidden="true">📚</div>
                <h3 class="card-title">Learn Proper Segregation</h3>
                <p class="card-text">Browse published waste management guides maintained by administrators for each waste category.</p>
            </article>

            <article class="card card--neumorphic feature-card">
                <div class="feature-card__icon" aria-hidden="true">🔔</div>
                <h3 class="card-title">Stay Notified</h3>
                <p class="card-text">Receive in-system notifications whenever your report or collection request changes status.</p>
            </article>

            <article class="card card--neumorphic feature-card">
                <div class="feature-card__icon" aria-hidden="true">📊</div>
                <h3 class="card-title">Analytics for Staff</h3>
                <p class="card-text">Staff and administrators can view charts and statistics generated from real database records.</p>
            </article>
        </div>
    </div>
</section>

<section class="section steps">
    <div class="container">
        <h2 class="section-title">How it works</h2>
        <ol class="steps__list">
            <li class="steps__item">
                <span class="steps__number">1</span>
                <div><h3>Register</h3><p>Create a free resident account in less than a minute.</p></div>
            </li>
            <li class="steps__item">
                <span class="steps__number">2</span>
                <div><h3>Report</h3><p>Submit a waste report with at least two photos and a location.</p></div>
            </li>
            <li class="steps__item">
                <span class="steps__number">3</span>
                <div><h3>Track</h3><p>Follow your report status and receive updates until resolved.</p></div>
            </li>
        </ol>
    </div>
</section>

<section class="section cta">
    <div class="container cta__inner">
        <div>
            <h2 class="cta__title">Ready to help keep Minglanilla clean?</h2>
            <p class="cta__text">Join your community on TrashWise today. Reporting a waste concern only takes a minute.</p>
        </div>
        <a class="btn btn-primary btn-lg" href="<?= e(BASE_URL) ?>/register.php">Create an Account</a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>