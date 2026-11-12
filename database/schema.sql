-- Apex Pickleball Courts — database schema + starter data
-- 1. Create an empty database first (locally: phpMyAdmin > New > "apex_pickleball";
--    on web hosting: use the database your hosting control panel creates).
-- 2. Select that database in phpMyAdmin, open the Import tab, and import this file.

DROP TABLE IF EXISTS booking_slots;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS open_play_signups;
DROP TABLE IF EXISTS open_play_sessions;
DROP TABLE IF EXISTS event_inquiries;
DROP TABLE IF EXISTS event_packages;
DROP TABLE IF EXISTS courts;
DROP TABLE IF EXISTS admins;

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE courts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  court_type VARCHAR(20) NOT NULL DEFAULT 'Indoor',
  hourly_rate DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'available', -- available | maintenance
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(12) NOT NULL UNIQUE,
  court_id INT NOT NULL,
  booking_date DATE NOT NULL,
  start_hour INT NOT NULL,                      -- first booked hour (for sorting)
  hours INT NOT NULL,
  time_label VARCHAR(80) NOT NULL,             -- e.g. "5:00 PM – 7:00 PM"
  customer_name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(150) NOT NULL,
  players INT NOT NULL DEFAULT 4,
  payment_method VARCHAR(20) NOT NULL,          -- gcash | card | venue
  payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid', -- unpaid | paid
  status VARCHAR(20) NOT NULL DEFAULT 'confirmed',      -- confirmed | checked_in | completed | cancelled
  total DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (court_id) REFERENCES courts(id),
  INDEX (booking_date)
) ENGINE=InnoDB;

-- One row per booked hour. The UNIQUE key makes double-booking impossible,
-- even if two people click "Confirm" at the same moment.
CREATE TABLE booking_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  court_id INT NOT NULL,
  booking_date DATE NOT NULL,
  slot_hour INT NOT NULL,
  UNIQUE KEY uniq_slot (court_id, booking_date, slot_hour),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE open_play_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  session_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  courts_label VARCHAR(50) NOT NULL,
  skill_level VARCHAR(20) NOT NULL DEFAULT 'All levels', -- Beginner | Intermediate | Advanced | All levels
  capacity INT NOT NULL DEFAULT 16,
  price DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE open_play_signups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(150) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'joined', -- joined | waitlist | cancelled
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_signup (session_id, email),
  FOREIGN KEY (session_id) REFERENCES open_play_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE event_packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  price DECIMAL(10,2) NULL,          -- NULL = custom quote
  features TEXT NOT NULL,            -- one feature per line
  is_popular TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE event_inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  organization VARCHAR(100) NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  package_id INT NULL,
  preferred_date DATE NOT NULL,
  guests INT NOT NULL,
  message TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new', -- new | contacted | booked | closed
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (package_id) REFERENCES event_packages(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Starter data (edit prices in the admin panel) ----------

-- Default admin login:  admin / admin123   (change it in Admin > My account!)
INSERT INTO admins (username, password_hash, full_name) VALUES
('admin', '$2y$10$eOdN9C7iHfTS2.wol88/e.Q0TenL/Xukf4kXYFC6rvRwTnaDjL08C', 'Administrator');

INSERT INTO courts (name, court_type, hourly_rate, status, sort_order) VALUES
('Court 1', 'Outdoor', 250.00, 'available', 1),
('Court 2', 'Outdoor', 250.00, 'available', 2);

INSERT INTO open_play_sessions (title, session_date, start_time, end_time, courts_label, skill_level, capacity, price) VALUES
('Morning Open Play', CURDATE() + INTERVAL 1 DAY, '07:00', '10:00', 'Courts 1–2', 'Beginner', 16, 150.00),
('Evening Open Play', CURDATE() + INTERVAL 1 DAY, '18:00', '21:00', 'Courts 1–2', 'Intermediate', 24, 200.00),
('Advanced Round Robin', CURDATE() + INTERVAL 2 DAY, '16:00', '19:00', 'Courts 1–2', 'Advanced', 12, 250.00),
('Weekend Social Play', CURDATE() + INTERVAL 3 DAY, '08:00', '11:00', 'Courts 1–2', 'All levels', 24, 180.00);

INSERT INTO event_packages (name, price, features, is_popular, sort_order) VALUES
('Half-Day', 5000.00, '1 court for 4 hours\nPaddles & balls included\nSeating area', 0, 1),
('Full-Day', 12000.00, 'Both courts for 8 hours\nPaddles, balls & scoreboards\nSound system', 1, 2),
('Tournament', NULL, 'Multi-day venue use\nBrackets & referees\nCatering options', 0, 3);
