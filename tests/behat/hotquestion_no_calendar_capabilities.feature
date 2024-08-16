@mod @mod_hotquestion @javascript
Feature: HotQuestion with no calendar capabilites
  In order to allow work effectively
  As a teacher
  I need to be able to create HotQuestion activities even when I cannot edit calendar events

  Scenario: Editing a HotQuestion with no calendar capabilites
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Course 1" "permissions" page
    And I override the system permissions of "Teacher" role with:
      | capability | permission |
      | moodle/calendar:manageentries | Prohibit |
    And I add a hotquestion activity to course "Course 1" section "1"
    And I set the following fields to these values:
      | Activity Name | Test hotquestion name |
      | Description | Test hotquestion description |
      | id_timeopen_enabled | 1 |
      | id_timeopen_day | 1 |
      | id_timeopen_month | 1 |
      | id_timeopen_year | 2034 |
      | id_timeclose_enabled | 1 |
      | id_timeclose_day | 1 |
      | id_timeclose_month | 2 |
      | id_timeclose_year | 2034 |
    And I press "Save and return to course"
    And I log out
    When I am on the "Test hotquestion name" "mod_hotquestion > Edit" page logged in as "teacher1"
    And I set the following fields to these values:
      | id_timeopen_year | 2018 |
      | id_timeclose_year | 2018 |
    And I press "Save and return to course"
    Then I should see "Test hotquestion name"
