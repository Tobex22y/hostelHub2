<?php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();
header("Content-Type: application/json");

if (isset($_SESSION["student_id"])) {
    echo json_encode([
        "loggedIn" => true,
        "user" => [
            "id" => $_SESSION["student_id"],
            "name" => $_SESSION["student_name"]
        ]
    ]);
} else {
    echo json_encode([
        "loggedIn" => false
    ]);
}
