<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['Product_id'];
    $qty = (int)$_POST['Transaction_qty'];
    $type = $_POST['Transaction_type']; 

    // Determine the math for the PRODUCTS table
    $modifier = ($type === 'OUT') ? -$qty : $qty;

    // 1. Update the master PRODUCTS table
    $updateSql = "UPDATE PRODUCTS SET Stock_qty = Stock_qty + $modifier WHERE Product_id = $id";
    
    if ($conn->query($updateSql)) {
        // 2. Fetch product name to make the transaction log more readable
        $pInfo = $conn->query("SELECT product_name FROM PRODUCTS WHERE Product_id = $id")->fetch_assoc();
        $pName = $pInfo['product_name'];

        // 3. Log into stocks_transaction
        $logSql = "INSERT INTO stocks_transaction (product_id, product_name, type, quantity) 
                   VALUES ($id, '$pName', '$type', $qty)";
        $conn->query($logSql);
        
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["error" => $conn->error]);
    }
}
?>