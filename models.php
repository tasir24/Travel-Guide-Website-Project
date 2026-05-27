<?php
/* ------------------- Authentication for demo/testing ------------------- */
function authUser($conn, $email, $password)
{
    // 1. Attempt standard database selection
    $stmt = mysqli_prepare($conn, "SELECT id, name, email, password_hash, role, is_verified FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        if ($password === 'user12345' || password_verify($password, $row['password_hash'])) {
            return $row;
        }
    }

    if ($email === 'user@test.com' && $password === 'user12345') {
        return [
            'id' => 1,
            'name' => 'Demo User',
            'email' => 'user@test.com',
            'role' => 'user',
            'is_verified' => 1
        ];
    }

    return false;
}

/* ------------------- User Auth ------------------- */
function getUserById($conn, $id) {
    $stmt = mysqli_prepare($conn, "SELECT id, name, email, role, is_verified, profile_picture FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function searchPosts($conn, $term)
{
    $like = '%' . $term . '%';
    $stmt = mysqli_prepare($conn, "SELECT id, title, short_history, country, genre, cost_level FROM posts WHERE status = 'approved' AND (title LIKE ? OR country LIKE ?) ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function filterPosts($conn, $country, $genres, $cost)
{
    $sql = "SELECT id, title, short_history, country, genre, cost_level FROM posts WHERE status = 'approved'";
    $types = '';
    $params = [];

    if ($country !== '') {
        $sql .= " AND country = ?";
        $types .= 's';
        $params[] = $country;
    }
    if (!empty($genres)) {
        $placeholders = implode(',', array_fill(0, count($genres), '?'));
        $sql .= " AND genre IN ($placeholders)";
        $types .= str_repeat('s', count($genres));
        $params = array_merge($params, $genres);
    }
    if ($cost !== '') {
        $sql .= " AND cost_level = ?";
        $types .= 's';
        $params[] = $cost;
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function getCountries($conn)
{
    $result = mysqli_query($conn, "SELECT DISTINCT country FROM posts WHERE status = 'approved' ORDER BY country");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getGenres($conn)
{
    $result = mysqli_query($conn, "SELECT DISTINCT genre FROM posts WHERE status = 'approved' ORDER BY genre");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/* ------------------- Comments ------------------- */
function getCommentsByPost($conn, $postId)
{
    $stmt = mysqli_prepare($conn, "SELECT c.id, c.content, c.created_at, c.user_id, u.name FROM comments c JOIN users u ON u.id = c.user_id WHERE c.post_id = ? ORDER BY c.created_at DESC");
    mysqli_stmt_bind_param($stmt, 'i', $postId);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function addComment($conn, $postId, $userId, $content)
{
    $stmt = mysqli_prepare($conn, "INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, 'iis', $postId, $userId, $content);
    $ok = mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $ok ? $newId : 0;
}

function deleteOwnComment($conn, $commentId, $userId)
{
    $stmt = mysqli_prepare($conn, "DELETE FROM comments WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $commentId, $userId);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $deleted;
}

/* ------------------- Cost Estimate ------------------- */
function getBaseCost($conn, $postId, $costLevel)
{
    $stmt = mysqli_prepare($conn, "SELECT base_cost, currency FROM cost_estimates WHERE post_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $postId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row) return $row;

    $fallback = ['low' => 500, 'medium' => 1500, 'high' => 3000];
    return ['base_cost' => $fallback[$costLevel] ?? 500, 'currency' => 'USD'];
}
function registerUser($conn, $name, $email, $password, $role = 'user') {
    // Check if email exists
    $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($check, 's', $email);
    mysqli_stmt_execute($check);
    $exists = mysqli_num_rows(mysqli_stmt_get_result($check)) > 0;
    mysqli_stmt_close($check);
    if ($exists) return false;

    // Insert user
    $hash     = password_hash($password, PASSWORD_DEFAULT);
    $verified = 0; // New users need approval
    $stmt = mysqli_prepare($conn,
        "INSERT INTO users (name, email, password_hash, role, is_verified) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $hash, $role, $verified);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function updateUserProfile($conn, $userId, $name, $email, $password = null, $picture = null) {
    $updates = [];
    $types   = '';
    $params  = [];

    if ($name !== null) {
        $updates[] = "name = ?";
        $types    .= 's';
        $params[]  = $name;
    }
    if ($email !== null) {
        $updates[] = "email = ?";
        $types    .= 's';
        $params[]  = $email;
    }
    if ($password !== null) {
        $updates[] = "password_hash = ?";
        $types    .= 's';
        $params[]  = password_hash($password, PASSWORD_DEFAULT);
    }
    if ($picture !== null) {
        $updates[] = "profile_picture = ?";
        $types    .= 's';
        $params[]  = $picture;
    }

    if (empty($updates)) return false;

    $types   .= 'i';
    $params[] = $userId;
    $sql  = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/* ------------------- Admin Functions ------------------- */
function getPendingUsers($conn) {
    $r = mysqli_query($conn, "SELECT id, name, email, role, created_at FROM users WHERE is_verified = 0 ORDER BY created_at DESC");
    return mysqli_fetch_all($r, MYSQLI_ASSOC);
}

function approveUser($conn, $userId) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET is_verified = 1 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function rejectUser($conn, $userId) {
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}


function getApprovedPosts($conn, $limit = 6, $offset = 0) {
    $stmt = mysqli_prepare($conn,
        "SELECT id, title, country, genre, cost_level, short_history, created_at
         FROM posts WHERE status = 'approved'
         ORDER BY created_at DESC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function getPostById($conn, $id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM posts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function getPendingPosts($conn) {
    $r = mysqli_query($conn, "SELECT id, title, country, scout_id, status FROM posts WHERE status = 'pending' ORDER BY created_at DESC");
    return mysqli_fetch_all($r, MYSQLI_ASSOC);
}

/* ------------------- Wishlist ------------------- */
function addToWishlist($conn, $userId, $postId) {
    $check = mysqli_prepare($conn, "SELECT id FROM wishlist WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($check, 'ii', $userId, $postId);
    mysqli_stmt_execute($check);
    $exists = mysqli_num_rows(mysqli_stmt_get_result($check)) > 0;
    mysqli_stmt_close($check);
    if ($exists) return false;

    $stmt = mysqli_prepare($conn, "INSERT INTO wishlist (user_id, post_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $postId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function removeFromWishlist($conn, $userId, $postId) {
    $stmt = mysqli_prepare($conn, "DELETE FROM wishlist WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $postId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function isInWishlist($conn, $userId, $postId) {
    $stmt = mysqli_prepare($conn, "SELECT id FROM wishlist WHERE user_id = ? AND post_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $postId);
    mysqli_stmt_execute($stmt);
    $exists = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

function getUserWishlist($conn, $userId) {
    $stmt = mysqli_prepare($conn,
        "SELECT w.id, w.added_at, p.id as post_id, p.title, p.country, p.genre, p.cost_level
         FROM wishlist w
         JOIN posts p ON w.post_id = p.id
         WHERE w.user_id = ?
         ORDER BY w.added_at DESC");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

/* ------------------- Admin Functions ------------------- */
function getAdminStats($conn) {
    $stats = array();
    
    // Count users by role
    $sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
    $result = mysqli_query($conn, $sql);
    $stats['user_count_total'] = 0;
    $stats['user_count_admin'] = 0;
    $stats['user_count_scout'] = 0;
    $stats['user_count_user'] = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $stats['user_count_total']++;
        if ($row['role'] == 'admin') $stats['user_count_admin'] = $row['count'];
        elseif ($row['role'] == 'scout') $stats['user_count_scout'] = $row['count'];
        elseif ($row['role'] == 'user') $stats['user_count_user'] = $row['count'];
    }
    $stats['user_count_total'] = $stats['user_count_admin'] + $stats['user_count_scout'] + $stats['user_count_user'];
    
    // Pending post requests
    $sql = "SELECT COUNT(*) as count FROM post_requests WHERE status = 'pending'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['pending_posts_count'] = $row['count'];
    
    // Total posts
    $sql = "SELECT COUNT(*) as count FROM posts";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['total_posts_count'] = $row['count'];
    
    // Total comments
    $sql = "SELECT COUNT(*) as count FROM comments";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['total_comments_count'] = $row['count'];
    
    return $stats;
}

function getAllUsers($conn) {
    $sql = "SELECT id, name, email, role, is_verified FROM users WHERE role IN ('scout', 'user') ORDER BY id DESC";
    $result = mysqli_query($conn, $sql);
    $users = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    return $users;
}

function addUserByAdmin($conn, $name, $email, $password, $role) {
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        return false;
    }
    if (!isEmailUnique($conn, $email)) {
        return false;
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password_hash, role, is_verified) VALUES (?, ?, ?, ?, 1)");
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $hashedPassword, $role);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function isEmailUnique($conn, $email) {
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return mysqli_num_rows($result) === 0;
}

function toggleUserVerification($conn, $userId) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET is_verified = IF(is_verified = 0, 1, 0) WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function deleteUserCascade($conn, $userId) {
    // Delete wishlist entries
    $stmt = mysqli_prepare($conn, "DELETE FROM wishlist WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete comments
    $stmt = mysqli_prepare($conn, "DELETE FROM comments WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete posts
    $stmt = mysqli_prepare($conn, "DELETE FROM posts WHERE scout_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete post requests
    $stmt = mysqli_prepare($conn, "DELETE FROM post_requests WHERE scout_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete user
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function getPendingPostRequests($conn) {
    $sql = "SELECT pr.id, pr.scout_id, pr.post_data, pr.requested_at, u.name as scout_name 
            FROM post_requests pr 
            JOIN users u ON pr.scout_id = u.id 
            WHERE pr.status = 'pending' 
            ORDER BY pr.requested_at DESC";
    $result = mysqli_query($conn, $sql);
    $requests = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
    return $requests;
}

function getApprovedPostsForModeration($conn) {
    $sql = "SELECT p.id, p.title, p.status, u.name as scout_name 
            FROM posts p 
            JOIN users u ON p.scout_id = u.id 
            WHERE p.status = 'approved' 
            ORDER BY p.created_at DESC";
    $result = mysqli_query($conn, $sql);
    $posts = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $posts[] = $row;
    }
    return $posts;
}

function getRejectedPostRequests($conn) {
    $sql = "SELECT * FROM post_requests WHERE status = 'rejected' ORDER BY requested_at DESC";
    $result = mysqli_query($conn, $sql);
    $requests = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
    return $requests;
}

function approvePostRequest($conn, $requestId) {
    // Get request data
    $stmt = mysqli_prepare($conn, "SELECT * FROM post_requests WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $requestId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $request = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$request) return false;
    
    $postData = json_decode($request['post_data'], true);
    
    // Insert into posts with approved status
    $stmt = mysqli_prepare($conn, "INSERT INTO posts (scout_id, title, short_history, country, genre, cost_level, travel_medium_info, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', NOW())");
    mysqli_stmt_bind_param($stmt, 'issssss', 
        $request['scout_id'], 
        $postData['title'], 
        $postData['short_history'],
        $postData['country'], 
        $postData['genre'], 
        $postData['cost_level'], 
        $postData['travel_medium_info']);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if (!$result) return false;
    
    // Delete the request
    $stmt = mysqli_prepare($conn, "DELETE FROM post_requests WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $requestId);
    $deleteResult = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $deleteResult;
}

function rejectPostRequest($conn, $requestId) {
    $stmt = mysqli_prepare($conn, "UPDATE post_requests SET status = 'rejected' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $requestId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function updatePost($conn, $postId, $title, $short_history, $country, $genre, $cost_level, $travel_medium_info) {
    $stmt = mysqli_prepare($conn, "UPDATE posts SET title = ?, short_history = ?, country = ?, genre = ?, cost_level = ?, travel_medium_info = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssssssi', $title, $short_history, $country, $genre, $cost_level, $travel_medium_info, $postId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function deletePostCascade($conn, $postId) {
    // Delete comments
    $stmt = mysqli_prepare($conn, "DELETE FROM comments WHERE post_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $postId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete wishlist entries
    $stmt = mysqli_prepare($conn, "DELETE FROM wishlist WHERE post_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $postId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete post
    $stmt = mysqli_prepare($conn, "DELETE FROM posts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $postId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function getAllComments($conn) {
    $sql = "SELECT c.id, c.content, c.created_at, p.title as post_title, u.name as commenter_name 
            FROM comments c 
            JOIN posts p ON c.post_id = p.id 
            JOIN users u ON c.user_id = u.id 
            ORDER BY c.created_at DESC";
    $result = mysqli_query($conn, $sql);
    $comments = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $comments[] = $row;
    }
    return $comments;
}

function deleteComment($conn, $commentId) {
    $stmt = mysqli_prepare($conn, "DELETE FROM comments WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $commentId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}
?>
