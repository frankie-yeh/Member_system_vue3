<?php
opcache_reset();
date_default_timezone_set('Asia/Taipei');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
/*header('Content-Type: application/json; charset=utf-8');*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

/* ============================================================
   TOKEN 驗證
============================================================ */
function validateTokenAndExit($pdo) {

    $token = '';

    // 1️⃣ 先吃 Authorization: Bearer
    if (!empty($_SERVER['HTTP_AUTHORIZATION']) &&
        preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $m)
    ) {
        $token = $m[1];
    }

    if (!$token) {
        $token = $_POST['token'] ?? $_GET['token'] ?? '';
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'權杖遺失']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT admin_id 
        FROM tokens 
        WHERE token = ? 
          AND expires_at > NOW()
    ");
    $stmt->execute([$token]);

    if (!$stmt->fetch()) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'權杖無效或過期']);
        exit;
    }
}

/* ============================================================
   資料庫連線
============================================================ */

$dsn = "mysql:host=localhost;dbname=masterx0_yamay_products;charset=utf8mb4";
$user = "masterx0_admin";
$pass = "admin5308";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'資料庫連線失敗']);
    exit;
}

/* ============================================================
   API 路由
============================================================ */

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register_member':       handleRegisterMember($pdo); break;
    case 'search_member':         handleSearchMember($pdo); break;
    case 'record_transaction':    handleRecordTransaction($pdo); break;
    case 'renew_member':          handleRenewMember($pdo); break;

    case 'get_daily_revenue':     handleGetRevenue($pdo, 'day'); break;
    case 'get_monthly_revenue':   handleGetRevenue($pdo, 'month'); break;

    case 'get_all_members':       handleGetAllMembers($pdo); break;
    case 'get_all_transactions':  handleGetAllTransactions($pdo); break;
    case 'get_members_by_join_date': handleGetMembersByJoinDate($pdo); break;

    case 'admin_login':           handleAdminLogin($pdo); break;
    case 'validate_token':        handleValidateToken($pdo); break;
    case 'admin_logout':          handleAdminLogout($pdo); break;
    case 'export_members_csv':    handleExportMembersCSV($pdo); break;
    case 'import_members_csv':    handleImportMembersCSV($pdo); break;
    case 'update_member_basic':   handleUpdateMemberBasic($pdo); break;
    case 'admin_update_member_full': handleAdminUpdateMemberFull($pdo); break;

    default:
        echo json_encode(['status'=>'error','message'=>'無效 action']);
}

