<?php
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* ============== Login for local testing ============== */
function loginCtrl($conn) {
    $error  = '';
    $prefill = $_COOKIE['remember_user'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if ($email === '' || $password === '') {
            $error = 'Email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $user = authUser($conn, $email, $password);
            if ($user) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'is_verified' => $user['is_verified']
                ];
                if ($remember) setcookie('remember_email', $email, time() + 86400 * 30, '/');
                else setcookie('remember_email', '', time() - 3600, '/');
                $goto = $user['role'] == 'user' ? 'browse' : $user['role'];
                header('Location: index.php?page='.$goto);
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }

    require 'views/login.php';
}

function browseCtrl($conn) {
    $posts = getApprovedPosts($conn);
    $countries = getCountries($conn);
    $genres = getGenres($conn);
    $featuredDests = getFeaturedDestinations($conn);
    require 'views/browse.php';
}

/* ============== Detail Page ============== */
function detailCtrl($conn) {
    $id = intval($_GET['id'] ?? 0);
    $post = getPostById($conn, $id);

    if (!$post) {
        header('Location: index.php?page=browse&msg=notfound');
        exit;
    }

    $comments = getCommentsByPost($conn, $id);
    $cost = getBaseCost($conn, $id, $post['cost_level']);
    require 'views/detail.php';
}

