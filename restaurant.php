<?php
/**
 * restaurant.php
 * Detail view for a single restaurant.
 * Reads: ?id= via $_GET — must be a valid positive integer.
 */
require_once 'db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($id === false || $id === null) {
    http_response_code(400);
    $pageTitle = 'Invalid ID — DineHub';
    require_once 'includes/header.php';
    echo '<div class="detail-container"><div class="alert alert-error">⚠ Invalid restaurant ID. <a href="index.php">Go back to listings</a></div></div>';
    require_once 'includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = :id");
$stmt->execute([':id' => $id]);
$restaurant = $stmt->fetch();

if ($restaurant === false) {
    http_response_code(404);
    $pageTitle = 'Not Found — DineHub';
    require_once 'includes/header.php';
    echo '<div class="detail-container"><div class="alert alert-error">⚠ Restaurant not found. <a href="index.php">Go back to listings</a></div></div>';
    require_once 'includes/footer.php';
    exit;
}

// All reviews for this restaurant, newest first
$reviewStmt = $pdo->prepare(
    "SELECT customer_name, email, rating, review_text, created_at
     FROM   reviews
     WHERE  restaurant_id = :id
     ORDER  BY created_at DESC"
);
$reviewStmt->execute([':id' => $id]);
$reviews = $reviewStmt->fetchAll();

// Average rating + total count in one query
$avgStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total, ROUND(AVG(rating), 1) AS avg_rating
     FROM   reviews
     WHERE  restaurant_id = :id"
);
$avgStmt->execute([':id' => $id]);
$stats = $avgStmt->fetch();

$pageTitle  = htmlspecialchars($restaurant['name']) . ' — DineHub';
$activePage = 'home';
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span class="sep">›</span>
        <span><?= htmlspecialchars($restaurant['name']) ?></span>
    </nav>
</div>

<div class="detail-container">

    <!-- ── Restaurant detail card ── -->
    <div class="detail-card">

        <?php if (!empty($restaurant['image']) && file_exists(__DIR__ . '/uploads/' . $restaurant['image'])): ?>
            <img class="detail-hero-img"
                 src="uploads/<?= htmlspecialchars($restaurant['image']) ?>"
                 alt="<?= htmlspecialchars($restaurant['name']) ?>">
        <?php else: ?>
            <div class="detail-hero-placeholder">🍽</div>
        <?php endif; ?>

        <div class="detail-body">
            <h2><?= htmlspecialchars($restaurant['name']) ?></h2>

            <div class="detail-meta">
                <span class="detail-badge"><?= htmlspecialchars($restaurant['cuisine_type']) ?></span>
                <span class="detail-location">📍 <?= htmlspecialchars($restaurant['location']) ?></span>

                <?php if ($stats['total'] > 0): ?>
                    <span class="rating-chip">
                        ★ <?= $stats['avg_rating'] ?> / 5
                        &nbsp;·&nbsp; <?= $stats['total'] ?> review<?= $stats['total'] != 1 ? 's' : '' ?>
                    </span>
                <?php else: ?>
                    <span class="rating-chip empty">No reviews yet</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($restaurant['description'])): ?>
                <p class="section-label">About</p>
                <p class="detail-description">
                    <?= nl2br(htmlspecialchars($restaurant['description'])) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($restaurant['opening_hours'])): ?>
                <p class="section-label">Opening Hours</p>
                <div class="detail-hours">
                    🕐 <?= nl2br(htmlspecialchars($restaurant['opening_hours'])) ?>
                </div>
            <?php endif; ?>

            <div class="detail-actions">
                <a class="btn btn-primary"
                   href="submit-review.php?id=<?= $id ?>">✍ Write a Review</a>
                <a class="btn btn-outline"
                   href="edit-restaurant.php?id=<?= $id ?>">✏ Edit Details</a>
                <a class="btn btn-ghost" href="index.php">← All Restaurants</a>
            </div>
        </div>
    </div>

    <!-- ── Reviews ── -->
    <div class="reviews-section">
        <div class="reviews-header">
            <h3>Reviews (<?= $stats['total'] ?>)</h3>
            <a class="btn btn-primary btn-sm" href="submit-review.php?id=<?= $id ?>">
                + Add Yours
            </a>
        </div>

        <?php if (count($reviews) === 0): ?>
            <div class="no-reviews-msg">
                No reviews yet — <a href="submit-review.php?id=<?= $id ?>"
                   style="color:var(--red)">be the first to write one!</a>
            </div>

        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-top">
                        <div class="reviewer-info">
                            <div class="reviewer-name">
                                <?= htmlspecialchars($review['customer_name']) ?>
                            </div>
                            <div class="reviewer-email">
                                <?= htmlspecialchars($review['email']) ?>
                            </div>
                        </div>
                        <div class="review-right">
                            <div class="review-stars">
                                <?= str_repeat('★', (int)$review['rating']) ?>
                                <?= str_repeat('☆', 5 - (int)$review['rating']) ?>
                            </div>
                            <div class="review-date">
                                <?= date('d M Y, g:ia', strtotime($review['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <p class="review-text">
                        <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