/* ============================================================
   A. 新會員註冊 (付款 $3000 給 10 次，可選立即使用 1 次)
============================================================ */
function handleRegisterMember($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (
        !isset($data['name']) || 
        !isset($data['phone']) || 
        !isset($data['associated_product_id']) || 
        !isset($data['operator'])
    ) {
        http_response_code(400); 
        echo json_encode(['status' => 'error', 'message' => '缺少必要欄位']);
        return;
    }

    $name       = trim($data['name']); 
    $phone      = trim($data['phone']);
    $product_id = (int)$data['associated_product_id']; 
    $operator   = $data['operator'];

    // 是否加入當天就先使用 1 次（true/1 表示要扣一次）
    $use_immediately = !empty($data['useImmediately']) || !empty($data['use_immediately']);

    try {
        $pdo->beginTransaction();
        
        // 檢查電話是否已存在
        $stmt_check = $pdo->prepare("SELECT id FROM members WHERE phone = ?");
        $stmt_check->execute([$phone]);
        if ($stmt_check->fetch()) { 
            throw new Exception("該電話號碼已存在，請使用續約功能。"); 
        }

        // 1) 寫入 members（先給 10 次）
        $note = $data['note'] ?? null;
        $sql_member = "
            INSERT INTO members (name, phone, note, associated_product_id, remaining_quota, join_date) 
            VALUES (?, ?, ?, ?, 10, NOW())
        ";
        $stmt_member = $pdo->prepare($sql_member);
        $stmt_member->execute([$name, $phone, $note, $product_id]);
        $member_id = (int)$pdo->lastInsertId();

        // 2) 寫入 member_fees（收 3000，付款日 = NOW）
        $sql_fee = "
            INSERT INTO member_fees (member_id, fee_amount, payment_date, operator) 
            VALUES (?, 3000.00, NOW(), ?)
        ";
        $stmt_fee = $pdo->prepare($sql_fee);
        $stmt_fee->execute([$member_id, $operator]);

        // 3) 如果「加入當下就要用 1 次」
        if ($use_immediately) {
            $sql_update_quota = "
                UPDATE members 
                SET remaining_quota = remaining_quota - 1 
                WHERE id = ? AND remaining_quota > 0
            ";
            $stmt_update = $pdo->prepare($sql_update_quota);
            $stmt_update->execute([$member_id]);

            // 3-2 寫入一筆會員服務交易紀錄（0 元、扣 1 次）
            $sql_tx = "
                INSERT INTO transactions 
                    (customer_type, member_id, product_id, amount_paid, quota_deducted, operator, transaction_date)
                VALUES 
                    ('MEMBER', ?, ?, 0.00, 1, ?, NOW())
            ";
            $stmt_tx = $pdo->prepare($sql_tx);
            $stmt_tx->execute([$member_id, $product_id, $operator]);
        }

        $pdo->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => $use_immediately 
                ? '新會員註冊成功並已使用 1 次。' 
                : '新會員註冊成功！',
            'member_id' => $member_id
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '註冊失敗: ' . $e->getMessage()]);
    }
}

