@communication @communication_matrix @javascript
Feature: Display communication room status banner
  Show a banner depending on the room status
  As a teacher or admin

  Background: Make sure the mock server is initialized and a course is created
    Given a Matrix mock server is configured
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname    | shortname   | category | selectedcommunication | communicationroomname |
      | Test course | Test course | 0        | communication_matrix  | matrixroom            |
    And the following "course enrolments" exist:
      | user     | course      | role           |
      | teacher1 | Test course | editingteacher |
      | student1 | Test course | student        |

  Scenario: I can see the room has been created and in a pending status
    Given I am on the "Test course" "Course" page logged in as "teacher1"
    Then I should see "Your course chat room will be ready soon." in the "page-content" "region"
    And I am on the "Test course" "Course" page logged in as "student1"
    # Not for students to see.
    Then I should not see "Your course chat room will be ready soon." in the "page-content" "region"

  Scenario: I can see the room has been created and ready to access
    Given I run all adhoc tasks
    And I am on the "Test course" "Course" page logged in as "teacher1"
    Then I should see "Success! Your course chat room is ready!" in the "page-content" "region"
    # This is a one time message per user.
    And I reload the page
    Then I should not see "Success! Your course chat room is ready!" in the "page-content" "region"
    # Not for students to see.
    And I am on the "Test course" "Course" page logged in as "student1"
    Then I should not see "Success! Your course chat room is ready!" in the "page-content" "region"
