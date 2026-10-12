<?php
session_start();
require_once "db.php";

// =========================================
// 1. อัปเดตสถานะออเดอร์เมื่อมีการกดปุ่ม
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = trim($_POST['status']);
    
    $allowedStatuses = ['รอรับออเดอร์', 'กำลังเตรียมอาหาร', 'พร้อมเสิร์ฟ', 'ชำระเงินแล้ว', 'ยกเลิก'];

    if (in_array($newStatus, $allowedStatuses, true) && $orderId > 0) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: kitchen.php");
    exit;
}

// =========================================
// 2. รับค่าแท็บการแสดงผล (orders หรือ analytics)
// =========================================
$tab = $_GET['tab'] ?? 'orders';

// =========================================
// 3. ดึงข้อมูลรายการสั่งซื้อที่ยังไม่เสร็จสิ้น
// =========================================
$sql = "SELECT * FROM orders 
        WHERE status NOT IN ('ชำระเงินแล้ว', 'ยกเลิก') 
        ORDER BY id ASC";
$ordersResult = $conn->query($sql);

$orders = [];
if ($ordersResult && $ordersResult->num_rows > 0) {
    while ($row = $ordersResult->fetch_assoc()) {
        $orderId = (int)$row['id'];
        
        $itemSql = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC";
        $itemStmt = $conn->prepare($itemSql);
        $itemStmt->bind_param("i", $orderId);
        $itemStmt->execute();
        $itemsResult = $itemStmt->get_result();
        
        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = $item;
        }
        $itemStmt->close();
        
        $row['items'] = $items;
        $orders[] = $row;
    }
}

// =========================================
// 4. สรุปข้อมูลทางสถิติและรายได้
// =========================================
// ยอดขายและจำนวนออเดอร์วันนี้
$dailyQuery = $conn->query("SELECT IFNULL(SUM(total_amount), 0) AS total, COUNT(id) AS count FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'ยกเลิก'");
$dailyData = $dailyQuery->fetch_assoc();
$dailyTotal = (float)$dailyData['total'];
$dailyCount = (int)$dailyData['count'];

// รายได้รวมประจำเดือนนี้
$monthlyQuery = $conn->query("SELECT IFNULL(SUM(total_amount), 0) AS total, COUNT(id) AS count FROM orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status != 'ยกเลิก'");
$monthlyData = $monthlyQuery->fetch_assoc();
$monthlyTotal = (float)$monthlyData['total'];
$monthlyCount = (int)$monthlyData['count'];

