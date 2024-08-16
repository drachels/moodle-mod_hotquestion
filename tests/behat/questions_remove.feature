@mod @mod_hotquestion
Feature: Teachers, admin and managers can remove named or anonymous posts
  In order to manage questions
  As a teacher, manager, or admin
  I need to be able to remove a hotquestion entry.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
	  | teacher2 | Teacher   | 2        | teacher2@asd.com |
	  | manager1 | Manager   | 1        | manager@asd.com  |
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
      | activity     | name                   | intro             | course | idnumber     | submitdirections           |
      | hotquestion  | Test hotquestion name  | Hotquestion intro | C1     | hotquestion1 | Submit your question here: |
  Scenario: A teacher, manager, or admin follows remove tool to delete a post
    # Teacher 1 adds posts.
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | First question by teacher 1 |
    And I press "Click to post"
    Then I set the following fields to these values:
      | Submit your question here: | Second question by teacher 1 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I log out
	# Admin User adds posts.
    Given I log in as "admin"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Third question by admin user |
    And I press "Click to post"
    Then I set the following fields to these values:
      | Submit your question here: | Fourth question by admin user |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I log out
	# Manager 1 adds posts.
    Given I log in as "manager1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Fifth question by  manager 1 |
    And I press "Click to post"
    Then I set the following fields to these values:
      | Submit your question here: | Sixth question by manager 1 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I log out
    # Student 1 adds posts.
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Seventh question by student 1 |
    And I press "Click to post"
    Then I set the following fields to these values:
      | Submit your question here: | Eighth question by student 1 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I log out
	# Teacher 1 removes a post.
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Remove" "link" in the "Seventh question by student 1" "table_row"
    Then I should not see "Seventh question by student 1"
	# Teacher 1 verifies removing post and votes is logged.
    And I am on "Course 1" course homepage
    When I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I set the field "menumodid" to "Test hotquestion name"
    And I press "Get these logs"
    Then I should see "Teacher 1" in the "#report_log_r1_c1" "css_element"
    And I should see "Remove question" in the "#report_log_r1_c5" "css_element"
    Then I log out
	# Admin User removes a post.
    Given I log in as "admin"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Remove" "link" in the "Eighth question by student 1" "table_row"
    Then I should not see "Eighth question by student 1"
	# Admin User verifies removing post and votes is logged.
    And I am on "Course 1" course homepage
    When I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I set the field "menumodid" to "Test hotquestion name"
    And I press "Get these logs"
    Then I should see "Admin User" in the "#report_log_r1_c1" "css_element"
    And I should see "Remove question" in the "#report_log_r1_c5" "css_element"
    Then I log out
	# Manager 1 removes a post.
    Given I log in as "manager1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Remove" "link" in the "First question by teacher 1" "table_row"
    Then I should not see "First question by teacher 1"
	# Manager 1 verifies removing post and votes is logged.
    And I am on "Course 1" course homepage
    When I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I set the field "menumodid" to "Test hotquestion name"
    And I press "Get these logs"
    Then I should see "Manager 1" in the "#report_log_r1_c1" "css_element"
    And I should see "Remove question" in the "#report_log_r1_c5" "css_element"
    Then I log out
