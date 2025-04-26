<?php
session_start();
include 'config.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM tutors WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Generate token (32 characters)
            $token = bin2hex(random_bytes(16));
            $expiry = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiry
            
            // Store token in database
            $update = $conn->prepare("UPDATE tutors SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $update->bind_param("sss", $token, $expiry, $email);
            $update->execute();
            
            // Send email (you'll need to implement your email sending function)
            $reset_link = "https://openclass.co.zw/tutors/password-reset.php?token=$token";
            $subject = "Password Reset Request";
            $message = "Click this link to reset your password: $reset_link\n\n";
            $message .= "This link will expire in 1 hour.\n";
            $message .= "If you didn't request this, please ignore this email.";
            
            // This is a placeholder - use your actual email sending method
            if (send_email($email, $subject, $message)) {
                $success = "Password reset link sent to your email!";
            } else {
                $error = "Failed to send email. Please try again.";
            }
        } else {
            $error = "No account found with that email address";
        }
    }
}
//Email sending function using PHPMailer
function send_email($to, $subject, $message) {
    require 'mail-config.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // HTML version
        $htmlMessage = nl2br($message);
        $mail->Body    = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .button { 
                        display: inline-block; 
                        padding: 10px 20px; 
                        background-color: #007bff; 
                        color: white !important; 
                        text-decoration: none; 
                        border-radius: 5px; 
                        margin: 15px 0;
                    }
                    .footer { margin-top: 20px; font-size: 12px; color: #777; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>OpenClass Tutors Password Reset</h2>
                    <p>Click the button below to reset your password:</p>
                    <a href='" . str_replace("\n", "", $message) . "' class='button'>Reset Password</a>
                    <p>Or copy and paste this link into your browser:<br>
                    <code>" . str_replace("\n", "", $message) . "</code></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this password reset, please ignore this email.</p>
                    <div class='footer'>
                        <p>© " . date('Y') . " OpenClass Tutors. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Plain text version for non-HTML clients
        $mail->AltBody = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Find trusted tutors in Zimbabwe for ECD to A Level. Learn via WhatsApp, Zoom, or in-person. Free listing for tutors. Get started today!">
    <meta property="og:image" content="https://openclass.co.zw/tutors/images/openclass-tutor.webp">
    <meta property="og:url" content="https://openclass.co.zw/tutors/">
    <meta name="author" content="Tay Digital">
    <meta name="keywords" content="OpenClass Tutors">

    <!-- Title Page-->
    <title>Openclass | Tutor Login</title>

    <!-- Fontfaces CSS-->
    <link href="css/font-face.css" rel="stylesheet" media="all">
    <link href="vendor/font-awesome-4.7/css/font-awesome.min.css" rel="stylesheet" media="all">
    <link href="vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">

    <!-- Bootstrap CSS-->
    <link href="vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">

    <!-- Vendor CSS-->
    <link href="vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="vendor/bootstrap-progressbar/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet" media="all">
    <link href="vendor/wow/animate.css" rel="stylesheet" media="all">
    <link href="vendor/css-hamburgers/hamburgers.min.css" rel="stylesheet" media="all">
    <link href="vendor/slick/slick.css" rel="stylesheet" media="all">
    <link href="vendor/select2/select2.min.css" rel="stylesheet" media="all">
    <link href="vendor/perfect-scrollbar/perfect-scrollbar.css" rel="stylesheet" media="all">

    <!-- Main CSS-->
    <link href="css/theme.css" rel="stylesheet" media="all">

</head>
<body class="animsition">
    <div class="page-wrapper">
        <div class="page-content--bge5">
            <div class="container">
                <div class="login-wrap">
                    <div class="login-content">
                        <div class="login-logo">
                            <a href="#">
                                <img src="images/openclass.webp" alt="Openclass" width="300px" height="150px">
                            </a>
                        </div>
                        <div class="login-form">
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label>Email Address</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                            </form>
                            <div class="register-link mt-3">
                                <p>Remember your password? <a href="login.php">Login here</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
                <!-- Jquery JS-->
                <script src="vendor/jquery-3.2.1.min.js"></script>
    <!-- Bootstrap JS-->
    <script src="vendor/bootstrap-4.1/popper.min.js"></script>
    <script src="vendor/bootstrap-4.1/bootstrap.min.js"></script>
    <!-- Vendor JS       -->
    <script src="vendor/slick/slick.min.js">
    </script>
    <script src="vendor/wow/wow.min.js"></script>
    <script src="vendor/animsition/animsition.min.js"></script>
    <script src="vendor/bootstrap-progressbar/bootstrap-progressbar.min.js">
    </script>
    <script src="vendor/counter-up/jquery.waypoints.min.js"></script>
    <script src="vendor/counter-up/jquery.counterup.min.js">
    </script>
    <script src="vendor/circle-progress/circle-progress.min.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="vendor/chartjs/Chart.bundle.min.js"></script>
    <script src="vendor/select2/select2.min.js">
    </script>

    <!-- Main JS-->
    <script src="js/main.js"></script><!-- Your existing JS includes -->
</body>
</html>