/* ============================================================
   B. 查詢會員
============================================================ */
function handleSearchMember($pdo) {

    $query = trim($_GET['query'] ?? '');

    if ($query === '') {
        echo json_encode(['status'=>'error','message'=>'query empty']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.name,
            m.phone,
            m.note,
            m.remaining_quota,
            m.associated_product_id,
            m.join_date,
            p.name AS service_name
        FROM members m
        JOIN products p ON m.associated_product_id = p.id
        WHERE m.phone = :phone
        LIMIT 1
    ");
    $stmt->execute(['phone' => $query]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.name,
                m.phone,
                m.note,
                m.remaining_quota,
                m.associated_product_id,
                m.join_date,
                p.name AS service_name
            FROM members m
            JOIN products p ON m.associated_product_id = p.id
            WHERE m.name LIKE :name
            ORDER BY m.join_date DESC
            LIMIT 1
        ");
        $stmt->execute(['name' => "%{$query}%"]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $row
    ]);
}



/* ============================================================
   C. 記錄交易（單次消費 + 會員扣次）
============================================================ */
function handleRecordTransaction($pdo) {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['product_id'],$data['customer_type'],$data['operator'])) {
        http_response_code(400); 
        echo json_encode(['status'=>'error','message'=>'缺少必要欄位']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $customer_type = $data['customer_type'];
        $product_id = (int)$data['product_id'];
        $member_id = (int)($data['member_id'] ?? 0);

        $member_id_insert = null;
        $amount_paid = 0;
        $quota_deducted = 0;

        if ($customer_type === 'MEMBER') {

            if ($member_id <= 0) throw new Exception("會員ID無效");

            $stmt = $pdo->prepare("SELECT remaining_quota FROM members WHERE id=?");
            $stmt->execute([$member_id]);
            $remain = $stmt->fetchColumn();
            if ($remain === false) {
                throw new Exception("找不到會員資料");
            }
            if ($remain < 1) throw new Exception("會員剩餘次數不足");

            $pdo->prepare("UPDATE members SET remaining_quota = remaining_quota - 1 WHERE id=?")
                ->execute([$member_id]);

            $member_id_insert = $member_id;
            $quota_deducted = 1;

        } else if ($customer_type === 'NON_MEMBER') {
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id=?");
            $stmt->execute([$product_id]);
            $price = $stmt->fetchColumn();
            if ($price === false) {
                throw new Exception("找不到服務項目");
            }
            $amount_paid = (float)$price;
        } else {
            throw new Exception("無效的客戶類型");
        }

        $pdo->prepare("
            INSERT INTO transactions 
                (customer_type, member_id, product_id, amount_paid, quota_deducted, operator, transaction_date)
            VALUES 
                (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $customer_type, $member_id_insert, $product_id,
            $amount_paid, $quota_deducted, $data['operator']
        ]);

        $pdo->commit();
        echo json_encode(['status'=>'success','message'=>'交易成功']);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

/* ============================================================
   D. 會員續約（加 10 次，收 3000）
============================================================ */
function handleRenewMember($pdo) {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['member_id'],$data['operator'])) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'缺少必要欄位']);
        return;
    }

    $member_id = (int)$data['member_id'];
    $operator  = $data['operator'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE members SET remaining_quota = remaining_quota + 10
            WHERE id=?
        ")->execute([$member_id]);

        $pdo->prepare("
            INSERT INTO member_fees (member_id, fee_amount, payment_date, operator)
            VALUES (?, 3000, NOW(), ?)
        ")->execute([$member_id,$operator]);

        $pdo->commit();
        echo json_encode(['status'=>'success','message'=>'續約成功']);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

/* ============================================================
   E. 營收 + 來客數查詢（以 payment_date 判斷新會員）
============================================================ */
function handleGetRevenue($pdo, $type) {

    $date = $_GET['date'] ?? date('Y-m-d');

    $isDaily = ($type === 'day');
    $period  = $isDaily ? $date : substr($date, 0, 7);

    // 明確定義時間區間（台北）
    if ($isDaily) {
        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';
    } else {
        $start = $period . '-01 00:00:00';
        $end   = date('Y-m-t 23:59:59', strtotime($start));
    }

    try {
        /* ========= 1. 會員費 ========= */
        $stmt_fee = $pdo->prepare("
            SELECT COALESCE(SUM(fee_amount),0)
            FROM member_fees
            WHERE payment_date BETWEEN :start AND :end
        ");
        $stmt_fee->execute([
            'start' => $start,
            'end'   => $end
        ]);
        $member_fee_revenue = (float)$stmt_fee->fetchColumn();

        /* ========= 2. 非會員收入 ========= */
        $stmt_nm = $pdo->prepare("
            SELECT COALESCE(SUM(amount_paid),0)
            FROM transactions
            WHERE customer_type = 'NON_MEMBER'
              AND transaction_date BETWEEN :start AND :end
        ");
        $stmt_nm->execute([
            'start' => $start,
            'end'   => $end
        ]);
        $non_member_revenue = (float)$stmt_nm->fetchColumn();

        $total_revenue = $member_fee_revenue + $non_member_revenue;

        /* ========= 3. 新會員（首次付款日） ========= */
        $stmt_new = $pdo->prepare("
            SELECT member_id
            FROM member_fees
            GROUP BY member_id
            HAVING MIN(payment_date) BETWEEN :start AND :end
        ");
        $stmt_new->execute([
            'start' => $start,
            'end'   => $end
        ]);
        $new_member_ids = $stmt_new->fetchAll(PDO::FETCH_COLUMN);
        $new_member_count = count($new_member_ids);

        /* ========= 4. 會員來客 ========= */
        $stmt_mv = $pdo->prepare("
            SELECT DISTINCT member_id
            FROM transactions
            WHERE customer_type = 'MEMBER'
              AND transaction_date BETWEEN :start AND :end
        ");
        $stmt_mv->execute([
            'start' => $start,
            'end'   => $end
        ]);
        $member_visit_ids = $stmt_mv->fetchAll(PDO::FETCH_COLUMN);

        $member_total_visitors = count(array_unique(array_merge(
            $new_member_ids,
            $member_visit_ids
        )));

        /* ========= 5. 非會員來客 ========= */
        $stmt_nmv = $pdo->prepare("
            SELECT COUNT(*)
            FROM transactions
            WHERE customer_type = 'NON_MEMBER'
              AND transaction_date BETWEEN :start AND :end
        ");
        $stmt_nmv->execute([
            'start' => $start,
            'end'   => $end
        ]);
        $non_member_count = (int)$stmt_nmv->fetchColumn();

        $total_visitors = $member_total_visitors + $non_member_count;

        echo json_encode([
            'status' => 'success',
            'data' => [
                'period' => $period,
                'member_fee_revenue' => $member_fee_revenue,
                'non_member_revenue' => $non_member_revenue,
                'total_revenue' => $total_revenue,
                'visitor_stats' => [
                    'member_count' => $member_total_visitors,
                    'non_member_count' => $non_member_count,
                    'new_member_count' => $new_member_count,
                    'total_visitors' => $total_visitors
                ],
                // debug 用（可之後移除）
                'debug_range' => [
                    'start' => $start,
                    'end'   => $end
                ]
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/* ============================================================
   F. 所有會員資料
============================================================ */
function handleGetAllMembers($pdo) {
    validateTokenAndExit($pdo);

    $sql = "
        SELECT m.*, p.name AS service_name
        FROM members m
        JOIN products p ON m.associated_product_id = p.id
        ORDER BY join_date DESC
        Limit 50
    ";

    echo json_encode([
        'status'=>'success',
        'data'=>$pdo->query($sql)->fetchAll()
    ]);
}

/* ============================================================
   G. 所有交易記錄
============================================================ */
function handleGetAllTransactions($pdo) {
    validateTokenAndExit($pdo);

    $sql = "
        SELECT t.*, p.name AS service_name, m.name AS member_name
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        LEFT JOIN members m ON t.member_id = m.id
        ORDER BY transaction_date DESC
        Limit 50
    ";

    echo json_encode([
        'status'=>'success',
        'data'=>$pdo->query($sql)->fetchAll()
    ]);
}

/* ============================================================
   H. 登入
============================================================ */
function handleAdminLogin($pdo) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id,password_hash FROM admins WHERE username=?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password,$admin['password_hash'])) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'帳號或密碼錯誤']);
        return;
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time()+3600);

    $pdo->prepare("INSERT INTO tokens (admin_id,token,expires_at) VALUES (?,?,?)")
        ->execute([$admin['id'],$token,$expires]);

    echo json_encode(['status'=>'success','token'=>$token]);
}

/* ============================================================
   I. 驗證 Token
============================================================ */
function handleValidateToken($pdo) {
    $token = $_GET['token'] ?? $_POST['token'] ?? '';

    if (!$token) {
        echo json_encode(['status'=>'error']); 
        return;
    }

    $stmt = $pdo->prepare("SELECT admin_id FROM tokens WHERE token=? AND expires_at > NOW()");
    $stmt->execute([$token]);

    echo json_encode(['status'=>$stmt->fetch()?'success':'error']);
}

/* ============================================================
   J. 登出
============================================================ */
function handleAdminLogout($pdo) {

    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s(\S+)/',$token,$m)) $token = $m[1];

    $pdo->prepare("DELETE FROM tokens WHERE token=?")->execute([$token]);

    echo json_encode(['status'=>'success']);
}

/* ============================================================
   K. 依加入日期查會員（單日 / 單月）
============================================================ */
function handleGetMembersByJoinDate($pdo) {

    $date  = $_GET['date']  ?? null; // YYYY-MM-DD
    $month = $_GET['month'] ?? null; // YYYY-MM

    if (!$date && !$month) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => '缺少 date 或 month 參數'
        ]);
        return;
    }

    try {

        if ($date) {
            // ✅ 單日查詢
            $sql = "
                SELECT 
                    m.id,
                    m.name,
                    m.phone,
                    m.note,
                    m.remaining_quota,
                    m.associated_product_id,
                    m.join_date,
                    p.name AS service_name
                FROM members m
                JOIN products p ON m.associated_product_id = p.id
                WHERE DATE(m.join_date) = :date
                ORDER BY m.join_date DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['date' => $date]);

        } else {
            // ✅ 單月查詢
            $sql = "
                SELECT 
                    m.id,
                    m.name,
                    m.phone,
                    m.note,
                    m.remaining_quota,
                    m.associated_product_id,
                    m.join_date,
                    p.name AS service_name
                FROM members m
                JOIN products p ON m.associated_product_id = p.id
                WHERE DATE_FORMAT(m.join_date, '%Y-%m') = :month
                ORDER BY m.join_date DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['month' => $month]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'count'  => count($rows),
            'data'   => $rows
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => '會員查詢失敗：' . $e->getMessage()
        ]);
    }
}

/* ============================================================
   L. 匯出會員 CSV
 ============================================================*/
function handleExportMembersCSV($pdo) {
    validateTokenAndExit($pdo);

    $date = $_GET['date'] ?? '';
    if (!$date) {
        http_response_code(400);
        echo 'Missing date';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT 
            m.name,
            m.phone,
            m.note,
            m.remaining_quota,
            m.associated_product_id,
            m.join_date
        FROM members m
        WHERE DATE(m.join_date) = ?
        ORDER BY m.join_date ASC
    ");
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="members_' . $date . '.csv"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    fputcsv($out, ['name', 'phone', 'note', 'remaining_quota', 'associated_product_id', 'join_date']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['name'] ?? '',
            $row['phone'] ?? '',
            $row['note'] ?? '',
            $row['remaining_quota'] ?? 0,
            $row['associated_product_id'] ?? 1,
            $row['join_date'] ?? ''
        ]);
    }

    fclose($out);
    exit;
}

