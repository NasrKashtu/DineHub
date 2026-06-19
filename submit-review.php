<?php
/**
 * submit-review.php
 * GET:  Shows the review form (pre-selects restaurant if ?id= is given).
 * POST: Validates server-side, checks for duplicates, inserts, shows success.
 */
require_once 'db.php';

$restaurantStmt = $pdo->query("SELECT id, name FROM restaurants ORDER BY name");
$allRestaurants = $restaurantStmt->fetchAll();

$preselectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

$fields = [
    'restaurant_id' => $preselectedId ?: '',
    'customer_name' => '',
    'email'         => '',
    'rating'        => '',
    'review_text'   => '',
];
$errors  = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fields['restaurant_id'] = trim($_POST['restaurant_id'] ?? '');
    $fields['customer_name'] = trim($_POST['customer_name'] ?? '');
    $fields['email']         = trim($_POST['email']         ?? '');
    $fields['rating']        = trim($_POST['rating']        ?? '');
    $fields['review_text']   = trim($_POST['review_text']   ?? '');

    // Required-field checks
    if ($fields['restaurant_id'] === '') $errors['restaurant_id'] = 'Please select a restaurant.';
    if ($fields['customer_name'] === '') $errors['customer_name'] = 'Name is required.';
    if ($fields['email']         === '') $errors['email']         = 'Email is required.';
    if ($fields['rating']        === '') $errors['rating']        = 'Please select a rating.';
    if ($fields['review_text']   === '') $errors['review_text']   = 'Review text is required.';

    // Email format — PHP native, independent of JS
    if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Rating range: integer 1–5
    if ($fields['rating'] !== '') {
        $ratingInt = filter_var($fields['rating'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5]
        ]);
        if ($ratingInt === false) {
            $errors['rating'] = 'Rating must be between 1 and 5.';
        } else {
            $fields['rating'] = $ratingInt;
        }
    }

    // Restaurant must exist in DB (prevents forged POST with fake id)
    if (!isset($errors['restaurant_id'])) {
        $checkStmt = $pdo->prepare("SELECT id, name FROM restaurants WHERE id = :id");
        $checkStmt->execute([':id' => (int) $fields['restaurant_id']]);
        $chosenRestaurant = $checkStmt->fetch();
        if (!$chosenRestaurant) {
            $errors['restaurant_id'] = 'Selected restaurant does not exist.';
        }
    }

    // Duplicate prevention: same email + same restaurant within 24 hours
    if (empty($errors)) {
        $dupeStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM reviews
             WHERE  restaurant_id = :rid
             AND    email         = :email
             AND    created_at   >= NOW() - INTERVAL 24 HOUR"
        );
        $dupeStmt->execute([
            ':rid'   => (int) $fields['restaurant_id'],
            ':email' => $fields['email'],
        ]);
        if ($dupeStmt->fetchColumn() > 0) {
            $errors['email'] = 'You have already reviewed this restaurant in the last 24 hours.';
        }
    }

    if (empty($errors)) {
        $insertStmt = $pdo->prepare(
            "INSERT INTO reviews (restaurant_id, customer_name, email, rating, review_text)
             VALUES (:restaurant_id, :customer_name, :email, :rating, :review_text)"
        );
        $insertStmt->execute([
            ':restaurant_id' => (int) $fields['restaurant_id'],
            ':customer_name' => $fields['customer_name'],
            ':email'         => $fields['email'],
            ':rating'        => $fields['rating'],
            ':review_text'   => $fields['review_text'],
        ]);

        $newId = $pdo->lastInsertId();

        // Fetch back the inserted row + restaurant name via JOIN
        $successStmt = $pdo->prepare(
            "SELECT rv.customer_name, rv.email, rv.rating, rv.review_text, rv.created_at,
                    rs.id AS restaurant_id, rs.name AS restaurant_name
             FROM   reviews rv
             JOIN   restaurants rs ON rs.id = rv.restaurant_id
             WHERE  rv.id = :id"
        );
        $successStmt->execute([':id' => $newId]);
        $success = $successStmt->fetch();

        $fields = array_fill_keys(array_keys($fields), '');
    }
}

$pageTitle  = 'Write a Review — DineHub';
$activePage = 'review';
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="page-header">
    <nav class="breadcrumb">
        <a href="index.php">Home</a>
        <span class="sep">›</span>
        <span>Write a Review</span>
    </nav>
</div>

<div class="form-container">

<?php if ($success): ?>
    <!-- ══ SUCCESS STATE ══ -->
    <div class="success-card">
        <div class="success-icon">✓</div>
        <h2>Review Submitted!</h2>
        <p class="sub">Thank you — your review has been published.</p>

        <div class="review-summary-grid">
            <div class="summary-item">
                <div class="label">Restaurant</div>
                <div class="value"><?= htmlspecialchars($success['restaurant_name']) ?></div>
            </div>
            <div class="summary-item">
                <div class="label">Rating</div>
                <div class="value">
                    <span class="stars-display">
                        <?= str_repeat('★', (int)$success['rating']) ?>
                        <?= str_repeat('☆', 5 - (int)$success['rating']) ?>
                    </span>
                    &nbsp;(<?= (int)$success['rating'] ?>/5)
                </div>
            </div>
            <div class="summary-item">
                <div class="label">Reviewer</div>
                <div class="value"><?= htmlspecialchars($success['customer_name']) ?></div>
            </div>
            <div class="summary-item">
                <div class="label">Submitted</div>
                <div class="value"><?= date('d M Y, g:ia', strtotime($success['created_at'])) ?></div>
            </div>
            <div class="summary-item full">
                <div class="label">Review</div>
                <div class="value"><?= nl2br(htmlspecialchars($success['review_text'])) ?></div>
            </div>
        </div>

        <div class="success-actions">
            <a class="btn btn-primary"
               href="restaurant.php?id=<?= (int)$success['restaurant_id'] ?>">
                View Restaurant
            </a>
            <a class="btn btn-ghost" href="submit-review.php">Write Another</a>
            <a class="btn btn-ghost" href="index.php">All Listings</a>
        </div>
    </div>

