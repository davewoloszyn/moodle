<?php

/**
 * Services definition.
 *
 * @package assignfeedback_cloudpoodll
 * @author  Justin Hunt Poodll.com
 */

$functions = array(

    'assignfeedback_cloudpoodll_check_grammar' => array(
        'classname'   => '\assignfeedback_cloudpoodll\external',
        'methodname'  => 'check_grammar',
        'description' => 'check grammar',
        'capabilities'=> 'assignfeedback/cloudpoodll:use',
        'type'        => 'read',
        'ajax'        => true,
    ),
    'assignfeedback_cloudpoodll_render_diffs' => array(
        'classname'   => '\assignfeedback_cloudpoodll\external',
        'methodname'  => 'render_diffs',
        'description' => 'render diffs between two texts',
        'capabilities'=> 'assignfeedback/cloudpoodll:use',
        'type'        => 'read',
        'ajax'        => true,
    )
);