/* ============== AJAX: Search/Filter/Add Comment/Delete Comment ============== */
function ajaxCtrl($conn) {
    $type = $_GET['type'] ?? '';

    if ($type === 'search') {
        $q = trim($_GET['q'] ?? '');
        jsonResponse(['success' => true, 'posts' => searchPosts($conn, $q)]);
    }

    if ($type === 'filter') {
        $country = trim($_GET['country'] ?? '');
        $genre = trim($_GET['genre'] ?? '');
        $cost = trim($_GET['cost'] ?? '');
        $allowedCosts = ['', 'low', 'medium', 'high'];
        $allowedGenres = ['beach', 'mountain', 'city', 'historical'];

        if (!in_array($cost, $allowedCosts, true)) {
            jsonResponse(['success' => false, 'message' => 'Invalid cost level.'], 400);
        }

        $genres = [];
        if ($genre !== '') {
            foreach (explode(',', $genre) as $g) {
                $g = trim($g);
                if (in_array($g, $allowedGenres, true)) $genres[] = $g;
            }
        }

        jsonResponse(['success' => true, 'posts' => filterPosts($conn, $country, $genres, $cost)]);
    }

    if ($type === 'add_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isVerifiedGeneralUser()) {
            jsonResponse(['success' => false, 'message' => 'Only verified general users can comment.'], 403);
        }

        $postId = intval($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if ($postId <= 0 || $content === '') {
            jsonResponse(['success' => false, 'message' => 'Comment cannot be empty.'], 422);
        }
        if (strlen($content) > 500) {
            jsonResponse(['success' => false, 'message' => 'Comment must be within 500 characters.'], 422);
        }
        if (!getPostById($conn, $postId)) {
            jsonResponse(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $newId = addComment($conn, $postId, $_SESSION['user']['id'], $content);
        jsonResponse([
            'success' => true,
            'comment' => [
                'id' => $newId,
                'name' => $_SESSION['user']['name'],
                'content' => e($content),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }

    if ($type === 'delete_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isVerifiedGeneralUser()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $commentId = intval($_POST['comment_id'] ?? 0);
        if ($commentId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid comment ID.'], 422);
        }

        if (deleteOwnComment($conn, $commentId, $_SESSION['user']['id'])) {
            jsonResponse(['success' => true, 'message' => 'Comment deleted.']);
        }
        jsonResponse(['success' => false, 'message' => 'Comment not found or not yours.'], 404);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid AJAX request.'], 400);
}

/* ============== Featured Destinations ============== */
function getFeaturedDestinations($conn)
{
    $stmt = mysqli_prepare($conn, "SELECT id, title, country, genre, cost_level, short_history, travel_medium_info FROM posts WHERE status = 'approved' ORDER BY created_at DESC LIMIT 18");
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function featuredCtrl($conn)
{
    $postId = intval($_GET['dest'] ?? -1);
    $dest = getPostById($conn, $postId);

    if (!$dest || $dest['status'] !== 'approved') {
        header('Location: index.php?page=browse');
        exit;
    }

    $cost = getBaseCost($conn, $postId, $dest['cost_level']);
    $ck   = 'fcomments_' . $postId;

    if (!isset($_SESSION[$ck])) $_SESSION[$ck] = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isVerifiedGeneralUser()) {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $content = trim($_POST['content'] ?? '');
            if ($content !== '' && strlen($content) <= 500) {
                $_SESSION[$ck][] = [
                    'id'         => uniqid('fc_', true),
                    'user_id'    => $_SESSION['user']['id'],
                    'name'       => $_SESSION['user']['name'],
                    'content'    => $content,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
        } elseif ($action === 'delete') {
            $cid = $_POST['comment_id'] ?? '';
            $_SESSION[$ck] = array_values(array_filter($_SESSION[$ck], fn($c) => $c['id'] !== $cid));
        }

        header('Location: index.php?page=featured&dest=' . $postId);
        exit;
    }

    $comments = array_reverse($_SESSION[$ck]);
    require 'views/featured_detail.php';
}

function registerCtrl($conn) {
    $error = $success = '';
    $old   = ['name' => '', 'email' => '', 'role' => 'user'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $role     = $_POST['role'] ?? 'user';
        $old      = compact('name', 'email', 'role');

        if ($name === '' || $email === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            if (registerUser($conn, $name, $email, $password, $role)) {
                $success = 'Account created! Please wait for admin approval.';
                $old     = ['name' => '', 'email' => '', 'role' => 'user'];
            } else {
                $error = 'Email already exists or registration failed.';
            }
        }
    }

    require 'views/register.php';
}

function homeCtrl($conn) {
    $user  = $_SESSION['user'] ?? null;
    $posts = [];

    if ($user && $user['verified']) {
        $posts = getApprovedPosts($conn, 6, 0);
    }

    require 'views/home.php';
}

/* ============== Wishlist ============== */
function wishlistCtrl($conn) {
    if (!isset($_SESSION['user'])) {
        header('Location: index.php?page=login');
        exit;
    }

    $user   = $_SESSION['user'];
    $userId = $user['id'];
    $items  = getUserWishlist($conn, $userId);

    require 'views/wishlist.php';
}

/* ============== AJAX: Wishlist Add ============== */
function ajaxWishlistAdd($conn) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }

    $data   = json_decode(file_get_contents('php://input'), true);
    $postId = $data['post_id'] ?? 0;
    $userId = $_SESSION['user']['id'];

    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post ID']);
        exit;
    }

    if (addToWishlist($conn, $userId, $postId)) {
        echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Already in wishlist or failed']);
    }
    exit;
}

/* ============== AJAX: Wishlist Remove ============== */
function ajaxWishlistRemove($conn) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }

    $data   = json_decode(file_get_contents('php://input'), true);
    $postId = $data['post_id'] ?? 0;
    $userId = $_SESSION['user']['id'];

    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post ID']);
        exit;
    }

    if (removeFromWishlist($conn, $userId, $postId)) {
        echo json_encode(['success' => true, 'message' => 'Removed from wishlist']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to remove']);
    }
    exit;
}

/* ============== AJAX: Wishlist Check ============== */
function ajaxWishlistCheck($conn) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }

    $data   = json_decode(file_get_contents('php://input'), true);
    $postId = $data['post_id'] ?? 0;
    $userId = $_SESSION['user']['id'];

    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post ID']);
        exit;
    }

    echo json_encode(['in_wishlist' => isInWishlist($conn, $userId, $postId)]);
    exit;
}

