<?php
/******************************************************************************
 * An implementation of dicto (scg.unibe.ch/dicto) in and for PHP.
 *
 * Copyright (c) 2016, 2015 Richard Klees <richard.klees@rwth-aachen.de>
 *
 * This software is licensed under The MIT License. You should have received
 * a copy of the license along with the code.
 */

use Lechimp\Dicto\Graph\Graph;
use Lechimp\Dicto\Graph\Query;

class GraphQueryTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->g = new Graph(); 
    }

    public function test_match_all() {
        $n1 = $this->g->create_node("a_type", []);
        $n2 = $this->g->create_node("b_type", []);

        $query = (new Query())
            ->with_condition(function($_) {
                return true;
            });
        $res = $query->execute_on($this->g);

        $this->assertEquals([[$n1],[$n2]], $res);
    }

    public function test_match_none() {
        $n1 = $this->g->create_node("a_type", []);
        $n2 = $this->g->create_node("b_type", []);

        $query = (new Query())
            ->with_condition(function($_) {
                return false;
            });
        $res = $query->execute_on($this->g);

        $this->assertEquals([], $res);
    }

    public function test_path() {
        $n1 = $this->g->create_node("a_type", []);
        $n2 = $this->g->create_node("b_type", []);
        $rel = $this->g->add_relation($n1, "rel_type", [], $n2);

        $all = function($_) { return true; };

        $query = (new Query())
            ->with_condition($all)
            ->with_condition($all)
            ->with_condition($all);
        $res = $query->execute_on($this->g);
        $this->assertEquals([[$n1,$rel,$n2]], $res);
    }

    public function test_path2() {
        $n1 = $this->g->create_node("a_type", []);
        $n2 = $this->g->create_node("b_type", []);
        $n3 = $this->g->create_node("c_type", []);
        $r1 = $this->g->add_relation($n1, "rel_A", [], $n2);
        $r2 = $this->g->add_relation($n2, "rel_B", [], $n3);

        $all = function($_) { return true; };

        $query = (new Query())
            ->with_condition($all)
            ->with_condition(function($e) {
                return $e->type() == "rel_B";
            })
            ->with_condition($all);
        $res = $query->execute_on($this->g);
        $this->assertEquals([[$n2,$r2,$n3]], $res);
    }

    public function test_paths() {
        $n1 = $this->g->create_node("a_type", []);
        $n2 = $this->g->create_node("b_type", []);
        $n3 = $this->g->create_node("c_type", []);
        $r1 = $this->g->add_relation($n1, "rel_A", [], $n2);
        $r2 = $this->g->add_relation($n2, "rel_A", [], $n3);

        $all = function($_) { return true; };

        $query = (new Query())
            ->with_condition($all)
            ->with_condition($all)
            ->with_condition($all);
        $res = $query->execute_on($this->g);
        $this->assertEquals([[$n1,$r1,$n2],[$n2,$r2,$n3]], $res);
    }
}