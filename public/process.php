<?php
/**
*   Copyright (C) 2014 University of Central Florida, created by Jacob Bates, Eric Colon, Fenel Joseph, and Emily Sachs.
*
*   This program is free software: you can redistribute it and/or modify
*   it under the terms of the GNU General Public License as published by
*   the Free Software Foundation, either version 3 of the License, or
*   (at your option) any later version.
*
*   This program is distributed in the hope that it will be useful,
*   but WITHOUT ANY WARRANTY; without even the implied warranty of
*   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*   GNU General Public License for more details.
*
*   You should have received a copy of the GNU General Public License
*   along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*   Primary Author Contact:  Jacob Bates <jacob.bates@ucf.edu>
*/
require_once('../config/settings.php');
require_once('../lib/quail/quail/quail.php');
require_once('../lib/Udoit.php');
require_once('../lib/Ufixit.php');

session_start();
//General sanitization of $_POST parameters
$post_input  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

use Httpful\Request;
$base_url = $_SESSION['base_url'];
$SESSION_course_id = filter_input(INPUT_POST,'course_id',FILTER_SANITIZE_NUMBER_INT); //Sanitize specifically for int.
$SESSION_context_label = $post_input['context_label'];
$SESSION_context_title = $post_input['context_title'];
session_write_close();


function test_api_key($user_id, $base_url, $api_key){
    $url = $base_url.'api/v1/users/'.$user_id.'/profile';
    $resp = Request::get($url)
        ->addHeader('Authorization', 'Bearer '.$api_key)
        ->send();
    return isset($resp->body->id);
}

function refresh_key($o2_id, $o2_uri, $o2_key, $base_url, $refresh_token){
    $url = $base_url . '/login/oauth2/token';

    $postdata = array(
        'grant_type' => 'refresh_token',
        'client_id' => $o2_id,
        'redirect_uri' => $o2_uri,
        'client_secret' => $o2_key,
        'refresh_token' => $refresh_token
    );

    $post = http_build_query($postdata);
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = json_decode(curl_exec($ch));
    curl_close($ch);

    // Check response to make sure the refresh token is still valid.  If not valid, go through Oauth2
    if ( isset($response->access_token) ){
        return $response->access_token;
    } else {
        return false;
    }
}

session_start();

// If the API key is not valid, refresh it
if( !test_api_key($_SESSION['launch_params']['custom_canvas_user_id'], $base_url, $_SESSION['api_key']) ){
    $_SESSION['api_key'] = refresh_key($oauth2_id, $oauth2_uri, $oauth2_key, $base_url, $_SESSION['refresh_token']);
    if( $_SESSION['api_key'] === false ){
        echo "Error refreshing authorization.  Please re-load UDOIT and try again.";
        die();
    }
}

session_write_close();

