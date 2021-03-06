<?php
/**
* @author   Laurent Jouanneau
* @copyright 2014 Laurent Jouanneau
* @link     http://www.jelix.org
* @licence  MIT
*/
namespace Jelix\Legacy;

/**
 * Class to load deprecated classes
 */
class Autoloader {

    static protected $classList;

    static function init() {
        self::$classList = json_decode(file_get_contents(__DIR__.'/mapping.json'), true);
    }

    static function loadClass($class) {
        if (isset(self::$classList[$class])) {
            $f = __DIR__.'/'.self::$classList[$class];
            if (file_exists($f)) {
                require($f);
            }
        }
    }
}

