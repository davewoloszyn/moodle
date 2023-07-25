@communication @javascript
Feature: Access the communication configuration page
  As an editing teacher
  See dynamic form fields based on selected provider

  Background: Set up teachers and course for the communication confifiguration page
    Given I enable communication experimental feature
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
    And the following "courses" exist:
      | fullname    | shortname   | category | selectedcommunication |
      | Test course | Test course | 0        | none                  |
    And the following "course enrolments" exist:
      | user     | course      | role           |
      | teacher1 | Test course | editingteacher |
      | teacher2 | Test course | teacher        |

  Scenario: Only users with the correct capability can access the communication configuration page
    # Teacher2 is a non-editing teacher and will not have the capability to access this page.
    Given I am on the "Test course" "Course" page logged in as "teacher2"
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    Then "Communication" "link" should not exist in the ".dropdown-menu.dropdown-menu-left.show" "css_element"
    # Teacher1 is an editing teacher and will have the capability to access this page.
    When I am on the "Test course" "Course" page logged in as "teacher1"
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    Then "Communication" "link" should exist in the ".dropdown-menu.dropdown-menu-left.show" "css_element"
    And I follow "Communication"
    Then I should see "Communication"

  Scenario: I cannot see the communication link when communication provider is disabled
    Given I disable communication experimental feature
    When I am on the "Test course" "Course" page logged in as "teacher1"
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    Then "Communication" "link" should not exist in the ".dropdown-menu.dropdown-menu-left.show" "css_element"

  Scenario: The communication form fields toggle dynamically when valid provider is set
    When I am on the "Test course" "Course" page logged in as "teacher1"
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    And I follow "Communication"
    And I set the following fields to these values:
      | selectedcommunication | communication_matrix |
    Then I should see "Room name"
    And I should see "Room topic"
