<?php

/*
   Database Configuration
   This file redirects to the correct database configuration
*/

// Use local database configuration (for XAMPP/WAMP)
require_once __DIR__ . '/database_local.php';

// To use InfinityFree instead, comment the line above and uncomment the line below:
// require_once __DIR__ . '/database_infinityfree.php';
