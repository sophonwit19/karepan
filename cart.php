<?php
session_start();
require_once "db.php";

$cart = $_SESSION['cart'] ?? [];
$total = 0;

foreach ($cart as $item) {
    $price = (float)($item['price'] ?? 0);
    $quantity = (int)($item['quantity'] ?? 0);
    $total += $price * $quantity;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตะกร้าอาหาร | คาเรปัน</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-item {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 22px;
            margin-bottom: 16px;
            background: #fffdf9;
            border: 1px solid #eadbca;
            border-radius: 18px;
            box-shadow: 0 5px 18px rgba(80, 50, 30, 0.05);
        }
        .cart-item-info { flex: 1; min-width: 0; }
        .cart-item-info h3 { margin: 0 0 12px; color: #4d3223; font-size: 19px; }
        .cart-item-info p { margin: 7px 0; color: #76563f; font-size: 14px; line-height: 1.6; }
        .cart-item-price { color: #8b5e3c !important; font-size: 18px !important; font-weight: bold; }
        .cart-actions { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; margin-top: 16px; }
        .cart-actions form { margin: 0; }
        .quantity-button { width: 36px; height: 36px; border: 1px solid #dfc8b2; border-radius: 10px; background: #f7f1e8; color: #6f442c; font-size: 20px; cursor: pointer; }
        .quantity-button:hover:not(:disabled) { background: #ead8c4; }
        .quantity-button:disabled { opacity: 0.45; cursor: not-allowed; }
        .quantity-number { min-width: 25px; text-align: center; font-weight: bold; color: #4d3223; }
        .remove-button { padding: 9px 13px; border: 1px solid #d9b8a9; border-radius: 10px; background: #fff5f1; color: #a04432; cursor: pointer; }
        .remove-button:hover { background: #f8e2db; }
        .cart-summary { margin-top: 25px; padding: 25px; background: #fffdf9; border: 1px solid #eadbca; border-radius: 18px; }
        .cart-summary-row { display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .cart-total { color: #8b5e3c; font-size: 26px; font-weight: bold; }
        .cart-buttons { display: flex; justify-content: flex-end; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
        .continue-button, .checkout-button { display: inline-block; padding: 13px 20px; border-radius: 12px; text-decoration: none; text-align: center; font-size: 15px; font-weight: bold; transition: 0.2s; }
        .continue-button { background: #ead8c4; color: #6f442c; }
        .continue-button:hover { background: #dfc5a9; }
        .checkout-button { background: #7b4f32; color: white; }
        .checkout-button:hover { background: #5f3b26; transform: translateY(-1px); }
        .empty-cart { padding: 35px 20px; text-align: center; background: #fffdf9; border: 1px solid #eadbca; border-radius: 18px; }
        .empty-cart p { color: #76563f; line-height: 1.7; }
        @media (max-width: 600px) {
            .cart-item { padding: 16px; }
            .cart-summary { padding: 18px; }
            .cart-summary-row { align-items: flex-start; flex-direction: column; }
            .cart-total { font-size: 23px; }
            .cart-buttons { flex-direction: column; align-items: stretch; }
            .continue-button, .checkout-button { width: 100%; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="logo">
        <h1>คาเรปัน</h1>
        <p>Karepan</p>
    </div>
    <a href="index.php" class="cart-button">&larr; กลับไปเลือกเมนู</a>
</header>

<main>
    <section class="hero">
        <h2>ตะกร้าอาหารของคุณ</h2>
        <p>ตรวจสอบรายการอาหารก่อนยืนยันการสั่งที่ร้าน</p>
    </section>

    <section class="menu-section">
        <h2>รายการที่สั่ง</h2>

        <?php if (empty($cart)): ?>
            <div class="empty-cart">
                <h3>ยังไม่มีรายการอาหารในตะกร้า</h3>
                <p>เลือกเมนูที่คุณชอบ แล้วเพิ่มลงตะกร้าได้เลย</p>
                <a href="index.php" class="continue-button">ไปเลือกเมนูอาหาร</a>
            </div>
        <?php else: ?>
            <?php foreach ($cart as $key => $item): ?>
                <?php
                $name = $item['name'] ?? 'เมนูอาหาร';
                $price = (float)($item['price'] ?? 0);
                $basePrice = (float)($item['base_price'] ?? $price);
                $quantity = (int)($item['quantity'] ?? 1);
                $spicy = $item['spicy'] ?? 'ไม่ระบุ';
                $extras = $item['extras'] ?? [];
                if (!is_array($extras)) { $extras = []; }
                $subtotal = $price * $quantity;

                $categoryId = (int)($item['category_id'] ?? 0);
                $isDrink = ($categoryId === 4);
                ?>
                <article class="cart-item">
                    <div class="cart-item-info">
                        <h3><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo $isDrink ? 'ราคาเครื่องดื่ม' : 'ราคาอาหาร'; ?>: <?php echo number_format($basePrice, 2); ?> บาท</p>
                        
                        <?php if (!$isDrink && $spicy !== 'ไม่ระบุ'): ?>
                            <p>ระดับความเผ็ด: <?php echo htmlspecialchars($spicy, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>

                        <?php if (!$isDrink): ?>
                            <p>รายการเพิ่มเติม: <?php echo empty($extras) ? 'ไม่มี' : htmlspecialchars(implode(', ', $extras), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>

                        <p>ราคาต่อหน่วย: <?php echo number_format($price, 2); ?> บาท</p>
                        <p class="cart-item-price">รวมรายการนี้: <?php echo number_format($subtotal, 2); ?> บาท</p>

                        <div class="cart-actions">
                            <form action="update_cart.php" method="POST">
                                <input type="hidden" name="key" value="<?php echo htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="decrease">
                                <button type="submit" class="quantity-button" <?php echo $quantity <= 1 ? 'disabled' : ''; ?>>-</button>
                            </form>

                            <span class="quantity-number"><?php echo $quantity; ?></span>

                            <form action="update_cart.php" method="POST">
                                <input type="hidden" name="key" value="<?php echo htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="increase">
                                <button type="submit" class="quantity-button" <?php echo $quantity >= 99 ? 'disabled' : ''; ?>>+</button>
                            </form>

                            <form action="update_cart.php" method="POST" onsubmit="return confirm('ต้องการลบเมนูนี้ออกจากตะกร้าหรือไม่?');">
                                <input type="hidden" name="key" value="<?php echo htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="remove-button">ลบรายการ</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <div class="cart-summary">
                <div class="cart-summary-row">
                    <div>
                        <h3>ยอดรวมทั้งหมด</h3>
                        <p>สำหรับการสั่งอาหารภายในร้าน</p>
                    </div>
                    <div class="cart-total"><?php echo number_format($total, 2); ?> บาท</div>
                </div>

                <div class="cart-buttons">
                    <a href="index.php" class="continue-button">เลือกเมนูเพิ่ม</a>
                    <a href="checkout.php" class="checkout-button">ยืนยันการสั่งอาหาร &rarr;</a>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>
    <p>© 2026 คาเรปัน</p>
</footer>

</body>
</html>