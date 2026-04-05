<?php
// process.php — Fixed: AUTO_INCREMENT-safe inserts, get_result() instead of bind_result(),
//               null-safe supplier/category IDs, delete category/supplier with product guard
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
    // Use get_result() to avoid bind_result() null-variable bug
    $stmt = $conn->prepare("SELECT Category_id FROM categories WHERE Category_name = ?");
    $stmt->bind_param("s", $cat_name);
    $stmt->execute();
    $res    = $stmt->get_result();
    $catRow = $res->fetch_assoc();
    $stmt->close();
    $cat_id = $catRow ? (int)$catRow['Category_id'] : null;

    if ($cat_id === null) {
        // New category — AUTO_INCREMENT assigns the ID
        $stmt = $conn->prepare("INSERT INTO categories (Category_name) VALUES (?)");
        $stmt->bind_param("s", $cat_name);
        if (!$stmt->execute()) {
            echo json_encode(["error" => "Category insert failed: " . $stmt->error]);
            $stmt->close(); exit;
        }
        $cat_id = (int)$conn->insert_id;
        $stmt->close();
    }

    // --- 2. Resolve Supplier ---
    $stmt = $conn->prepare("SELECT Supplier_id FROM suppliers WHERE supplier_name = ?");
    $stmt->bind_param("s", $sup_name);
    $stmt->execute();
    $res    = $stmt->get_result();
    $supRow = $res->fetch_assoc();
    $stmt->close();
    $sup_id = $supRow ? (int)$supRow['Supplier_id'] : null;

    if ($sup_id === null) {
        // New supplier — AUTO_INCREMENT assigns the ID
        $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name) VALUES (?)");
        $stmt->bind_param("s", $sup_name);
        if (!$stmt->execute()) {
            echo json_encode(["error" => "Supplier insert failed: " . $stmt->error]);
            $stmt->close(); exit;
        }
        $sup_id = (int)$conn->insert_id;
        $stmt->close();
    }

    // Safety check — both IDs must be valid positive integers
    if (!$sup_id || !$cat_id) {
        echo json_encode(["error" => "Could not resolve supplier (ID=$sup_id) or category (ID=$cat_id)."]);
        exit;
    }

    // --- 3. Insert Product ---
    // Do NOT include Product_id — AUTO_INCREMENT handles it
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
    $new_id = (int)$conn->insert_id;
    $stmt->close();

    // --- 4. Log initial stock as a transaction (type: NEW) ---
    if ($qty > 0) {
        logTransaction($conn, $new_id, $qty, 'NEW');
    }

    echo json_encode([
        "status"      => "success",
        "product_id"  => $new_id,
        "supplier_id" => $sup_id,
        "category_id" => $cat_id
    ]);

