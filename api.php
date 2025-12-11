<?php
// 設置 CORS 標頭，允許 Vue 3 前端發送請求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// 處理預檢請求 (Preflight request)，這是瀏覽器發送的檢查請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// =========================================================================================
// 1. 資料庫連線配置 (請務必替換為您的 cPanel 資訊)
// =========================================================================================
$host = 'localhost'; 
$db   = 'masterx0_yamay_products'; // <-- 替換
$user = 'masterx0_admin';       // <-- 替換
$pass = 'admin5308';     // <-- 替換
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
     $pdo = new PDO($dsn, $user, $pass, [
         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
     ]);
     $pdo->exec("SET time_zone = '+8:00';");
} catch (\PDOException $e) {
     http_response_code(500); 
     echo json_encode(['status' => 'error', 'message' => '資料庫連線失敗: ' . $e->getMessage()]); 
     exit;
}

// =========================================================================================
// 2. 路由與動作處理 (根據前端傳來的 action 參數，執行對應的函數)
// =========================================================================================
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register_member': handleRegisterMember($pdo); break;
    case 'search_member': handleSearchMember($pdo); break;
    case 'record_transaction': handleRecordTransaction($pdo); break;
    case 'renew_member': handleRenewMember($pdo); break;
    case 'get_daily_revenue': handleGetRevenue($pdo, 'day'); break;
    case 'get_monthly_revenue': handleGetRevenue($pdo, 'month'); break;
    case 'get_all_members': handleGetAllMembers($pdo); break;
    case 'get_all_transactions': handleGetAllTransactions($pdo); break;
    default: 
        http_response_code(400); 
        echo json_encode(['status' => 'error', 'message' => '無效的 API 動作，請提供 action 參數。']);
        break;
}

// =========================================================================================
// 3. 核心功能函數定義
// =========================================================================================

// [A] 會員註冊 ($3000，給予 10 次額度)
function handleRegisterMember($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['name']) || !isset($data['phone']) || !isset($data['associated_product_id']) || !isset($data['operator'])) {
        http_response_code(400); return;
    }
    $name = trim($data['name']); $phone = trim($data['phone']);
    $product_id = (int)$data['associated_product_id']; 
    $operator = $data['operator'];

    try {
        $pdo->beginTransaction();
        
        $stmt_check = $pdo->prepare("SELECT id FROM members WHERE phone = ?");
        $stmt_check->execute([$phone]);
        if ($stmt_check->fetch()) { throw new \Exception("該電話號碼已存在，請使用續約功能。"); }

        // 寫入 members 表 (給予 10 次額度)
        $sql_member = "INSERT INTO members (name, phone, associated_product_id, remaining_quota, join_date) VALUES (?, ?, ?, 10, NOW())";
        $stmt_member = $pdo->prepare($sql_member);
        $stmt_member->execute([$name, $phone, $product_id]);
        $member_id = $pdo->lastInsertId();

        // 寫入 member_fees 表 (記錄 $3000 收入)
        $sql_fee = "INSERT INTO member_fees (member_id, fee_amount, operator) VALUES (?, 3000.00, ?)";
        $stmt_fee = $pdo->prepare($sql_fee);
        $stmt_fee->execute([$member_id, $operator]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => '新會員註冊及繳費成功！']);

    } catch (\Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '註冊失敗: ' . $e->getMessage()]);
    }
}


// [B] 查詢會員
function handleSearchMember($pdo) {
    $query = $_GET['query'] ?? '';

    $sql = "
        SELECT 
            m.id, m.name, m.phone, m.remaining_quota, m.associated_product_id,
            p.name AS service_name
        FROM members m
        JOIN products p ON m.associated_product_id = p.id
        WHERE m.phone = :query OR m.name LIKE :query_like
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':query' => $query, ':query_like' => "%$query%"]);
    $member = $stmt->fetch();

    echo json_encode(['status' => 'success', 'data' => $member]);
}

// [C] 記錄交易 (單次收費/會員扣次)
function handleRecordTransaction($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['product_id']) || !isset($data['customer_type']) || !isset($data['operator'])) {
        http_response_code(400); return;
    }

    $customer_type = $data['customer_type'];
    $product_id = (int)$data['product_id'];
    $operator = $data['operator'];
    $member_id = (int)($data['member_id'] ?? 0);
    $used_times = 1;

    try {
        $pdo->beginTransaction();
        $quota_deducted = 0; $amount_paid = 0.00;
        $member_id_insert = null;

        if ($customer_type === 'MEMBER') {
            // 模式 1: 會員扣次
            if ($member_id <= 0) throw new \Exception("會員ID無效。");
            $stmt = $pdo->prepare("SELECT remaining_quota FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            if ($stmt->fetchColumn() < $used_times) throw new \Exception("剩餘額度不足。");

            $sql_update_quota = "UPDATE members SET remaining_quota = remaining_quota - ? WHERE id = ?";
            $stmt = $pdo->prepare($sql_update_quota);
            $stmt->execute([$used_times, $member_id]);
            
            $quota_deducted = $used_times;
            $member_id_insert = $member_id;

        } elseif ($customer_type === 'NON_MEMBER') {
            // 模式 2: 非會員收費
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $price = $stmt->fetchColumn();
            if (!$price) throw new \Exception("無效的服務項目。");
            $amount_paid = (float)$price;
        } else {
            throw new \Exception("無效的客戶類型。");
        }

        // 記錄到 transactions 表
        $sql_insert = "INSERT INTO transactions (customer_type, member_id, product_id, amount_paid, quota_deducted, operator, transaction_date) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute([ $customer_type, $member_id_insert, $product_id, $amount_paid, $quota_deducted, $operator ]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => '交易紀錄成功。']);

    } catch (\Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '交易失敗: ' . $e->getMessage()]);
    }
}

