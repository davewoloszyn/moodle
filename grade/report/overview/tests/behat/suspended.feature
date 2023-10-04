@gradereport @gradereport_overview
Feature: Suspended students should not see grades in overview report
  While viewing the grade overview report
  As a student
  I only want to see courses I am active in

  Background:
    Given the following "courses" exist:
      | fullname    | shortname |
      | Active      | C1        |
      | Suspended   | C2        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    | status |
      | student1 | C1     | student | 0      |
      | student1 | C2     | student | 1      |

  Scenario: Students should not see their own suspended courses
    Given I am on the "Active" "grades > Overview report > View" page logged in as "student1"
    Then I should not see "Suspended" in the "overview-grade" "table"
    And I should see "Active" in the "overview-grade" "table"

  Scenario: Admins should see suspended courses
    Given I log in as "admin"
    And I navigate to "Users > Browse list of users" in site administration
    And I follow "Student 1"
    When I click on "Grades overview" "link"
    Then I should see "Suspended" in the "overview-grade" "table"
    And I should see "Active" in the "overview-grade" "table"