function profileCtrl($conn) {
    if (!isset($_SESSION['user'])) {
        header('Location: index.php?page=login');
        exit;
    }

    $userId = $_SESSION['user']['id'];
    $user   = getUserById($conn, $userId);
    $error  = $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name            = trim($_POST['name'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $picture         = null;

        // File upload
        if (!empty($_FILES['picture']['name'])) {
            $file    = $_FILES['picture'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed)) {
                $error = 'Only JPG, PNG, GIF allowed.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'File size must be under 2MB.';
            } else {
                $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
                $picture = 'uploads/' . uniqid() . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $picture)) {
                    $error   = 'File upload failed.';
                    $picture = null;
                }
            }
        }

        // Validate inputs
        if ($name === '')                              $error = 'Name is required.';
        if ($email === '')                             $error = 'Email is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email format.';

        // Password change
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                $error = 'New password must be 8+ characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match.';
            }
        }

        if ($error === '') {
            updateUserProfile($conn, $userId, $name, $email,
                $newPassword !== '' ? $newPassword : null,
                $picture);
            $success = 'Profile updated successfully!';
            $_SESSION['user']['name']  = $name;
            $_SESSION['user']['email'] = $email;
            $user = getUserById($conn, $userId);
        }
    }

    require 'views/profile.php';
}

/* ============== Admin Dashboard ============== */
function adminCtrl($conn) {
    // Check admin role
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        header('Location: index.php?page=home');
        exit;
    }

    $submodule = $_GET['module'] ?? 'dashboard';
    
    // Initialize variables
    $allUsers = array();
    $pendingRequests = array();
    $approvedPosts = array();
    $allComments = array();
    $adminStats = array();
    $error = '';
    $success = '';
    
    // Get admin statistics
    $adminStats = getAdminStats($conn);
    
    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($submodule === 'users') {
            if (isset($_POST['add_user'])) {
                $name = trim($_POST['user_name'] ?? '');
                $email = trim($_POST['user_email'] ?? '');
                $password = $_POST['user_password'] ?? '';
                $role = $_POST['user_role'] ?? 'user';
                
                if (empty($name) || empty($email) || empty($password)) {
                    $error = 'All fields are required.';
                } else {
                    if (addUserByAdmin($conn, $name, $email, $password, $role)) {
                        $success = 'User added successfully.';
                    } else {
                        $error = 'Email already exists or user add failed.';
                    }
                }
            } elseif (isset($_POST['verify_user'])) {
                $userId = $_POST['user_id'] ?? 0;
                if (toggleUserVerification($conn, $userId)) {
                    $success = 'User verification toggled.';
                } else {
                    $error = 'Failed to toggle verification.';
                }
            } elseif (isset($_POST['delete_user'])) {
                $userId = $_POST['user_id'] ?? 0;
                if ($userId != $_SESSION['user']['id']) {
                    if (deleteUserCascade($conn, $userId)) {
                        $success = 'User deleted successfully.';
                    } else {
                        $error = 'Failed to delete user.';
                    }
                } else {
                    $error = 'You cannot delete your own admin account.';
                }
            }
        } elseif ($submodule === 'posts') {
            if (isset($_POST['approve_post'])) {
                $requestId = $_POST['request_id'] ?? 0;
                if (approvePostRequest($conn, $requestId)) {
                    $success = 'Post request approved.';
                } else {
                    $error = 'Failed to approve post request.';
                }
            } elseif (isset($_POST['reject_post'])) {
                $requestId = $_POST['request_id'] ?? 0;
                if (rejectPostRequest($conn, $requestId)) {
                    $success = 'Post request rejected.';
                } else {
                    $error = 'Failed to reject post request.';
                }
            } elseif (isset($_POST['delete_post'])) {
                $postId = $_POST['post_id'] ?? 0;
                if (deletePostCascade($conn, $postId)) {
                    $success = 'Post deleted successfully.';
                } else {
                    $error = 'Failed to delete post.';
                }
            } elseif (isset($_POST['update_post'])) {
                $postId = $_POST['post_id'] ?? 0;
                $title = $_POST['post_title'] ?? '';
                $history = $_POST['post_history'] ?? '';
                $country = $_POST['post_country'] ?? '';
                $genre = $_POST['post_genre'] ?? '';
                $cost_level = $_POST['post_cost_level'] ?? '';
                $travel_info = $_POST['post_travel_info'] ?? '';
                
                if (updatePost($conn, $postId, $title, $history, $country, $genre, $cost_level, $travel_info)) {
                    $success = 'Post updated successfully.';
                } else {
                    $error = 'Failed to update post.';
                }
            }
        } elseif ($submodule === 'comments') {
            if (isset($_POST['delete_comment'])) {
                $commentId = $_POST['comment_id'] ?? 0;
                if (deleteComment($conn, $commentId)) {
                    $success = 'Comment deleted successfully.';
                } else {
                    $error = 'Failed to delete comment.';
                }
            }
        }
    }
    
    // Load appropriate data based on module
    if ($submodule === 'users') {
        $allUsers = getAllUsers($conn);
    } elseif ($submodule === 'posts') {
        $pendingRequests = getPendingPostRequests($conn);
        $approvedPosts = getApprovedPostsForModeration($conn);
    } elseif ($submodule === 'comments') {
        $allComments = getAllComments($conn);
    }
    
    require 'views/admin.php';
}

