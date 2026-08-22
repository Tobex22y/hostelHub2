<?php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();
session_destroy();

echo json_encode([
    "success" => true,
    "message" => "Logged out"
]);
