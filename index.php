<?php
/**
 * index.php
 * Homepage: lists all restaurants with search, cuisine filter, and sort.
 * Reads: ?search=, ?cuisine=, ?sort= via $_GET (all optional).
 */
require_once 'db.php';

// --- Read and sanitise GET inputs ---
// htmlspecialchars() here is for safely re-displaying the value in the HTML form,
// NOT for SQL safety (that's handled by prepared statements below).
$search  = isset($_GET['search'])  ? trim($_GET['search'])  : '';
$cuisine = isset($_GET['cuisine']) ? trim($_GET['cuisine']) : '';
$sort    = isset($_GET['sort'])    ? trim($_GET['sort'])    : 'name_asc';

// --- Build the WHERE conditions array ---
// We collect conditions as strings and params as an array, then assemble them.
// This avoids touching the SQL string with user input directly.
$conditions = [];
$params     = [];

if ($search !== '') {
    $conditions[] = 'r.name LIKE :search';
    $params[':search'] = '%' . $search . '%';   // LIKE wildcard wrapping
}

if ($cuisine !== '') {
    $conditions[] = 'r.cuisine_type = :cuisine';
    $params[':cuisine'] = $cuisine;
}

$where = count($conditions) > 0
    ? 'WHERE ' . implode(' AND ', $conditions)
    : '';

// --- Determine ORDER BY clause ---
// Only allow known sort values to prevent any SQL injection through the sort param.
$orderBy = match($sort) {
    'rating_desc' => 'avg_rating DESC NULLS LAST',  // highest rated first; NULLs (no reviews) last
    'name_asc'    => 'r.name ASC',
    default       => 'r.name ASC',
};

// --- Main query ---
// The subquery computes each restaurant's average rating once.
// LEFT JOIN keeps restaurants with no reviews (avg_rating will be NULL).
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

// --- Cuisine filter dropdown: pull distinct values from DB (never hardcode) ---
$cuisineStmt = $pdo->query("SELECT DISTINCT cuisine_type FROM restaurants ORDER BY cuisine_type");
$cuisines    = $cuisineStmt->fetchAll(PDO::FETCH_COLUMN); // returns a flat array of strings
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineHub — Restaurant Listings</title>
    <style>
        /* ── Reset & base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; color: #333; }

        /* ── Header ── */
        header {
            background: #c0392b;
            color: #fff;
            padding: 1.2rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header h1 { font-size: 1.8rem; letter-spacing: 1px; }
        header a { color: #fff; text-decoration: none; font-size: 0.9rem; }

        /* ── Search / filter bar ── */
        .controls {
            background: #fff;
            padding: 1rem 2rem;
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            align-items: center;
            border-bottom: 1px solid #ddd;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .controls input[type="text"],
        .controls select {
            padding: 0.5rem 0.8rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.95rem;
            flex: 1;
            min-width: 160px;
        }
        .controls button {
            padding: 0.5rem 1.2rem;
            background: #c0392b;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .controls button:hover { background: #a93226; }
        .controls a.clear-btn {
            padding: 0.5rem 0.8rem;
            color: #555;
            text-decoration: none;
            font-size: 0.9rem;
        }

        /* ── Main container ── */
        main { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }

        .result-count { margin-bottom: 1rem; color: #666; font-size: 0.9rem; }

        /* ── Restaurant grid ── */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 1.4rem;
        }

        /* ── Card ── */
        .card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0,0,0,.14);
        }
        .card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }
        .card .no-image {
            width: 100%;
            height: 160px;
            background: #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-size: 2.5rem;
        }
        .card-body { padding: 0.9rem 1rem 1rem; flex: 1; }
        .card-body h2 { font-size: 1.05rem; margin-bottom: 0.25rem; }
        .card-body .tag {
            display: inline-block;
            background: #fdecea;
            color: #c0392b;
            font-size: 0.75rem;
            padding: 2px 7px;
            border-radius: 12px;
            margin-bottom: 0.4rem;
        }
        .card-body .location { font-size: 0.85rem; color: #666; }
        .card-body .rating {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #e67e22;
        }

        /* ── Empty state ── */
        .empty { text-align: center; padding: 4rem 1rem; color: #888; }
        .empty p { font-size: 1.1rem; }
    </style>
</head>
<body>

<header>
    <h1>DineHub</h1>
    <a href="submit-review.php">+ Write a Review</a>
</header>

<!-- Search / filter / sort form — method GET so the URL stays shareable -->
<form class="controls" method="get" action="index.php">
    <input type="text"
           name="search"
           placeholder="Search by name…"
           value="<?= htmlspecialchars($search) ?>">

    <!-- Cuisine dropdown: options come from the DB query above -->
    <select name="cuisine">
        <option value="">All cuisines</option>
        <?php foreach ($cuisines as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"
                <?= $cuisine === $c ? 'selected' : '' ?>>
                <?= htmlspecialchars($c) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- Sort dropdown -->
    <select name="sort">
        <option value="name_asc"    <?= $sort === 'name_asc'    ? 'selected' : '' ?>>Name A–Z</option>
        <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>Highest Rated</option>
    </select>

    <button type="submit">Search</button>
    <a class="clear-btn" href="index.php">Clear</a>
</form>

<main>
    <p class="result-count">
        <?= count($restaurants) ?> restaurant<?= count($restaurants) !== 1 ? 's' : '' ?> found
    </p>

    <?php if (count($restaurants) === 0): ?>
        <div class="empty">
            <p>No restaurants match your search. <a href="index.php">Show all</a></p>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($restaurants as $row): ?>
                <!-- Each card is an <a> tag — clicking anywhere on the card navigates to the detail page -->
                <a class="card" href="restaurant.php?id=<?= (int) $row['id'] ?>">
                    <?php if (!empty($row['image']) && file_exists(__DIR__ . '/uploads/' . $row['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($row['image']) ?>"
                             alt="<?= htmlspecialchars($row['name']) ?>">
                    <?php else: ?>
                        <div class="no-image">🍽</div>
                    <?php endif; ?>

                    <div class="card-body">
                        <h2><?= htmlspecialchars($row['name']) ?></h2>
                        <span class="tag"><?= htmlspecialchars($row['cuisine_type']) ?></span>
                        <p class="location">📍 <?= htmlspecialchars($row['location']) ?></p>
                        <p class="rating">
                            <?php if ($row['avg_rating'] !== null): ?>
                                ★ <?= $row['avg_rating'] ?> / 5
                            <?php else: ?>
                                No reviews yet
                            <?php endif; ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