// ลำดับเมนูขายดีประจำวัน
$topMenusQuery = $conn->query("SELECT oi.menu_name, SUM(oi.quantity) AS total_qty, SUM(oi.subtotal) AS total_sales 
                               FROM order_items oi 
                               JOIN orders o ON oi.order_id = o.id 
                               WHERE DATE(o.created_at) = CURDATE() AND o.status != 'ยกเลิก' 
                               GROUP BY oi.menu_name 
                               ORDER BY total_qty DESC LIMIT 10");

// สรุปท็อปปิ้งที่ถูกสั่งซื้อวันนี้
$toppingsQuery = $conn->query("SELECT oi.extras, SUM(oi.quantity) AS qty 
                               FROM order_items oi 
                               JOIN orders o ON oi.order_id = o.id 
                               WHERE DATE(o.created_at) = CURDATE() AND o.status != 'ยกเลิก' AND oi.extras != '' AND oi.extras IS NOT NULL 
                               GROUP BY oi.extras");

$toppingSummary = [];
if ($toppingsQuery) {
    while ($tRow = $toppingsQuery->fetch_assoc()) {
        $items = explode(',', $tRow['extras']);
        foreach ($items as $tName) {
            $tName = trim($tName);
            if ($tName !== '') {
                if (!isset($toppingSummary[$tName])) {
                    $toppingSummary[$tName] = 0;
                }
                $toppingSummary[$tName] += (int)$tRow['qty'];
            }
        }
    }
    arsort($toppingSummary);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="15">
    <title>ระบบจัดการร้านและวิเคราะห์ยอดขาย | คาเรปัน</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #fbf8f3;
            color: #4a3b32;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .kitchen-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #ffffff;
            padding: 18px 40px;
            border-bottom: 1px solid #ebdcd0;
            box-shadow: 0 2px 10px rgba(107, 68, 42, 0.03);
        }

        .kitchen-title h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #5c3a21;
        }

        .kitchen-title p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #8c786c;
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-btn {
            text-decoration: none;
            color: #ffffff;
            background-color: #7b4f32;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .nav-btn:hover {
            background-color: #5f3b26;
        }

        main {
            max-width: 1280px;
            margin: 0 auto;
            padding: 28px 24px;
        }

        /* การ์ดภาพรวมสถิติ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #ebdcd0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(123, 79, 50, 0.03);
        }

        .stat-card h3 {
            margin: 0 0 8px;
            font-size: 13px;
            color: #8c786c;
            font-weight: 600;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #5c3a21;
        }

        .stat-sub {
            font-size: 12px;
            color: #a39286;
            margin-top: 4px;
        }

        /* ปุ่มสลับหมวดหมู่หน้าจอ */
        .tab-menu {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #ebdcd0;
            margin-bottom: 24px;
        }

        .tab-item {
            padding: 10px 20px;
            text-decoration: none;
            color: #8c786c;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .tab-item.active {
            color: #7b4f32;
            border-bottom-color: #7b4f32;
            background-color: #ffffff;
            border-radius: 8px 8px 0 0;
        }

        /* โครงสร้างการ์ดออเดอร์ */
        .kitchen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 22px;
        }

        .order-card {
            background: #ffffff;
            border: 1px solid #ebdcd0;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 14px rgba(123, 79, 50, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .order-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #f2e8de;
            padding-bottom: 14px;
            margin-bottom: 14px;
        }

        .table-badge {
            background: #7b4f32;
            color: #ffffff;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 700;
            display: inline-block;
        }

        .order-time {
            font-size: 12px;
            color: #8c786c;
            margin-top: 6px;
        }

        .order-code-text {
            font-size: 11px;
            color: #a39286;
            margin-top: 4px;
            text-align: right;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .bg-pending { background: #fff8ec; color: #b45309; border: 1px solid #fde68a; }
        .bg-cooking { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .bg-ready { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

        .items-list {
            margin-bottom: 18px;
        }

        .item-row {
            padding: 10px 0;
            border-bottom: 1px solid #f7f1eb;
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }

        .item-title {
            font-weight: 600;
            font-size: 15px;
            color: #3d2c22;
        }

        .item-qty {
            color: #7b4f32;
            font-size: 16px;
            font-weight: 700;
        }

        .item-detail {
            font-size: 12px;
            color: #766355;
            margin-top: 4px;
            line-height: 1.5;
        }

        .btn-group {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .btn-status {
            flex: 1;
            padding: 10px 14px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .btn-cooking { background: #2563eb; color: #ffffff; }
        .btn-cooking:hover { background: #1d4ed8; }

        .btn-ready { background: #16a34a; color: #ffffff; }
        .btn-ready:hover { background: #15803d; }

        .btn-finish { background: #7b4f32; color: #ffffff; }
        .btn-finish:hover { background: #5f3b26; }

        .btn-cancel { background: #ffffff; color: #dc2626; border: 1px solid #fca5a5; padding: 10px 12px; }
        .btn-cancel:hover { background: #fef2f2; }

        /* ตารางรายงาน */
        .analytics-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .report-card {
            background: #ffffff;
            border: 1px solid #ebdcd0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(123, 79, 50, 0.02);
        }

        .report-card h2 {
            margin: 0 0 16px;
            font-size: 17px;
            color: #5c3a21;
            border-bottom: 1px solid #f2e8de;
            padding-bottom: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f7f1eb;
        }

        .data-table th {
            color: #8c786c;
            font-weight: 600;
            background-color: #faf5f0;
        }

        .empty-orders {
            text-align: center;
            padding: 64px 20px;
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #ebdcd0;
            color: #766355;
        }

        @media (max-width: 900px) {
            .analytics-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<header class="kitchen-header">
    <div class="kitchen-title">
        <h1>คาเรปัน - ระบบจัดการร้านและวิเคราะห์ข้อมูล</h1>
        <p>Kitchen & Analytics Dashboard</p>
    </div>
    <div class="header-nav">
        <a href="index.php" class="nav-btn">หน้าหลักสั่งอาหาร</a>
    </div>
</header>

<main>

    <!-- การ์ดสรุปยอดขายภาพรวม -->
    <section class="stats-grid">
        <div class="stat-card">
            <h3>ยอดขายประจำวันนี้</h3>
            <div class="stat-value"><?php echo number_format($dailyTotal, 2); ?> บาท</div>
            <div class="stat-sub">จำนวน <?php echo $dailyCount; ?> รายการสั่งซื้อ</div>
        </div>

        <div class="stat-card">
            <h3>รายได้รวมประจำเดือนนี้</h3>
            <div class="stat-value"><?php echo number_format($monthlyTotal, 2); ?> บาท</div>
            <div class="stat-sub">จำนวน <?php echo $monthlyCount; ?> รายการสั่งซื้อ</div>
        </div>

        <div class="stat-card">
            <h3>รายการที่กำลังดำเนินการ</h3>
            <div class="stat-value"><?php echo count($orders); ?> ออเดอร์</div>
            <div class="stat-sub">สถานะรอรับและกำลังเตรียมอาหาร</div>
        </div>
    </section>

    <!-- ปุ่มสลับหน้าจอการทำงาน -->
    <div class="tab-menu">
        <a href="kitchen.php?tab=orders" class="tab-item <?php echo $tab === 'orders' ? 'active' : ''; ?>">
            รายการสั่งซื้อหน้าร้าน
        </a>
        <a href="kitchen.php?tab=analytics" class="tab-item <?php echo $tab === 'analytics' ? 'active' : ''; ?>">
            รายงานยอดขายและวัตถุดิบ
        </a>
    </div>

    <!-- ส่วนที่ 1: หน้าจอแสดงรายการสั่งซื้อหน้าร้าน -->
    <?php if ($tab === 'orders'): ?>

        <?php if (empty($orders)): ?>

            <div class="empty-orders">
                <h2>ไม่มีรายการสั่งซื้อที่รอดำเนินการ</h2>
                <p>รายการสั่งซื้อใหม่จากลูกค้าจะแสดงขึ้นที่นี่โดยอัตโนมัติ</p>
            </div>

        <?php else: ?>

            <div class="kitchen-grid">

                <?php foreach ($orders as $order): ?>

                    <?php
                    $badgeClass = 'bg-pending';

                    if ($order['status'] === 'กำลังเตรียมอาหาร') {
                        $badgeClass = 'bg-cooking';
                    } elseif ($order['status'] === 'พร้อมเสิร์ฟ') {
                        $badgeClass = 'bg-ready';
                    }
                    ?>

                    <div class="order-card">

                        <div>
                            <div class="order-top">
                                <div>
                                    <span class="table-badge">โต๊ะ <?php echo htmlspecialchars($order['table_number']); ?></span>
                                    <div class="order-time">เวลา <?php echo date('H:i', strtotime($order['created_at'])); ?> น.</div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="status-badge <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                    <div class="order-code-text"><?php echo htmlspecialchars($order['order_code']); ?></div>
                                </div>
                            </div>

                            <div class="items-list">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="item-row">
                                        <div class="item-header">
                                            <span class="item-title"><?php echo htmlspecialchars($item['menu_name']); ?></span>
                                            <span class="item-qty">x<?php echo (int)$item['quantity']; ?></span>
                                        </div>
                                        
                                        <div class="item-detail">
                                            <?php if ($item['spicy'] !== 'ไม่ระบุ'): ?>
                                                ระดับความเผ็ด: <?php echo htmlspecialchars($item['spicy']); ?><br>
                                            <?php endif; ?>

                                            <?php if (!empty($item['extras'])): ?>
                                                ท็อปปิ้งเพิ่มเติม: <?php echo htmlspecialchars($item['extras']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="btn-group">

                            <?php if ($order['status'] === 'รอรับออเดอร์'): ?>
                                <form action="kitchen.php?tab=orders" method="POST" style="flex: 1;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="status" value="กำลังเตรียมอาหาร">
                                    <button type="submit" class="btn-status btn-cooking">เริ่มทำอาหาร</button>
                                </form>
                            <?php elseif ($order['status'] === 'กำลังเตรียมอาหาร'): ?>
                                <form action="kitchen.php?tab=orders" method="POST" style="flex: 1;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="status" value="พร้อมเสิร์ฟ">
                                    <button type="submit" class="btn-status btn-ready">ทำเสร็จแล้ว (พร้อมเสิร์ฟ)</button>
                                </form>
                            <?php elseif ($order['status'] === 'พร้อมเสิร์ฟ'): ?>
                                <form action="kitchen.php?tab=orders" method="POST" style="flex: 1;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="status" value="ชำระเงินแล้ว">
                                    <button type="submit" class="btn-status btn-finish">เสิร์ฟและเช็กบิลเรียบร้อย</button>
                                </form>
                            <?php endif; ?>

                            <form action="kitchen.php?tab=orders" method="POST" onsubmit="return confirm('ยืนยันยกเลิกออเดอร์นี้หรือไม่?');">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="status" value="ยกเลิก">
                                <button type="submit" class="btn-status btn-cancel">ยกเลิก</button>
                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    <!-- ส่วนที่ 2: หน้าวิเคราะห์ข้อมูลและสรุปการใช้วัตถุดิบ -->
    <?php elseif ($tab === 'analytics'): ?>

        <div class="analytics-section">
            
            <!-- ตารางสรุปอันดับเมนูขายดีประจำวัน -->
            <div class="report-card">
                <h2>อันดับเมนูขายดีประจำวันนี้</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>รายการเมนู</th>
                            <th style="text-align: center;">จำนวนที่ขายได้</th>
                            <th style="text-align: right;">ยอดขายรวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($topMenusQuery && $topMenusQuery->num_rows > 0): ?>
                            <?php while ($mRow = $topMenusQuery->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($mRow['menu_name']); ?></strong></td>
                                    <td style="text-align: center;"><?php echo (int)$mRow['total_qty']; ?> จาน/แก้ว</td>
                                    <td style="text-align: right;"><?php echo number_format((float)$mRow['total_sales'], 2); ?> บาท</td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #8c786c;">ยังไม่มีข้อมูลการขายในวันนี้</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- สรุปประมาณการใช้วัตถุดิบและท็อปปิ้งประจำวัน -->
            <div class="report-card">
                <h2>สรุปการใช้ท็อปปิ้งประจำวัน</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>รายการท็อปปิ้ง</th>
                            <th style="text-align: right;">จำนวนที่ใช้ไป</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($toppingSummary)): ?>
                            <?php foreach ($toppingSummary as $topName => $topQty): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($topName); ?></td>
                                    <td style="text-align: right;"><strong><?php echo $topQty; ?></strong> รายการ</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" style="text-align: center; color: #8c786c;">ยังไม่มีข้อมูลการใช้วัตถุดิบเพิ่มเติม</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    <?php endif; ?>

</main>

</body>
</html>