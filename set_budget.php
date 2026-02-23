<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uid = $_SESSION['user_id'];
    $category_id = $_POST['category_id'];
    $budget_limit = $_POST['budget_limit'];

    // Check if budget already exists for this category
    $check = $conn->prepare("SELECT id FROM budgets WHERE user_id=? AND category_id=?");
    $check->bind_param("ii", $uid, $category_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        // Update existing budget
        $stmt = $conn->prepare("UPDATE budgets SET budget_limit=? WHERE user_id=? AND category_id=?");
        $stmt->bind_param("dii", $budget_limit, $uid, $category_id);
    } else {
        // Insert new budget
        $stmt = $conn->prepare("INSERT INTO budgets (user_id, category_id, budget_limit) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $uid, $category_id, $budget_limit);
    }

    if ($stmt->execute()) {
        header("Location: budgets.php");
        exit();
    } else {
        echo "Failed to save budget.";
    }
}
?>
