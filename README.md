# 🌍 Travel Guide Website

A full-stack travel guide web application developed for the Web Technologies course project.

The platform helps users explore tourist destinations worldwide with role-based features including:
- Travel information submission
- Wishlist management
- Comments
- Dynamic search & filtering

---

# 🛠️ Technologies Used

### Frontend
- HTML5
- CSS3
- JavaScript

### Backend
- PHP

### Database
- MySQL

### Architecture
- MVC Pattern

### Additional Technologies
- AJAX
- JSON APIs

---

# 🚀 Features

### 👤 Authentication & User Management
- User Registration & Login
- Secure password hashing using `password_hash()`
- Session-based authentication
- Role-based access control
- Remember Me functionality
- Profile management with image upload

---

### 🧭 Travel Post System
- Scouts can submit travel destination requests
- Admin approval/rejection system
- CRUD operations for travel posts
- Image upload support

---

### 🔎 Search & Filtering
- AJAX-powered live search
- Filter posts by:
  - Country
  - Genre
  - Cost Level

---

### ❤️ Wishlist System
- Add/remove destinations dynamically using AJAX
- Personalized wishlist page

---

### 💬 Comment System
- Users can add/delete comments
- Instant AJAX updates

---

### 📊 Admin Dashboard
- User management
- Post moderation
- Comment moderation
- Dashboard statistics

---

### 💰 Cost Estimation
Travel cost calculator based on:
- Number of travelers
- Number of days
- Destination cost level

---

# ⚙️ How to Run the Project

### Make sure you have
- XAMPP
- PHP 8+
- MySQL
- Web Browser

- Note: It is best if the installed XAMPP is in C:/xampp directory

## Steps to Run

###1️⃣ Clone or Download the Repository

###2️⃣ Move Project Folder

Place the project inside:

```bash
htdocs/
```

Example:

```bash
C:/xampp/htdocs/travel-guide-website
```

---

### 3️⃣ Start Apache & MySQL

Open XAMPP Control Panel and start:
- Apache
- MySQL
- Note: Make sure that both runs without any error, if you face error its like because of port issue that can be solved from checking videos online.

---

### 4️⃣ Create Database

Open phpMyAdmin and create a database named:

```sql
travel_guide
```

Note: To open phpMyAdmin you need to go in a link like

```
localhost:8081/phpmyadmin/
```

Import the provided SQL file from the `database/` folder named travel_guide_db.sql
Note: Click the database you created then go to the import tab to import the database file

---

### 5️⃣ Configure Database Connection

Open the file in coding software:

```bash
config.php
```

Update database credentials if needed.

---

### 6️⃣ Run the Project

Open your browser and visit the directory in localhost:

Example:
```bash
http://localhost:8081/wbt/index.php
```
Note: Here your format will be localhost:PortNumber/file you kept the project/index.php

---

# 🔑 Test Roles

- Admin
- Scout
- General User

You can create accounts manually but the Admin Account is hard coded inside config.php

---

# 📚 What I Learned

- Building a full-stack web application using PHP and MySQL
- Implementing MVC architecture
- Using AJAX for dynamic updates without page reload
- Session management and role-based authentication
- CRUD operations with database integration
- Secure coding practices like password hashing and prepared statements
- Team collaboration using Git and GitHub

---

# 🚀 Future Improvements
- **Note there are few issues and corrupted features due to it being a merged project**
- Add Google Maps integration
- Add hotel and flight booking system
- Improve UI/UX with modern frontend frameworks
- Add real-time chat and notifications
- Implement AI-based travel recommendations
- Add multilingual support and responsive enhancements