/* ============== Admin AJAX Handler ============== */
function adminAjax($conn) {
    header('Content-Type: application/json');
    
    // Check admin role
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $action = $_GET['action'] ?? '';
    $response = ['status' => 'error', 'message' => 'Unknown action'];
    
    if ($action === 'toggle_verify') {
        $userId = $_GET['user_id'] ?? 0;
        if (toggleUserVerification($conn, $userId)) {
            $response = ['status' => 'success', 'message' => 'User verification toggled'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to toggle verification'];
        }
    } elseif ($action === 'delete_comment') {
        $commentId = $_GET['comment_id'] ?? 0;
        if (deleteComment($conn, $commentId)) {
            $response = ['status' => 'success', 'message' => 'Comment deleted'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to delete comment'];
        }
    } elseif ($action === 'approve_post') {
        $requestId = $_GET['request_id'] ?? 0;
        if (approvePostRequest($conn, $requestId)) {
            $response = ['status' => 'success', 'message' => 'Post approved'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to approve post'];
        }
    }
    
    echo json_encode($response);
    exit;
}
//Done by Ramim
//Ramim change-1
function scoutCtrl($conn) {
    
    if (!isset($_SESSION['user'])) {
        header('Location: index.php?page=login');
        exit;
    }
    
    $user = $_SESSION['user'];
    $scoutId = $user['id'];
    
    // Only scouts and admins can access scout panel
    if ($user['role'] !== 'scout' && $user['role'] !== 'admin') {
        header('Location: index.php?page=home');
        exit;
    }
    
    // Initialize variables
    $errors = [];
    $success = '';
    $error = '';
    $old_input = [];
    
    // Handle post request submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post_request'])) {
        $title = trim($_POST['title'] ?? '');
        $short_history = trim($_POST['short_history'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $genre = $_POST['genre'] ?? '';
        $cost_level = $_POST['cost_level'] ?? '';
        $travel_medium_info = trim($_POST['travel_medium_info'] ?? '');
        
        // Store old input for repopulating form
        $old_input = [
            'title' => $title,
            'short_history' => $short_history,
            'country' => $country,
            'genre' => $genre,
            'cost_level' => $cost_level,
            'travel_medium_info' => $travel_medium_info
        ];
        
        // Individual field validation
        if (empty($title)) {
            $errors['title'] = "Title is required";
        }
        if (empty($short_history)) {
            $errors['short_history'] = "Short history is required";
        }
        if (empty($country)) {
            $errors['country'] = "Country is required";
        }
        if (empty($genre)) {
            $errors['genre'] = "Please select a genre";
        }
        if (empty($cost_level)) {
            $errors['cost_level'] = "Please select a cost level";
        }
        if (empty($travel_medium_info)) {
            $errors['travel_medium_info'] = "Travel medium info is required";
        }
        
        // If no errors, insert into database
        if (empty($errors)) {
            $post_data = json_encode([
                'title' => $title,
                'short_history' => $short_history,
                'country' => $country,
                'genre' => $genre,
                'cost_level' => $cost_level,
                'travel_medium_info' => $travel_medium_info
            ]);
            
            $stmt = mysqli_prepare($conn, "INSERT INTO post_requests (scout_id, post_data, status) VALUES (?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, 'is', $scoutId, $post_data);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Post request submitted successfully! Waiting for admin approval.";
                // Clear old input on success
                $old_input = [];
                // Redirect to prevent form resubmission
                header("Location: index.php?page=scout&success=1");
                exit;
            } else {
                $error = "Failed to submit request. Please try again.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Please fill the required fields";
        }
    }
    
    // Check for success message from redirect
    if (isset($_GET['success']) && $_GET['success'] == 1) {
        $success = "Post request submitted successfully! Waiting for admin approval.";
    }
    
    // Get scout's pending requests
    $stmt = mysqli_prepare($conn, "SELECT * FROM post_requests WHERE scout_id = ? ORDER BY requested_at DESC");
    mysqli_stmt_bind_param($stmt, 'i', $scoutId);
    mysqli_stmt_execute($stmt);
    $requests = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    // Get approved posts from posts table
    $stmt = mysqli_prepare($conn, "SELECT * FROM posts WHERE status = 'approved' ORDER BY created_at DESC");
    mysqli_stmt_execute($stmt);
    $posts = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    require 'views/scout.php';
}

//Ramim change-2
function scoutRequestsCtrl($conn) {
    if (!isset($_SESSION['user'])) {
        header('Location: index.php?page=login');
        exit;
    }
    
    $user = $_SESSION['user'];
    
    // Only scouts and admins can access
    if ($user['role'] !== 'scout' && $user['role'] !== 'admin') {
        header('Location: index.php?page=home');
        exit;
    }
    
    $scoutId = $user['id'];
    $message = '';
    $error = '';
    $editRequest = null;
    $edit_errors = [];
    
    // Check for success message from redirect
    if (isset($_GET['updated']) && $_GET['updated'] == 1) {
        $message = "Request updated successfully!";
    }
    
    if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
        $message = "Request deleted successfully!";
    }
    
    // Handle Edit - GET request to get data for editing
    if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $requestId = $_GET['edit'];
        $stmt = mysqli_prepare($conn, "SELECT * FROM post_requests WHERE id = ? AND scout_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt, 'ii', $requestId, $scoutId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $editRequest = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$editRequest) {
            $error = "Request not found or cannot be edited.";
        }
    }
    
    // Handle Update (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
        $requestId = $_POST['request_id'];
        $title = trim($_POST['title'] ?? '');
        $short_history = trim($_POST['short_history'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $genre = $_POST['genre'] ?? '';
        $cost_level = $_POST['cost_level'] ?? '';
        $travel_medium_info = trim($_POST['travel_medium_info'] ?? '');
        
        // PHP Validation
        if (empty($title)) $edit_errors['title'] = "Title is required";
        if (empty($short_history)) $edit_errors['short_history'] = "Short history is required";
        if (empty($country)) $edit_errors['country'] = "Country is required";
        if (empty($genre)) $edit_errors['genre'] = "Please select a genre";
        if (empty($cost_level)) $edit_errors['cost_level'] = "Please select a cost level";
        if (empty($travel_medium_info)) $edit_errors['travel_medium_info'] = "Travel medium info is required";
        
        if (empty($edit_errors)) {
            $post_data = json_encode([
                'title' => $title,
                'short_history' => $short_history,
                'country' => $country,
                'genre' => $genre,
                'cost_level' => $cost_level,
                'travel_medium_info' => $travel_medium_info
            ]);
            
            $stmt = mysqli_prepare($conn, "UPDATE post_requests SET post_data = ? WHERE id = ? AND scout_id = ? AND status = 'pending'");
            mysqli_stmt_bind_param($stmt, 'sii', $post_data, $requestId, $scoutId);
            
            if (mysqli_stmt_execute($stmt)) {
                header("Location: index.php?page=scoutrequests&updated=1");
                exit;
            } else {
                $error = "Failed to update request.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Please fill the required fields";
            // Fetch the request again to show the edit form with errors
            $stmt = mysqli_prepare($conn, "SELECT * FROM post_requests WHERE id = ? AND scout_id = ? AND status = 'pending'");
            mysqli_stmt_bind_param($stmt, 'ii', $requestId, $scoutId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $editRequest = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        }
    }
    
    // Handle Delete (POST for delete)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
        $requestId = $_POST['request_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM post_requests WHERE id = ? AND scout_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt, 'ii', $requestId, $scoutId);
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: index.php?page=scoutrequests&deleted=1");
            exit;
        } else {
            $error = "Failed to delete request.";
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get all requests for this scout (only pending new post requests, not change requests)
    $stmt = mysqli_prepare($conn, "SELECT * FROM post_requests WHERE scout_id = ? AND status = 'pending' AND original_post_id IS NULL ORDER BY requested_at DESC");
    mysqli_stmt_bind_param($stmt, 'i', $scoutId);
    mysqli_stmt_execute($stmt);
    $requests = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    require 'views/scoutrequests.php';
}

//Ramim change-3
function approvedPostsCtrl($conn) {
    if (!isset($_SESSION['user'])) {
        header('Location: index.php?page=login');
        exit;
    }
    
    $user = $_SESSION['user'];
    $scoutId = $user['id'];
    
    // Only scouts and admins can access
    if ($user['role'] !== 'scout' && $user['role'] !== 'admin') {
        header('Location: index.php?page=home');
        exit;
    }
    
    // Handle change request submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_change_request'])) {
        $original_post_id = $_POST['original_post_id'] ?? 0;
        $title = trim($_POST['title'] ?? '');
        $short_history = trim($_POST['short_history'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $genre = $_POST['genre'] ?? '';
        $cost_level = $_POST['cost_level'] ?? '';
        $travel_medium_info = trim($_POST['travel_medium_info'] ?? '');
        
        $errors = [];
        
        if (empty($title)) $errors[] = "Title is required";
        if (empty($short_history)) $errors[] = "Short history is required";
        if (empty($country)) $errors[] = "Country is required";
        if (empty($genre)) $errors[] = "Genre is required";
        if (empty($cost_level)) $errors[] = "Cost level is required";
        if (empty($travel_medium_info)) $errors[] = "Travel medium info is required";
        
        if (empty($errors)) {
            $post_data = json_encode([
                'title' => $title,
                'short_history' => $short_history,
                'country' => $country,
                'genre' => $genre,
                'cost_level' => $cost_level,
                'travel_medium_info' => $travel_medium_info
            ]);
            
            $stmt = mysqli_prepare($conn, "INSERT INTO post_requests (scout_id, original_post_id, post_data, status) VALUES (?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, 'iis', $scoutId, $original_post_id, $post_data);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Change request submitted successfully! Waiting for admin approval.";
            } else {
                $error = "Failed to submit change request.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = implode(", ", $errors);
        }
    }
    
    // Get all approved posts
    $stmt = mysqli_prepare($conn, "SELECT * FROM posts WHERE status = 'approved' ORDER BY created_at DESC");
    mysqli_stmt_execute($stmt);
    $posts = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    require 'views/scoutapprovedposts.php';
}

//Ramim change-4
function ajaxChangeRequest($conn) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['error' => 'Not logged in', 'success' => false]);
        exit;
    }
    
    $user = $_SESSION['user'];
    $scoutId = $user['id'];
    
    // Only scouts and admins can submit change requests
    if ($user['role'] !== 'scout' && $user['role'] !== 'admin') {
        echo json_encode(['error' => 'Unauthorized', 'success' => false]);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $original_post_id = $data['original_post_id'] ?? 0;
    $title = trim($data['title'] ?? '');
    $short_history = trim($data['short_history'] ?? '');
    $country = trim($data['country'] ?? '');
    $genre = $data['genre'] ?? '';
    $cost_level = $data['cost_level'] ?? '';
    $travel_medium_info = trim($data['travel_medium_info'] ?? '');
    
    // Validate
    if (empty($title) || empty($short_history) || empty($country) || empty($genre) || empty($cost_level) || empty($travel_medium_info)) {
        echo json_encode(['error' => 'All fields are required', 'success' => false]);
        exit;
    }
    
    $post_data = json_encode([
        'title' => $title,
        'short_history' => $short_history,
        'country' => $country,
        'genre' => $genre,
        'cost_level' => $cost_level,
        'travel_medium_info' => $travel_medium_info
    ]);
    
    $stmt = mysqli_prepare($conn, "INSERT INTO post_requests (scout_id, original_post_id, post_data, status) VALUES (?, ?, ?, 'pending')");
    mysqli_stmt_bind_param($stmt, 'iis', $scoutId, $original_post_id, $post_data);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => mysqli_error($conn), 'success' => false]);
    }
    mysqli_stmt_close($stmt);
}

//Ramim till here