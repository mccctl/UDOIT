<?php
/**
*	Copyright (C) 2014 University of Central Florida, created by Jacob Bates, Eric Colon, Fenel Joseph, and Emily Sachs.
*
*	This program is free software: you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation, either version 3 of the License, or
*	(at your option) any later version.
*
*	This program is distributed in the hope that it will be useful,
*	but WITHOUT ANY WARRANTY; without even the implied warranty of
*	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*	GNU General Public License for more details.
*
*	You should have received a copy of the GNU General Public License
*	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*	Primary Author Contact:  Jacob Bates <jacob.bates@ucf.edu>
*/
require_once('../config/settings.php');

session_start();

$get = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING); //jb: sanitize $_GET global

function printError($msg){
	echo '
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>UDOIT Accessibility Checker</title>
		<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" />
		<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap-theme.min.css" />
	</head>
	<body>
		<div style="padding: 12px;">
			<div class="alert alert-danger">
				<span class="glyphicon glyphicon-exclamation-sign"></span> '.$msg.'
			</div>
		</div>
	</body>
</html>
	';
	die();
}

$base_url = $_SESSION['base_url'];

if (isset($get['code'])) {
	//Exchange code for API key
	$url = $base_url . '/login/oauth2/token';

	$postdata = array(
		'grant_type' => 'authorization_code',
		'client_id' => $oauth2_id,
		'redirect_uri' => $oauth2_uri,
		'client_secret' => $oauth2_key,
		'code' => $get['code']
	);

	$post = http_build_query($postdata);
	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	$response = json_decode(curl_exec($ch));
	curl_close($ch);

	$_SESSION['api_key'] = $response->access_token;

	// TODO: Modify to save refresh token instead
	// Save API Key to DB
	$dbh = include('../lib/db.php');

	$sth = $dbh->prepare("SELECT * FROM $db_user_table WHERE id=:userid LIMIT 1");
	$sth->bindParam(':userid', $_SESSION['launch_params']['custom_canvas_user_id'], PDO::PARAM_INT);
	$sth->execute();

	$result = $sth->fetchAll();

	if(isset($result[0])) {
		$sth = $dbh->prepare("UPDATE $db_user_table SET api_key=:key WHERE id=:userid LIMIT 1");
	} else {
		$sth = $dbh->prepare("INSERT INTO $db_user_table (id, api_key, date_created) VALUES (:userid, :key, CURRENT_TIMESTAMP)");
	}

	$sth->bindParam(':key', $_SESSION['api_key'], PDO::PARAM_STR);
	$sth->bindParam(':userid', $_SESSION['launch_params']['custom_canvas_user_id'], PDO::PARAM_INT);
	$sth->execute();

	session_write_close();
	header('Location:index.php');
} elseif (isset($get['error'])) {
	printError('Authentication problem:  Access Denied.');
} else {
	printError('Authentication problem, please ensure that your instance of UDOIT is configured correctly.');
}
