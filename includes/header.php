<?php
/**
 * includes/header.php
 * Shared navbar and <head> section included by every page.
 * Expects the calling page to have set $pageTitle and optionally $activePage.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'DineHub') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar" id="navbar">
    <div class="nav-container">

        <!-- Logo -->
        <a class="nav-logo" href="index.php">
            <span class="logo-icon">🍽</span>
            Dine<span>Hub</span>
        </a>

        <!-- Hamburger button (visible on mobile only) -->
        <button class="hamburger" id="hamburger" aria-label="Toggle navigation" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- Nav links -->
        <ul class="nav-links" id="navLinks" role="list">
            <li>
                <a href="index.php"
                   class="<?= ($activePage ?? '') === 'home' ? 'active' : '' ?>">
                    Home
                </a>
            </li>
            <li>
                <a href="submit-review.php"
                   class="nav-btn <?= ($activePage ?? '') === 'review' ? 'active' : '' ?>">
                    ✍ Write a Review
                </a>
            </li>
        </ul>

    </div>
</nav>

<script>
    /* Hamburger toggle */
    const hamburger = document.getElementById('hamburger');
    const navLinks  = document.getElementById('navLinks');
    hamburger.addEventListener('click', function () {
        const isOpen = navLinks.classList.toggle('open');
        hamburger.classList.toggle('open', isOpen);
        hamburger.setAttribute('aria-expanded', isOpen);
    });

    /* Close menu when a link is clicked */
    navLinks.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            navLinks.classList.remove('open');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', false);
        });
    });

    /* Add .scrolled class to navbar after scrolling 10px */
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', function () {
        navbar.classList.toggle('scrolled', window.scrollY > 10);
    }, { passive: true });
</script>
