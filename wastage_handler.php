<?php
include_once("db.php");
date_default_timezone_set('Asia/Kolkata');

// Auto-create table on first use
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS daily_wastage (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        product_id  INT NOT NULL,
        prod_name   VARCHAR(255) NOT NULL,
        wastage_qty INT NOT NULL DEFAULT 0,
        reason      ENUM('unsold','damaged','other') NOT NULL DEFAULT 'unsold',
        notes       VARCHAR(500) DEFAULT NULL,
        wastage_date DATE NOT NULL,
        created_at  DATETIME NOT NULL
    )
");

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'log') {
    $product_id = intval($_GET['product_id'] ?? 0);
    $qty        = intval($_GET['qty'] ?? 0);
    $reason     = in_array($_GET['reason'] ?? '', ['unsold','damaged','other']) ? $_GET['reason'] : 'unsold';
    $notes      = substr(trim($_GET['notes'] ?? ''), 0, 500);
    $prod_name  = substr(trim($_GET['prod_name'] ?? ''), 0, 255);
    // Match the shop session logic: before 2 AM belongs to the previous day's session
    $hour = (int)date('H');
    $date = $hour < 2 ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
    $now  = date('Y-m-d H:i:s');

    if ($qty <= 0 || $product_id <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid quantity or product']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO daily_wastage (product_id, prod_name, wastage_qty, reason, notes, wastage_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isissss", $product_id, $prod_name, $qty, $reason, $notes, $date, $now);
    if ($stmt->execute()) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();

} elseif ($action === 'list') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $res  = $conn->prepare("
        SELECT id, prod_name, wastage_qty, reason, notes, created_at
        FROM daily_wastage
        WHERE wastage_date = ?
        ORDER BY created_at DESC
    ");
    $res->bind_param("s", $date);
    $res->execute();
    $rows = $res->get_result()->fetch_all(MYSQLI_ASSOC);
    $res->close();
    echo json_encode($rows);

} elseif ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM daily_wastage WHERE id = ?");
    $stmt->bind_param("i", $id);
    echo json_encode(['ok' => $stmt->execute()]);
    $stmt->close();

} else {
    echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
}
?>
