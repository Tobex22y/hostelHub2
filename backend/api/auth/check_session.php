<?php

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