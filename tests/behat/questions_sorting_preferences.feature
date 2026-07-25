@mod @mod_hotquestion
Feature: Users can sort HotQuestion headings per user without changing the default order
  In order to review questions in different ways
  As a user
  I need clickable sort headings that toggle ascending and descending

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
    And the following "activities" exist:
      | activity    | name                  | intro             | course | idnumber     | submitdirections           | questionlabel | teacherprioritylabel | heatlabel | teacherpriorityvisibility | heatvisibility | heatlimit |
      | hotquestion | Test hotquestion name | Hotquestion intro | C1     | hotquestion1 | Submit your question here: | Questions     | Priority             | Heat      | 1                         | 1              | 5         |

  Scenario: Default order remains time-based and heading sort toggles per user
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Alpha sort question |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Zulu sort question |
    And I press "Click to post"
    Then "Zulu sort question" "text" should appear before "Alpha sort question" "text"

    When I click on "Questions" "link"
    Then I should see "Questions (asc)"
    And "Alpha sort question" "text" should appear before "Zulu sort question" "text"

    When I click on "Questions (asc)" "link"
    Then I should see "Questions (desc)"
    And "Zulu sort question" "text" should appear before "Alpha sort question" "text"

    When I click on "Priority" "link"
    Then I should see "Priority (asc)"
    When I click on "Priority (asc)" "link"
    Then I should see "Priority (desc)"

    When I click on "Heat" "link"
    Then I should see "Heat (asc)"
    When I click on "Heat (asc)" "link"
    Then I should see "Heat (desc)"
    And I log out

    Given I log in as "student2"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    Then "Zulu sort question" "text" should appear before "Alpha sort question" "text"
