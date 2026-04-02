<?php
header('Content-Type: application/json');
include 'db.php';

if ($conn->connect_error) {
    echo json_encode(["error" => "Critical: Database offline."]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'products';
$data = [];

switch ($action) {

    case 'products':
        $sql = "SELECT p.Product_id, p.product_name, p.Category_name,
                       p.Unit_price, p.Stock_qty,
                       s.supplier_name
                FROM products p
                LEFT JOIN suppliers s ON p.Supplier_id = s.Supplier_id";
        break;

    case 'categories':
        $sql = "SELECT * FROM categories";
        break;

    case 'suppliers':
        $sql = "SELECT * FROM suppliers";
        break;

    case 'transactions':
        $sql = "SELECT 
                    t.Transaction_id,
                    p.product_name,
                    t.Transaction_qty,
                    t.Transaction_type,
                    t.transaction_date
                FROM stock_transactions t
                LEFT JOIN products p ON t.Product_id = p.Product_id
                ORDER BY t.transaction_date DESC";
        break;

    case 'inventory_overview':
        $sql = "SELECT 
                    p.Product_id,
                    p.product_name,
                    p.Category_name,
                    p.Unit_price,
                    p.Stock_qty,
                    s.supplier_name,
                    COALESCE(sold.total_sold, 0) AS total_sold,
                    COALESCE(p.Stock_qty * p.Unit_price, 0) AS stock_value
                FROM products p
                LEFT JOIN suppliers s ON p.Supplier_id = s.Supplier_id
                LEFT JOIN (
                    SELECT Product_id, SUM(Transaction_qty) AS total_sold
                    FROM stock_transactions
                    WHERE Transaction_type = 'OUT'
                    GROUP BY Product_id
                ) sold ON p.Product_id = sold.Product_id
                ORDER BY p.Stock_qty ASC";
        break;

    default:
        echo json_encode(["error" => "Invalid Command."]);
        exit;
}

$result = $conn->query($sql);
if (!$result) {
    echo json_encode(["error" => "SQL Error: " . $conn->error]);
    exit;
}

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
?>
