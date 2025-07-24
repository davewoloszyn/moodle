@mod @mod_data @javascript @editor_tiny
Feature: Edit existing entries as a teacher and modify these.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activities" exist:
      | activity | name               | intro | course | idnumber |
      | data     | Test database name | n     | C1     | data1    |
    And the following "mod_data > fields" exist:
      | database | type        | name        | required | description  |
      | data1    | text        | headline    | 1        | Headline     |
      | data1    | textarea    | description | 0        | Description  |
    And the following "mod_data > entries" exist:
      | database | user     | headline   | description        |
      | data1    | teacher1 | Headline 1 | Some text is here. |
      | data1    | teacher1 | Headline 2 |                    |

  Scenario: Text areas are filled correctly when editing datasets.
    Given I am on the "Test database name" "data activity" page logged in as <teacher1>
    And I select "Single view" from the "jump" singleselect
    And I should see "Some text is here." in the "region-main" "region"
    And I click on ".defaulttemplate-single-body .action-menu" "css_element"
    And I click on "Edit" "link" in the ".defaulttemplate-single-body .dropdown-menu" "css_element"
    And I wait until the page is ready
    And I set the field "description" to "<p>Some plain text</p><p>Some more text</p>"
    And I click on "Save" "button" in the "sticky-footer" "region"
    Then I should not see "Some text is here." in the "region-main" "region"
    And I should see "Some plain text" in the "region-main" "region"
    And I should see "Some more text" in the "region-main" "region"
    And I select "Single view" from the "jump" singleselect
    And I click on "2" "link" in the "sticky-footer" "region"
    And I click on ".defaulttemplate-single-body .action-menu" "css_element"
    And I click on "Edit" "link" in the ".defaulttemplate-single-body .dropdown-menu" "css_element"
    And I wait until the page is ready
    And I set the field "headline" to "Headline B"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I select "Single view" from the "jump" singleselect
    And I click on "2" "link" in the "sticky-footer" "region"
    And I should see "Headline B" in the "region-main" "region"
    And I should not see "Some plain text" in the "region-main" "region"
    And I should not see "Some more text" in the "region-main" "region"

  Scenario: Text areas are filled correctly when triggering autosave without making changes.
    Given I am on the "Test database name" "data activity" page logged in as <teacher1>
    And I select "Single view" from the "jump" singleselect
    And I should see "Some text is here." in the "region-main" "region"
    And I click on ".defaulttemplate-single-body .action-menu" "css_element"
    And I click on "Edit" "link" in the ".defaulttemplate-single-body .dropdown-menu" "css_element"
    And I wait until the page is ready
    And I switch to "tox-edit-area__iframe" class iframe
    And I press enter
    And I wait "1" seconds
    And I switch to the main frame
    And I click on "Cancel" "button" in the "sticky-footer" "region"
    And I select "Single view" from the "jump" singleselect
    And I click on "2" "link" in the "sticky-footer" "region"
    And I click on ".defaulttemplate-single-body .action-menu" "css_element"
    And I click on "Edit" "link" in the ".defaulttemplate-single-body .dropdown-menu" "css_element"
    And I wait until the page is ready
    And I set the field "headline" to "Headline new B"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I select "Single view" from the "jump" singleselect
    And I click on "2" "link" in the "sticky-footer" "region"
    Then I should see "Headline new B" in the "region-main" "region"
    And I should not see "Some plain text" in the "region-main" "region"
    And I should not see "Some text is here" in the "region-main" "region"
