<?php
/**
 * restaurant.php
 * Detail view for a single restaurant.
 * Reads: ?id= via $_GET — must be a valid positive integer.
 */
require_once 'db.php';

// --- Validate the id parameter before touching the DB ---
// filter_var with FILTER_VALIDATE_INT rejects strings, floats, negatives, and missing values.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($id === false || $id === null) {
    // Don't show a broken page — give a clear message and stop.
    http_response_code(400);
    die('<p style="font-family:sans-serif;padding:2rem">Invalid restaurant ID. <a href="index.php">Go back</a></p>');
}

// --- Fetch the restaurant row ---
$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = :id");
$stmt->execute([':id' => $id]);
$restaurant = $stmt->fetch(); // fetch() returns false if not found

if ($restaurant === false) {
    http_response_code(404);
    die('<p style="font-family:sans-serif;padding:2rem">Restaurant not found. <a href="index.php">Go back</a></p>');
}

// --- Fetch all reviews for this restaurant, newest first ---
$reviewStmt = $pdo->prepare(
    "SELECT customer_name, email, rating, review_text, created_at
     FROM   reviews
     WHERE  restaurant_id = :id
     ORDER  BY created_at DESC"
);
$reviewStmt->execute([':id' => $id]);
$reviews = $reviewStmt->fetchAll();

// --- Calculate average rating ---
// We do this with a separate COUNT so we can show "X reviews" as well.
$avgStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total, ROUND(AVG(rating), 1) AS avg_rating
     FROM   reviews
     WHERE  restaurant_id = :id"
);
$avgStmt->execute([':id' => $id]);
$stats = $avgStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($restaurant['name']) ?> — DineHub</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; color: #333; }

        header {
            background: #c0392b;
            color: #fff;
            padding: 1.2rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        header a { color: #fff; text-decoration: none; font-size: 0.9rem; }
        header h1 { font-size: 1.4rem; }

        main { max-width: 860px; margin: 2rem auto; padding: 0 1rem; }

        /* ── Restaurant detail card ── */
        .detail-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            margin-bottom: 2rem;
        }
        .detail-card img {
            width: 100%;
            max-height: 320px;
            object-fit: cover;
        }
        .detail-card .no-image {
            width: 100%;
            height: 200px;
            background: #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #aaa;
        }
        .detail-body { padding: 1.5rem 2rem; }
        .detail-body h2 { font-size: 1.8rem; margin-bottom: 0.5rem; }

        .meta { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 1.2rem; align-items: center; }
        .tag {
            background: #fdecea;
            color: #c0392b;
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 12px;
        }
        .meta-item { font-size: 0.9rem; color: #555; }

        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.95rem;
            color: #e67e22;
            font-weight: 600;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.4rem;
        }
        .description { font-size: 0.95rem; line-height: 1.65; margin-bottom: 1.2rem; }
        .hours { font-size: 0.9rem; color: #444; margin-bottom: 1.2rem; }

        .actions { display: flex; gap: 0.8rem; flex-wrap: wrap; margin-top: 1rem; }
        .btn {
            padding: 0.55rem 1.2rem;
            border-radius: 4px;
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: #c0392b; color: #fff; }
        .btn-primary:hover { background: #a93226; }
        .btn-secondary { background: #eee; color: #333; }
        .btn-secondary:hover { background: #ddd; }

        /* ── Reviews section ── */
        .reviews-section { margin-top: 1rem; }
        .reviews-section h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #eee;
        }

        .review-card {
            background: #fff;
            border-radius: 6px;
            padding: 1rem 1.2rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 0.3rem;
        }
        .reviewer-name { font-weight: 600; font-size: 0.95rem; }
        .reviewer-email { font-size: 0.8rem; color: #888; }
        .review-stars { color: #e67e22; font-size: 1rem; }
        .review-date { font-size: 0.78rem; color: #aaa; }
        .review-text { font-size: 0.92rem; line-height: 1.6; color: #444; margin-top: 0.4rem; }

        .no-reviews { color: #888; font-size: 0.95rem; padding: 1.5rem 0; }
    </style>
</head>
<body>

<header>
    <a href="index.php">← Back to listings</a>
    <h1>DineHub</h1>
</header>

<main>
    <!-- ── Restaurant detail ── -->
    <div class="detail-card">
        <?php if (!empty($restaurant['image']) && file_exists(__DIR__ . '/uploads/' . $restaurant['image'])): ?>
            <img src="uploads/<?= htmlspecialchars($restaurant['image']) ?>"
                 alt="<?= htmlspecialchars($restaurant['name']) ?>">
        <?php else: ?>
            <div class="no-image">🍽</div>
        <?php endif; ?>

        <div class="detail-body">
            <h2><?= htmlspecialchars($restaurant['name']) ?></h2>

            <div class="meta">
                <span class="tag"><?= htmlspecialchars($restaurant['cuisine_type']) ?></span>
                <span class="meta-item">📍 <?= htmlspecialchars($restaurant['location']) ?></span>

                <?php if ($stats['total'] > 0): ?>
                    <span class="rating-badge">
                        ★ <?= $stats['avg_rating'] ?> / 5
                        &nbsp;·&nbsp; <?= $stats['total'] ?> review<?= $stats['total'] != 1 ? 's' : '' ?>
                    </span>
                <?php else: ?>
                    <span class="rating-badge" style="color:#aaa;border-color:#ddd;background:#fafafa">
                        No reviews yet
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($restaurant['description'])): ?>
                <p class="section-title">About</p>
                <p class="description"><?= nl2br(htmlspecialchars($restaurant['description'])) ?></p>
            <?php endif; ?>

            <?php if (!empty($restaurant['opening_hours'])): ?>
                <p class="section-title">Opening Hours</p>
                <p class="hours"><?= nl2br(htmlspecialchars($restaurant['opening_hours'])) ?></p>
            <?php endif; ?>

            <div class="actions">
                <a class="btn btn-primary"
                   href="submit-review.php?id=<?= $id ?>">✏ Write a Review</a>
                <a class="btn btn-secondary"
                   href="edit-restaurant.php?id=<?= $id ?>">Edit Restaurant</a>
            </div>
        </div>
    </div>

    <!-- ── Reviews ── -->
    <div class="reviews-section">
        <h3>Reviews (<?= $stats['total'] ?>)</h3>

        <?php if (count($reviews) === 0): ?>
            <p class="no-reviews">No reviews yet — be the first to write one!</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div>
                            <span class="reviewer-name">
                                <?= htmlspecialchars($review['customer_name']) ?>
                            </span>
                            <br>
                            <span class="reviewer-email">
                                <?= htmlspecialchars($review['email']) ?>
                            </span>
                        </div>
                        <div style="text-align:right">
                            <!-- Build a star string: filled stars up to rating, empty after -->
                            <span class="review-stars">
                                <?= str_repeat('★', (int)$review['rating']) ?>
                                <?= str_repeat('☆', 5 - (int)$review['rating']) ?>
                            </span>
                            <br>
                            <span class="review-date">
                                <?= date('d M Y, g:ia', strtotime($review['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    <p class="review-text"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
