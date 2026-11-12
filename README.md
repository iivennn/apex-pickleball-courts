# Apex Pickleball Courts

A responsive booking and court-management website for a pickleball venue: online court reservations, open play sign-ups, event rental inquiries, and an admin dashboard for staff.

Built with **PHP 8 + MySQL**, plain HTML/CSS and a little vanilla JavaScript — no frameworks, so it runs on XAMPP or any basic PHP hosting.

## Features

**For players**
- **Book a Court**: pick a date (up to 14 days ahead), a court, and up to 4 hourly time slots, with a live price summary. Booked and past slots are blocked automatically.
- **Booking confirmation** with a unique reference code (e.g. `APX-7K2M9Q`) and GCash payment instructions.
- **Open Play**: browse upcoming sessions, filter by skill level, and join. Once a session is full, new sign-ups go on a **waitlist**.
- **Event Rentals**: packages (Half-Day, Full-Day, Tournament) and an inquiry form.
- Mobile-friendly layout with keyboard and screen-reader support.

**For staff (`/admin`)**
- **Dashboard**: today's bookings, booked value, court occupancy, live court status, today's open play, latest inquiries, and a 7-day bookings chart.
- **Bookings**: search and filter; mark paid, check in, complete, or cancel (cancelling frees the time slots).
- **Open play**: create and delete sessions, see player lists, and remove players (the next waitlisted player is moved in automatically).
- **Event inquiries**: track each inquiry as New → Contacted → Booked → Closed.
- **Courts & rates**: edit prices, add courts, or put a court under maintenance.
- **My account**: change password.

## Security
- **Prepared statements** (PDO) for every query, to prevent SQL injection
- **Output escaping** on all user content, to prevent XSS
- **CSRF tokens** on every form
- Passwords stored with `password_hash()`, a session ID regenerated on login, and login attempts rate-limited
- **No double bookings**: a `UNIQUE (court, date, hour)` key in `booking_slots` makes the database itself reject a second booking for the same slot, even if two people confirm at the same time

## Run it locally (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this folder into `C:\xampp\htdocs\apex-pickleball-courts`.
3. Open **phpMyAdmin** (http://localhost/phpmyadmin), click **New**, create a database named `apex_pickleball`, then open its **Import** tab, choose `database/schema.sql`, and click **Go**.
   This creates all the tables with sample courts, sessions, and packages.
4. Visit **http://localhost/apex-pickleball-courts/**

**Admin login:** http://localhost/apex-pickleball-courts/admin/
Username `admin` · Password `admin123`. **Change it right away** in *My account*.

## Put it online (free, InfinityFree)
1. Create a free account at [infinityfree.com](https://infinityfree.com) and a hosting account with a free subdomain.
2. In the control panel, open **MySQL Databases** and create a database. Note the **host, database name, username, and password**.
3. Open **phpMyAdmin** for that database and **Import** `database/schema.sql`.
4. Edit `config.php`: set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` to the values from step 2.
5. Open the **File Manager**, go into `htdocs`, delete the default files, and upload everything in this project.
6. Visit your subdomain, log in at `/admin`, and change the admin password.

## Customize
- **`config.php`**: database login, business address, phone, email, hours, Google Maps embed, and booking rules (opening hours, days ahead, max hours).
- **Prices**: change court rates in *Admin → Courts & rates*. The open play prices and event package prices in `schema.sql` are sample values.
- **Gallery**: drop `.jpg`, `.png`, or `.webp` photos into `assets/img/gallery/` and a Gallery section appears on the home page.

## Project structure
```
├── index.php              Home
├── book.php               Court booking (availability + checkout)
├── booking-success.php    Confirmation page
├── open-play.php          Open play schedule + join/waitlist
├── rentals.php            Event packages + inquiry form
├── admin/                 Staff dashboard (login required)
├── includes/              Shared helpers, header/footer, admin layout
├── assets/                CSS, JS, logo, gallery
├── database/schema.sql    Tables + starter data
└── config.php             Settings
```

## Database
| Table | Purpose |
|---|---|
| `courts` | Courts, type, hourly rate, status |
| `bookings` | One row per reservation (reference, customer, payment, status) |
| `booking_slots` | One row per booked hour, with a unique key that prevents double booking |
| `open_play_sessions` / `open_play_signups` | Sessions and the players who joined or are on the waitlist |
| `event_packages` / `event_inquiries` | Rental packages and incoming inquiries |
| `admins` | Staff logins |

---
Built by **Iven Rey B. Tañara**, BS Computer Engineering, Silliman University.
