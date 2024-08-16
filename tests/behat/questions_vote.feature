@mod @mod_hotquestion @javascript
Feature: Users can vote on named or anonymous entries to hotquestion
  In order to vote on a question
  As a user
  I need to see a hotquestion post

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
	  | teacher2 | Teacher   | 2        | teacher2@asd.com |
	  | manager1 | Manager   | 1        | manager@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
	  | teacher2 | C1     | teacher        |
	  | manager1 | C1     | manager        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity    | name                  | intro             | course | idnumber     | submitdirections           | heatvisibility | heatlabel | heatlimit |
      | hotquestion | Test hotquestion name | Hotquestion intro | C1     | hotquestion1 | Submit your question here: | 1              | Heat      | 5         |
  Scenario: A user follows vote to increase heat
    # Student 1 adds posts.
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | First question by student 1 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Second question by student 1 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I should see "Second question by student 1"
    And I should see "Posted by Anonymous"
    Then I should see "First question by student 1"
    And I should see "Posted by Student 1"
    Then I log out
	#Student 2 votes.
    Given I log in as "student2"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "1" in the "Anonymous" "table_row"
    Then I log out
    # Admin 1 votes.
    Given I log in as "admin"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "2" in the "Anonymous" "table_row"
    And I click on "Vote" "link" in the "Student 1" "table_row"
    Then I should see "1" in the "Student 1" "table_row"
    Then I log out
	# Teacher 1 votes.
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "2" in the "Anonymous" "table_row"
    And I click on "Vote" "link" in the "Student 1" "table_row"
    Then I should see "1" in the "Student 1" "table_row"
    Then I log out
	#Manager 1 votes.
    Given I log in as "manager1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "3" in the "Anonymous" "table_row"
    And I click on "Vote" "link" in the "Student 1" "table_row"
    Then I should see "2" in the "Student 1" "table_row"
    Then I log out
	#Teacher 2 votes.
    Given I log in as "teacher2"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "4" in the "Anonymous" "table_row"
    And I click on "Vote" "link" in the "Student 1" "table_row"
    Then I should see "4" in the "Student 1" "table_row"
    # Teacher 2 verifies adding votes is logged for everyone.
    And I am on "Course 1" course homepage
    When I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I set the field "menumodid" to "Test hotquestion name"
    And I press "Get these logs"
    Then I should see "Teacher 2" in the "#report_log_r0_c1" "css_element"
    And I should see "Updated vote" in the "#report_log_r0_c5" "css_element"
    Then I should see "Teacher 2" in the "#report_log_r2_c1" "css_element"
    And I should see "Updated vote" in the "#report_log_r2_c5" "css_element"
	# Check for two manager votes.
    Then I should see "Manager 1" in the "#report_log_r5_c1" "css_element"
    And I should see "Updated vote" in the "#report_log_r5_c5" "css_element"
    Then I should see "Manager 1" in the "#report_log_r7_c1" "css_element"
    And I should see "Updated vote" in the "#report_log_r7_c5" "css_element"
	# Check for two teacher 1 votes.
    Then I should see "Teacher 1" in the "#report_log_r10_c1" "css_element"
    And I should see "Updated vote" in the "#report_log_r10_c5" "css_element"
    Then I should see "Teacher 1" in the "#report_log_r12_c1" "css_element"
    And I should see "Updated vote" in the "#report_log_r12_c5" "css_element"
    # Check for two admin votes.
    Then I should see "Admin User" in the "#report_log_r15_c1" "css_element"
    And I should see "Updated vote" in the "#report_log_r15_c5" "css_element"
    Then I should see "Admin User" in the "#report_log_r17_c1" "css_element"
    And I should see "Updated vote" in the "#report_log_r17_c5" "css_element"
	# Student 1 only voted once.
    Then I should see "Student 2" in the "#report_log_r20_c1" "css_element"
    And I should see "Updated vote" in the "#report_log_r20_c5" "css_element"
    Then I log out
