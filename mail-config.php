<?php
// mail_config.php

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

// Email configuration - replace with your actual SMTP details
define('SMTP_HOST', 'mail.openclass.co.zw');  // e.g., smtp.gmail.com
define('SMTP_USER', 'tutors@openclass.co.zw');
define('SMTP_PASS', 'hgWc2ZBWU@8o');
define('SMTP_PORT', 465);  // Typically 587 for TLS, 465 for SSL
define('SMTP_SECURE', 'tls');  // 'tls' or 'ssl'
define('FROM_EMAIL', 'no-reply@penclass.co.zw');
define('FROM_NAME', 'OpenClass Tutors');