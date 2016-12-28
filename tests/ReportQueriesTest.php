<?php
/******************************************************************************
 * An implementation of dicto (scg.unibe.ch/dicto) in and for PHP.
 *
 * Copyright (c) 2016, 2015 Richard Klees <richard.klees@rwth-aachen.de
 *
 * This software is licensed under The MIT License. You should have received
 * a copy of the license along with the code.
 */

use Lechimp\Dicto\Report\ResultDB;
use Lechimp\Dicto\Report\Queries;
use Lechimp\Dicto\Rules as Rules;
use Lechimp\Dicto\Variables as Vars;
use Lechimp\Dicto\Analysis\Violation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class ReportQueriesTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->connection = DriverManager::getConnection
            ( array
                ( "driver" => "pdo_sqlite"
                //, "path" => "/home/lechimp/dt.sqlite"
                , "memory" => true
                )
            );
        $this->db = new ResultDB($this->connection);
        $this->db->init_database_schema();
        $this->queries = new Queries($this->db);
    }

    protected function builder() {
        return $this->connection->createQueryBuilder();
    }

    // Some example rules

    public function all_classes_cannot_depend_on_globals() {
        return new Rules\Rule
            ( Rules\Rule::MODE_CANNOT
            , new Vars\Classes("allClasses")
            , new Rules\DependOn()
            , array(new Vars\Globals("allGlobals"))
            );
    }

    public function all_classes_cannot_invoke_functions() {
        return new Rules\Rule
            ( Rules\Rule::MODE_CANNOT
            , new Vars\Classes("allClasses")
            , new Rules\Invoke()
            , array(new Vars\Functions("allFunctions"))
            );
    }

    // Actual Tests

    public function test_current_run() {
        $this->db->begin_run("#COMMIT_HASH#");
        $this->db->end_run();
        $cur_run = $this->queries->current_run();

        $this->db->begin_run("#COMMIT_HASH#");
        $this->db->end_run();
        $next_run = $this->queries->current_run();

        $this->assertGreaterThan($cur_run, $next_run);
    }

    public function test_previous_run() {
        $this->db->begin_run("#COMMIT_HASH#");
        $this->db->end_run();
        $first_run = $this->queries->current_run();
        $this->db->begin_run("#COMMIT_HASH#");
        $this->db->end_run();

        $cur_run = $this->queries->current_run();
        $prev_run = $this->queries->previous_run();

        $this->assertGreaterThan($prev_run, $cur_run);
        $this->assertEquals($first_run, $prev_run);
    }

    public function test_previous_run_with_different_commit() {
        $this->db->begin_run("#COMMIT_HASH#");
        $this->db->end_run();
        $first_run = $this->queries->current_run();
        $this->db->begin_run("#COMMIT_HASH2#");
        $this->db->end_run();
        $this->db->begin_run("#COMMIT_HASH2#");
        $this->db->end_run();

        $prev_run = $this->queries->previous_run_with_different_commit();

        $this->assertEquals($first_run, $prev_run);
    }

    public function test_last_run_for() {
        $this->db->begin_run("#COMMIT_HASH#");
        $this->db->end_run();
        $run1 = $this->queries->current_run();

        $this->db->begin_run("#COMMIT_HASH#");
        $this->db->end_run();
        $run2 = $this->queries->current_run();

        $this->db->begin_run("#COMMIT_HASH2#");
        $this->db->end_run();
        $run3 = $this->queries->current_run();

        $this->db->begin_run("#COMMIT_HASH3#");
        $this->db->end_run();
        $run4 = $this->queries->current_run();

        $qrun2 = $this->queries->last_run_for("#COMMIT_HASH#");
        $qrun3 = $this->queries->last_run_for("#COMMIT_HASH2#");

        $this->assertEquals($run2, $qrun2);
        $this->assertEquals($run3, $qrun3);
    }


    public function test_run_info() {
        $hash = "#COMMIT_HASH#";
        $this->db->begin_run($hash);
        $this->db->end_run();
        $run = $this->queries->current_run();

        $info = $this->queries->run_info($run);
 
        $this->assertEquals($hash, $info["commit_hash"]);
    }

    protected function init_scenario() {
        // Some scenario containing two rules, three runs
        // and different violations.
        $rule1 = $this->all_classes_cannot_depend_on_globals();
        $rule2 = $this->all_classes_cannot_invoke_functions();
        $vars = array_merge($rule1->variables(), $rule2->variables());
        $ruleset = new Rules\Ruleset($vars, [$rule1, $rule2]);

        $commit1 = "#COMMIT_1#";
        // One violation of rule1, no violation of rule2
        $this->db->begin_run($commit1);
        $this->db->begin_ruleset($ruleset);
        $this->db->begin_rule($rule1);
        $this->db->report_violation(
            new Violation($rule1, "file.php", 42, "file.php_line_42"));
        $this->db->end_rule();
        $this->db->end_ruleset();
        $this->db->end_run();

        $commit2 = "#COMMIT_2#";
        // One new violation of rule1, previous violation still exists.
        // One new violation of rule2.
        $this->db->begin_run($commit2);
        $this->db->begin_ruleset($ruleset);
        $this->db->begin_rule($rule1);
        $this->db->report_violation(
            new Violation($rule1, "file.php", 42, "file.php_line_42"));
        $this->db->report_violation(
            new Violation($rule1, "file2.php", 23, "file2.php_line_23"));
        $this->db->end_rule();
        $this->db->begin_rule($rule2);
        $this->db->report_violation(
            new Violation($rule2, "file3.php", 13, "file2.php_line_13"));
        $this->db->end_rule();
        $this->db->end_ruleset();
        $this->db->end_run();

        $commit3 = "#COMMIT_3#";
        // All violations of rule1 resolved.
        // Two new violation of rule2, previous violation resolved.
        $this->db->begin_run($commit3);
        $this->db->begin_ruleset($ruleset);
        $this->db->begin_rule($rule1);
        $this->db->end_rule();
        $this->db->begin_rule($rule2);
        $this->db->report_violation(
            new Violation($rule2, "file.php", 42, "file.php_line_42"));
        $this->db->report_violation(
            new Violation($rule2, "file2.php", 23, "file2.php_line_23"));
        $this->db->end_rule();
        $this->db->end_ruleset();
        $this->db->end_run();

        $this->rule1 = $rule1;
        $this->rule2 = $rule2;
    }

    // Helper to query ids of known rules.
    public function query_rule_ids() {
        $run = $this->queries->last_run_for("#COMMIT_3#");
        $rules = $this->queries->analyzed_rules($run);
        $info = $this->queries->rule_info($rules[0]);

        if ($info["rule"] == $this->rule1->pprint()) {
            return [$rules[0], $rules[1]];
        }
        else {
            return [$rules[1], $rules[0]];
        }
    }

    public function test_analyzed_rules() {
        $this->init_scenario();

        $run = $this->queries->last_run_for("#COMMIT_1#");
        $this->assertCount(1, $this->queries->analyzed_rules($run));

        $run = $this->queries->last_run_for("#COMMIT_2#");
        $this->assertCount(2, $this->queries->analyzed_rules($run));

        $run = $this->queries->last_run_for("#COMMIT_3#");
        $this->assertCount(2, $this->queries->analyzed_rules($run));
    }

    /**
     * @depends test_analyzed_rules
     */
    public function test_rule_info() {
        $this->init_scenario();

        $run = $this->queries->last_run_for("#COMMIT_3#");
        $rules = $this->queries->analyzed_rules($run);

        $rule1 = $this->queries->rule_info($rules[0]);
        $rule2 = $this->queries->rule_info($rules[1]);

        $this->assertArrayHasKey("rule", $rule1);
        $this->assertArrayHasKey("rule", $rule2);
        // TODO: check 'explanation' key

        $this->assertTrue( $rule1["rule"] == $this->rule1->pprint()
                        || $rule2["rule"] == $this->rule1->pprint());
        $this->assertTrue( $rule1["rule"] == $this->rule2->pprint()
                        || $rule2["rule"] == $this->rule2->pprint());
    }

    public function test_count_violations_in_run() {
        $this->init_scenario();

        $run = $this->queries->last_run_for("#COMMIT_1#");
        $this->assertEquals(1, $this->queries->count_violations_in($run));

        $run = $this->queries->last_run_for("#COMMIT_2#");
        $this->assertEquals(3, $this->queries->count_violations_in($run));

        $run = $this->queries->last_run_for("#COMMIT_3#");
        $this->assertEquals(2, $this->queries->count_violations_in($run));
    }

    /**
     * @depends test_rule_info
     */
    public function test_count_violations_in_run_and_rule($_) {
        $this->init_scenario();

        list($rule1, $rule2) = $this->query_rule_ids();

        $run = $this->queries->last_run_for("#COMMIT_1#");
        $this->assertEquals(1, $this->queries->count_violations_in($run, $rule1));
        $this->assertEquals(0, $this->queries->count_violations_in($run, $rule2));

        $run = $this->queries->last_run_for("#COMMIT_2#");
        $this->assertEquals(2, $this->queries->count_violations_in($run, $rule1));
        $this->assertEquals(1, $this->queries->count_violations_in($run, $rule2));

        $run = $this->queries->last_run_for("#COMMIT_3#");
        $this->assertEquals(0, $this->queries->count_violations_in($run, $rule1));
        $this->assertEquals(2, $this->queries->count_violations_in($run, $rule2));
    }

    public function test_count_added_violations() {
        $this->init_scenario();

        $run1 = $this->queries->last_run_for("#COMMIT_1#");
        $run2 = $this->queries->last_run_for("#COMMIT_2#");
        $run3 = $this->queries->last_run_for("#COMMIT_3#");

        $this->assertEquals(2, $this->queries->count_added_violations($run1, $run2));
        $this->assertEquals(2, $this->queries->count_added_violations($run2, $run3));
    }

    public function test_count_added_violations_in_rule() {
        $this->init_scenario();

        list($rule1, $rule2) = $this->query_rule_ids();

        $run1 = $this->queries->last_run_for("#COMMIT_1#");
        $run2 = $this->queries->last_run_for("#COMMIT_2#");
        $run3 = $this->queries->last_run_for("#COMMIT_3#");

        $this->assertEquals(1, $this->queries->count_added_violations($run1, $run2, $rule1));
        $this->assertEquals(1, $this->queries->count_added_violations($run1, $run2, $rule2));
        $this->assertEquals(0, $this->queries->count_added_violations($run2, $run3, $rule1));
        $this->assertEquals(2, $this->queries->count_added_violations($run2, $run3, $rule2));
    }

    public function test_count_resolved_violations() {
        $this->init_scenario();

        $run1 = $this->queries->last_run_for("#COMMIT_1#");
        $run2 = $this->queries->last_run_for("#COMMIT_2#");
        $run3 = $this->queries->last_run_for("#COMMIT_3#");

        $this->assertEquals(0, $this->queries->count_resolved_violations($run1, $run2));
        $this->assertEquals(3, $this->queries->count_resolved_violations($run2, $run3));
    }

    public function test_count_resolved_violations_in_rule() {
        $this->init_scenario();

        list($rule1, $rule2) = $this->query_rule_ids();

        $run1 = $this->queries->last_run_for("#COMMIT_1#");
        $run2 = $this->queries->last_run_for("#COMMIT_2#");
        $run3 = $this->queries->last_run_for("#COMMIT_3#");

        $this->assertEquals(0, $this->queries->count_resolved_violations($run1, $run2, $rule1));
        $this->assertEquals(0, $this->queries->count_resolved_violations($run1, $run2, $rule2));
        $this->assertEquals(2, $this->queries->count_resolved_violations($run2, $run3, $rule1));
        $this->assertEquals(1, $this->queries->count_resolved_violations($run2, $run3, $rule2));
    }

    public function test_violations_of() {
        $this->init_scenario();

        list($rule1, $rule2) = $this->query_rule_ids();

        $run1 = $this->queries->last_run_for("#COMMIT_1#");
        $run2 = $this->queries->last_run_for("#COMMIT_2#");
        $run3 = $this->queries->last_run_for("#COMMIT_3#");

        $run1_rule1 = $this->queries->violations_of($rule1, $run1);
        $this->assertCount(1, $run1_rule1);
        $this->assertContains
                (   [ "file" => "file.php"
                    , "line_no" => 42
                    , "introduced_in" => $run1
                    ]
                , $run1_rule1
                );

        $run1_rule2 = $this->queries->violations_of($rule2, $run1);
        $this->assertCount(0, $run1_rule2);

        $run2_rule1 = $this->queries->violations_of($rule1, $run2);
        $this->assertCount(2, $run2_rule1);
        $this->assertContains
                (   [ "file" => "file.php"
                    , "line_no" => 42
                    , "introduced_in" => $run1
                    ]
                , $run2_rule1
                );
        $this->assertContains
                (   [ "file" => "file2.php"
                    , "line_no" => 23
                    , "introduced_in" => $run2
                    ]
                , $run2_rule1
                );

        $run2_rule2 = $this->queries->violations_of($rule2, $run2);
        $this->assertCount(1, $run2_rule2);
        $this->assertContains
                (   [ "file" => "file3.php"
                    , "line_no" => 13
                    , "introduced_in" => $run2
                    ]
                , $run2_rule2
                );

        $run3_rule1 = $this->queries->violations_of($rule1, $run3);
        $this->assertCount(0, $run3_rule1);

        $run3_rule2 = $this->queries->violations_of($rule2, $run3);
        $this->assertCount(2, $run2_rule1);
        $this->assertContains
                (   [ "file" => "file.php"
                    , "line_no" => 42
                    , "introduced_in" => $run3
                    ]
                , $run3_rule2
                );
        $this->assertContains
                (   [ "file" => "file2.php"
                    , "line_no" => 23
                    , "introduced_in" => $run3
                    ]
                , $run3_rule2
                );
    }

    // TODO: Check what happens if a violation is first found, then resolved,
    // then introduced again.

    /*
        $this->db->begin_rule($this->all_classes_cannot_depend_on_globals());
        $rule = $this->all_classes_cannot_depend_on_globals();
        $violation = new Violation($rule, "file.php", 42, "line of code");
        $this->db->report_violation($violation);
    */
}
