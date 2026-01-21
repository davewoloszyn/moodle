@mod @mod_assign @assignfeedback @assignfeedback_comments
Feature: In an assignment, teachers can provide feedback comments on student submissions
  In order to provide feedback to students on their assignments
  As a teacher,
  I need to create feedback comments against their submissions.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | teacher2 | Teacher | 2 | teacher2@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | teacher |
      | student1 | C1 | student |

  @javascript @skip_chrome_zerosize
  Scenario: Teachers should be able to add and remove feedback comments via the quick grading interface
    Given the following "activities" exist:
      | activity  | course  | name                  | assignsubmission_onlinetext_enabled  | assignfeedback_comments_enabled  |
      | assign    | C1      | Test assignment name  | 1                                    | 1                                |
    And the following "mod_assign > submissions" exist:
      | assign                | user      | onlinetext                   |
      | Test assignment name  | student1  | I'm the student1 submission  |
    And I am on the "Test assignment name" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    Then I click on "Quick grading" "checkbox"
    And I set the field "Feedback comments" to "Feedback from teacher."
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I should see "The grade changes were saved"
    And I press "Continue"
    And I should see "Feedback from teacher."
    And I set the field "Feedback comments" to ""
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I should see "The grade changes were saved"
    And I press "Continue"
    And I should not see "Feedback from teacher."

  @javascript
  Scenario: Teachers should be able to add and remove feedback comments as allocated markers, via the quick grading interface
    Given the following "activity" exists:
      | activity                            | assign        |
      | course                              | C1            |
      | idnumber                            | A1            |
      | name                                | Assignment 1  |
      | section                             | 1             |
      | completion                          | 1             |
      | markingworkflow                     | 1             |
      | markingallocation                   | 1             |
      | markercount                         | 2             |
      | assignfeedback_comments_enabled     | 1             |
    And the following "mod_assign > marker_allocations" exist:
      | assign       | user          | marker      |
      | Assignment 1 | student1      | teacher1    |
      | Assignment 1 | student1      | teacher2    |
    When I am on the "A1" "assign activity" page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Quick grading" "checkbox"
    And I set the field "Marker 1 comment" to "Feedback from marker one."
    And I click on "Save" "button" in the "sticky-footer" "region"
    Then I should see "The grade changes were saved"
    And I press "Continue"
    And I should see "Feedback from marker one."
    And I set the field "Marker 1 comment" to ""
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I should see "The grade changes were saved"
    And I press "Continue"
    And I should not see "Feedback from marker one."