/* ============================================================
    M. 匯入會員 CSV
 ============================================================*/
function handleImportMembersCSV($pdo) {
    validateTokenAndExit($pdo);

    if (!isset($_FILES['file'])) {
        echo json_encode(['status'=>'error','message'=>'未上傳檔案']);
        return;
    }

    $file = $_FILES['file']['tmp_name'];
    $handle = fopen($file, 'r');

    if (!$handle) {
        echo json_encode(['status'=>'error','message'=>'無法讀取 CSV']);
        return;
    }

    /* =========================================================
       1️⃣ 讀取 Header（完整清 BOM + trim）
    ========================================================= */
    $header = fgetcsv($handle);
    if (!$header) {
        echo json_encode(['status'=>'error','message'=>'CSV header 讀取失敗']);
        return;
    }

    $header = array_map(function ($h) {
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); 
        return trim($h);
    }, $header);

    $inserted = 0;
    $updated  = 0;
    $errors   = [];

    $pdo->beginTransaction();

    try {
        $rowIndex = 1;

        /* =========================================================
           2️⃣ 開始逐行讀取資料
        ========================================================= */
        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            if (count($row) !== count($header)) {
                $errors[] = [
                    'row' => $rowIndex,
                    'reason' => '欄位數不符'
                ];
                continue;
            }

            $data = array_combine($header, $row);

            file_put_contents(
                __DIR__ . '/csv_debug.log',
                print_r($data, true),
                FILE_APPEND
            );

            if (empty($data['phone'])) {
                $errors[] = [
                    'row' => $rowIndex,
                    'reason' => 'phone 為空'
                ];
                continue;
            }

            /* =====================================================
               3️⃣ 檢查是否已存在（phone）
            ===================================================== */
            $stmt = $pdo->prepare("SELECT id FROM members WHERE phone = ?");
            $stmt->execute([$data['phone']]);
            $memberId = $stmt->fetchColumn();

            if ($memberId) {
                // UPDATE
                $pdo->prepare("
                    UPDATE members SET
                        name = ?,
                        note = ?,
                        remaining_quota = ?,
                        associated_product_id = ?,
                        join_date = ?
                    WHERE phone = ?
                ")->execute([
                    $data['name'] ?? '',
                    $data['note'] ?? null,
                    (int)($data['remaining_quota'] ?? 0),
                    (int)($data['associated_product_id'] ?? 1),
                    $data['join_date'] ?? date('Y-m-d'),
                    $data['phone']
                ]);
                $updated++;

            } else {
                //  INSERT
                $pdo->prepare("
                    INSERT INTO members
                        (name, phone, note, remaining_quota, associated_product_id, join_date)
                    VALUES (?,?,?,?,?,?)
                ")->execute([
                    $data['name'] ?? '',
                    $data['phone'],
                    $data['note'] ?? null,
                    (int)($data['remaining_quota'] ?? 0),
                    (int)($data['associated_product_id'] ?? 1),
                    $data['join_date'] ?? date('Y-m-d')
                ]);
                $inserted++;
            }
        }

        fclose($handle);
        $pdo->commit();

        echo json_encode([
            'status'   => 'success',
            'inserted' => $inserted,
            'updated'  => $updated,
            'errors'   => $errors
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'status'=>'error',
            'message'=>$e->getMessage()
        ]);
    }
}

