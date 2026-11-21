<?php
/** admin/logout.php */
require __DIR__ . '/../includes/bootstrap.php';
auth_logout();
flash('success', 'You have been signed out.');
redirect('admin/login.php');
