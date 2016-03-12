<?php
/******************************************************************************
 * An implementation of dicto (scg.unibe.ch/dicto) in and for PHP.
 * 
 * Copyright (c) 2016, 2015 Richard Klees <richard.klees@rwth-aachen.de>
 *
 * This software is licensed under The MIT License. You should have received 
 * a copy of the along with the code.
 */

namespace Lechimp\Dicto\Definition;

/**
 * Provides fluid interface to _with.
 */
class _WithName extends _Variable {
    /**
     * @var string
     */
    private $regexp;

    /**
     * @var _Variable
     */
    private $other;

    public function __construct($regexp, _Variable $other) {
        preg_match("%$regexp%", "");
        $this->regexp = $regexp;
        $this->other = $other;
    }

    /**
     * @inheritdoc
     */
    public function explain($text) {
        $v = new _Class();
        $v->setExplanation($text);
        return $v;
    }

}
