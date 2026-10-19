<?php
declare(strict_types=1);

return [
    ['GET', '/', 'AuthController@login'],
    ['GET', '/login', 'AuthController@login'],
    ['POST', '/login', 'AuthController@login'],
    ['POST', '/logout', 'AuthController@logout'],
    ['GET', '/admin', 'AdminController@index'],
    ['GET', '/admin/users', 'AdminController@users'],
    ['GET', '/admin/users/create', 'AdminController@createUserForm'],
    ['POST', '/admin/users', 'AdminController@createUser'],
    ['GET', '/admin/database', 'DatabaseManagerController@index'],
    ['GET', '/admin/database/metrics', 'DatabaseManagerController@metrics'],
    ['GET', '/admin/database/table', 'DatabaseManagerController@table'],
    ['GET', '/admin/database/create', 'DatabaseManagerController@createForm'],
    ['GET', '/admin/database/row', 'DatabaseManagerController@editForm'],
    ['POST', '/admin/database/save', 'DatabaseManagerController@save'],
    ['POST', '/admin/database/delete', 'DatabaseManagerController@delete'],
    ['GET', '/admin/tools/id-generator', 'AdminController@idGenerator'],
    ['GET', '/dashboard', 'AdminController@staffDashboard'],
];
