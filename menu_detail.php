<?php
session_start();
require_once "db.php";

// =========================
// 1. รับ ID ของเมนู
// =========================
$menuId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$menuId || $menuId <= 0) {
    header("Location: index.php");
    exit;
}

// =========================
// 2. ดึงข้อมูลเมนูจากฐานข้อมูล
// =========================
$stmt = $conn->prepare("SELECT id, category_id, name, price, image, description FROM menus WHERE id = ?");
$stmt->bind_param("i", $menuId);
$stmt->execute();
$result = $stmt->get_result();
$menu = $result->fetch_assoc();
$stmt->close();

if (!$menu) {
    header("Location: index.php");
    exit;
}

// =========================
// 3. ตรวจสอบว่าเป็นหมวดเครื่องดื่มหรือไม่ (category_id = 4)
// =========================
$categoryId = (int)$menu['category_id'];
$isDrink = ($categoryId === 4);

// =========================
// 4. ดึงท็อปปิ้งทั้งหมด (สำหรับเมนูที่ไม่ใช่เครื่องดื่ม)
// =========================
$toppingResult = null;
if (!$isDrink) {
    $toppingResult = $conn->query("SELECT * FROM toppings ORDER BY id ASC");
}

// =========================
// 5. เงื่อนไขรายการแนะนำทานคู่กันตามเมนู (เฉพาะเมนูอาหาร 1-8)
// =========================
$recommendationRules = [
    1 => [
        ['name' => 'หอมทอด', 'type' => 'topping'],
        ['name' => 'ชีส', 'type' => 'topping'],
        ['name' => 'ชาเขียวมะนาว', 'type' => 'menu'],
    ],
    2 => [
        ['name' => 'หัวไชเท้าดอง', 'type' => 'topping'],
        ['name' => 'ขิงดอง', 'type' => 'topping'],
        ['name' => 'ชามะนาว', 'type' => 'menu'],
        ['name' => 'น้ำผึ้งมะนาว', 'type' => 'menu'],
    ],
    3 => [
        ['name' => 'ไข่ออนเซ็น', 'type' => 'topping'],
        ['name' => 'ขิงดอง', 'type' => 'topping'],
        ['name' => 'ชาเขียว', 'type' => 'menu'],
    ],
    4 => [
        ['name' => 'หัวไชเท้าดอง', 'type' => 'topping'],
        ['name' => 'ซุปมิโสะ', 'type' => 'topping'],
        ['name' => 'ชามะนาวน้ำผึ้ง', 'type' => 'menu'],
        ['name' => 'ชาเขียว', 'type' => 'menu'],
    ],
    5 => [
        ['name' => 'ชีส', 'type' => 'topping'],
        ['name' => 'ขิงดอง', 'type' => 'topping'],
        ['name' => 'ชากุหลาบน้ำผึ้งมะนาว', 'type' => 'menu'],
        ['name' => 'ชามะนาว', 'type' => 'menu'],
    ],
    6 => [
        ['name' => 'ขิงดอง', 'type' => 'topping'],
        ['name' => 'ชาเขียวมะนาว', 'type' => 'menu'],
        ['name' => 'น้ำผึ้งมะนาว', 'type' => 'menu'],
    ],
    7 => [
        ['name' => 'ไข่ออนเซ็น', 'type' => 'topping'],
        ['name' => 'หัวไชเท้าดอง', 'type' => 'topping'],
        ['name' => 'ชามะนาว', 'type' => 'menu'],
    ],
    8 => [
        ['name' => 'ชีส', 'type' => 'topping'],
        ['name' => 'ซุปมิโสะ', 'type' => 'topping'],
        ['name' => 'น้ำผึ้งมะนาว', 'type' => 'menu'],
        ['name' => 'ชามะนาวน้ำผึ้ง', 'type' => 'menu'],
    ],
];

// =========================
// 6. ดึงข้อมูลรายการแนะนำ (เฉพาะเมนูอาหาร)
// =========================
$recommendList = [];

if (!$isDrink && isset($recommendationRules[$menuId])) {
    foreach ($recommendationRules[$menuId] as $item) {
        $name = $item['name'];
        $type = $item['type'];

        if ($type === 'topping') {
            $tStmt = $conn->prepare("SELECT id, name, price, image FROM toppings WHERE name = ? LIMIT 1");
            $tStmt->bind_param("s", $name);
            $tStmt->execute();
            $res = $tStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $row['type'] = 'topping';
                $recommendList[] = $row;
            }
            $tStmt->close();
        } else {
            $mStmt = $conn->prepare("SELECT id, name, price, image FROM menus WHERE name = ? LIMIT 1");
            $mStmt->bind_param("s", $name);
            $mStmt->execute();
            $res = $mStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $row['type'] = 'menu';
                $recommendList[] = $row;
            }
            $mStmt->close();
        }
    }
}

// =========================
// 7. คำนวณจำนวนชิ้นในตะกร้าปัจจุบัน
// =========================
$cart = $_SESSION['cart'] ?? [];
$cartTotalItems = 0;
foreach ($cart as $cItem) {
    $cartTotalItems += (int)($cItem['quantity'] ?? 0);
}

