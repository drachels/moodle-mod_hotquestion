@mod @mod_hotquestion
Feature: Remove links include confirmation before deleting entries
  In order to avoid accidental entry removal
  As a teacher, manager, or admin
  I need remove links to include a confirmation prompt.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity    | name                  | intro             | course | idnumber     | submitdirections           |
      | hotquestion | Test hotquestion name | Hotquestion intro | C1     | hotquestion1 | Submit your question here: |

  Scenario: Teacher sees remove confirmation wiring and can remove an entry
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Question pending remove confirmation |
    And I press "Click to post"
    And I log out

    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    Then the page source should contain "Confirm you want to delete entry"
    And I click on "Remove" "link" in the "Question pending remove confirmation" "table_row"
    And I should not see "Question pending remove confirmation"
