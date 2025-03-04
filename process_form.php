<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Input Sanitization and Validation
    $name = strip_tags(trim($_POST["name"]));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $subject = strip_tags(trim($_POST["subject"]));
    $message = strip_tags(trim($_POST["message"]));

    if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($subject) || empty($message)) {
        http_response_code(400);
        echo "Please complete the form and try again.";
        exit;
    }

    $uploadsDir = "/volume1/web/uploads/"; // Make sure this is the correct path
    $maxFiles = 30;

    // --- File Limit and Deletion ---
    $files = scandir($uploadsDir);
    $numFiles = count($files) - 2; // Subtract 2 for '.' and '..'

    if ($numFiles >= $maxFiles) {
        $oldestFile = "";
        $oldestTime = PHP_INT_MAX;

        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $filePath = $uploadsDir . $file;
                $fileTime = filemtime($filePath);

                if ($fileTime < $oldestTime) {
                    $oldestTime = $fileTime;
                    $oldestFile = $filePath;
                }
            }
        }

        if (!empty($oldestFile)) {
            unlink($oldestFile);
        }
    }
    // --- End File Limit and Deletion ---

    // --- Rate Limiting (IP-based) ---
    $rateLimitFile = $uploadsDir . "rate_limit.txt";
    $limit = 5; // Submissions per hour
    $timeWindow = 3600; // 1 hour in seconds

    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        $currentTime = time();

        // Clean up old entries
        foreach ($data as $ip => $time) {
            if ($currentTime - $time > $timeWindow) {
                unset($data[$ip]);
            }
        }

        $userIP = $_SERVER['REMOTE_ADDR'];

        if (isset($data[$userIP]) && $currentTime - $data[$userIP] < $timeWindow) {
            $remainingTime = $timeWindow - ($currentTime - $data[$userIP]);
            http_response_code(429); // Too Many Requests
            echo "You've reached the submission limit. Please try again in " . ceil($remainingTime / 60) . " minutes.";
            exit;
        } else {
            $data[$userIP] = $currentTime;
        }
    } else {
        $data = array();
        $userIP = $_SERVER['REMOTE_ADDR'];
        $data[$userIP] = time();
    }

    file_put_contents($rateLimitFile, json_encode($data));
    // --- End Rate Limiting ---

    // --- File Saving Logic ---
    $timestamp = date("YmdHis");
    $filename = $uploadsDir . "submission_" . $timestamp . ".txt";

    $file_content = "Name: " . $name . "\n";
    $file_content .= "Email: " . $email . "\n";
    $file_content .= "Subject: " . $subject . "\n";
    $file_content .= "Message:\n" . $message;

    // Debugging
    if (!is_writable($uploadsDir)) {
        echo "Error: Uploads directory is not writable.";
        exit;
    }

    if (file_put_contents($filename, $file_content)) {
        http_response_code(200);
        echo "Thank You! Your message has been saved.";
    } else {
        http_response_code(500);
        echo "Oops! Something went wrong, and we couldn't save your message.";
        // Debugging
        $error = error_get_last();
        if ($error) {
            echo "<br>Error details: " . $error['message'];
        }
    }
    // --- End File Saving Logic ---

} else {
    http_response_code(403);
    echo "There was a problem with your submission, please try again.";
}
?>