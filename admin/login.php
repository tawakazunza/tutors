<?php
// login.php - Admin login form and processing

// Start session
session_start();

// Include database configuration
require_once 'config.php';

// Initialize variables
$username = $password = '';
$username_err = $password_err = $login_err = '';

// Process form data when submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Validate username
    if (empty(trim($_POST['username']))) {
        $username_err = 'Please enter username.';
    } else {
        $username = trim($_POST['username']);
    }
    
    // Validate password
    if (empty(trim($_POST['password']))) {
        $password_err = 'Please enter your password.';
    } else {
        $password = trim($_POST['password']);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Create connection
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Prepare a select statement
        $sql = "SELECT id, username, password, full_name FROM admin_users WHERE username = ? AND is_active = 1";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if username exists
                if ($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($id, $username, $hashed_password, $full_name);
                    
                    if ($stmt->fetch()) {
                        // Verify password
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION['loggedin'] = true;
                            $_SESSION['id'] = $id;
                            $_SESSION['username'] = $username;
                            $_SESSION['full_name'] = $full_name;
                            
                            // Update last login time
                            $update_sql = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
                            if ($update_stmt = $conn->prepare($update_sql)) {
                                $update_stmt->bind_param("i", $id);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                            
                            // Redirect to dashboard
                            header("location: dashboard.php");
                        } else {
                            // Password not valid
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            
            // Close statement
            $stmt->close();
        }
        
        // Close connection
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <style>
        body {
            font: 14px sans-serif;
            background-color: #f8f9fa;
        }
        .wrapper {
            width: 400px;
            padding: 30px;
            margin: 100px auto;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2 class="text-center mb-4">Admin Login</h2>
        
        <?php 
        if (!empty($login_err)) {
            echo '<div class="alert alert-danger">' . $login_err . '</div>';
        }        
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                <span class="invalid-feedback"><?php echo $username_err; ?></span>
            </div>    
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <span class="invalid-feedback"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group text-center">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
        </form>
    </div>
</body>
</html>