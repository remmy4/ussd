<?php
require 'db.php';

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timeout
set_time_limit(30);

// USSD Response Headers
header('Content-type: text/plain');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Get USSD parameters from Africa's Talking
$sessionId = $_POST['sessionId'] ?? '';
$serviceCode = $_POST['serviceCode'] ?? '';
$phoneNumber = $_POST['phoneNumber'] ?? '';
$text = $_POST['text'] ?? '';

// Log incoming requests for debugging
error_log("USSD Request - SessionID: $sessionId, Phone: $phoneNumber, Text: $text");

// Initialize response
$response = '';

try {
    // Split the USSD text into an array
    $textArray = explode('*', $text);
    $level = count($textArray);

    // Main USSD Menu
    if ($level == 1 && $text == '') {
        $response = "CON Welcome to Rwanda Polytechnic Admin Portal\n";
        $response .= "1. Login\n";
        $response .= "2. Exit";
    }

    // Handle Login
    else if ($level == 1 && $text == '1') {
        $response = "CON Enter your username:";
    }

    // Handle Username Input
    else if ($level == 2 && $textArray[0] == '1') {
        $response = "CON Enter your password:";
    }

    // Handle Password Input and Authentication
    else if ($level == 3 && $textArray[0] == '1') {
        $username = $textArray[1];
        $password = $textArray[2];
        
        try {
            $stmt = $pdo->prepare("SELECT id, password FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Store admin session
                $_SESSION['admin_id'] = $admin['id'];
                $response = "CON Welcome Admin!\n";
                $response .= "1. View Appeals\n";
                $response .= "2. Manage Students\n";
                $response .= "3. Manage Modules\n";
                $response .= "4. Manage Marks\n";
                $response .= "5. Logout";
            } else {
                $response = "END Invalid username or password";
            }
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $response = "END System error. Please try again later.";
        }
    }

    // Handle Main Menu Options
    else if ($level == 4 && $textArray[0] == '1') {
        switch ($textArray[3]) {
            case '1': // View Appeals
                try {
                    $stmt = $pdo->query("
                        SELECT a.*, s.name as student_name, m.module_name 
                        FROM appeals a 
                        JOIN students s ON a.student_id = s.id 
                        JOIN modules m ON a.module_id = m.id 
                        WHERE a.status = 'pending' 
                        ORDER BY a.created_at DESC 
                        LIMIT 5
                    ");
                    $appeals = $stmt->fetchAll();
                    
                    $response = "CON Recent Appeals:\n";
                    foreach ($appeals as $appeal) {
                        $response .= $appeal['student_name'] . " - " . $appeal['module_name'] . "\n";
                        $response .= "Status: " . $appeal['status'] . "\n\n";
                    }
                    $response .= "1. Back to Menu";
                } catch (PDOException $e) {
                    error_log("Database Error: " . $e->getMessage());
                    $response = "END Error fetching appeals";
                }
                break;
                
            case '2': // Manage Students
                $response = "CON Student Management:\n";
                $response .= "1. View All Students\n";
                $response .= "2. Add New Student\n";
                $response .= "3. Back to Menu";
                break;
                
            case '3': // Manage Modules
                $response = "CON Module Management:\n";
                $response .= "1. View All Modules\n";
                $response .= "2. Add New Module\n";
                $response .= "3. Back to Menu";
                break;
                
            case '4': // Manage Marks
                $response = "CON Marks Management:\n";
                $response .= "1. View All Marks\n";
                $response .= "2. Add New Mark\n";
                $response .= "3. Back to Menu";
                break;
                
            case '5': // Logout
                session_destroy();
                $response = "END You have been logged out successfully";
                break;
        }
    }

    // Handle Student Management
    else if ($level == 5 && $textArray[0] == '1' && $textArray[3] == '2') {
        switch ($textArray[4]) {
            case '1': // View All Students
                try {
                    $stmt = $pdo->query("SELECT regno, name FROM students ORDER BY name LIMIT 5");
                    $students = $stmt->fetchAll();
                    
                    $response = "CON Recent Students:\n";
                    foreach ($students as $student) {
                        $response .= $student['regno'] . " - " . $student['name'] . "\n";
                    }
                    $response .= "\n1. Back to Menu";
                } catch (PDOException $e) {
                    error_log("Database Error: " . $e->getMessage());
                    $response = "END Error fetching students";
                }
                break;
                
            case '2': // Add New Student
                $response = "CON Enter student details:\n";
                $response .= "Enter Registration Number:";
                break;
        }
    }

    // Handle Module Management
    else if ($level == 5 && $textArray[0] == '1' && $textArray[3] == '3') {
        switch ($textArray[4]) {
            case '1': // View All Modules
                try {
                    $stmt = $pdo->query("SELECT module_name FROM modules ORDER BY module_name LIMIT 5");
                    $modules = $stmt->fetchAll();
                    
                    $response = "CON Available Modules:\n";
                    foreach ($modules as $module) {
                        $response .= $module['module_name'] . "\n";
                    }
                    $response .= "\n1. Back to Menu";
                } catch (PDOException $e) {
                    error_log("Database Error: " . $e->getMessage());
                    $response = "END Error fetching modules";
                }
                break;
                
            case '2': // Add New Module
                $response = "CON Enter module name:";
                break;
        }
    }

    // Handle Marks Management
    else if ($level == 5 && $textArray[0] == '1' && $textArray[3] == '4') {
        switch ($textArray[4]) {
            case '1': // View All Marks
                try {
                    $stmt = $pdo->query("
                        SELECT s.regno, s.name as student_name, m.module_name, mk.mark 
                        FROM marks mk 
                        JOIN students s ON mk.student_id = s.id 
                        JOIN modules m ON mk.module_id = m.id 
                        ORDER BY s.name 
                        LIMIT 5
                    ");
                    $marks = $stmt->fetchAll();
                    
                    $response = "CON Recent Marks:\n";
                    foreach ($marks as $mark) {
                        $response .= $mark['student_name'] . " - " . $mark['module_name'] . ": " . $mark['mark'] . "\n";
                    }
                    $response .= "\n1. Back to Menu";
                } catch (PDOException $e) {
                    error_log("Database Error: " . $e->getMessage());
                    $response = "END Error fetching marks";
                }
                break;
                
            case '2': // Add New Mark
                $response = "CON Enter mark details:\n";
                $response .= "Enter Student Registration Number:";
                break;
        }
    }

    // Handle Exit
    else if ($text == '2') {
        $response = "END Thank you for using Rwanda Polytechnic Admin Portal";
    }

    // Default response for invalid input
    else {
        $response = "END Invalid input. Please try again.";
    }

} catch (Exception $e) {
    // Log the error
    error_log("USSD Error: " . $e->getMessage());
    $response = "END Network error. Please try again later.";
}

// Ensure response is not empty
if (empty($response)) {
    $response = "END System error. Please try again later.";
}

// Log the response for debugging
error_log("USSD Response: " . $response);

// Output the response
echo $response;
?> 