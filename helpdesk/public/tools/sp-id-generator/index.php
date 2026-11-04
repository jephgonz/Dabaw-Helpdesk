<?php
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/tools/sp-id-generator/index.php');
$publicPath = dirname(dirname(dirname($scriptName)));
$dashboardPath = rtrim(str_replace('\\', '/', $publicPath), '/').'/admin/tools/id-generator';

header('Location: '.$dashboardPath, true, 302);
exit;