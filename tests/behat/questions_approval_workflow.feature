@mod @mod_hotquestion
Feature: Approval workflow controls student visibility
  In order to moderate entries when approval is required
  As a teacher
  I need unapproved entries hidden and approvable.

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
      | activity    | name                  | intro             | course | idnumber     | submitdirections           | approval |
      | hotquestion | Test hotquestion name | Hotquestion intro | C1     | hotquestion1 | Submit your question here: | 1        |

  Scenario: Student post is hidden until teacher approval
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Approval workflow question |
    And I press "Click to post"
    Then I should see "This entry is not currently approved for viewing."
    And I log out

    Given I log in as "student2"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    Then I should not see "Approval workflow question"
    And I log out

    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I follow "Not approved"
    And I log out

    Given I log in as "student2"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    Then I should see "Approval workflow question"
