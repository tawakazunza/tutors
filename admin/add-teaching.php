<?php
// cities.php - Admin city management page

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// Include database configuration
require_once 'config.php';

// Initialize variables
$city_name = "";
$city_name_err = "";
$success_message = "";
$error_message = "";

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check which form was submitted
    if (isset($_POST["add_city"])) {
        // Validate city name
        if (empty(trim($_POST["platform"]))) {
            $city_name_err = "Please enter a city name.";
        } else {
            $city_name = trim($_POST["platform"]);
            
            // Check if city name already exists
            $sql = "SELECT id FROM platforms WHERE name = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_city_name);
                $param_city_name = $city_name;
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows > 0) {
                        $city_name_err = "This city already exists.";
                    }
                } else {
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }
        
        // Check input errors before inserting into database
        if (empty($city_name_err)) {
            // Prepare an insert statement
            $sql = "INSERT INTO platforms (name) VALUES (?)";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_city_name);
                $param_city_name = $city_name;
                
                if ($stmt->execute()) {
                    $success_message = "Platform added successfully!";
                    $city_name = ""; // Clear the form
                } else {
                    $error_message = "Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST["delete_city"])) {
        // Process city deletion
        $city_id = trim($_POST["city_id"]);
        
        $sql = "DELETE FROM platforms WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $city_id);
            
            if ($stmt->execute()) {
                $success_message = "City deleted successfully!";
            } else {
                $error_message = "Error deleting city. Please try again.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST["update_city"])) {
        // Process city update
        $city_id = trim($_POST["city_id"]);
        $city_name = trim($_POST["city_name"]);
        
        // Validate city name
        if (empty($city_name)) {
            $error_message = "City name cannot be empty.";
        } else {
            // Check if new name already exists for another city
            $sql = "SELECT id FROM platforms WHERE name = ? AND id != ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("si", $city_name, $city_id);
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows > 0) {
                        $error_message = "This city name already exists.";
                    } else {
                        // Update the city name
                        $update_sql = "UPDATE platforms SET name = ? WHERE id = ?";
                        
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("si", $city_name, $city_id);
                            
                            if ($update_stmt->execute()) {
                                $success_message = "City updated successfully!";
                            } else {
                                $error_message = "Error updating city. Please try again.";
                            }
                            $update_stmt->close();
                        }
                    }
                } else {
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }
    }
}

// Fetch all cities for display
$cities = array();
$sql = "SELECT id, name FROM platforms ORDER BY name ASC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row;
    }
    $result->free();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teaching Platforms Management - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font: 14px sans-serif;
            background-color: #f8f9fa;
        }
        .wrapper {
            width: 80%;
            padding: 20px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-section, .list-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
        }
        .actions {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Admin Panel</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="add-city.php">Manage Cities</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage-tutors.php">Manage Tutors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="add-subjects.php">Manage Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="add-teaching.php">Manage Teaching Platforms</a>
                    </li>
                    <!-- Add more navigation items here -->
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="wrapper">
        <h2>Teaching Platforms Management</h2>
        <p>Add, edit, or remove platforms from the database</p>
        
        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Add City Form -->
        <div class="form-section">
            <h4>Add New Teaching Platform</h4>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="row g-3">
                <div class="col-md-6">
                    <label for="city_name" class="form-label">City Name</label>
                    <input type="text" class="form-control <?php echo (!empty($city_name_err)) ? 'is-invalid' : ''; ?>" 
                           id="city_name" name="platform" value="<?php echo htmlspecialchars($city_name); ?>">
                    <span class="invalid-feedback"><?php echo $city_name_err; ?></span>
                </div>
                <div class="col-12">
                    <button type="submit" name="add_city" class="btn btn-primary">Add Platform</button>
                </div>
            </form>
        </div>
        
        <!-- City List -->
        <div class="list-section">
            <h4>City List</h4>
            <?php if(count($cities) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>City Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cities as $city): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($city["id"]); ?></td>
                                    <td><?php echo htmlspecialchars($city["name"]); ?></td>
                                    <td class="actions">
                                        <button type="button" class="btn btn-sm btn-primary edit-btn" 
                                                data-bs-toggle="modal" data-bs-target="#editCityModal" 
                                                data-id="<?php echo $city["id"]; ?>" 
                                                data-name="<?php echo htmlspecialchars($city["name"]); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                data-bs-toggle="modal" data-bs-target="#deleteCityModal" 
                                                data-id="<?php echo $city["id"]; ?>" 
                                                data-name="<?php echo htmlspecialchars($city["name"]); ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No cities found. Add a new city above.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit City Modal -->
    <div class="modal fade" id="editCityModal" tabindex="-1" aria-labelledby="editCityModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCityModalLabel">Edit City</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="city_id" id="edit_city_id">
                        <div class="mb-3">
                            <label for="edit_city_name" class="form-label">City Name</label>
                            <input type="text" class="form-control" id="edit_city_name" name="city_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_city" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete City Modal -->
    <div class="modal fade" id="deleteCityModal" tabindex="-1" aria-labelledby="deleteCityModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCityModalLabel">Delete City</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this city?</p>
                    <p><strong>City: </strong><span id="delete_city_name"></span></p>
                </div>
                <div class="modal-footer">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="city_id" id="delete_city_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_city" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit button click handler
        document.querySelectorAll('.edit-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                var cityId = this.getAttribute('data-id');
                var cityName = this.getAttribute('data-name');
                
                document.getElementById('edit_city_id').value = cityId;
                document.getElementById('edit_city_name').value = cityName;
            });
        });
        
        // Delete button click handler
        document.querySelectorAll('.delete-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                var cityId = this.getAttribute('data-id');
                var cityName = this.getAttribute('data-name');
                
                document.getElementById('delete_city_id').value = cityId;
                document.getElementById('delete_city_name').textContent = cityName;
            });
        });
    </script>
</body>
</html>