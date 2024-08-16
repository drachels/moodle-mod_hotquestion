@mod @mod_hotquestion
Feature: Teacher can export questions from current HotQuestion activity
  In order to document posts to a HotQuestion activity
  As a teacher
  I need to be able to export questions.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@asd.com |
      | teacher2 | Teacher | 2 | teacher1@asd.com |
      | student1 | Student | 1 | student1@asd.com |
      | student2 | Student | 2 | student2@asd.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    And the following "course enrolments" exist:
      | user     | course | role |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | teacher1 | C2     | editingteacher |
      | teacher2 | C2     | teacher |
      | student1 | C2     | student |
      | student2 | C2     | student |
    And the following "activities" exist:
      | activity     | name                     | intro                | course | idnumber     | submitdirections           |
      | hotquestion  | Test hotquestion name 1  | hotquestion question | C1     | hotquestion1 | Submit your question here: |
      | hotquestion  | Test hotquestion name 2  | hotquestion question | C2     | hotquestion2 | Submit your question here: |
  Scenario: A teacher follows export to csv toolbutton to export questions
    # Admin User adds posts to course 1
    Given I log in as "admin"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name 1"
    And I set the following fields to these values:
      | Submit your question here: | First question 1 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Second question 1 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    # Admin User adds posts to course 2
    When I am on "Course 2" course homepage
    And I follow "Test hotquestion name 2"
    And I set the following fields to these values:
      | Submit your question here: | First question 2 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Second question 2 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I log out
    #Teacher 1 posts entries to course 1
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name 1"
    And I set the following fields to these values:
      | Submit your question here: | Third question 1 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Fourth question 1 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    #Teacher 1 posts entries to course 2
    When I am on "Course 2" course homepage
    And I follow "Test hotquestion name 2"
    And I set the following fields to these values:
      | Submit your question here: | Third question 2 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Fourth question 2 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I log out
    #Non-editing teacher 2 posts entries course 1
    Given I log in as "teacher2"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name 1"
    And I set the following fields to these values:
      | Submit your question here: | Fifth question 1 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Sixth question 1 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    #Non-editing teacher 2 posts entries to course 2
    When I am on "Course 2" course homepage
    And I follow "Test hotquestion name 2"
    And I set the following fields to these values:
      | Submit your question here: | Fifth question 2 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Sixth question 2 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I log out
	#Student 1 posts entries to course 1
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name 1"
    And I set the following fields to these values:
      | Submit your question here: | Seventh question 1 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Eighth question 1 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I log out
    #Student 1 posts entries course 2
    Given I log in as "student1"
    When I am on "Course 2" course homepage
    And I follow "Test hotquestion name 2"
    And I set the following fields to these values:
      | Submit your question here: | Seventh question 2 |
    And I press "Click to post"
    And I set the following fields to these values:
      | Submit your question here: | Eighth question 2 |
    And I set the field "Display as anonymous" to "1"
    And I press "Click to post"
    Then I should see "Eighth question 2"
    And I should see "Posted by Anonymous"
    And I should see "Seventh question 2"
    And I should see "Posted by Student 1"
    And I should see "Sixth question 2"
    And I should see "Posted by Anonymous"
    And I should see "Fifth question 2"
    And I should see "Posted by Teacher 2"
    And I should see "Fourth question 2"
    And I should see "Posted by Anonymous"
    And I should see "Third question 2"
    And I should see "Posted by Teacher 1"
    And I should see "Second question 2"
    And I should see "Posted by Anonymous"
    And I should see "First question 2"
    And I should see "Posted by Admin User"
    Then I log out
	#Scenario: Clicking on export
    #Teacher downloads current HotQuestion questions
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name 1"
    Then I should see "Eighth question 1"
    And I should see "Posted by Anonymous"
    And I should see "Seventh question 1"
    And I should see "Posted by Student 1"
    And I should see "Sixth question 1"
    And I should see "Posted by Anonymous"
    And I should see "Fifth question 1"
    And I should see "Posted by Teacher 1"
    And I should see "Fourth question 1"
    And I should see "Posted by Anonymous"
    And I should see "Third question 1"
    And I should see "Posted by Teacher 1"
    And I should see "Second question 1"
    And I should see "Posted by Anonymous"
    And I should see "First question 1"
    And I should see "Posted by Admin User"
    And following "Export to .csv" should download between "700" and "990" bytes
    # Verify download by Teacher 1 was logged.
    And I am on "Course 1" course homepage
    When I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I set the field "menumodid" to "Test hotquestion name"
    And I press "Get these logs"
    Then I should see "Teacher 1" in the "#report_log_r0_c1" "css_element"
    And I should see "Download questions" in the "#report_log_r0_c5" "css_element"
    Then I log out
