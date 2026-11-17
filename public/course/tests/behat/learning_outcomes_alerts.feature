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
      | Course 3 | C3        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher1 | C2     | editingteacher |
      | teacher1 | C3     | editingteacher |
      | teacher2 | C2     | editingteacher |
      | student1 | C1     | student        |
      | student1 | C2     | student        |
    And the following config values are set as admin:
      | enableoutcomes | 1 |
    And the following "grade outcomes" exist:
      | fullname  | shortname | course |
      | Outcome A | outa      | C1     |
      | Outcome B | outb      | C1     |

  Scenario: Teachers see the setup prompt telling them they can set up learning outcomes
    Given the following config values are set as admin:
      | recommendedlearningoutcomes | 0 |
    When I am on the "Course 2" "Course" page logged in as "teacher1"
    Then I should see "Did you know you can add learning outcomes to this course?"
    And I click on "Set up learning outcomes" "link"
    And I should see "No learning outcomes are associated with this course."

  Scenario: The setup prompt is not shown when the course already has a learning outcome
    When I am on the "Course 1" "Course" page logged in as "teacher1"
    Then I should not see "Did you know you can add learning outcomes to this course?"

  Scenario: The setup prompt is not shown when learning outcomes are disabled
    Given the following config values are set as admin:
      | enableoutcomes | 0 |
    When I am on the "Course 2" "Course" page logged in as "teacher1"
    Then I should not see "Did you know you can add learning outcomes to this course?"

  Scenario: Students do not see the setup prompt or the recommended minimum reminder
    When I am on the "Course 2" "Course" page logged in as "student1"
    Then I should not see "Did you know you can add learning outcomes to this course?"
    And I am on "Course 1" course homepage
    And I navigate to "Learning outcomes" in current page administration
    And I should see "Outcome A"
    And I should not see "Your organisation recommends"

  Scenario: Dismissing the setup prompt only dismisses it for that teacher in that course
    Given I am on the "Course 2" "Course" page logged in as "teacher1"
    When I click on "Dismiss this notification" "button"
    Then I should not see "Did you know you can add learning outcomes to this course?"
    And I reload the page
    And I should not see "Did you know you can add learning outcomes to this course?"
    # The recommended minimum reminder is still shown.
    And I navigate to "Learning outcomes" in current page administration
    And I should see "Your organisation recommends at least 3 learning outcomes for each course."
    # As is the setup prompt in other courses.
    And I am on "Course 3" course homepage
    And I should see "Did you know you can add learning outcomes to this course?"
    # And for other teachers.
    And I am on the "Course 2" "Course" page logged in as "teacher2"
    And I should see "Did you know you can add learning outcomes to this course?"

  Scenario: Teachers see the recommended minimum reminder until their course has that many learning outcomes
    Given I am on the "Course 1" "Course" page logged in as "teacher1"
    When I navigate to "Learning outcomes" in current page administration
    Then I should see "Your organisation recommends at least 3 learning outcomes for each course."
    And the following "grade outcomes" exist:
      | fullname  | shortname | course |
      | Outcome C | outc      | C1     |
    And I reload the page
    And I should see "Outcome C"
    And I should not see "Your organisation recommends"

  Scenario: Teachers see the recommended minimum reminder when their course has no learning outcomes
    Given I am on the "Course 2" "Course" page logged in as "teacher1"
    When I navigate to "Learning outcomes" in current page administration
    Then I should see "No learning outcomes are associated with this course."
    And I should see "Your organisation recommends at least 3 learning outcomes for each course."

  Scenario: The recommended minimum reminder uses the recommended number set by the admin
    Given the following config values are set as admin:
      | recommendedlearningoutcomes | 1 |
    And I am on the "Course 2" "Course" page logged in as "teacher1"
    When I navigate to "Learning outcomes" in current page administration
    Then I should see "Your organisation recommends at least 1 learning outcome for each course."
    And I am on "Course 1" course homepage
    And I navigate to "Learning outcomes" in current page administration
    And I should not see "Your organisation recommends"

  Scenario: The recommended minimum reminder is not shown when there is no recommendation
    Given the following config values are set as admin:
      | recommendedlearningoutcomes | 0 |
    And I am on the "Course 1" "Course" page logged in as "teacher1"
    When I navigate to "Learning outcomes" in current page administration
    Then I should see "Outcome A"
    And I should not see "Your organisation recommends"

  Scenario: The recommended minimum reminder cannot be dismissed
    Given I am on the "Course 1" "Course" page logged in as "teacher1"
    When I navigate to "Learning outcomes" in current page administration
    Then I should see "Your organisation recommends at least 3 learning outcomes for each course."
    And "Dismiss this notification" "button" should not exist

  Scenario: Admins can set the recommended number of learning outcomes once learning outcomes are enabled
    Given the following config values are set as admin:
      | enableoutcomes | 0 |
    And I log in as "admin"
    And I navigate to "Advanced features" in site administration
    And "Recommended learning outcomes per course" "field" should not be visible
    When I set the field "Enable learning outcomes" to "1"
    Then "Recommended learning outcomes per course" "field" should be visible
    And I set the field "Recommended learning outcomes per course" to "5"
    And I press "Save changes"
    And the field "Recommended learning outcomes per course" matches value "5"
    And I set the field "Recommended learning outcomes per course" to "No recommendation"
    And I press "Save changes"
    And the field "Recommended learning outcomes per course" matches value "No recommendation"
