@ticket-BAP-11242
@automatically-ticket-tagged
@skip
Feature: Get help link
  I order to find help
  As crm user
  I need to have link to documentation

  Scenario: Click help link
    Given I login as administrator
    When I click on "Help Icon"
    Then the documentation "https://oroinc.com/doc/orocrm/current" will opened
