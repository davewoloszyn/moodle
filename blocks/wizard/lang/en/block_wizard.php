<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Strings for the Wizard block.
 *
 * @package    block_wizard
 * @copyright  2023 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['moreitems'] = 'Show more items';
$string['noitems'] = 'No recent items';
$string['pluginname'] = 'Wizard';
$string['privacy:metadata:cmid'] = 'The ID of the activity or resource';
$string['privacy:metadata:courseid'] = 'Course the item belongs to';
$string['privacy:metadata:block_wizardtablesummary'] = 'The Wizard block stores information about items that the user accessed recently';
$string['privacy:metadata:timeaccess'] = 'The time when the user last accessed the item';
$string['privacy:metadata:userid'] = 'The ID of the user who accessed the item';
$string['privacy:wizardpath'] = 'Wizard';
$string['wizard:myaddinstance'] = 'Add a new Wizard block to Dashboard';

// Wizard 1: Setting up Google oAuth 2
$string['w1_title'] = 'Google oAuth 2';
$string['w1_description'] = 'Get help setting up Google oAuth 2.';

$string['w1_s1_ss1'] = 'Enable <strong>OAuth 2</strong> by clicking the <strong>eye</strong> icon.';
$string['w1_s2_ss1'] = 'Create a new Google service my clicking on the <strong>Google</strong> button.';

$string['w1_s2_ss2'] = 'Fill in the form with the detail obtained when setting up your Google App <a href="https://docs.moodle.org/402/en/OAuth_2_Google_service" target="_blank">(more info)</a>.';
$string['w1_s2_ss3'] = 'Click <strong>Save changes</strong>.';

// Wizard 2: Setting up MNet
$string['w2_title'] = 'MNet';
$string['w2_description'] = 'Connect your Moodle sites using MNet.';

$string['w2_s1_ss1'] = 'To setup MNet you will need two Moodle installations. We will refer to them as <strong>Moodle A (here)</strong> and <strong>Moodle B</strong>.';
$string['w2_s1_ss2'] = 'Browse this list and ensure you have <strong>curl</strong> installed.';
$string['w2_s1_ss3'] = 'Do the same on <strong>Moodle B</strong>.';

$string['w2_s2_ss1'] = 'Turn on <strong>Networking</strong>.';
$string['w2_s2_ss2'] = 'Click <strong>Save changes</strong>.';
$string['w2_s2_ss3'] = 'Do the same on <strong>Moodle B</strong>.';

$string['w2_s3_ss1'] = 'If both sites are hosted in the same domain, ensure they have a different <strong>Cookie prefix</strong>.';
$string['w2_s3_ss2'] = 'Click <strong>Save changes</strong> (this will log you out, but don\'t worry, I\'ll remember your progress).';
$string['w2_s3_ss3'] = 'If you need to, set a unique <strong>Cookie prefix</strong> on <strong>Moodle B</strong>.';

$string['w2_s4_ss1'] = 'Enter in <strong>Moodle B\'s</strong> url in the <strong>Hostname</strong> field. It should look something like this <em>https://www.mymoodle.org/moodle</em>';
$string['w2_s4_ss2'] = 'If it\'s not already, change <strong>Application type</strong> to <em>moodle</em>.';
$string['w2_s4_ss3'] = 'Click <strong>Add host</strong>.';
$string['w2_s4_ss4'] = 'You should now see the host settings page for <strong>Moodle B</strong>. Click <strong>Save changes</strong>.';
$string['w2_s4_ss5'] = 'Do the equivalent on <strong>Moodle B</strong>.';

$string['w2_s5_ss1'] = 'Enable <strong>MNet authentication</strong> by clicking the <strong>eye</strong> icon.';
$string['w2_s5_ss2'] = 'Do the same on <strong>Moodle B</strong>.';

$string['w2_s6_ss1'] = 'Choose <strong>Moodle B</strong> from the list of sites.';
$string['w2_s6_ss2'] = 'Click on the <strong>Services</strong> tab.';
$string['w2_s6_ss3'] = 'Locate <strong>SSO (Identity Provider)</strong> and check the boxes for <strong>Publish</strong> and <strong>Subscribe</strong>.';
$string['w2_s6_ss4'] = 'Locate <strong>SSO (Service Provider)</strong> and check the boxes for <strong>Publish</strong> and <strong>Subscribe</strong>.';
$string['w2_s6_ss5'] = 'Do the same on <strong>Moodle B</strong>.';

$string['w2_s7_ss1'] = 'Decide which role you want to allow MNet to use.';
$string['w2_s7_ss2'] = 'Click on the <strong>cog</strong> icon to edit the role.';
$string['w2_s7_ss3'] = 'Locate the capability called <strong>Roam to a remote application via MNet</strong> and check the box <strong>Allow</strong>.';
$string['w2_s7_ss4'] = 'Scroll down and click <strong>Save changes</strong>.';
$string['w2_s7_ss5'] = 'Do the same on <strong>Moodle B</strong>.';

$string['w2_s8_ss1'] = 'Turn on <strong>Edit mode</strong>.';
$string['w2_s8_ss2'] = 'From the right-hand-side block drawer, click <strong>Add a block</strong>.';
$string['w2_s8_ss3'] = 'Choose <strong>Network servers</strong>.';
$string['w2_s8_ss5'] = 'Do the same on <strong>Moodle B</strong>.';

$string['w2_s9_ss1'] = 'You can now access <strong>Moodle B</strong> from the newly added block, just log in with a user who has the permissions added to the role from <strong>Step 7</strong>.';

// Wizard 3: Enrol users into a course.
$string['w3_title'] = 'Enrol users into a course';
$string['w3_description'] = 'Create a course and add teachers and students.';

$string['w3_s1_ss1'] = 'Let\'s add some users first. Click on <strong>Add a new user</strong>.';
$string['w3_s1_ss2'] = 'Fill in the required information for the new user.';
$string['w3_s1_ss3'] = 'Click <strong>Create user</strong>.';
$string['w3_s1_ss4'] = 'Repeat this for ever user you want to add to the course. We will assign the <em>Student</em> and <em>Teacher</em> roles later.';

$string['w3_s2_ss1'] = 'Click on <strong>Add a new course</strong> from the <strong>Courses</strong> section.';
$string['w3_s2_ss2'] = 'Fill in the course creation form details.';
$string['w3_s2_ss3'] = 'Click <strong>Save and display</strong>.';

$string['w3_s3_ss1'] = 'From the secondary navigation, click on <strong>Participants</strong>.';
$string['w3_s3_ss2'] = 'Click on <strong>Enrol users</strong>.';
$string['w3_s3_ss3'] = 'In the <strong>Search</strong> window, type in a user\'s username or email.';
$string['w3_s3_ss4'] = 'Select the user from the results.';
$string['w3_s3_ss5'] = 'Assign a role to the user using the <strong>Assign role</strong> dropdown.';
$string['w3_s3_ss6'] = 'Repeat this for all users.';
$string['w3_s3_ss7'] = 'When you are finished, click <strong>Enrol users</strong>.';