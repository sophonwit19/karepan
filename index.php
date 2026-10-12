<?php
session_start();
require_once "db.php";

// =========================
// รับค่าเลขโต๊ะผ่าน URL (กรณีสแกน QR Code)
// =========================
if (isset($_GET['table']) && preg_match('/^[0-9]{1,10}$/', $_GET['table'])) {
    $_SESSION['table_number'] = $_GET['table'];
}

// =========================
// รับค่าหน้าปัจจุบัน
// =========================
$view = $_GET['view'] ?? 'menu';
$allowedViews = ['menu', 'popular'];

if (!in_array($view, $allowedViews, true)) {
    $view = 'menu';
}

// =========================
// ดึงหมวดหมู่
// =========================
$categorySql = "SELECT * FROM categories ORDER BY id ASC";
$categoryResult = $conn->query($categorySql);

// =========================
// รับค่าหมวดหมู่และคำค้นหา
// =========================
$categoryId = isset($_GET['category']) ? max(0, (int)$_GET['category']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// =========================
// เตรียมตัวแปรผลลัพธ์
// =========================
$menuResult = null;
$popularResult = null;
$stmt = null;

if ($view === 'menu') {
    if ($categoryId > 0 && $search !== '') {
        $sql = "SELECT * FROM menus WHERE category_id = ? AND name LIKE ? ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        $keyword = "%{$search}%";
        $stmt->bind_param("is", $categoryId, $keyword);
    } elseif ($categoryId > 0) {
        $sql = "SELECT * FROM menus WHERE category_id = ? ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $categoryId);
    } elseif ($search !== '') {
        $sql = "SELECT * FROM menus WHERE name LIKE ? ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        $keyword = "%{$search}%";
        $stmt->bind_param("s", $keyword);
    } else {
        $sql = "SELECT * FROM menus ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
    }

    if ($stmt && $stmt->execute()) {
        $menuResult = $stmt->get_result();
    }
} elseif ($view === 'popular') {
    $sql = "SELECT * FROM popular_menus WHERE is_active = 1";
    if ($search !== '') {
        $sql .= " AND name LIKE ?";
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $conn->prepare($sql);
    if ($search !== '') {
        $keyword = "%{$search}%";
        $stmt->bind_param("s", $keyword);
    }

    if ($stmt && $stmt->execute()) {
        $popularResult = $stmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คาเรปัน | Curry &amp; Bread</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .header-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .kitchen-link-btn {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            background: #7b4f32;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s ease;
        }

        .kitchen-link-btn:hover {
            background: #5f3b26;
        }

        .menu-image { overflow: hidden; }
        .menu-image a { display: block; width: 100%; height: 100%; overflow: hidden; }
        .menu-image img { display: block; width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .menu-image a:hover img { transform: scale(1.04); }
        .menu-info h3 a { color: inherit; text-decoration: none; }
        .menu-info h3 a:hover { color: #8b5e3c; }

        .menu-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
        }

        .menu-bottom .price { margin: 0; font-weight: bold; }

        .add-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            padding: 0;
            border: none;
            border-radius: 50%;
            background: #7b4f32;
            color: #fff;
            font-size: 24px;
            line-height: 1;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            flex-shrink: 0;
        }

        .add-button:hover { background: #5f3b26; transform: scale(1.05); }

        .category-list { display: flex; flex-wrap: wrap; gap: 10px; }
        .category.active { background: #7b4f32; color: #fff; }
        .popular-description { margin-top: 6px; color: #777; font-size: 13px; line-height: 1.4; }
        .empty-message { grid-column: 1 / -1; text-align: center; padding: 30px 12px; color: #777; }

        @media (max-width: 600px) {
            .kitchen-link-btn, .cart-button { padding: 8px 12px; font-size: 13px; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="logo">
        <h1>คาเรปัน</h1>
        <p>Karepan</p>
    </div>

    <div class="header-buttons">
        <a href="kitchen.php" class="kitchen-link-btn">
            ห้องครัว
        </a>
        <a href="cart.php" class="cart-button">
            ตะกร้า
        </a>
    </div>
</header>

<main>
    <section class="hero">
        <h2>คาเรปัน ข้าวแกงกะหรี่</h2>
        <?php if (isset($_SESSION['table_number'])): ?>
            <p><strong>(โต๊ะ <?php echo htmlspecialchars($_SESSION['table_number']); ?>)</strong></p>
        <?php endif; ?>
    </section>

    <section class="search-box">
        <form action="index.php" method="GET">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($view === 'menu' && $categoryId > 0): ?>
                <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
            <?php endif; ?>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ค้นหาเมนูอาหารหรือเครื่องดื่ม...">
            <button type="submit">ค้นหา</button>
        </form>
    </section>

    <section class="categories">
        <h2>ประเภทอาหาร</h2>
        <div class="category-list">
            <a href="index.php" class="category <?php echo ($view === 'menu' && $categoryId === 0) ? 'active' : ''; ?>">
                ทั้งหมด
            </a>
            <a href="index.php?view=popular" class="category <?php echo $view === 'popular' ? 'active' : ''; ?>">
                ⭐ เมนูยอดนิยม
            </a>
            <?php if ($categoryResult): ?>
                <?php while ($category = $categoryResult->fetch_assoc()): ?>
                    <a href="index.php?category=<?php echo (int)$category['id']; ?>" class="category <?php echo ($view === 'menu' && $categoryId === (int)$category['id']) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="menu-section">
        <?php if ($view === 'menu'): ?>
            <h2>เมนูอาหารและเครื่องดื่ม</h2>
            <div class="menu-grid">
                <?php if ($menuResult && $menuResult->num_rows > 0): ?>
                    <?php while ($menu = $menuResult->fetch_assoc()): ?>
                        <?php
                        $menuId = (int)$menu['id'];
                        $detailUrl = 'menu_detail.php?id=' . $menuId;
                        $menuName = htmlspecialchars($menu['name'], ENT_QUOTES, 'UTF-8');
                        $imagePath = trim((string)($menu['image'] ?? ''));
                        ?>
                        <div class="menu-card">
                            <div class="menu-image">
                                <a href="<?php echo $detailUrl; ?>">
                                    <?php if ($imagePath !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $menuName; ?>" loading="lazy">
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="menu-info">
                                <h3><a href="<?php echo $detailUrl; ?>"><?php echo $menuName; ?></a></h3>
                                <div class="menu-bottom">
                                    <p class="price"><?php echo number_format((float)$menu['price'], 2); ?> บาท</p>
                                    <a href="<?php echo $detailUrl; ?>" class="add-button" title="เลือกเมนูนี้">+</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-message">ไม่พบเมนูที่ค้นหา</p>
                <?php endif; ?>
            </div>

        <?php elseif ($view === 'popular'): ?>
            <h2>⭐ เมนูยอดนิยม</h2>
            <div class="menu-grid">
                <?php if ($popularResult && $popularResult->num_rows > 0): ?>
                    <?php while ($popular = $popularResult->fetch_assoc()): ?>
                        <?php
                        $popularId = (int)($popular['menu_id'] ?? $popular['id']);
                        $detailUrl = 'menu_detail.php?id=' . $popularId;
                        $popularName = htmlspecialchars($popular['name'], ENT_QUOTES, 'UTF-8');
                        $popularImage = trim((string)($popular['image'] ?? ''));
                        $popularDescription = trim((string)($popular['description'] ?? ''));
                        ?>
                        <div class="menu-card">
                            <div class="menu-image">
                                <a href="<?php echo $detailUrl; ?>">
                                    <?php if ($popularImage !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($popularImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $popularName; ?>" loading="lazy">
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="menu-info">
                                <h3><a href="<?php echo $detailUrl; ?>"><?php echo $popularName; ?></a></h3>
                                <?php if ($popularDescription !== ''): ?>
                                    <p class="popular-description"><?php echo htmlspecialchars($popularDescription, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <div class="menu-bottom">
                                    <p class="price"><?php echo number_format((float)$popular['price'], 2); ?> บาท</p>
                                    <a href="<?php echo $detailUrl; ?>" class="add-button" title="เลือกเมนูนี้">+</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-message">ยังไม่มีเมนูยอดนิยม หรือไม่พบเมนูที่ค้นหา</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>
    <p>© 2026 คาเรปัน</p>
</footer>

</body>
</html>
<?php
if ($stmt instanceof mysqli_stmt) {
    $stmt->close();
}
?>