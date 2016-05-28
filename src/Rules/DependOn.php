<?php
/******************************************************************************
 * An implementation of dicto (scg.unibe.ch/dicto) in and for PHP.
 *
 * Copyright (c) 2016 Richard Klees <richard.klees@rwth-aachen.de>
 *
 * This software is licensed under The MIT License. You should have received
 * a copy of the licence along with the code.
 */

namespace Lechimp\Dicto\Rules;

use Lechimp\Dicto\Variables\Variable;
use Lechimp\Dicto\Indexer\Location;
use Lechimp\Dicto\Indexer\Insert;
use Lechimp\Dicto\Indexer\ListenerRegistry;
use PhpParser\Node as N;

/**
 * A class or function is considered do depend on something if its body
 * of definition makes use of the thing. Language constructs, files or globals
 * can't depend on anything.
 */
class DependOn extends Relation {
    /**
     * @inheritdoc
     */
    public function name() {
        return "depend_on";    
    }

    /**
     * @inheritdoc
     */
    public function register_listeners(ListenerRegistry $registry) {
        $this->register_method_call_listener($registry);
        $this->register_func_call_listener($registry);
        $this->register_global_listener($registry);
        $this->register_array_dim_fetch_listener($registry);
        $this->register_error_suppressor_listener($registry);
    }

    protected function register_method_call_listener(ListenerRegistry $registry) {
        $registry->on_enter_misc
            ( array(N\Expr\MethodCall::class)
            , function(Insert $insert, Location $location, N\Expr\MethodCall $node) {
                // The 'name' could also be a variable like in $this->$method();
                if (is_string($node->name)) {
                    $ref_id = $insert->get_reference
                        ( Variable::METHOD_TYPE
                        , $node->name
                        , $location->file_path()
                        , $node->getAttribute("startLine")
                        );
                    $this->insert_relation_into
                        ( $insert
                        , $location
                        , $ref_id
                        );
                }
            });
    }

    protected function register_func_call_listener(ListenerRegistry $registry) {
        $registry->on_enter_misc
            ( array(N\Expr\FuncCall::class)
            , function(Insert $insert, Location $location, N\Expr\FuncCall $node) {
                // Omit calls to closures, we would not be able to
                // analyze them anyway atm.
                // Omit functions in arrays, we would not be able to
                // analyze them anyway atm.
                if (!($node->name instanceof N\Expr\Variable ||
                      $node->name instanceof N\Expr\ArrayDimFetch)) {
                    $ref_id = $insert->get_reference
                        ( Variable::FUNCTION_TYPE
                        , $node->name->parts[0]
                        , $location->file_path()
                        , $node->getAttribute("startLine")
                        );
                    $this->insert_relation_into
                        ( $insert
                        , $location
                        , $ref_id
                        );
                }
            });
    }

    protected function register_global_listener(ListenerRegistry $registry) {
        $registry->on_enter_misc
            ( array(N\Stmt\Global_::class)
            , function(Insert $insert, Location $location, N\Stmt\Global_ $node) {
                foreach ($node->vars as $var) {
                    if (!($var instanceof N\Expr\Variable) || !is_string($var->name)) {
                        throw new \RuntimeException(
                            "Expected Variable with string name, found: ".print_r($var, true));
                    }
                    $ref_id = $insert->get_reference
                        ( Variable::GLOBAL_TYPE
                        , $var->name
                        , $location->file_path()
                        , $node->getAttribute("startLine")
                        );
                $this->insert_relation_into
                    ( $insert
                    , $location
                    , $ref_id
                    );
                }
            });
    }

    protected function register_array_dim_fetch_listener(ListenerRegistry $registry) {
        $registry->on_enter_misc
            ( array(N\Expr\ArrayDimFetch::class)
            , function(Insert $insert, Location $location, N\Expr\ArrayDimFetch $node) {
                if ($node->var instanceof N\Expr\Variable 
                &&  $node->var->name == "GLOBALS"
                // Ignore usage of $GLOBALS with variable index.
                && !($node->dim instanceof N\Expr\Variable)) {
                    $ref_id = $insert->get_reference
                        ( Variable::GLOBAL_TYPE
                        , $node->dim->value
                        , $location->file_path()
                        , $node->getAttribute("startLine")
                        );
                    $this->insert_relation_into
                        ( $insert
                        , $location
                        , $ref_id
                        );
                }
            });
    }

    protected function register_error_suppressor_listener(ListenerRegistry $registry) {
        $registry->on_enter_misc
            ( array(N\Expr\ErrorSuppress::class)
            , function(Insert $insert, Location $location, N\Expr\ErrorSuppress $node) {
                $ref_id = $insert->get_reference
                    ( Variable::LANGUAGE_CONSTRUCT_TYPE
                    , "@"
                    , $location->file_path()
                    , $node->getAttribute("startLine")
                    );
                $this->insert_relation_into
                    ( $insert
                    , $location
                    , $ref_id
                    );
            });
        }
}
