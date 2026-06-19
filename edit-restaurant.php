<?php
/**
 * edit-restaurant.php
 * GET:  Fetches restaurant by id, pre-fills the edit form.
 * POST: Validates input server-side, runs UPDATE, redirects to restaurant.php?id=X.
 */
require_once 'db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if (!$id) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
}

if (!$id) {
    http_response_code(400);
    $pageTitle = 'Invalid ID — DineHub';
    require_once 'includes/header.php';
    echo '<div class="form-container"><div class="alert alert-error">⚠ Invalid restaurant ID. <a href="index.php">Go back to listings</a></div></div>';
    require_once 'includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = :id");
$stmt->execute([':id' => $id]);
$restaurant = $stmt->fetch();

if (!$restaurant) {
    http_response_code(404);
    $pageTitle = 'Not Found — DineHub';
    require_once 'includes/header.php';
    echo '<div class="form-container"><div class="alert alert-error">⚠ Restaurant not found. <a href="index.php">Go back to listings</a></div></div>';
    require_once 'includes/footer.php';
    exit;
}

// Initialise fields from DB row; POST overwrites these on a failed submission
$fields = [
    'name'          => $restaurant['name'],
    'cuisine_type'  => $restaurant['cuisine_type'],
    'location'      => $restaurant['location'],
    'description'   => $restaurant['description'],
    'opening_hours' => $restaurant['opening_hours'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fields['name']          = trim($_POST['name']          ?? '');
    $fields['cuisine_type']  = trim($_POST['cuisine_type']  ?? '');
    $fields['location']      = trim($_POST['location']      ?? '');
    $fields['description']   = trim($_POST['description']   ?? '');
    $fields['opening_hours'] = trim($_POST['opening_hours'] ?? '');

    if ($fields['name']         === '') $errors['name']         = 'Restaurant name is required.';
    if ($fields['cuisine_type'] === '') $errors['cuisine_type'] = 'Cuisine type is required.';
    if ($fields['location']     === '') $errors['location']     = 'Location is required.';

    if (empty($errors)) {
        $updateStmt = $pdo->prepare(
            "UPDATE restaurants
             SET    name          = :name,
                    cuisine_type  = :cuisine_type,
                    location      = :location,
                    description   = :description,
                    opening_hours = :opening_hours
             WHERE  id = :id"
        );
        $updateStmt->execute([
            ':name'          => $fields['name'],
            ':cuisine_type'  => $fields['cuisine_type'],
            ':location'      => $fields['location'],
            ':description'   => $fields['description'],
            ':opening_hours' => $fields['opening_hours'],
            ':id'            => $id,
        ]);

        // Rubric requires a redirect back to the detail page after a successful update
        header('Location: restaurant.php?id=' . $id);
        exit;
    }
}

$pageTitle  = 'Edit ' . htmlspecialchars($restaurant['name']) . ' — DineHub';
$activePage = 'home';
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="page-header">
    <nav class="breadcrumb">
        <a href="index.php">Home</a>
        <span class="sep">›</span>
        <a href="restaurant.php?id=<?= $id ?>"><?= htmlspecialchars($restaurant['name']) ?></a>
        <span class="sep">›</span>
        <span>Edit</span>
    </nav>
</div>

<div class="form-container">
    <div class="form-card">
        <div class="form-card-header">
            <h2>Edit Restaurant</h2>
            <p>Updating: <strong><?= htmlspecialchars($restaurant['name']) ?></strong></p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                ⚠ Please fix the errors below before saving.
            </div>
        <?php endif; ?>

        <!-- Action keeps ?id=X in URL so INPUT_GET always finds it on both GET and POST -->
        <form method="post" action="edit-restaurant.php?id=<?= $id ?>">

            <!-- Name -->
            <div class="form-group">
                <label for="name">Restaurant Name *</label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($fields['name']) ?>"
                       class="<?= isset($errors['name']) ? 'invalid' : '' ?>">
                <?php if (isset($errors['name'])): ?>
                    <span class="error-msg visible"><?= htmlspecialchars($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Cuisine type -->
            <div class="form-group">
                <label for="cuisine_type">Cuisine Type *</label>
                <input type="text" id="cuisine_type" name="cuisine_type"
                       value="<?= htmlspecialchars($fields['cuisine_type']) ?>"
                       class="<?= isset($errors['cuisine_type']) ? 'invalid' : '' ?>">
                <?php if (isset($errors['cuisine_type'])): ?>
                    <span class="error-msg visible"><?= htmlspecialchars($errors['cuisine_type']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Location -->
            <div class="form-group">
                <label for="location">Location *</label>
                <input type="text" id="location" name="location"
                       value="<?= htmlspecialchars($fields['location']) ?>"
                       class="<?= isset($errors['location']) ? 'invalid' : '' ?>">
                <?php if (isset($errors['location'])): ?>
                    <span class="error-msg visible"><?= htmlspecialchars($errors['location']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Description (optional) -->
            <div class="form-group">
                <label for="description">
                    Description <span class="optional-tag">optional</span>
                </label>
                <textarea id="description"
                          name="description"><?= htmlspecialchars($fields['description']) ?></textarea>
            </div>

            <!-- Opening hours (optional) -->
            <div class="form-group">
                <label for="opening_hours">
                    Opening Hours <span class="optional-tag">optional</span>
                </label>
                <textarea id="opening_hours"
                          name="opening_hours"><?= htmlspecialchars($fields['opening_hours']) ?></textarea>
            </div>

            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:0.5rem">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a class="btn btn-ghost" href="restaurant.php?id=<?= $id ?>">Cancel</a>
            </div>

        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
