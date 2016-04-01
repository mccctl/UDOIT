<?php

if (getenv('USE_HEROKU_CONFIG'))
{
    require_once('herokuConfig.php');
    if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
      $_SERVER['HTTPS'] = 'on';
    }
}
else
{
    require_once('localConfig.php');
}

require_once(__DIR__.'/../vendor/autoload.php');
require_once('tests.php');

/* Prevent Caching */
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

/* Messages */
$udoit_welcome_message = 'The Universal Design Online content Inspection Tool (U<strong>DO</strong>IT) was created by the Center for Distributed Learning at the University of Central Florida. U<strong>DO</strong>IT will scan your course content, generate a report and provide instructions on how to correct accessibility issues. Funding for U<strong>DO</strong>IT was provided by a Canvas Grant awarded in 2014.';

/* Resource links */
$resource_link = [
    'doc' => 'http://webaim.org/techniques/word/',
    'pdf' => 'http://webaim.org/techniques/acrobat/',
    'ppt' => 'http://webaim.org/techniques/powerpoint/',
];
