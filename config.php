<?php
// ============================================================
//  Apex Pickleball Courts — settings
//  Edit these values to match your computer / hosting.
// ============================================================

// Database (XAMPP default: user "root", empty password)
define('DB_HOST', 'localhost');
define('DB_NAME', 'apex_pickleball');
define('DB_USER', 'root');
define('DB_PASS', '');

// Business info (shown in the footer and pages)
define('SITE_NAME', 'Apex Pickleball Courts');
define('SITE_CITY', 'Dumaguete City');
define('SITE_ADDRESS', '[Your street address], Dumaguete City');
define('SITE_PHONE', '0917 000 0000');
define('SITE_EMAIL', 'hello@apexpickleball.ph');
define('SITE_HOURS', 'Daily, 6:00 AM – 12:00 midnight');
define('SITE_FACEBOOK', '#');
define('SITE_INSTAGRAM', '#');
// Paste a Google Maps "Embed a map" URL here to show a map in the footer (leave '' to hide)
define('SITE_MAP_EMBED', '');

// Booking rules
define('OPEN_HOUR', 6);          // first bookable slot starts 6:00 AM
define('CLOSE_HOUR', 24);        // last slot ends 12:00 midnight
define('DAYS_AHEAD', 14);        // how many days ahead people can book
define('MAX_HOURS', 4);          // max hours per booking
define('CANCEL_HOURS', 24);      // free cancellation notice (shown to customers)

date_default_timezone_set('Asia/Manila');
