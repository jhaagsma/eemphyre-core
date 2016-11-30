<?php

/**
 * Math is a class to add some useful math things
 *
 * PHP version 5
 *
 * ------
 * These files are part of the empiresPHPframework;
 * The original framework core (specifically the mysql.php
 * the router.php and the errorlog) was started by Timo Ewalds,
 * and rewritten to use APC and extended by Julian Haagsma,
 * for use in Earth Empires (located at http://www.earthempires.com );
 * it was spun out for use on other projects.
 *
 * The general.php contains content from Earth Empires
 * written by Dave McVittie and Joe Obbish.
 *
 * The example website files were written by Julian Haagsma.
 *
 * @category Core
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @author   Timo Ewalds <tewalds@gmail.com>
 * @author   Dave McVittie <dave.mcvittie@gmail.com>
 * @author   Joe Obbish <slagpit@earthempires.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Pulled out of errorlog.php 2016-03-15
 */

namespace EmPHyre;

class Math
{
    /**
     * Returns the erf
     *
     * @param  float $x A value
     *
     * @return float    The erf($x)
     */
    public static function erf($x)
    {
        // constants
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p  = 0.3275911;

        // Save the sign of x
        $sign = 1;
        if ($x < 0) {
            $sign = -1;
        }

        $x = abs($x);

        // A&S formula 7.1.26
        $t = 1.0 / (1.0 + $p * $x);
        $y = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x);

        return $sign * $y;
    }//end erf()

    /**
     * Rounds DOWN to significant figures
     *
     * @param  float $x The number to FLOOR
     *
     * @return float    The FLOORED number
     */
    public static function floorToSigFig($x = 0.0)
    {
        if (0 === $x) {
            return 0;
        }

        $nearest = pow(10, floor(log($x, 10)));
        return floor($x / $nearest) * $nearest;
    }//end floorToSigFig()

    /**
     * Rounds UP to significant figures
     *
     * @param  float $x The number to CEIL
     *
     * @return floor    The CEILED number
     */
    public static function ceilToSigFig($x = 0.0)
    {
        if (0 === $x) {
            return 0;
        }

        $nearest = pow(10, floor(log($x, 10)));
        return ceil($x / $nearest) * $nearest;
    }//end ceilToSigFig()


    /**
     * Rounds UP to significant figures
     *
     * @param  float $x The number to round
     * @param  float $n The number of significant figures
     *
     * @return float    The sigFig'd number
     */
    public static function roundToSignificantFigures($x = 0.0, $n = 3)
    {
        if ($x == 0) {
            return 0;
        }

        $d     = ceil(log10($x < 0 ? -$x : $x));
        $power = $n - (int)$d;

        $magnitude = pow(10, $power);
        $shifted   = round($x * $magnitude);
        return $shifted / $magnitude;
    }//end roundToSignificantFigures()
}//end class
