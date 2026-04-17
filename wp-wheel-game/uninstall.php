<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;
require_once __DIR__ . '/includes/class-activator.php';
Wheel_Game_Activator::uninstall();