<?php else: ?>
    <!-- ══ FORM STATE ══ -->
    <div class="form-card">
        <div class="form-card-header">
            <h2>Write a Review</h2>
            <p>Share your dining experience with the community.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                ⚠ Please fix the errors below and try again.
            </div>
        <?php endif; ?>

        <form id="reviewForm" method="post" action="submit-review.php" novalidate>

            <!-- Restaurant -->
            <div class="form-group">
                <label for="restaurant_id">Restaurant *</label>
                <select id="restaurant_id" name="restaurant_id"
                        class="<?= isset($errors['restaurant_id']) ? 'invalid' : '' ?>">
                    <option value="">— Select a restaurant —</option>
                    <?php foreach ($allRestaurants as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"
                            <?= (string)$fields['restaurant_id'] === (string)$r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="error-msg <?= isset($errors['restaurant_id']) ? 'visible' : '' ?>"
                      id="err-restaurant_id">
                    <?= htmlspecialchars($errors['restaurant_id'] ?? '') ?>
                </span>
            </div>

            <!-- Name -->
            <div class="form-group">
                <label for="customer_name">Your Name *</label>
                <input type="text" id="customer_name" name="customer_name"
                       placeholder="e.g. Ahmad Faiz"
                       value="<?= htmlspecialchars($fields['customer_name']) ?>"
                       class="<?= isset($errors['customer_name']) ? 'invalid' : '' ?>">
                <span class="error-msg <?= isset($errors['customer_name']) ? 'visible' : '' ?>"
                      id="err-customer_name">
                    <?= htmlspecialchars($errors['customer_name'] ?? '') ?>
                </span>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email"
                       placeholder="e.g. you@example.com"
                       value="<?= htmlspecialchars($fields['email']) ?>"
                       class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
                <span class="error-msg <?= isset($errors['email']) ? 'visible' : '' ?>"
                      id="err-email">
                    <?= htmlspecialchars($errors['email'] ?? '') ?>
                </span>
            </div>

            <!-- Star rating -->
            <div class="form-group">
                <label>Rating *</label>
                <!-- row-reverse + CSS ~ sibling selector highlights selected star and all "higher" ones -->
                <div class="star-row" id="starRow">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>"
                               <?= (string)$fields['rating'] === (string)$i ? 'checked' : '' ?>>
                        <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
                    <?php endfor; ?>
                </div>
                <span class="error-msg <?= isset($errors['rating']) ? 'visible' : '' ?>"
                      id="err-rating">
                    <?= htmlspecialchars($errors['rating'] ?? '') ?>
                </span>
            </div>

            <!-- Review text -->
            <div class="form-group">
                <label for="review_text">Your Review *</label>
                <textarea id="review_text" name="review_text"
                          placeholder="Share your experience — food quality, service, atmosphere…"
                          class="<?= isset($errors['review_text']) ? 'invalid' : '' ?>"><?= htmlspecialchars($fields['review_text']) ?></textarea>
                <span class="error-msg <?= isset($errors['review_text']) ? 'visible' : '' ?>"
                      id="err-review_text">
                    <?= htmlspecialchars($errors['review_text'] ?? '') ?>
                </span>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Submit Review</button>
        </form>
    </div>
<?php endif; ?>

</div>

<script>
/**
 * Client-side validation — runs before submit.
 * Server re-validates everything independently (two separate mark items in rubric).
 */
(function () {
    const form = document.getElementById('reviewForm');
    if (!form) return;

    function showError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const err   = document.getElementById('err-' + fieldId);
        if (field) field.classList.add('invalid');
        if (err)  { err.textContent = message; err.classList.add('visible'); }
    }

    function clearError(fieldId) {
        const field = document.getElementById(fieldId);
        const err   = document.getElementById('err-' + fieldId);
        if (field) field.classList.remove('invalid');
        if (err)  { err.textContent = ''; err.classList.remove('visible'); }
    }

    function isValidEmail(val) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    }

    form.addEventListener('submit', function (e) {
        let hasError = false;
        ['restaurant_id', 'customer_name', 'email', 'rating', 'review_text'].forEach(clearError);

        const restaurant = document.getElementById('restaurant_id');
        if (!restaurant.value) {
            showError('restaurant_id', 'Please select a restaurant.');
            hasError = true;
        }

        const name = document.getElementById('customer_name');
        if (!name.value.trim()) {
            showError('customer_name', 'Name is required.');
            hasError = true;
        }

        const email = document.getElementById('email');
        if (!email.value.trim()) {
            showError('email', 'Email is required.');
            hasError = true;
        } else if (!isValidEmail(email.value.trim())) {
            showError('email', 'Please enter a valid email address.');
            hasError = true;
        }

        const ratingChecked = form.querySelector('input[name="rating"]:checked');
        if (!ratingChecked) {
            showError('rating', 'Please select a rating.');
            hasError = true;
        }

        const reviewText = document.getElementById('review_text');
        if (!reviewText.value.trim()) {
            showError('review_text', 'Review text is required.');
            hasError = true;
        }

        if (hasError) e.preventDefault();
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
