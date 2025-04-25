<?php
function createSlug($string) {
    // Convert to lowercase
    $string = strtolower($string);
    
    // Replace special characters with empty string
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    
    // Replace spaces and multiple hyphens with single hyphen
    $string = preg_replace('/[\s-]+/', '-', $string);
    
    // Trim hyphens from beginning and end
    $string = trim($string, '-');
    
    return $string;
}

// Function to generate unique slug
function generateUniqueSlug($conn, $name, $id = null) {
    $slug = createSlug($name);
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $query = "SELECT id FROM tutors WHERE slug = ?";
        if ($id) {
            $query .= " AND id != ?";
        }
        
        $stmt = $conn->prepare($query);
        if ($id) {
            $stmt->bind_param("si", $slug, $id);
        } else {
            $stmt->bind_param("s", $slug);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}


function trackProfileView($conn, $tutor_id) {
    // Get visitor information
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';

    // Check if this IP has viewed this profile before
    $stmt = $conn->prepare("
        SELECT id, view_time 
        FROM profile_view_history 
        WHERE tutor_id = ? AND ip_address = ?
        ORDER BY view_time DESC 
        LIMIT 1
    ");
    $stmt->bind_param("is", $tutor_id, $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();

    $is_unique = 0;
    $cookie_name = 'profile_view_'.$tutor_id;

    // If no record exists or last view was > 24 hours ago, count as unique
    if ($result->num_rows === 0) {
        $is_unique = 1;
    } else {
        $last_view = $result->fetch_assoc();
        $last_view_time = strtotime($last_view['view_time']);
        $twenty_four_hours_ago = time() - (24 * 60 * 60);

        if ($last_view_time < $twenty_four_hours_ago) {
            $is_unique = 1;
        }
    }

    // Check cookie to prevent same-session duplicates
    if (!isset($_COOKIE[$cookie_name])) {
        $is_unique = 1;
        setcookie($cookie_name, '1', time() + (24 * 60 * 60), '/'); // 24-hour cookie
    }

    // Insert view record
    $insert_stmt = $conn->prepare("
        INSERT INTO profile_view_history 
        (tutor_id, ip_address, user_agent, referrer, is_unique) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert_stmt->bind_param("isssi", $tutor_id, $ip_address, $user_agent, $referrer, $is_unique);
    $insert_stmt->execute();

    // Update main view counts
    if ($is_unique) {
        // Check if record exists in profile_views
        $check_stmt = $conn->prepare("SELECT id FROM profile_views WHERE tutor_id = ?");
        $check_stmt->bind_param("i", $tutor_id);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $update_stmt = $conn->prepare("
                UPDATE profile_views 
                SET total_views = total_views + 1, 
                    unique_views = unique_views + 1,
                    last_viewed = CURRENT_TIMESTAMP 
                WHERE tutor_id = ?
            ");
        } else {
            $update_stmt = $conn->prepare("
                INSERT INTO profile_views 
                (tutor_id, total_views, unique_views) 
                VALUES (?, 1, 1)
            ");
        }
    } else {
        $update_stmt = $conn->prepare("
            UPDATE profile_views 
            SET total_views = total_views + 1,
                last_viewed = CURRENT_TIMESTAMP 
            WHERE tutor_id = ?
        ");
    }

    $update_stmt->bind_param("i", $tutor_id);
    $update_stmt->execute();

    return $is_unique;
}
?>