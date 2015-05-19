# UDOIT Developer Guide

Installing and developing on UDOIT is actually quite easy, below is the documentation to help you get started!

## License
Copyright (C) 2014 University of Central Florida, created by Jacob Bates, Eric Colon, Fenel Joseph, and Emily Sachs.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

Primary Author Contact:  Jacob Bates <jacob.bates@ucf.edu>

Please see `UDOIT_Release.pdf` (distributed with the source code) for more information about licensing.

## Installing
UDOIT uses [Composer](https://getcomposer.org/) to manage its dependencies, so `cd` into your UDOIT directory and run this command before anything else:

```
$ php composer.phar update
```

If you are adding UDOIT on a new machine and don't want to register a new oauth2_key for development, you can grant access to a new user by manually adding them to the user table within the database.

## Database Configuration
There are only two tables required to run UDOIT.  They are:

reports
```
CREATE TABLE `reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `course_id` int(10) unsigned NOT NULL,
  `file_path` text NOT NULL,
  `date_run` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `errors` int(10) unsigned NOT NULL,
  `suggestions` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=442 DEFAULT CHARSET=latin1;
```

users
```
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `date_created` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

## Configuration
Make a copy of `config/localConfig.template.php`, rename it to `localConfig.php`.

### Canvas API
Please refer to the [Canvas API Policy](http://www.canvaslms.com/policies/api-policy) before using this application, as it makes heavy use of the Canvas API.

`$base_url`
This is the URL of your Canvas installation

`$consume_key` Your consumer developer key from Canvas
`$shared_secret` Your shared secret key from Canvas

### Database Config
These value of these vars should be obvious:

`$db_host`
`$db_user`
`$db_password`
`$db_name`
`$db_user_table`
`$db_reports_table`

## Dependencies
UDOIT relies on 3 libraries installed through Composer to function:

[Httpful](http://phphttpclient.com/)

[HTML Minifier](https://github.com/zaininnari/html-minifier)

[mPDF](https://github.com/finwe/mpdf)

Please refer to the documentation for these three libraries for additional information.

## The Udoit class
File: *lib/Udoit.php*

This is the class which will scan a Canvas course's content and a return a UDOIT report if any problems are found.

### Properties
*See class definition*

### Methods

#### buildReport()
Iterates through selected content types to build the final report object.

#### generateReport()
This calls the Quail library to scan HTML and check it for accessibility problems.

#### getCourseContent()
Makes API calls to Canvas and retrieves HTML and other data from selected content types.

#### parseLinks()
Increments the current page number for either Files or Pages in the event that their results are paginated.

## The Ufixit class
File: *lib/Ufixit.php*

This is the class which will fix problem content and upload it to a Canvas course.

### Properties
*See class definition*

### Methods

#### fixAltText()
Adds the alt text value to the image that's missing it.

#### fixCss()
Replaces the old color/background value with the one chosen by the user.

#### fixTableHeaders()
Converts the first row, first column, or both into `th` elements - It gives them the proper `scope` value as well.

#### fixTableThScopes()
Adds the `col` or `row` attribute to any `th` elements without them.

#### getFile()
Gets a file from a Canvas course based on the `$start_id` parameter.

#### renameElement()
Renames an element to whatever name is specified in the `$name` parameter.

#### uploadFixedAssignments()
Fixes the HTML within assignments and uploads it back to the Canvas course.

#### uploadFixedDiscussions()
Fixes the HTML within discussions and announcements - they are of the same content type - and uploads it back to the Canvas course.

#### uploadFixedFiles()
Creates a temporary file, fixes the HTML within it, then uploads it back to the Canvas course.

#### uploadFixedPages()
Fixes the HTML within pages and uploads it back to the Canvas course.

#### uploadFixedSyllabus()
Fixes the HTML within the syllabus and uploads it back to the Canvas course.

## Lib files
All of these files are located within the */lib* directory.

### cached.php
This file is called when clicking the "View Old Reports" tab. It connects to the database using [PDO](http://php.net/manual/en/class.pdo.php), selects reports matching the course id, and outputs a table with the data.

### parseResults.php
This file decodes a a UDOIT report either from a JSON file or a JSON string (if not viewing a cached report). Then, the report HTML is echoed to be displayed to the user.

### parsePdf.php
This creates a PDF from the HTML of a UDOIT scan and allows the user to download it.

### process.php
The file is where all the magic happens, so to speak.

### progress.php
Increments a progress key in the global `$_SESSION` variable that some AJAX in default.js talks to in order to track the progress of a UDOIT scan.

## Contributors
* Jacob Bates
* Eric Colon
* Fenel Joseph
* Emily Sachs
* Karen Tinsley-Kim
* Joe Fauvel
* John Raible
* Kathleen Bastedo
* Nancy Swenson