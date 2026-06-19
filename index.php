<?php
/**
 * index.php
 * Homepage: lists all restaurants with search, cuisine filter, and sort.
 * Reads: ?search=, ?cuisine=, ?sort= via $_GET (all optional).
 */
require_once 'db.php';

$search  = isset($_GET['search'])  ? trim($_GET['search'])  : '';
$cuisine = isset($_GET['cuisine']) ? trim($_GET['cuisine']) : '';
$sort    = isset($_GET['sort'])    ? trim($_GET['sort'])    : 'name_asc';

$conditions = [];
$params     = [];

if ($search !== '') {
    $conditions[] = 'r.name LIKE :search';
    $params[':search'] = '%' . $search . '%';
}
if ($cuisine !== '') {
    $conditions[] = 'r.cuisine_type = :cuisine';
    $params[':cuisine'] = $cuisine;
}

$where = count($conditions) > 0
    ? 'WHERE ' . implode(' AND ', $conditions)
    : '';

// Whitelist sort to prevent SQL injection via ORDER BY (can't bind column names)
$orderBy = match($sort) {
    'rating_desc' => 'avg_rating DESC NULLS LAST',
    'name_asc'    => 'r.name ASC',
    default       => 'r.name ASC',
};

$sql = "
    SELECT r.id, r.name, r.cuisine_type, r.location, r.image,
           ROUND(avg_sub.avg_rating, 1) AS avg_rating
    FROM   restaurants r
    LEFT JOIN (
        SELECT restaurant_id, AVG(rating) AS avg_rating
        FROM   reviews
        GROUP  BY restaurant_id
    ) avg_sub ON avg_sub.restaurant_id = r.id
    $where
    ORDER BY $orderBy
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$restaurants = $stmt->fetchAll();

// Cuisine dropdown options — pulled from DB, never hardcoded
$cuisineStmt = $pdo->query("SELECT DISTINCT cuisine_type FROM restaurants ORDER BY cuisine_type");
$cuisines    = $cuisineStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle  = 'DineHub — Restaurant Listings';
$activePage = 'home';
require_once 'includes/header.php';
?>

<!-- Hero -->
<section class="hero">
    <h1>Discover Your Next <span>Favourite</span> Restaurant</h1>
    <p>Browse, filter and review the best restaurants around you.</p>
</section>

<!-- Sticky filter bar -->
<div class="filter-bar">
    <form class="filter-inner" method="get" action="index.php">

        <input type="text"
               name="search"
               placeholder="🔍  Search by restaurant name…"
               value="<?= htmlspecialchars($search) ?>">

        <select name="cuisine">
            <option value="">All Cuisines</option>
            <?php foreach ($cuisines as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"
                    <?= $cuisine === $c ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort">
            <option value="name_asc"    <?= $sort === 'name_asc'    ? 'selected' : '' ?>>Sort: A–Z</option>
            <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>Sort: Highest Rated</option>
        </select>

        <button type="submit" class="btn btn-search">Search</button>

        <?php if ($search !== '' || $cuisine !== '' || $sort !== 'name_asc'): ?>
            <a href="index.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>

    </form>
</div>

<!-- Results -->
<div class="main-container">
    <div class="results-meta">
        <span class="count">
            <?= count($restaurants) ?> restaurant<?= count($restaurants) !== 1 ? 's' : '' ?> found
        </span>
    </div>

    <?php if (count($restaurants) === 0): ?>
        <div class="empty-state">
            <div class="empty-icon">🍽</div>
            <h3>No restaurants found</h3>
            <p>Try a different search term or <a href="index.php">clear all filters</a>.</p>
        </div>

    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($restaurants as $row): ?>
                <a class="card" href="restaurant.php?id=<?= (int) $row['id'] ?>">

                    <div class="card-img-wrap">
                        <?php if (!empty($row['image']) && file_exists(__DIR__ . '/uploads/' . $row['image'])): ?>
                            <img src="uploads/<?= htmlspecialchars($row['image']) ?>"
                                 alt="<?= htmlspecialchars($row['name']) ?>">
                        <?php else: ?>
                            <div class="no-image">🍽</div>
                        <?php endif; ?>
                        <span class="card-badge"><?= htmlspecialchars($row['cuisine_type']) ?></span>
                    </div>

                    <div class="card-body">
                        <div class="card-name"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="card-location">
                            📍 <?= htmlspecialchars($row['location']) ?>
                        </div>
                        <div class="card-rating">
                            <?php if ($row['avg_rating'] !== null): ?>
                                ★ <?= $row['avg_rating'] ?> <span style="color:var(--text-muted);font-weight:400">/ 5</span>
                            <?php else: ?>
                                <span class="no-rating">No reviews yet</span>
                            <?php endif; ?>
                        </div>
                    </div>

                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