/* ============================================================
   N. 更新會員基本資料（姓名 / 電話）【總控台用】
   不需 admin token，只限基本欄位
============================================================ */
function handleUpdateMemberBasic($pdo) {

    $data = json_decode(file_get_contents('php://input'), true);

    if (
        empty($data['member_id']) ||
        empty($data['name']) ||
        empty($data['phone'])
    ) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => '缺少必要欄位'
        ]);
        return;
    }

    $memberId = (int)$data['member_id'];
    $name     = trim($data['name']);
    $phone    = trim($data['phone']);

    try {
        // 檢查電話是否被其他會員使用
        $stmt = $pdo->prepare("
            SELECT id 
            FROM members 
            WHERE phone = ? AND id != ?
        ");
        $stmt->execute([$phone, $memberId]);

        if ($stmt->fetch()) {
            echo json_encode([
                'status' => 'error',
                'message' => '此電話號碼已被其他會員使用'
            ]);
            return;
        }

        //  更新會員基本資料
        $stmt = $pdo->prepare("
            UPDATE members
            SET name = ?, phone = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $phone, $memberId]);

        echo json_encode([
            'status' => 'success',
            'message' => '會員基本資料已更新'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => '更新失敗：' . $e->getMessage()
        ]);
    }
}

/* ============================================================
   O. 管理員更新會員完整資料（後台用）
============================================================ */
function handleAdminUpdateMemberFull($pdo) {

    //  管理員驗證
    validateTokenAndExit($pdo);

    $data = json_decode(file_get_contents('php://input'), true);
    $required = ['member_id','name','phone','remaining_quota','associated_product_id','join_date'];
    foreach ($required as $key) {
        if (!isset($data[$key])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => "缺少欄位：{$key}"
            ]);
            return;
        }
    }

    $memberId   = (int)$data['member_id'];
    $name       = trim($data['name']);
    $phone      = trim($data['phone']);
    $quota      = (int)$data['remaining_quota'];
    $productId  = (int)$data['associated_product_id'];
    $joinDate   = $data['join_date'];
    $note       = $data['note'] ?? null; 

    try {
        //  電話唯一性檢查
        $stmt = $pdo->prepare("
            SELECT id FROM members
            WHERE phone = ? AND id != ?
        ");
        $stmt->execute([$phone, $memberId]);

        if ($stmt->fetch()) {
            echo json_encode([
                'status' => 'error',
                'message' => '此電話已被其他會員使用'
            ]);
            return;
        }

        //  更新會員
        $stmt = $pdo->prepare("
            UPDATE members SET
                name = ?,
                phone = ?,
                note = ?,
                remaining_quota = ?,
                associated_product_id = ?,
                join_date = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name,
            $phone,
            $data['note'] ?? null,
            $quota,
            $productId,
            $joinDate,
            $memberId
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => '會員資料已更新'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => '更新失敗：' . $e->getMessage()
        ]);
    }
}

?>

