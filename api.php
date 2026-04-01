<?php
// api.php - Integrated Argo Core API (Fixed for Column Error)
header('Content-Type: application/json');
include 'db.php';

if ($conn->connect_error) {
    echo json_encode(["error" => "Critical: Database offline."]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'products';
$data = [];
$sql = "";

switch ($action) {
    case 'products':
        // Simplified query to avoid the "Unknown column" error
        $sql = "SELECT * FROM PRODUCTS"; 
        break;

    case 'categories':
        $sql = "SELECT * FROM CATEGORIES";
        break;

    case 'suppliers':
        $sql = "SELECT * FROM SUPPLIERS";
        break;

    case 'transactions':
        $sql = "SELECT * FROM TRANSACTIONS ORDER BY Transaction_id DESC";
        break;
    case 'transactions':
        $sql = "SELECT * FROM stocks_transaction ORDER BY date DESC";
        break;

    default:
        echo json_encode(["error" => "Invalid Command."]);
        exit;
}

$result = $conn->query($sql);

if (!$result) {
    // This will tell us the EXACT column names available in your table
    echo json_encode(["error" => "SQL Error: " . $conn->error]);
    exit;
}

while($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
?>