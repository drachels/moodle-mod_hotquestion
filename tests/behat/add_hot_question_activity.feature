@mod @mod_hotquestion
Feature: Add HotQuestion activity
  In order to allow work effectively
  As a teacher
  I need to be able to create HotQuestion activities

  Scenario: Add a hotquestion activity and complete the activity as a student and check logs as a teacher
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Hot Question" to section "1" and I fill the form with:
      | Activity Name | Test Hot Question name |
      | Description | Test Hot Question Description |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test Hot Question name"
    And I set the following fields to these values:
      | Submit your question here: | First question |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Second question |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Logs" in current page administration
    And I press "Get these logs"
    Then I should see "Student 1" in the "#report_log_r2_c1" "css_element"
    And I should see "Added a question" in the "#report_log_r2_c5" "css_element"
    And I should see "Student 1" in the "#report_log_r5_c1" "css_element"
    And I should see "Added a question" in the "#report_log_r5_c5" "css_element"
    Then I log out
