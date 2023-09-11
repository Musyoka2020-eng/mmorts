<?php
// Check if the request method is POST and if the register button is clicked
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Check if all required form fields are filled
    $data = !empty($_POST['name']) &&
        !empty($_POST['email']) &&
        !empty($_POST['username']) &&
        !empty($_POST['password']) &&
        !empty($_POST['confirmedpassword']);

    if ($data) {
        // Sanitize user inputs
        $playerName = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        $playerEmail = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS);
        $playerUserName = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
        $playerPassword = $_POST['password'];
        $playerConfirmPassword = $_POST['confirmedpassword'];

        // Check if passwords match
        if ($playerPassword == $playerConfirmPassword) {
            // Hash password
            $newpassword = md5($playerPassword);

            // Check if username already exists
            $query = "SELECT username FROM players";
            $results = $conn->query($query);
            $exists = false;

            while ($row = mysqli_fetch_assoc($results)) {
                if ($row['username'] == $playerUserName) {
                    $exists = true;
                    break;
                }
            }

            // If username doesn't exist, insert new player data into the database
            if ($exists) {
                // Username already exists
                $msg = 'Username already exists';
            } else {
                // Insert new player into database
                $query = "INSERT INTO players (name, email, username, password) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssss", $playerName, $playerEmail, $playerUserName, $newpassword);

                if ($stmt->execute()) {
                    // Registration successful
                    $msg = 'Registration successful';
                    header('Location: index.php?page=login&msg=' . urlencode($msg));
                    exit;
                } else {
                    // Error inserting data
                    $msg = 'Error inserting data: ' . $stmt->error;
                }

                $stmt->close();
            }
        } else {
            // Passwords don't match
            $msg = 'Passwords are not the same';
        }
    } else {
        // Not all fields were filled
        $msg = 'Please enter all fields';
    }
}
