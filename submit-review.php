<?php
/**
 * submit-review.php
 * GET:  Shows the review form (pre-selects restaurant if ?id= is given).
 * POST: Validates input server-side, checks for duplicates, inserts into reviews,
 *       then shows the submitted review on success.
 */
require_once 'db.php';

// --- Load restaurant list for the dropdown (needed on GET and on failed POST) ---
$restaurantStmt = $pdo->query("SELECT id, name FROM restaurants ORDER BY name");
$allRestaurants = $restaurantStmt->fetchAll();

// --- Pre-selected restaurant id from URL (e.g. coming from restaurant.php?id=3) ---
// We validate it the same way as restaurant.php — must be a positive integer.
$preselectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

// --- Initialise form field values and errors ---
// These get overwritten on a failed POST so the user doesn't retype everything.
$fields = [
    'restaurant_id'   => $preselectedId ?: '',
    'customer_name'   => '',
    'email'           => '',
    'rating'          => '',
    'review_text'     => '',
];
$errors  = [];
$success = null; // will hold the submitted review data after a successful insert

// ============================================================
//  POST handler — server-side validation + insert
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Repopulate fields from POST so the form re-displays with the user's input
    $fields['restaurant_id'] = trim($_POST['restaurant_id'] ?? '');
    $fields['customer_name'] = trim($_POST['customer_name'] ?? '');
    $fields['email']         = trim($_POST['email']         ?? '');
    $fields['rating']        = trim($_POST['rating']        ?? '');
    $fields['review_text']   = trim($_POST['review_text']   ?? '');

    // --- Required-field checks ---
    if ($fields['restaurant_id'] === '') $errors['restaurant_id'] = 'Please select a restaurant.';
    if ($fields['customer_name'] === '') $errors['customer_name'] = 'Name is required.';
    if ($fields['email']         === '') $errors['email']         = 'Email is required.';
    if ($fields['rating']        === '') $errors['rating']        = 'Please select a rating.';
    if ($fields['review_text']   === '') $errors['review_text']   = 'Review text is required.';

    // --- Email format check (PHP native, independent of JS) ---
    if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // --- Rating range check: must be an integer 1–5 ---
    if ($fields['rating'] !== '') {
        $ratingInt = filter_var($fields['rating'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5]
        ]);
        if ($ratingInt === false) {
            $errors['rating'] = 'Rating must be between 1 and 5.';
        } else {
            $fields['rating'] = $ratingInt; // store the clean integer
        }
    }

    // --- Validate restaurant_id exists in the DB (prevent forged POST) ---
    if (!isset($errors['restaurant_id'])) {
        $checkStmt = $pdo->prepare("SELECT id, name FROM restaurants WHERE id = :id");
        $checkStmt->execute([':id' => (int) $fields['restaurant_id']]);
        $chosenRestaurant = $checkStmt->fetch();
        if (!$chosenRestaurant) {
            $errors['restaurant_id'] = 'Selected restaurant does not exist.';
        }
    }

    // --- Duplicate prevention ---
    // Block the same email from reviewing the same restaurant more than once
    // within the last 24 hours. Simple and explainable without overengineering.
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
            $errors['email'] = 'You have already submitted a review for this restaurant in the last 24 hours.';
        }
    }

    // --- Insert if no errors ---
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

        // Fetch the inserted row back (with the auto-generated created_at timestamp)
        // and JOIN to get the restaurant name in one query.
        $successStmt = $pdo->prepare(
            "SELECT rv.customer_name, rv.email, rv.rating, rv.review_text, rv.created_at,
                    rs.name AS restaurant_name
             FROM   reviews rv
             JOIN   restaurants rs ON rs.id = rv.restaurant_id
             WHERE  rv.id = :id"
        );
        $successStmt->execute([':id' => $newId]);
        $success = $successStmt->fetch();

        // Reset fields so the empty form would show again if page is refreshed,
        // but we show the success block instead.
        $fields = array_fill_keys(array_keys($fields), '');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Review — DineHub</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; color: #333; }

        header {
            background: #c0392b; color: #fff;
            padding: 1.2rem 2rem;
            display: flex; align-items: center; gap: 1rem;
        }
        header a { color: #fff; text-decoration: none; font-size: 0.9rem; }
        header h1 { font-size: 1.4rem; }

        main { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }

        /* ── Form card ── */
        .form-card {
            background: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .form-card h2 { font-size: 1.4rem; margin-bottom: 1.5rem; }

        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.3rem; }
        input[type="text"],
        input[type="email"],
        select,
        textarea {
            width: 100%; padding: 0.55rem 0.8rem;
            border: 1px solid #ccc; border-radius: 4px;
            font-size: 0.95rem; font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: #c0392b;
            box-shadow: 0 0 0 2px rgba(192,57,43,.15);
        }
        textarea { resize: vertical; min-height: 110px; }

        /* ── Star rating ── */
        .star-row { display: flex; gap: 0.3rem; flex-direction: row-reverse; justify-content: flex-end; }
        .star-row input[type="radio"] { display: none; }
        .star-row label {
            font-size: 1.8rem; cursor: pointer; color: #ccc;
            font-weight: normal;
            transition: color 0.1s;
        }
        /* Colour the hovered star AND all stars to its left (higher value) */
        .star-row label:hover,
        .star-row label:hover ~ label,
        .star-row input[type="radio"]:checked ~ label { color: #e67e22; }

        /* ── Inline errors ── */
        .error-msg {
            display: none; /* hidden by default; JS or PHP shows it */
            color: #c0392b; font-size: 0.82rem; margin-top: 0.3rem;
        }
        .error-msg.visible { display: block; }
        input.invalid, select.invalid, textarea.invalid {
            border-color: #c0392b;
        }

        /* ── Submit button ── */
        .btn-submit {
            width: 100%; padding: 0.7rem;
            background: #c0392b; color: #fff;
            border: none; border-radius: 4px;
            font-size: 1rem; cursor: pointer;
            margin-top: 0.5rem;
        }
        .btn-submit:hover { background: #a93226; }

        /* ── Success card ── */
        .success-card {
            background: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .success-banner {
            background: #eafaf1; border: 1px solid #a9dfbf;
            border-radius: 6px; padding: 1rem 1.2rem;
            color: #1e8449; margin-bottom: 1.5rem;
            font-weight: 600;
        }
        .review-summary { border-top: 1px solid #eee; padding-top: 1rem; }
        .review-summary p { margin-bottom: 0.5rem; font-size: 0.95rem; }
        .review-summary .label { font-weight: 600; color: #555; font-size: 0.82rem; }
        .stars-display { color: #e67e22; font-size: 1.1rem; }
        .action-links { margin-top: 1.5rem; display: flex; gap: 0.8rem; flex-wrap: wrap; }
        .action-links a {
            padding: 0.5rem 1rem; border-radius: 4px;
            text-decoration: none; font-size: 0.9rem;
        }
        .link-primary { background: #c0392b; color: #fff; }
        .link-secondary { background: #eee; color: #333; }
    </style>
</head>
<body>

<header>
    <a href="index.php">← Back to listings</a>
    <h1>DineHub</h1>
</header>

<main>

<?php if ($success): ?>
    <!-- ══════════════════════════════════════════════
         SUCCESS STATE — show the submitted review
         ══════════════════════════════════════════════ -->
    <div class="success-card">
        <div class="success-banner">✓ Your review has been submitted!</div>
        <div class="review-summary">
            <p><span class="label">RESTAURANT</span><br>
               <?= htmlspecialchars($success['restaurant_name']) ?></p>
            <p><span class="label">REVIEWER</span><br>
               <?= htmlspecialchars($success['customer_name']) ?>
               &lt;<?= htmlspecialchars($success['email']) ?>&gt;</p>
            <p><span class="label">RATING</span><br>
               <span class="stars-display">
                   <?= str_repeat('★', (int)$success['rating']) ?>
                   <?= str_repeat('☆', 5 - (int)$success['rating']) ?>
               </span>
               (<?= (int)$success['rating'] ?> / 5)
            </p>
            <p><span class="label">REVIEW</span><br>
               <?= nl2br(htmlspecialchars($success['review_text'])) ?></p>
            <p><span class="label">SUBMITTED</span><br>
               <?= date('d M Y, g:ia', strtotime($success['created_at'])) ?></p>
        </div>
        <div class="action-links">
            <?php
            // Work out the restaurant id from the last POST field (still in scope)
            $backId = (int) ($_POST['restaurant_id'] ?? 0);
            ?>
            <?php if ($backId > 0): ?>
                <a class="link-primary" href="restaurant.php?id=<?= $backId ?>">
                    View restaurant page
                </a>
            <?php endif; ?>
            <a class="link-secondary" href="submit-review.php">Submit another review</a>
            <a class="link-secondary" href="index.php">Back to listings</a>
        </div>
    </div>

<?php else: ?>
    <!-- ══════════════════════════════════════════════
         FORM STATE — new or after failed validation
         ══════════════════════════════════════════════ -->
    <div class="form-card">
        <h2>Write a Review</h2>

        <form id="reviewForm" method="post" action="submit-review.php" novalidate>

            <!-- Restaurant selector -->
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

            <!-- Customer name -->
            <div class="form-group">
                <label for="customer_name">Your Name *</label>
                <input type="text" id="customer_name" name="customer_name"
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
                <!-- Stars are laid out right-to-left via flex row-reverse so CSS ~ sibling
                     selector can highlight the chosen star and all "higher" ones. -->
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
                          class="<?= isset($errors['review_text']) ? 'invalid' : '' ?>"
                          placeholder="Share your experience…"><?= htmlspecialchars($fields['review_text']) ?></textarea>
                <span class="error-msg <?= isset($errors['review_text']) ? 'visible' : '' ?>"
                      id="err-review_text">
                    <?= htmlspecialchars($errors['review_text'] ?? '') ?>
                </span>
            </div>

            <button type="submit" class="btn-submit">Submit Review</button>
        </form>
    </div>

<?php endif; ?>
</main>

<script>
/**
 * Client-side validation — runs BEFORE the form is submitted.
 * The server re-validates everything independently; this is just UX.
 */
(function () {
    const form = document.getElementById('reviewForm');
    if (!form) return; // safety: don't run on the success page

    // Helper: show an error message and mark the field invalid
    function showError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const err   = document.getElementById('err-' + fieldId);
        if (field) field.classList.add('invalid');
        if (err)  { err.textContent = message; err.classList.add('visible'); }
    }

    // Helper: clear an error
    function clearError(fieldId) {
        const field = document.getElementById(fieldId);
        const err   = document.getElementById('err-' + fieldId);
        if (field) field.classList.remove('invalid');
        if (err)  { err.textContent = ''; err.classList.remove('visible'); }
    }

    // Email regex: basic RFC 5322 simplified check
    function isValidEmail(val) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    }

    form.addEventListener('submit', function (e) {
        let hasError = false;

        // Clear all previous client-side errors before re-checking
        ['restaurant_id', 'customer_name', 'email', 'rating', 'review_text']
            .forEach(clearError);

        // Required: restaurant
        const restaurant = document.getElementById('restaurant_id');
        if (!restaurant.value) {
            showError('restaurant_id', 'Please select a restaurant.');
            hasError = true;
        }

        // Required: name
        const name = document.getElementById('customer_name');
        if (!name.value.trim()) {
            showError('customer_name', 'Name is required.');
            hasError = true;
        }

        // Required + format: email
        const email = document.getElementById('email');
        if (!email.value.trim()) {
            showError('email', 'Email is required.');
            hasError = true;
        } else if (!isValidEmail(email.value.trim())) {
            showError('email', 'Please enter a valid email address.');
            hasError = true;
        }

        // Required: rating (at least one radio checked)
        const ratingChecked = form.querySelector('input[name="rating"]:checked');
        if (!ratingChecked) {
            showError('rating', 'Please select a rating.');
            hasError = true;
        }

        // Required: review text
        const reviewText = document.getElementById('review_text');
        if (!reviewText.value.trim()) {
            showError('review_text', 'Review text is required.');
            hasError = true;
        }

        // If any error, stop the form from submitting
        if (hasError) {
            e.preventDefault();
        }
    });
})();
</script>

</body>
</html>
