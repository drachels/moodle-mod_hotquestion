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
    And I navigate to "Participants" in current page administration
    And I select "Permissions" from the "jump" singleselect
    And I override the system permissions of "Teacher" role with:
      | capability | permission |
      | moodle/calendar:manageentries | Prohibit |
    And I am on "Course 1" course homepage
    And I add a "Hot Question" to section "1" and I fill the form with:
      | Activity Name | Test hotquestion name |
      | Description | Test hotquestion description |
      | id_timeopen_enabled | 1 |
      | id_timeopen_day | 1 |
      | id_timeopen_month | 1 |
      | id_timeopen_year | 2017 |
      | id_timeclose_enabled | 1 |
      | id_timeclose_day | 1 |
      | id_timeclose_month | 2 |
      | id_timeclose_year | 2017 |
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I click on ".modtype_hotquestion a" "css_element"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | id_timeopen_year | 2018 |
      | id_timeclose_year | 2018 |
    And I press "Save and return to course"
    Then I should see "Test hotquestion name"
