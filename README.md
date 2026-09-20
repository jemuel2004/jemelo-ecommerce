# Jemelo — Free PHP Ecommerce

A free, open-source ecommerce store built with **PHP**, **MySQL**, and **XAMPP**.

You can use, copy, modify, and share this project for personal or school work under the [MIT License](LICENSE). Attribution is appreciated but not required.

---

## Features

- Customer storefront (browse, search, cart, checkout)
- Admin panel (products, categories, orders, customers, staff, reports)
- Rider / delivery tracking
- Live chat and notifications
- Sample products, categories, and admin account included in the SQL file

---

## Requirements

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8+)
- A web browser

---

## Quick setup

The database is **already included** in this project:  
`database/ecommerce.sql`

Just import it — tables, sample products, categories, and the admin account are all inside. After that, the store is ready to run.

### 1. Download the project

Clone or download this repository, then place the folder here:

```text
C:\xampp\htdocs\Ecommerce
```

If you rename the folder, also update `SITE_URL` in `config/database.php`.

### 2. Start XAMPP

Open **XAMPP Control Panel** and start:

- **Apache**
- **MySQL**

### 3. Import the database

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click **New** and create a database named `ecommerce_db`
3. Select `ecommerce_db`
4. Go to the **Import** tab
5. Choose the file: `database/ecommerce.sql`
6. Click **Go** / **Import**

When the import finishes, everything should be ready to run.

### 4. Database settings (optional)

Default values in `config/database.php`:

| Setting   | Default value   |
|-----------|-----------------|
| Host      | `localhost`     |
| User      | `root`          |
| Password  | *(empty)*       |
| Database  | `ecommerce_db`  |
| Site URL  | `http://localhost/Ecommerce` |

Change these only if your MySQL password or folder name is different.

### 5. Open the store

| Page        | URL |
|-------------|-----|
| Storefront  | http://localhost/Ecommerce/ |
| Customer shop | http://localhost/Ecommerce/customer/index.php |
| Admin panel | http://localhost/Ecommerce/admin/index.php |
| Rider login | http://localhost/Ecommerce/rider/login.php |

### Alternative: use `setup.php`

If you prefer not to import manually, open:

```text
http://localhost/Ecommerce/setup.php
```

That creates the database and seed data for you. Delete `setup.php` afterward for safety.

---

## Default admin account

After importing `database/ecommerce.sql` (or running `setup.php`), log in with:

| Field    | Value |
|----------|-------|
| Email    | `admin@shop.com` |
| Password | `Admin@123` |

**Important:** Change this password after your first login if you use the project online or share your computer.

### How to log in as admin

1. Go to http://localhost/Ecommerce/
2. Click **Login**
3. Enter `admin@shop.com` and `Admin@123`
4. You will be redirected to the admin dashboard

### Create a customer account

1. Go to the storefront
2. Open **Register** / create account
3. Fill in your details and sign up
4. Shop as a normal customer

---

## Project structure

```text
Ecommerce/
├── admin/          # Admin & staff panel
├── ajax/           # AJAX API endpoints
├── assets/         # CSS, JS, images, uploads
├── config/         # Database & site settings
├── customer/       # Storefront pages
├── database/       # Included SQL dump (ecommerce.sql) — import this
├── includes/       # Shared PHP helpers
├── rider/          # Rider / delivery pages
├── setup.php       # Optional installer (alternative to SQL import)
├── LICENSE         # MIT — free to use
└── README.md       # This guide
```

---

## Common problems

**Blank page or database error**  
Make sure Apache and MySQL are running, that `ecommerce_db` exists, and that you imported `database/ecommerce.sql`.

**Wrong URL / CSS not loading**  
Check that `SITE_URL` in `config/database.php` matches your folder name  
(example: `http://localhost/Ecommerce`).

**Cannot log in as admin**  
Re-import `database/ecommerce.sql`, or run `setup.php` once to reset the admin password to `Admin@123`.

**Table doesn't exist in engine**  
The MySQL data may be corrupted. Drop `ecommerce_db`, create it again, then re-import `database/ecommerce.sql`.

---

## License

This project is free software under the **MIT License**.

That means you may:

- Use it for free
- Study and modify the code
- Share it with others
- Use it in school or personal projects

See [LICENSE](LICENSE) for the full text.

---

## Credits

Built as **Jemelo** — a simple ecommerce starter for learning and small shops.