// ─────────────────────────────────────────────
// ACTION: manage_stock (ADD or WITHDRAW)
// ─────────────────────────────────────────────
} elseif ($action === 'manage_stock') {
    $id   = (int)($_POST['Product_id'] ?? -1);
    $qty  = (int)($_POST['Transaction_qty'] ?? 0);
    $type = $_POST['Transaction_type'] ?? '';

    if (!in_array($type, ['IN', 'OUT'], true)) {
        echo json_encode(["error" => "Invalid transaction type."]);
        exit;
    }

    if ($id < 0 || $qty <= 0) {
        echo json_encode(["error" => "Invalid product ID or quantity."]);
        exit;
    }

    if ($type === 'OUT') {
        $stmt = $conn->prepare("SELECT Stock_qty FROM products WHERE Product_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $row  = $res->fetch_assoc();
        $stmt->close();
        $current_stock = $row ? (int)$row['Stock_qty'] : 0;

        if ($current_stock < $qty) {
            echo json_encode(["error" => "Insufficient stock. Available: $current_stock"]);
            exit;
        }
    }

    $modifier = ($type === 'OUT') ? -$qty : $qty;
    $stmt = $conn->prepare("UPDATE products SET Stock_qty = Stock_qty + ? WHERE Product_id = ?");
    $stmt->bind_param("ii", $modifier, $id);

    if (!$stmt->execute()) {
        echo json_encode(["error" => $stmt->error]);
        $stmt->close(); exit;
    }
    $stmt->close();

    logTransaction($conn, $id, $qty, $type);
    echo json_encode(["status" => "success"]);

// ─────────────────────────────────────────────
// ACTION: delete_product
// ─────────────────────────────────────────────
} elseif ($action === 'delete_product') {
    $id = (int)($_POST['Product_id'] ?? -1);

    if ($id < 0) {
        echo json_encode(["error" => "Invalid product ID."]);
        exit;
    }

    // Delete related transactions first (FK constraint)
    $stmt = $conn->prepare("DELETE FROM stock_transactions WHERE Product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM products WHERE Product_id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        echo json_encode(["error" => "Delete failed: " . $stmt->error]);
        $stmt->close(); exit;
    }
    $stmt->close();

    echo json_encode(["status" => "success"]);

// ─────────────────────────────────────────────
// ACTION: delete_category
// Blocked if any products still reference it
// ─────────────────────────────────────────────
} elseif ($action === 'delete_category') {
    $cat_name = trim($_POST['Category_name'] ?? '');

    if (!$cat_name) {
        echo json_encode(["error" => "Invalid category name."]);
        exit;
    }

    // Guard: check if any products still use this category
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE Category_name = ?");
    $stmt->bind_param("s", $cat_name);
    $stmt->execute();
    $res   = $stmt->get_result();
    $row   = $res->fetch_assoc();
    $count = (int)$row['cnt'];
    $stmt->close();

    if ($count > 0) {
        echo json_encode(["error" => "Cannot delete: $count product(s) still belong to this category."]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM categories WHERE Category_name = ?");
    $stmt->bind_param("s", $cat_name);
    if (!$stmt->execute()) {
        echo json_encode(["error" => "Delete failed: " . $stmt->error]);
        $stmt->close(); exit;
    }
    $stmt->close();

    echo json_encode(["status" => "success"]);

// ─────────────────────────────────────────────
// ACTION: delete_supplier
// Blocked if any products still reference it
// ─────────────────────────────────────────────
} elseif ($action === 'delete_supplier') {
    $sup_id = (int)($_POST['Supplier_id'] ?? -1);

    if ($sup_id < 0) {
        echo json_encode(["error" => "Invalid supplier ID."]);
        exit;
    }

    // Guard: check if any products still use this supplier
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE Supplier_id = ?");
    $stmt->bind_param("i", $sup_id);
    $stmt->execute();
    $res   = $stmt->get_result();
    $row   = $res->fetch_assoc();
    $count = (int)$row['cnt'];
    $stmt->close();

    if ($count > 0) {
        echo json_encode(["error" => "Cannot delete: $count product(s) still use this supplier."]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM suppliers WHERE Supplier_id = ?");
    $stmt->bind_param("i", $sup_id);
    if (!$stmt->execute()) {
        echo json_encode(["error" => "Delete failed: " . $stmt->error]);
        $stmt->close(); exit;
    }
    $stmt->close();

    echo json_encode(["status" => "success"]);

// ─────────────────────────────────────────────
// ACTION: update_category (edit Description)
// ─────────────────────────────────────────────
} elseif ($action === 'update_category') {
    $cat_name   = trim($_POST['Category_name'] ?? '');
    $description = trim($_POST['Description'] ?? '');

    if (!$cat_name) {
        echo json_encode(["error" => "Category name is required."]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE categories SET Description = ? WHERE Category_name = ?");
    $stmt->bind_param("ss", $description, $cat_name);

    if (!$stmt->execute()) {
        echo json_encode(["error" => "Update failed: " . $stmt->error]);
        $stmt->close(); exit;
    }
    $stmt->close();
    echo json_encode(["status" => "success"]);

// ─────────────────────────────────────────────
// ACTION: update_supplier (edit supply_contact)
// ─────────────────────────────────────────────
} elseif ($action === 'update_supplier') {
    $sup_id  = (int)($_POST['Supplier_id'] ?? -1);
    $contact = trim($_POST['supply_contact'] ?? '');

    if ($sup_id < 0) {
        echo json_encode(["error" => "Invalid supplier ID."]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE suppliers SET supply_contact = ? WHERE Supplier_id = ?");
    $stmt->bind_param("si", $contact, $sup_id);

    if (!$stmt->execute()) {
        echo json_encode(["error" => "Update failed: " . $stmt->error]);
        $stmt->close(); exit;
    }
    $stmt->close();
    echo json_encode(["status" => "success"]);

} else {
    echo json_encode(["error" => "Unknown action."]);
}

$conn->close();
?>