$menuName = htmlspecialchars($menu['name'], ENT_QUOTES, 'UTF-8');
$menuImage = htmlspecialchars($menu['image'] ?? '', ENT_QUOTES, 'UTF-8');
$menuPrice = (float)$menu['price'];

if (!empty($menu['description'])) {
    $menuDescription = htmlspecialchars($menu['description'], ENT_QUOTES, 'UTF-8');
} else {
    $menuDescription = $isDrink
        ? 'เมนูเครื่องดื่มสดชื่นของร้านคาเรปัน ดื่มคู่กับแกงกะหรี่เข้ากันได้อย่างลงตัว'
        : 'เมนูอาหารของร้านคาเรปัน สามารถเลือกเพิ่มเติมได้ตามต้องการ';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $menuName; ?> | คาเรปัน</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .detail-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 32px;
            max-width: 1100px;
            margin: 24px auto;
            padding: 28px;
            background: #fffdf9;
            border: 1px solid #eadbca;
            border-radius: 24px;
        }

        .detail-image img {
            display: block;
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 18px;
        }

        .detail-info { min-width: 0; }
        .detail-price { margin: 10px 0 18px; color: #8b5e3c; font-size: 23px; font-weight: bold; }
        .detail-description { margin-bottom: 24px; line-height: 1.8; color: #76563f; }
        .option-group { margin: 22px 0; }
        .option-group h3 { margin-bottom: 10px; font-size: 16px; }

        .topping-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            padding: 12px 14px;
            background: #f8f2eb;
            border: 1px solid #eadbca;
            border-radius: 11px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .option-item:hover { background: #f0e3d4; }
        .option-item input { width: auto; margin: 0; accent-color: #805334; }
        .quantity-box { margin: 22px 0; }
        .quantity-box label { display: block; margin-bottom: 8px; font-weight: bold; }
        .quantity-box input { width: 90px; padding: 10px; border: 1px solid #dfc8b2; border-radius: 10px; font-size: 16px; }

        .detail-add-button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: #7b4f32;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .detail-add-button:hover { background: #5f3b26; }
        .back-button { display: inline-block; margin: 20px max(calc((100% - 1100px) / 2), 20px) 0; color: #805334; text-decoration: none; font-weight: 500; }

        .recommend-section { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .recommend-section .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .recommend-section .menu-card { overflow: hidden; background: #fffdf9; border: 1px solid #eadbca; border-radius: 16px; }
        .recommend-section .menu-image img { width: 100%; height: 180px; object-fit: cover; }
        .recommend-section .menu-info { padding: 16px; }

        /* จัดแถวสเปซราคาและปุ่มบวกทรงกลม */
        .menu-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
        }

        .menu-bottom .price {
            margin: 0;
            font-weight: bold;
            color: #8b5e3c;
        }

        /* ปุ่ม + ทรงกลมตามรูปภาพ */
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
            color: #ffffff;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            flex-shrink: 0;
        }

        .add-button:hover {
            background: #5f3b26;
            transform: scale(1.06);
        }

        .add-button:active {
            transform: scale(0.95);
        }

        .topping-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 7px 12px;
            border-radius: 8px;
            background: #f0e3d4;
            color: #7b4f32;
            font-size: 13px;
            font-weight: 600;
        }

        .cart-button {
            position: relative;
        }

        .cart-badge {
            display: inline-block;
            margin-left: 6px;
            background: #ffffff;
            color: #7b4f32;
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        /* เอฟเฟกต์จุดลอยเข้าตะกร้า */
        .fly-dot {
            position: fixed;
            z-index: 9999;
            width: 18px;
            height: 18px;
            background: #7b4f32;
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s cubic-bezier(0.19, 1, 0.22, 1);
            box-shadow: 0 2px 8px rgba(123, 79, 50, 0.4);
        }

        .cart-bounce {
            animation: bounceCart 0.3s ease;
        }

        @keyframes bounceCart {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }

        @media (max-width: 760px) {
            .detail-card { grid-template-columns: 1fr; margin: 16px; padding: 18px; gap: 22px; }
            .topping-grid { grid-template-columns: 1fr 1fr; }
            .back-button { margin-left: 16px; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="logo">
        <h1>คาเรปัน</h1>
        <p>Karepan</p>
    </div>
    <a href="cart.php" class="cart-button" id="cartBtn">
        ตะกร้า <span class="cart-badge" id="cartBadge"><?php echo $cartTotalItems; ?></span>
    </a>
</header>

<main>
    <a href="index.php" class="back-button">&larr; กลับไปเลือกเมนู</a>

    <section class="detail-card">
        <div class="detail-image">
            <?php if (!empty($menuImage)): ?>
                <img src="<?php echo $menuImage; ?>" alt="<?php echo $menuName; ?>">
            <?php endif; ?>
        </div>

        <div class="detail-info">
            <h2><?php echo $menuName; ?></h2>
            <p class="detail-price"><?php echo number_format($menuPrice, 2); ?> บาท</p>
            <p class="detail-description"><?php echo $menuDescription; ?></p>

            <form action="add_to_cart.php" method="POST">
                <input type="hidden" name="menu_id" value="<?php echo (int)$menu['id']; ?>">

                <?php if (!$isDrink): ?>
                    <div class="option-group">
                        <h3>ระดับความเผ็ด</h3>
                        <?php $spicyOptions = ['ไม่เผ็ด', 'เผ็ดน้อย', 'เผ็ดปานกลาง', 'เผ็ดมาก']; ?>
                        <?php foreach ($spicyOptions as $index => $spicyOption): ?>
                            <label class="option-item">
                                <input type="radio" name="spicy" value="<?php echo htmlspecialchars($spicyOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $index === 0 ? 'checked' : ''; ?> required>
                                <?php echo htmlspecialchars($spicyOption, ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="option-group">
                        <h3>เพิ่มเติม (ท็อปปิ้ง)</h3>
                        <div class="topping-grid">
                            <?php if ($toppingResult && $toppingResult->num_rows > 0): ?>
                                <?php while ($topping = $toppingResult->fetch_assoc()): ?>
                                    <?php
                                    $tName = htmlspecialchars($topping['name'], ENT_QUOTES, 'UTF-8');
                                    $tPrice = (float)$topping['price'];
                                    ?>
                                    <label class="option-item">
                                        <input type="checkbox" name="extra[]" value="<?php echo $tName; ?>">
                                        <?php echo $tName; ?> (+<?php echo number_format($tPrice, 2); ?>฿)
                                    </label>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="quantity-box">
                    <label for="quantity">จำนวน</label>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="99" step="1" required>
                </div>

                <button type="submit" class="detail-add-button">เพิ่มลงตะกร้า</button>
            </form>
        </div>
    </section>

    <?php if (!$isDrink && !empty($recommendList)): ?>
        <section class="recommend-section">
            <h2>รายการแนะนำเครื่องดื่มและท็อปปิ้งที่ทานคู่กัน</h2>
            <div class="menu-grid">
                <?php foreach ($recommendList as $rec): ?>
                    <?php
                    $recName = htmlspecialchars($rec['name'], ENT_QUOTES, 'UTF-8');
                    $recImage = trim((string)($rec['image'] ?? ''));
                    $recPrice = (float)$rec['price'];
                    $recType = $rec['type'] ?? 'menu';
                    ?>
                    <div class="menu-card">
                        <div class="menu-image">
                            <?php if (!empty($recImage)): ?>
                                <img src="<?php echo htmlspecialchars($recImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $recName; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="menu-info">
                            <h3><?php echo $recName; ?></h3>
                            
                            <?php if ($recType === 'menu'): ?>
                                <div class="menu-bottom">
                                    <p class="price"><?php echo number_format($recPrice, 2); ?> บาท</p>
                                    <button type="button" class="add-button" onclick="addDrinkQuick(event, <?php echo (int)$rec['id']; ?>, this)" title="เพิ่มลงตะกร้า">+</button>
                                </div>
                            <?php else: ?>
                                <p class="price">+<?php echo number_format($recPrice, 2); ?> บาท</p>
                                <span class="topping-badge">ท็อปปิ้งแนะนำ (เลือกด้านบน)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer>
    <p>© 2026 คาเรปัน</p>
</footer>

<script>
function addDrinkQuick(event, menuId, btnElement) {
    event.preventDefault();

    const formData = new FormData();
    formData.append('menu_id', menuId);
    formData.append('quantity', 1);
    formData.append('spicy', 'ไม่ระบุ');
    formData.append('ajax', '1');

    fetch('add_to_cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            animateFlyToCart(btnElement);
            
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.innerText = data.total_items;
            }
        }
    })
    .catch(error => {
        console.error('Error adding drink to cart:', error);
    });
}

function animateFlyToCart(startElement) {
    const cartBtn = document.getElementById('cartBtn');
    if (!cartBtn || !startElement) return;

    const startRect = startElement.getBoundingClientRect();
    const endRect = cartBtn.getBoundingClientRect();

    const flyer = document.createElement('div');
    flyer.className = 'fly-dot';
    flyer.style.left = (startRect.left + startRect.width / 2 - 9) + 'px';
    flyer.style.top = (startRect.top + startRect.height / 2 - 9) + 'px';
    document.body.appendChild(flyer);

    setTimeout(() => {
        flyer.style.left = (endRect.left + endRect.width / 2 - 9) + 'px';
        flyer.style.top = (endRect.top + endRect.height / 2 - 9) + 'px';
        flyer.style.transform = 'scale(0.2)';
        flyer.style.opacity = '0.1';
    }, 10);

    setTimeout(() => {
        flyer.remove();
        cartBtn.classList.add('cart-bounce');
        setTimeout(() => cartBtn.classList.remove('cart-bounce'), 300);
    }, 500);
}
</script>

</body>
</html>