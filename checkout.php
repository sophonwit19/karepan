<?php
session_start();
require_once "db.php";

$cart = $_SESSION['cart'] ?? [];
$error = '';
$orderId = 0;
$orderCode = '';
$total = 0;

// ดึงหมายเลขโต๊ะจาก POST หรือดึงจาก SESSION (กรณีสแกน QR Code มา)
$tableNumber = trim($_POST['table_number'] ?? ($_SESSION['table_number'] ?? ''));

// คำนวณยอดรวมจากตะกร้า
foreach ($cart as $item) {
    $price = (float)($item['price'] ?? 0);
    $quantity = (int)($item['quantity'] ?? 0);

    if ($price < 0 || $quantity < 1 || $quantity > 99) {
        $error = 'ข้อมูลรายการอาหารไม่ถูกต้อง กรุณาตรวจสอบตะกร้า';
        break;
    }

    $total += $price * $quantity;
}

// เมื่อกดยืนยันคำสั่งซื้อ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {

    if (empty($cart)) {
        $error = 'ตะกร้าว่าง กรุณาเลือกเมนูก่อน';

    } elseif ($tableNumber === '') {
        $error = 'กรุณาระบุหมายเลขโต๊ะ';

    } elseif (!preg_match('/^[0-9]{1,10}$/', $tableNumber)) {
        $error = 'หมายเลขโต๊ะต้องเป็นตัวเลข 1-10 หลัก';

    } else {

        try {
            $conn->begin_transaction();

            // บันทึกหมายเลขโต๊ะไว้ใน Session เพื่อความสะดวกในการสั่งเพิ่มรอบถัดไป
            $_SESSION['table_number'] = $tableNumber;

            // สร้างเลขออเดอร์
            $orderCode = 'KP' . date('ymdHis') . random_int(100, 999);
            $status = 'รอรับออเดอร์';
            $customerName = 'ลูกค้าหน้าร้าน';

            // บันทึกออเดอร์
            $stmt = $conn->prepare(
                "INSERT INTO orders
                (order_code, customer_name, table_number,
                 total_amount, status)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssds",
                $orderCode,
                $customerName,
                $tableNumber,
                $total,
                $status
            );

            $stmt->execute();
            $orderId = $conn->insert_id;
            $stmt->close();

            // บันทึกรายการอาหารแต่ละรายการ
            $itemStmt = $conn->prepare(
                "INSERT INTO order_items
                (order_id, menu_id, menu_name, unit_price,
                 quantity, spicy, extras, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($cart as $item) {
                $menuId = (int)($item['id'] ?? 0);
                $menuName = (string)($item['name'] ?? '');
                $unitPrice = (float)($item['price'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 1);
                $spicy = (string)($item['spicy'] ?? 'ไม่ระบุ');

                $extras = $item['extras'] ?? [];

                if (is_array($extras)) {
                    $extrasText = implode(', ', $extras);
                } else {
                    $extrasText = (string)$extras;
                }

                $subtotal = $unitPrice * $quantity;

                $itemStmt->bind_param(
                    "iisdissd",
                    $orderId,
                    $menuId,
                    $menuName,
                    $unitPrice,
                    $quantity,
                    $spicy,
                    $extrasText,
                    $subtotal
                );

                $itemStmt->execute();
            }

            $itemStmt->close();
            $conn->commit();

            // ล้างตะกร้าเมื่อบันทึกสำเร็จเท่านั้น
            $_SESSION['cart'] = [];

        } catch (Throwable $e) {
            $conn->rollback();
            $orderId = 0;
            $orderCode = '';
            $error = 'บันทึกคำสั่งซื้อไม่สำเร็จ กรุณาตรวจสอบฐานข้อมูล';
            error_log($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันการสั่งอาหาร | คาเรปัน</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .checkout-box {
            max-width: 720px;
            margin: 0 auto;
            padding: 28px;
            background: #fffdf9;
            border: 1px solid #eadbca;
            border-radius: 18px;
            box-shadow: 0 5px 18px rgba(80,50,30,.07);
        }

        .checkout-box label {
            display: block;
            margin: 20px 0 8px;
            font-weight: bold;
        }

        .checkout-box input {
            width: 100%;
            padding: 13px;
            border: 1px solid #dfc8b2;
            border-radius: 10px;
            background: #fffaf4;
            font-size: 16px;
        }

        .checkout-total {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 24px 0;
            padding: 18px;
            border-radius: 12px;
            background: #f7f1e8;
            font-weight: bold;
        }

        .checkout-total strong,
        .order-code {
            color: #8b5e3c;
        }

        .checkout-total strong {
            font-size: 22px;
        }

        .checkout-submit,
        .checkout-link {
            display: inline-block;
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            background: #7b4f32;
            color: white;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .checkout-submit {
            width: 100%;
            font-size: 16px;
        }

        .checkout-submit:hover,
        .checkout-link:hover {
            background: #5f3b26;
        }

        .checkout-error {
            padding: 14px;
            margin-bottom: 18px;
            border-radius: 10px;
            background: #fff0ec;
            color: #a04432;
        }

        .checkout-success {
            text-align: center;
            padding: 35px 20px;
        }

        .order-code {
            margin: 18px 0;
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>

<body>

<header class="header">
    <div class="logo">
        <h1>คาเรปัน</h1>
        <p>Karepan</p>
    </div>

    <a href="cart.php" class="cart-button">กลับไปตะกร้า</a>
</header>

<main>

    <?php if ($orderId > 0): ?>

        <section class="checkout-box checkout-success">
            <h2>สั่งอาหารสำเร็จแล้ว!</h2>
            <p>กรุณารอร้านรับรายการและเตรียมอาหาร</p>

            <div class="order-code">
                เลขออเดอร์:
                <?php echo htmlspecialchars($orderCode); ?>
            </div>

            <p>โต๊ะหมายเลข <?php echo htmlspecialchars($tableNumber); ?></p>

            <p>
                ยอดรวม:
                <strong><?php echo number_format($total, 2); ?> บาท</strong>
            </p>

            <p>สถานะ: รอรับออเดอร์</p>

            <a href="index.php" class="checkout-link">กลับไปหน้าเมนู</a>
        </section>

    <?php elseif (empty($cart)): ?>

        <section class="checkout-box">
            <h2>ยังไม่มีรายการอาหาร</h2>
            <p>กรุณาเลือกอาหารจากเมนูก่อนสั่ง</p>
            <a href="index.php" class="checkout-link">เลือกเมนูอาหาร</a>
        </section>

    <?php else: ?>

        <section class="hero">
            <h2>ยืนยันการสั่งอาหาร</h2>
            <p>สำหรับลูกค้าที่รับประทานภายในร้าน</p>
        </section>

        <section class="checkout-box">

            <?php if ($error !== ''): ?>
                <div class="checkout-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <h2>ข้อมูลการสั่งอาหาร</h2>

            <form action="checkout.php" method="POST">

                <label for="table_number">หมายเลขโต๊ะ</label>

                <input
                    type="number"
                    id="table_number"
                    name="table_number"
                    min="1"
                    max="9999999999"
                    required
                    value="<?php echo htmlspecialchars($tableNumber); ?>"
                    placeholder="เช่น 1"
                >

                <div class="checkout-total">
                    <span>ยอดรวมทั้งหมด</span>
                    <strong><?php echo number_format($total, 2); ?> บาท</strong>
                </div>

                <button type="submit" class="checkout-submit">
                    ยืนยันการสั่งอาหาร
                </button>

            </form>

        </section>

    <?php endif; ?>

</main>

<footer>
    <p>© 2026 คาเรปัน</p>
</footer>

</body>
</html>