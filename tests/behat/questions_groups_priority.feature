@mod @mod_hotquestion
Feature: Group filtering and teacher priority controls
  In order to moderate entries by group and priority
  As a teacher
  I need group filtering and priority actions to work in Hot Question.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | ga       |
      | Group B | C1     | gb       |
    And the following "group members" exist:
      | user     | group   |
      | student1 | Group A |
      | student2 | Group B |
    And the following "activities" exist:
      | activity    | name                  | intro             | course | idnumber     | submitdirections           | teacherpriorityvisibility |
      | hotquestion | Test hotquestion name | Hotquestion intro | C1     | hotquestion1 | Submit your question here: | 1                         |

  Scenario: Teacher filters by group and adjusts priority
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Group A question |
    And I press "Click to post"
    And I log out

    Given I log in as "student2"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Group B question |
    And I press "Click to post"
    And I log out

    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I select "Group A" from the "Separate groups" singleselect
    Then I should see "Group A question"
    And I should not see "Group B question"

    When I select "Group B" from the "Separate groups" singleselect
    Then I should see "Group B question"
    And I should not see "Group A question"

    When I select "All participants" from the "Separate groups" singleselect
    And I click on "Priority" "link" in the "Group A question" "table_row"
    Then I should see "1" in the "Group A question" "table_row"