// check if course content is being scanned or fixed
switch ($post_input['main_action']) {
    case 'udoit':
        // for saving this report later
        session_start();
        $user_id = $_SESSION['launch_params']['custom_canvas_user_id'];
        session_write_close();

        // UDOIT can't scan what isn't selected
        if ($post_input['content'] === 'none') {
            die('<div class="alert alert-danger no-margin margin-top"><span class="glyphicon glyphicon-exclamation-sign"></span> Please select which course content you wish to scan above.</div>');
        }

        session_start();

        $data = [
            'api_key'       => $_SESSION['api_key'],
            'base_uri'      => $base_url,
            'content_types' => $post_input['content'],
            'course_id'     => $SESSION_course_id
        ];

        session_write_close();

        $udoit = new Udoit($data);
        $udoit->buildReport();

        $to_encode = [
            'course'        => $SESSION_context_title,
            'total_results' => $udoit->total_results,
            'content'       => $udoit->bad_content,
        ];
        $encoded_report   = json_encode($to_encode);
        $report_directory = '../reports/'.$user_id.'/'.$to_encode['course'];

        if (!file_exists($report_directory)) {

            mkdir($report_directory, 0755, true); //changed from 777 to 755 to promote security
            chmod('../reports/'.$user_id, 0755); //changed from 777 to 755 to promote security
            chmod($report_directory, 0755); //changed from 777 to 755 to promote security
        }

        $file = $report_directory.'/'.date('Y_m_d__g:i:s_a').'.json';

        file_put_contents($file, $encoded_report);
        chmod($file, 0644); //changed from 777 to 644 to promote security

        $dbh = include('../lib/db.php');

        $sth = $dbh->prepare("
            INSERT INTO
                $db_reports_table
                (user_id, course_id, file_path, date_run, errors, suggestions)
            VALUES
                (:userid, :courseid, :filepath, CURRENT_TIMESTAMP, :errors, :suggestions)");
        $sth->bindParam(':userid', $user_id, PDO::PARAM_INT);
        $sth->bindParam(':courseid', $data['course_id'], PDO::PARAM_INT);
        $sth->bindParam(':filepath', $file, PDO::PARAM_STR);
        $sth->bindParam(':errors', $udoit->total_results['errors'], PDO::PARAM_STR);
        $sth->bindParam(':suggestions', $udoit->total_results['suggestions'], PDO::PARAM_STR);

        if (!$sth->execute()) {
            error_log(print_r($sth->errorInfo(), true));
            die('Error inserting report into database');
        }

        $udoit_report = json_decode($encoded_report);

        require 'parseResults.php';

        break;
    case 'ufixit':
        $data = [
            'base_uri'     => $base_url,
            'content_id'   => $post_input['contentid'],
            'content_type' => $post_input['contenttype'],
            'error_html'   => htmlspecialchars_decode(filter_input(INPUT_POST,'errorhtml')),
            'error_colors' => isset($post_input['errorcolor']) ? $post_input['errorcolor'] : '',
            'error_type'   => $post_input['errortype'],
            'new_content'  => $post_input['newcontent'],
            'bold'         => isset($post_input['add-bold']) ? $post_input['add-bold'] : '',
            'italic'       => isset($post_input['add-italic']) ? $post_input['add-italic'] : ''
        ];

        session_start();

        $data['course_id'] = $SESSION_course_id;

        $dom = new DOMDocument();
        $data['api_key']   = $_SESSION['api_key'];

        session_write_close();

        $ufixit = new Ufixit($data);

        if ($data['content_type'] === 'files') {
            $ufixit->curled_file = $ufixit->getFile("root");

            if ($ufixit->curled_file == false) {
                header('HTTP/1.1 404 File Not Found');
                header('Content-Type: application/json; charset=UTF-8');
                die(json_encode(['message' => '404 Error: File not found']));
            }
        }

        $submitting_again = false;

        // fixes content based on what the error is
        switch ($data['error_type']) {
            case 'aMustContainText':
            case 'aSuspiciousLinkText':
            case 'aLinkTextDoesNotBeginWithRedundantWord':

                $corrected_error = $ufixit->fixLink($data['error_html'], $data['new_content'], $submitting_again);
                break;
            case 'cssTextHasContrast':
                $corrected_error = $ufixit->fixCssColor($data['error_colors'], $data['error_html'], $data['new_content'], ($data['bold'] == 'bold')? true: false, ($data['italic'] == 'italic')? true: false, $submitting_again);
                break;
            case 'cssTextStyleEmphasize':
                $corrected_error = $ufixit->fixCssEmphasize($data['error_html'], $data['new_content'], ($data['bold'] == 'bold')? true: false, ($data['italic'] == 'italic')? true: false, $submitting_again);
                break;
            case 'headersHaveText':
                $corrected_error = $ufixit->fixHeading($data['error_html'], $data['new_content'], $submitting_again);
                break;
            case 'imgHasAlt':
            case 'imgNonDecorativeHasAlt':
            case 'imgAltIsDifferent':
                //$data['error_html'] = str_replace('alt=""', 'alt', $data['error_html']);
                $corrected_error = $ufixit->fixAltText($data['error_html'], $data['new_content'], $submitting_again);

                $remove_attr = preg_replace("/ data-api-endpoint.*$/s", "", $data['error_html']);
                $data['error_html'] = $remove_attr;
                break;
            case 'tableDataShouldHaveTh':
                // fixing table headers is a special case...
                $corrected_error    = $ufixit->fixTableHeaders($data['error_html'], $data['new_content'], $submitting_again);
                $data['error_html'] = $corrected_error['old'];
                $corrected_error    = $corrected_error['fixed'];
                break;
            case 'tableThShouldHaveScope':
                $corrected_error = $ufixit->fixTableThScopes($data['error_html'], $data['new_content'], $submitting_again);
                break;
        }

        // uploads the fixed content
        switch ($data['content_type']) {
            case 'announcements':
            case 'discussions':
                $ufixit->uploadFixedDiscussions($corrected_error, $data['error_html']);
                break;
            case 'assignments':
                $ufixit->uploadFixedAssignments($corrected_error, $data['error_html']);
                break;
            case 'files':
                $ufixit->uploadFixedFiles($corrected_error, $data['error_html']);
                break;
            case 'pages':
                $ufixit->uploadFixedPages($corrected_error, $data['error_html']);
                break;
            case 'syllabus':
                $ufixit->uploadFixedSyllabus($corrected_error, $data['error_html']);
                break;
        }

        break;
}