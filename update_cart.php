
<?php

session_start();

// ตรวจสอบว่ามีตะกร้าหรือไม่
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// รับคำสั่งจากฟอร์ม
$action = $_POST['action'] ?? '';
$key = $_POST['key'] ?? '';

// ตรวจสอบว่ารายการนี้อยู่ในตะกร้าจริง
if ($key === '' || !isset($_SESSION['cart'][$key])) {
    header("Location: cart.php");
    exit;
}

// เพิ่มจำนวน
if ($action === 'increase') {

    if ($_SESSION['cart'][$key]['quantity'] < 99) {
        $_SESSION['cart'][$key]['quantity']++;
    }

// ลดจำนวน
} elseif ($action === 'decrease') {

    if ($_SESSION['cart'][$key]['quantity'] > 1) {
        $_SESSION['cart'][$key]['quantity']--;
    }

// ลบรายการ
} elseif ($action === 'remove') {

    unset($_SESSION['cart'][$key]);
}

header("Location: cart.php");
exit;