// [D] 會員續約 (重新繳費 $3000，儲值 10 次)
function handleRenewMember($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['member_id']) || !isset($data['operator'])) {
        http_response_code(400); return;
    }
    $member_id = (int)$data['member_id'];
    $operator = $data['operator'];
    $recharge_quota = 10;
    $fee_amount = 3000.00;

    try {
        $pdo->beginTransaction();

        // 1. 更新 members 表：增加 10 次額度
        $sql_update_quota = "UPDATE members SET remaining_quota = remaining_quota + ? WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update_quota);
        $stmt_update->execute([$recharge_quota, $member_id]);

        if ($stmt_update->rowCount() === 0) {
            throw new \Exception("無效的會員ID或更新失敗。");
        }

        // 2. 寫入 member_fees 表：記錄 $3000 收入
        $sql_fee = "INSERT INTO member_fees (member_id, fee_amount, operator) VALUES (?, ?, ?)";
        $stmt_fee = $pdo->prepare($sql_fee);
        $stmt_fee->execute([$member_id, $fee_amount, $operator]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "會員續約成功！已重新儲值 {$recharge_quota} 次額度，並記錄 {$fee_amount} 收入。"]);

    } catch (\Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '續約儲值失敗: ' . $e->getMessage()]);
    }
}


// [E] 營收報表 (日/月)
function handleGetRevenue($pdo, $type) {
    $date = $_GET['date'] ?? date('Y-m-d'); 
    $sql_fee = $sql_trans = "";

    if ($type === 'day') {
        $date_param = $date;
        $sql_fee = "SELECT SUM(fee_amount) FROM member_fees WHERE DATE(payment_date) = ?";
        $sql_trans = "SELECT SUM(amount_paid) FROM transactions WHERE customer_type = 'NON_MEMBER' AND DATE(transaction_date) = ?";
        $period = $date;
    } else { 
        $month_start = date('Y-m-01', strtotime($date));
        $month_end = date('Y-m-t', strtotime($date));
        $sql_fee = "SELECT SUM(fee_amount) FROM member_fees WHERE payment_date BETWEEN ? AND ?";
        $sql_trans = "SELECT SUM(amount_paid) FROM transactions WHERE customer_type = 'NON_MEMBER' AND transaction_date BETWEEN ? AND ?";
        $period = date('Y-m', strtotime($date));
        $date_param = [$month_start, $month_end];
    }
    
    try {
        // 1. 會員費收入 (3000 元)
        $stmt_fee = $pdo->prepare($sql_fee);
        $stmt_fee->execute(is_array($date_param) ? $date_param : [$date_param]);
        $member_fee_revenue = (float)$stmt_fee->fetchColumn() ?? 0.00;

        // 2. 非會員服務收入 (399/499)
        $stmt_trans = $pdo->prepare($sql_trans);
        $stmt_trans->execute(is_array($date_param) ? $date_param : [$date_param]);
        $non_member_revenue = (float)$stmt_trans->fetchColumn() ?? 0.00;

        $total_revenue = $member_fee_revenue + $non_member_revenue;

        echo json_encode([
            'status' => 'success',
            'data' => [
                'period' => $period,
                'non_member_revenue' => $non_member_revenue,
                'member_fee_revenue' => $member_fee_revenue,
                'total_revenue' => $total_revenue,
            ]
        ]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '營收查詢失敗: ' . $e->getMessage()]);
    }
}

// [F] 獲取所有會員列表 (for 後台)
function handleGetAllMembers($pdo) {
    $sql = "
        SELECT 
            m.id, m.name, m.phone, m.remaining_quota, m.join_date,
            p.name AS service_name
        FROM members m
        JOIN products p ON m.associated_product_id = p.id
        ORDER BY m.join_date DESC
    ";
    $stmt = $pdo->query($sql);
    $members = $stmt->fetchAll();

    echo json_encode(['status' => 'success', 'data' => $members]);
}

// [G] 獲取所有交易記錄 (for 後台)
function handleGetAllTransactions($pdo) {
    $sql = "
        SELECT 
            t.transaction_date, t.customer_type, t.amount_paid, t.quota_deducted, t.operator,
            p.name AS service_name,
            m.name AS member_name
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        LEFT JOIN members m ON t.member_id = m.id
        ORDER BY t.transaction_date DESC
    ";
    $stmt = $pdo->query($sql);
    $transactions = $stmt->fetchAll();

    echo json_encode(['status' => 'success', 'data' => $transactions]);
}