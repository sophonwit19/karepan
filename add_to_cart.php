<?php
session_start();
require_once "db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$menuId = filter_input(INPUT_POST, 'menu_id', FILTER_VALIDATE_INT);

if (!$menuId || $menuId < 1) {
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'เมนูไม่ถูกต้อง']);
        exit;
    }
    header("Location: index.php");
    exit;
}

// ดึงข้อมูลเมนูรวมถึง category_id
$stmt = $conn->prepare("SELECT id, category_id, name, price, image FROM menus WHERE id = ?");

if (!$stmt) {
    http_response_code(500);
    exit("ไม่สามารถตรวจสอบข้อมูลเมนูได้");
}

$stmt->bind_param("i", $menuId);
$stmt->execute();
$result = $stmt->get_result();
$menu = $result->fetch_assoc();
$stmt->close();

if (!$menu) {
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ไม่พบเมนู']);
        exit;
    }
    header("Location: index.php");
    exit;
}

$categoryId = (int)$menu['category_id'];
$isDrink = ($categoryId === 4);

$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
if ($quantity === false || $quantity === null || $quantity < 1 || $quantity > 99) {
    $quantity = 1;
}

// ถ้าเป็นเครื่องดื่ม ไม่ต้องคิดเรื่องความเผ็ดและท็อปปิ้ง
if ($isDrink) {
    $spicy = 'ไม่ระบุ';
    $extras = [];
} else {
    $allowedSpicy = ['ไม่เผ็ด', 'เผ็ดน้อย', 'เผ็ดปานกลาง', 'เผ็ดมาก'];
    $spicy = $_POST['spicy'] ?? 'ไม่เผ็ด';

    if (!in_array($spicy, $allowedSpicy, true)) {
        $spicy = 'ไม่เผ็ด';
    }

    $toppingQuery = $conn->query("SELECT name, price FROM toppings");
    $allowedExtras = [];

    if ($toppingQuery) {
        while ($tRow = $toppingQuery->fetch_assoc()) {
            $allowedExtras[$tRow['name']] = (float)$tRow['price'];
        }
    }

    $submittedExtras = $_POST['extra'] ?? [];
    if (!is_array($submittedExtras)) {
        $submittedExtras = [];
    }

    $extras = [];
    foreach ($submittedExtras as $extra) {
        if (is_string($extra) && array_key_exists($extra, $allowedExtras) && !in_array($extra, $extras, true)) {
            $extras[] = $extra;
        }
    }
}

$basePrice = (float)$menu['price'];
$extraPrice = 0;

if (!$isDrink) {
    foreach ($extras as $extra) {
        $extraPrice += $allowedExtras[$extra];
    }
}

$unitPrice = $basePrice + $extraPrice;

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

sort($extras);
$cartKey = $menuId . '_' . md5($spicy . '|' . implode('|', $extras));

if (isset($_SESSION['cart'][$cartKey])) {
    $oldQuantity = (int)$_SESSION['cart'][$cartKey]['quantity'];
    $_SESSION['cart'][$cartKey]['quantity'] = min(99, $oldQuantity + $quantity);
} else {
    $_SESSION['cart'][$cartKey] = [
        'id'          => (int)$menu['id'],
        'category_id' => $categoryId,
        'name'        => $menu['name'],
        'image'       => $menu['image'] ?? '',
        'base_price'  => $basePrice,
        'extra_price' => $extraPrice,
        'price'       => $unitPrice,
        'quantity'    => $quantity,
        'spicy'       => $spicy,
        'extras'      => $extras
    ];
}

// คำนวณจำนวนชิ้นรวมในตะกร้า
$totalItems = 0;
foreach ($_SESSION['cart'] as $item) {
    $totalItems += (int)($item['quantity'] ?? 0);
}

// ตอบกลับแบบ JSON หากส่งมาจาก AJAX
if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'total_items' => $totalItems,
        'message' => 'เพิ่มลงตะกร้าเรียบร้อย'
    ]);
    exit;
}

$redirectUrl = $_POST['redirect_url'] ?? '';

if (!empty($redirectUrl) && preg_match('/^menu_detail\.php\?id=\d+$/', $redirectUrl)) {
    header("Location: " . $redirectUrl);
} else {
    header("Location: cart.php");
}
exit;
?>