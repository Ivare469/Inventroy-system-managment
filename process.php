<?php
// process.php - Fixed: correct schema, prepared statements, stock guard, type whitelist, full transaction logging
include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Invalid request method."]);
    exit;
}

$action = $_POST['action'] ?? 'manage_stock';

// ─────────────────────────────────────────────
// HELPER: log a transaction
// ─────────────────────────────────────────────
function logTransaction($conn, $product_id, $qty, $type) {
    $stmt = $conn->prepare(
        "INSERT INTO stock_transactions (Product_id, Transaction_qty, Transaction_type)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iis", $product_id, $qty, $type);
    $stmt->execute();
    $stmt->close();
}

// ─────────────────────────────────────────────
// ACTION: create_product
// ─────────────────────────────────────────────
if ($action === 'create_product') {
    $name     = trim($_POST['product_name'] ?? '');
    $qty      = (int)($_POST['quantity'] ?? 0);
    $price    = (float)($_POST['price'] ?? 0);
    $cat_name = trim($_POST['category_name'] ?? '');
    $sup_name = trim($_POST['supplier_name'] ?? '');

    if (!$name || !$cat_name || !$sup_name || $qty < 0 || $price < 0) {
        echo json_encode(["error" => "Missing or invalid fields."]);
        exit;
    }

    // --- 1. Resolve Category ---
    $stmt = $conn->prepare("SELECT Category_id FROM categories WHERE Category_name = ?");
    $stmt->bind_param("s", $cat_name);
    $stmt->execute();
    $stmt->bind_result($cat_id);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        $stmt = $conn->prepare("INSERT INTO categories (Category_name) VALUES (?)");
        $stmt->bind_param("s", $cat_name);
        $stmt->execute();
        $cat_id = $conn->insert_id;
        $stmt->close();
    }

    // --- 2. Resolve Supplier ---
    $stmt = $conn->prepare("SELECT Supplier_id FROM suppliers WHERE supplier_name = ?");
    $stmt->bind_param("s", $sup_name);
    $stmt->execute();
    $stmt->bind_result($sup_id);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name) VALUES (?)");
        $stmt->bind_param("s", $sup_name);
        $stmt->execute();
        $sup_id = $conn->insert_id;
        $stmt->close();
    }

    // --- 3. Insert Product ---
    $stmt = $conn->prepare(
        "INSERT INTO products (product_name, Category_name, Supplier_id, Unit_price, Stock_qty)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("ssidi", $name, $cat_name, $sup_id, $price, $qty);

    if (!$stmt->execute()) {
        echo json_encode(["error" => "Insert failed: " . $stmt->error]);
        $stmt->close();
        exit;
    }
    $new_id = $conn->insert_id;
    $stmt->close();

    // --- 4. Log initial stock as a transaction (type: NEW) ---
    logTransaction($conn, $new_id, $qty, 'NEW');

    echo json_encode(["status" => "success"]);

// ─────────────────────────────────────────────
// ACTION: manage_stock (ADD or WITHDRAW)
// ─────────────────────────────────────────────
} elseif ($action === 'manage_stock') {
    $id   = (int)($_POST['Product_id'] ?? 0);
    $qty  = (int)($_POST['Transaction_qty'] ?? 0);
    $type = $_POST['Transaction_type'] ?? '';

    // Whitelist Transaction_type
    if (!in_array($type, ['IN', 'OUT'], true)) {
        echo json_encode(["error" => "Invalid transaction type."]);
        exit;
    }

    if ($id <= 0 || $qty <= 0) {
        echo json_encode(["error" => "Invalid product ID or quantity."]);
        exit;
    }

    // Guard against negative stock on withdrawal
    if ($type === 'OUT') {
        $stmt = $conn->prepare("SELECT Stock_qty FROM products WHERE Product_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($current_stock);
        $stmt->fetch();
        $stmt->close();

        if ($current_stock < $qty) {
            echo json_encode(["error" => "Insufficient stock. Available: $current_stock"]);
            exit;
        }
    }

    // Update stock
    $modifier = ($type === 'OUT') ? -$qty : $qty;
    $stmt = $conn->prepare(
        "UPDATE products SET Stock_qty = Stock_qty + ? WHERE Product_id = ?"
    );
    $stmt->bind_param("ii", $modifier, $id);

    if (!$stmt->execute()) {
        echo json_encode(["error" => $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Log the transaction
    logTransaction($conn, $id, $qty, $type);

    echo json_encode(["status" => "success"]);

} else {
    echo json_encode(["error" => "Unknown action."]);
}

$conn->close();
?>
