# Implemented Features by 23-52577-2

### Admin Authentication & Access Control

- Implemented an **Admin Gate System** where every admin page verifies `$_SESSION['role'] === 'admin'`.
- Unauthorized users are automatically redirected to prevent restricted access.

### Dashboard Summary

- Created an admin dashboard displaying:
  - Total number of users (categorized by roles)
  - Total pending post requests
  - Total approved posts
  - Total comments

### User Management System

Admin functionalities include:

- **Add New User**
  - Create users with:
    - Name
    - Email
    - Password
    - Role

  - Supports setting `is_verified` as `1` or `0` during creation.

- **Verify / Unverify Users**
  - Toggle user verification status dynamically.
  - Verified users receive full platform access.

- **Delete User** _(Implemented but not yet tested)_
  - Cascade deletion of:
    - Posts
    - Post requests
    - Wishlist entries
    - Comments

- **Change User Role** _(Optional feature – implemented but not yet tested)_

### Post Moderation System

- Displays:
  - All pending `post_requests`
  - All approved/rejected posts

Admin actions include:

- **Approve Post Request**
  - Transfers data from `post_requests` to `posts`
  - Creates a new post with `status = 'approved'`
  - Removes the original request entry

- **Reject Post Request**
  - Removes or marks requests as rejected with a reason

- **Edit Approved Posts** _(Implemented but not yet tested)_
  - Update:
    - Title
    - History
    - Country
    - Other post details

- **Delete Posts** _(Implemented but not yet tested)_
  - Removes related:
    - Comments
    - Wishlist entries

### Comment Moderation

- Displays all comments along with:
  - Post title
  - Commenter name

- Admin can delete comments using:
  - AJAX requests
  - Standard POST methods

_(Feature implemented but not yet tested)_

### AJAX Functionalities

Implemented AJAX support for:

- User verification toggling
- Inline approval of post requests
- Comment deletion

### Validation & Security

- Added PHP validation for all admin actions, including:
  - Preventing duplicate email registration
  - Restricting admins from deleting their own account

---

### Remaining Work

The **Post** and **Scout** page functionalities still need to be completed.
These are required to fully test and verify several admin-side features such as:

- Post moderation
- Cascade deletion
- Comment management
- Wishlist-related operations
