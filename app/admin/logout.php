<?php
/** admin/logout */
require __DIR__ . '/../includes/bootstrap.php';
auth_logout();
flash('success', 'You have been signed out.');
redirect('admin/login');
