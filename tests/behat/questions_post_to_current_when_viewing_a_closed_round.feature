@mod @mod_hotquestion
Feature: When posting a question while viewing a closed round, the question posts into the current round
  In order to post a question
  As a user
  I can post while viewing any round.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
	  | teacher2 | Teacher   | 2        | teacher2@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
	  | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity     | name                   | intro             | course | idnumber     | submitdirections           |
      | hotquestion  | Test hotquestion name  | Hotquestion intro | C1     | hotquestion1 | Submit your question here: |
  Scenario: A user posts entries to current round while viewing previous rounds
    # Student 1 adds posts.
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Round 1 first question by student 1 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Round 1 second question by student 1 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I log out
	# Teacher 1 opens a new round.
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    Then I should see "Round 1 second question by student 1"
    And I should see "Round 1 first question by student 1"
    And I follow "Open a new round"
	# Teacher verfies new round is logged.
    And I am on "Course 1" course homepage
    When I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I set the field "menumodid" to "Test hotquestion name"
    And I press "Get these logs"
    Then I should see "Teacher 1" in the "#report_log_r1_c1" "css_element"
    And I should see "Opened a new round" in the "#report_log_r1_c5" "css_element"
    Then I log out
	# Student 2 adds posts while viewing round 1 and views it in round 2.
    Given I log in as "student2"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Round 2 third question by student 2 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Round 2 fourth question by student 2 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I should see "Round 2 fourth question by student 2"
    And I should see "Round 2 third question by student 2"
    And I follow "Previous round"
    Then I should see "Round 1 second question by student 1"
    And I should see "Round 1 first question by student 1"
    And I set the following fields to these values:
      | Submit your question here: | Round 2 fifth question by student 2 |
    And I press "Click to post"
    Then I should see "Round 2 fifth question by student 2"
    And I should see "Round 2 fourth question by student 2"
    And I should see "Round 2 third question by student 2"
    And I follow "Previous round"
    Then I should see "Round 1 second question by student 1"
    And I should see "Round 1 first question by student 1"
    And I set the following fields to these values:
      | Submit your question here: | Round 2 sixth question by student 2 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I should see "Round 2 sixth question by student 2"
    Then I should see "Round 2 fifth question by student 2"
    And I should see "Round 2 fourth question by student 2"
    And I should see "Round 2 third question by student 2"
    Then I log out
