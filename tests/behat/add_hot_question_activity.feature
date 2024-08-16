@mod @mod_hotquestion @javascript
Feature: Add HotQuestion activity
  In order to allow work effectively
  As a teacher
  I need to be able to create HotQuestion activities

  Scenario: Add a hotquestion activity and complete the activity as a student
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
    And I add a hotquestion activity to course "Course 1" section "1"
    And I set the following fields to these values:
      | Activity Name      | Test Hot Question name        |
      | Description        | Test Hot Question Description |
      | cmidnumber         | TestHotQuestion               |
      | viewaftertimeclose | 0                             |
    And I press "Save and return to course"
    And I log out
    And I am on the "Test Hot Question name" "mod_hotquestion > View" page logged in as "student1"
    And I set the following fields to these values:
      | Submit your question here: | First question |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Second question |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    And I log out
    # Teacher 1 verifies the posts of Student 1 are logged.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I set the field "menumodid" to "Test Hot Question name"
    And I press "Get these logs"
    Then I should see "Student 1" in the "#report_log_r1_c1" "css_element"
    And I should see "Added a question" in the "#report_log_r1_c5" "css_element"
    And I should see "Student 1" in the "#report_log_r4_c1" "css_element"
    And I should see "Added a question" in the "#report_log_r4_c5" "css_element"
    Then I log out
