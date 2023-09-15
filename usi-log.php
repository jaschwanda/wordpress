<?php // ------------------------------------------------------------------------------------------------------------------------ //

(defined('ABSPATH') || (defined('DB_HOST') && defined('DB_NAME'))) or die('Accesss not allowed.');

/* 
Author:            Jim Schwanda
Author URI:        https://www.usi2solve.com/leader
Copyright:         2023 by Jim Schwanda.
Description:       This plugin provides logging to the database for debugging and tracing purposes. The WordPress database connection parameters must be defined before this plugin is loaded. The USI::log() plugin is is developed and maintained by Universal Solutions. 
Donate link:       https://www.usi2solve.com/donate/wordpress-solutions
License:           GPL-3.0
License URI:       https://github.com/jaschwanda/wordpress-solutions/blob/master/LICENSE.md
Plugin Name:       USI::log()
Plugin URI:        https://github.com/jaschwanda/wordpress-solutions
Requires at least: 5.0
Requires PHP:      7.0.0
Tested up to:      5.3.2
Version:           1.1.0
Warranty:          This software is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

class USI_Dbs_Exception extends Exception { } // Class USI_Dbs_Exception;

final class USI {

   const VERSION = '1.1.0 (2023-09-15)';

   private static $info   = null;
   private static $mysqli = null;
   private static $mysqli_stmt = null;
   private static $offset = 0;
   private static $user   = 0;

   private function __construct() {
   } // __construct();

   public static function dbs_connect() { // Share connection with other plugins;
      if (!self::$mysqli) {
         self::$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
         if (self::$mysqli->connect_errno) throw new USI_Dbs_Exception('HOST=' . DB_HOST . ':NAME=' . DB_NAME . ':USER=' . DB_USER . ':errno=' . self::$mysqli->connect_errno . ':error=' . self::$mysqli->connect_error);
      }
      return(self::$mysqli);
   } // dbs_connect();

   public static function log() {
      $info = null;
      try {
         $trace = debug_backtrace();
         if (!empty($trace[self::$offset+0])) {
            if (empty($trace[self::$offset+1])) {
               $info .= $trace[self::$offset+0]['file'];
            } else {
               $info .= !empty($trace[self::$offset+1]['class']) ? $trace[self::$offset+1]['class'] . ':' : $trace[self::$offset+0]['file'];
               if (!empty($trace[self::$offset+1]['function'])) {
                  switch ($trace[self::$offset+1]['function']) {
                  case 'include':
                  case 'include_once':
                  case 'require':
                  case 'require_once':
                     break;
                  default:
                     $info .= ':' . $trace[self::$offset+1]['function'] . '()';
                  }
               }
            }
            if (!empty($trace[self::$offset+0]['line'])) $info .= '~' . $trace[self::$offset+0]['line'] . ':';
         }
         if (isset($trace[self::$offset/2+0]['args'])) {
            $args = $trace[self::$offset/2+0]['args'];
            foreach ($args as $arg) {
               if (is_array($arg) || is_object($arg)) {
                  $info .= print_r($arg, true);
               } else if (is_string($arg)) {
                  $first = substr($arg, 0, 1);
                  if ('\\' == $first) {
                     $second = substr($arg, 1, 1);
                     if ('!' == $second) {
                        $info = substr($arg, 1);
                     } else if ('n' == $second) {
                        $info .= PHP_EOL . substr($arg, 2);
                     } else if ('%' == $second) {
                        $info .= PHP_EOL . 'backtrace=' . print_r($trace, true) . PHP_EOL;
                     } else if ('2n' == substr($arg, 1, 2)) {
                        $info .= PHP_EOL . PHP_EOL . substr($arg, 3);
                     }
                  } else {
                     $info .= $arg;
                  }
               } else {
                  $info .= $arg;
               }
            }
         }
      } catch (Exception $e) {
         $info .= PHP_EOL . 'exception=' . $e->GetMessage();
      }

      if (!self::$mysqli) self::$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
      if (!self::$mysqli_stmt) {
         global $table_prefix;
         self::$mysqli_stmt = new mysqli_stmt(self::$mysqli);
         self::$mysqli_stmt->prepare('INSERT INTO `' . $table_prefix . 'USI_log` (`user_id`, `action`) VALUES (?, ?)');     
         self::$mysqli_stmt->bind_param('is', self::$user, self::$info);
      }
      self::$info = substr($info, 0, 16777215); // If `action` field is MEDIUMTEXT;
      self::$user = function_exists('get_current_user_id') ? get_current_user_id() : 0;
      self::$mysqli_stmt->execute();

   } // log();

   public static function log2() { // call usi::log2('method()~'.__LINE__.':label='... or usi::log2('method():label='...
      self::$offset = 2;
      self::log();
      self::$offset = 0;
   } // log2();

} // Class USI

spl_autoload_register(
   function ($class_name) {
      $file = str_replace('_', '-', strtolower($class_name)) . '.php';
      if ('usi-' != substr($file, 0, 4)) return;
      $path = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . substr($file, 0, strpos($file, '-solutions', 5) + 10) . DIRECTORY_SEPARATOR . $file;
      if (!file_exists($path)) return;
      include $path;
   }
);

// --------------------------------------------------------------------------------------------------------------------------- // ?>