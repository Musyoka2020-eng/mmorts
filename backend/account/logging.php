<?php

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Check if the request method is POST and the 'login' button has been clicked
    $data = !empty($_POST['username']) && !empty($_POST['password']);

    if ($data) {
        // Check if the username and password fields have been filled
        $playerUserName = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
        $playerPassword = $_POST['password'];

        // Hash the password using md5
        $password = md5($playerPassword);

        // Use prepared statements to prevent SQL injection attacks
        $stmt = $conn->prepare("SELECT * FROM players WHERE (username = ? OR email = ?) AND password = ?");
        // Bind the username and hashed password
        $stmt->bind_param("sss", $playerUserName, $playerUserName, $password);
        $stmt->execute();

        // Get the result from the prepared statement
        $result = $stmt->get_result();

        // Check if the query returns a result
        if ($result->num_rows === 1) {
            // If there is a result, fetch the row and set the session variables
            $row = $result->fetch_assoc();
            $msg = 'You have successfully logged in';
            $_SESSION['logged_in'] = true;
            $_SESSION['user'] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'uname' => $row['username'],
            ];

            // Redirect to the homepage with a success message
            header('Location: index.php?page=home&msg=' . urlencode($msg));
            exit;
        } else {
            // If there is no result, set an error message
            $msg = 'Invalid username or password';
            $_SESSION['logged_in'] = false;
        }
    } else {
        // If the username or password fields are empty, set an error message
        $msg = 'Please fill all fields';
    }
}
