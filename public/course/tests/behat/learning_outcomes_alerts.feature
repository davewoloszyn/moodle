@core_course @javascript
Feature: Learning outcomes setup prompt and recommended minimum reminder
  In order to know that I can set up learning outcomes in my course
  As a teacher
  I need to be told about learning outcomes and how many my organisation recommends

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | teacher1 | C2     | editingteacher |
      | teacher2 | C2     | editingteacher |
    And the following config values are set as admin:
      | enableoutcomes | 1 |
    And the following "grade outcomes" exist:
      | fullname  | shortname | course |
      | Outcome A | outa      | C1     |
      | Outcome B | outb      | C1     |

  Scenario: Setup prompt displays until at least one outcome is added
    When I am on the "Course 2" "Course" page logged in as "teacher1"
    Then I should see "Did you know you can add learning outcomes to this course?"
    And the following "grade outcomes" exist:
      | fullname  | shortname | course |
      | Outcome Z | outc      | C2     |
    And I reload the page
    And I should not see "Did you know you can add learning outcomes to this course?"

  Scenario: Dismissing the setup prompt keeps it dismissed after a reload
    When I am on the "Course 2" "Course" page logged in as "teacher1"
    Then I should see "Did you know you can add learning outcomes to this course?"
    And I click on "Dismiss this notification" "button"
    And I reload the page
    And I should not see "Did you know you can add learning outcomes to this course?"
    # Setup prompt dismissal is user preferenced.
    And I am on the "Course 2" "Course" page logged in as "teacher2"
    And I should see "Did you know you can add learning outcomes to this course?"

  Scenario: Reminder shows until the recommended minimum is met
    Given I am on the "Course 1" "Course" page logged in as "teacher1"
    When I navigate to "Learning outcomes" in current page administration
    Then I should see "Your organisation recommends at least 3 learning outcomes for each course."
    And "Dismiss this notification" "button" should not exist
    # Add the third outcome to meet the minimum.
    And the following "grade outcomes" exist:
      | fullname  | shortname | course |
      | Outcome C | outc      | C1     |
    And I reload the page
    And I should see "Outcome C"
    And I should not see "Your organisation recommends at least 3 learning outcomes for each course."

  Scenario: Students do not see the setup prompt or the recommended minimum reminder
    When I am on the "Course 1" "Course" page logged in as "student1"
    Then I should not see "Did you know you can add learning outcomes to this course?"
    And I navigate to "Learning outcomes" in current page administration
    And I should not see "Your organisation recommends at least 3 learning outcomes for each course."

  Scenario: Recommended number admin setting is hidden until learning outcomes are enabled
    Given the following config values are set as admin:
      | enableoutcomes | 0 |
    And I log in as "admin"
    When I navigate to "Advanced features" in site administration
    Then "Recommended learning outcomes per course" "field" should not be visible
    And I set the field "Enable learning outcomes" to "1"
    And "Recommended learning outcomes per course" "field" should be visible
    And the field "Recommended learning outcomes per course" matches value "3"
