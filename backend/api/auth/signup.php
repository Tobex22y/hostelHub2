<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once "../config/db.php";

try {

    // Get form data
    $fullname = $_POST["fullname"] ?? "";
    $email = $_POST["email"] ?? "";
    $phone = $_POST["phone"] ?? "";
    $gender = $_POST["gender"] ?? "";
    $matric = $_POST["matric_number"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    // Validate required fields
    if (
        empty($fullname) ||
        empty($email) ||
        empty($phone) ||
        empty($gender) ||
        empty($matric) ||
        empty($password) ||
        empty($confirm)
    ) {
        echo json_encode([
            "success" => false,
            "message" => "All fields are required"
        ]);
        exit;
    }


    // Check passwords
    if ($password !== $confirm) {
        echo json_encode([
            "success" => false,
            "message" => "Passwords do not match"
        ]);
        exit;
    }


    $pdo = DB::get();


    // Check duplicate email
    $checkEmail = $pdo->prepare(
        "SELECT id FROM students WHERE email = ?"
    );

    $checkEmail->execute([$email]);

    if ($checkEmail->fetch()) {
        echo json_encode([
            "success" => false,
            "message" => "Email already exists"
        ]);
        exit;
    }


    // Check duplicate matric number
    $checkMatric = $pdo->prepare(
        "SELECT id FROM students WHERE matric_number = ?"
    );

    $checkMatric->execute([$matric]);

    if ($checkMatric->fetch()) {
        echo json_encode([
            "success" => false,
            "message" => "Matric number already exists"
        ]);
        exit;
    }


    // Hash password
    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    // Handle profile image
    $profileImage = null;

    if (isset($_FILES["profile_image"])) {

        $uploadDir = __DIR__ . "/../../uploads/profiles/";

        // Create folder if it does not exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }


        $file = $_FILES["profile_image"];

        $fileName = time() . "_" . basename($file["name"]);

        $targetPath = $uploadDir . $fileName;


        if (move_uploaded_file($file["tmp_name"], $targetPath)) {
            $profileImage = $fileName;
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Failed to upload image"
            ]);
            exit;
        }
    }


    // Insert student
    $stmt = $pdo->prepare(
        "INSERT INTO students 
        (fullname, email, phone, gender, matric_number, password, profile_image)
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );


    $stmt->execute([
        $fullname,
        $email,
        $phone,
        $gender,
        $matric,
        $hashedPassword,
        $profileImage
    ]);


    echo json_encode([
        "success" => true,
        "message" => "Account created successfully"
    ]);


} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}