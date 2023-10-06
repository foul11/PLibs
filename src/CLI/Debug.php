<?php
namespace Arturka\CLI;

use Arturka\CLI\EscapeColors as Colors;

/*
	* Добавить опциональную настройку для метода debug
	backtrace, что б отлаживать ошибки было проще, а так же настройку для
	каких типов сообщений делать трасировку
	
*/

require_once('EscapeColors.php');

class Debug{
	private static $max_args = 7;
	private static $max_array_args = 7;
	
	private static $debug_level = 3;
	public static $debug_color = [
		1 => ['none', 'cyan', 				'ECHO'],
		2 => ['red', 'black' , 				'ERROR'],
		3 => ['magenta', 'bold_cyan', 	'NOTICE'],
		4 => ['light_gray', 'green',		'Debug_1'],
		5 => ['light_gray', 'blue', 		'Debug_2'],
		6 => ['light_gray', 'red', 			'Debug_3'],
	];
	
	private static $warn_trace = false;
	private static $echo = [__CLASS__, 'default_echo'];
	// private static $handler_exit = null;
	
	private static function default_echo($a){
		echo($a);
	}
	
	private static function errno_to_str($errno){
		switch($errno){
			case E_ERROR: return 'E_ERROR';
			case E_WARNING: return 'E_WARNING';
			case E_PARSE: return'E_PARSE';
			case E_NOTICE: return'E_NOTICE';
			case E_CORE_ERROR: return'E_CORE_ERROR';
			case E_CORE_WARNING: return'E_CORE_WARNING';
			case E_COMPILE_ERROR: return'E_COMPILE_ERROR';
			case E_COMPILE_WARNING: return'E_COMPILE_WARNING';
			case E_USER_ERROR: return'E_USER_ERROR';
			case E_USER_WARNING: return'E_USER_WARNING';
			case E_USER_NOTICE: return'E_USER_NOTICE';
			case E_STRICT: return'E_STRICT';
			case E_RECOVERABLE_ERROR: return'E_RECOVERABLE_ERROR';
			case E_DEPRECATED: return'E_DEPRECATED';
			case E_USER_DEPRECATED: return'E_USER_DEPRECATED';
			default: return 'E_FATAL_ERROR';
		}
	}
	
	private static function str_colorer($str){
		// preg_match_all('/("[^"]*")*(\'[^\']*\')*(\([^\)]*\))*(\[[^\]]*\])*/', $str, $matchs, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE);
		
		// $add_offset = 0;
		
		// foreach($matchs as $k => $val1){
			// if(!$k) continue;
			
			// foreach($val1 as $val2){
				// if(!is_array($val2)) continue;
				// if(empty($value = $val2[0])) continue;
				
				// $offset = $val2[1];
				// $replace = Colors::cyan($value, true);
				// $replace = Colors::black($replace);
				// $str = substr_replace($str, $replace, $offset + $add_offset, strlen($value));
				// $add_offset += strlen($replace) - strlen($value);
			// }
		// }
		
		$str = preg_replace_callback('/("[^"]*")*(\'[^\']*\')*(\([^\)]*\))*(\[[^\]]*\])*/', function($match){
			if(empty($value = $match[0])) return;
			
			return Colors::black(Colors::cyan($value, true));
		}, $str);
		
		return $str;
	}
	
	private static function arg_to_str($args, $recurse = true, $max = 5){
		$ret = '';
		
		foreach(array_values($args) as $k => $val){
			if($max && $max <= $k) break;
			if($k) $ret .= ', ';
			
			switch(gettype($val)){
				case "boolean":
					$ret .= Colors::black(Colors::cyan($val ? 'true' : 'false', true));
					break;
					
				case "integer":
				case "double":
					$ret .= Colors::black(Colors::cyan($val, true));
					break;
					
				case "string":
					$ret .= Colors::black(Colors::cyan("'". $val ."'", true));
					break;
				
				case "NULL":
					$ret .= Colors::black(Colors::cyan('NULL', true));
					break;
					
				case "resource":
				case "resource (closed)":
					$ret .= Colors::black(Colors::cyan('#'.(int)$val, true));
					break;
				
				case "array":
					if($recurse){
						$ret .= 'array('. count($val) .'){'.
							// Colors::black(Colors::cyan(
								self::arg_to_str($val, false, self::$max_array_args) . (count($val) > self::$max_array_args ? ', ...' : '')
							// , true))
						.'}';
					}else
						$ret .= 'array('. count($val) .'){'. Colors::black(Colors::cyan('...', true)) .'}';
					break;
				
				case "object":
						$ret .= 'object('. get_class($val) .')';
					break;
					
				default:
					$ret .= Colors::black(Colors::red('*Unknown*', true));
					break;
			}
		}
		
		return $ret . (count($args) > self::$max_args ? ', ...' : '');
	}
	
	public static function stacktrace_cli($e = null){
		if($e){
			self::handler_error($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), true);
			$stacktrace = $e->getTrace(); // array_merge($e->getTrace(), [[]]);
		}else{
			$e = error_get_last();
			
			if($e)
				self::handler_error($e['type'], $e['message'], $e['file'], $e['line']);
			
			$stacktrace = debug_backtrace(); // array_merge(debug_backtrace(), [[]]);
		}
		
		if($stacktrace){
			$print = '';
			
			$print .= PHP_EOL . Colors::black(Colors::red((!is_null($e) ? 'Crash s' : 'S') .'tack trace:', true)) . PHP_EOL;
			
			foreach($stacktrace as $k => $val){
				$print .= Colors::black(Colors::blue("#$k", true)). (isset($val['function']) ? "    ". ($val['class'] ?? '') . ($val['type'] ?? '') .
					($val['function'] . '(' . self::arg_to_str($val['args'] ?? [], true, self::$max_args) . ')' . PHP_EOL) : '');
				$print .= "        at ". (isset($val['file']) ? (Colors::bold_red($val['file']) .':'. Colors::white($val['line'])) : '{main}') . PHP_EOL . PHP_EOL;
			}
			
			$print .= PHP_EOL;
			(self::$echo)($print);
		}
	}
	
	public static function handler_error($errno, $errstr, $errfile, $errline, $force = false){
		if(($errno === null || $errno === 0 || error_reporting() === 0) && !(is_bool($force) && $force)) { return false; }
		
		$colors = new Colors();
		$errstr = self::str_colorer($errstr);
		$errno_str = self::errno_to_str($errno);
		
		(self::$echo)(<<<EOF

{$colors::black($colors::red($errno_str, true))}:
    $errstr
        at {$colors::bold_red($errfile)}:{$colors::white($errline)}


EOF
		);
	
		if(self::$warn_trace)
			self::stacktrace_cli();
		
		return true;
	}
	
	public static function handler_exception($e){
		self::stacktrace_cli($e);
		
		// if(self::$handler_exit)
			// call_user_func(self::$handler_exit, $e);
		
		exit(1);
	}
	
	public static function init(){
		set_exception_handler([__CLASS__, 'handler_exception']);
		set_error_handler([__CLASS__, 'handler_error']);
	}
	
	public static function warn_trace($stat = null){
		if(is_null($stat)) return self::$warn_trace;
		if(is_bool($stat)) self::$warn_trace = $stat;
	}
	
	public static function debug_level($level = null){
		if(is_null($level)) return self::$debug_level;
		if(is_numeric($level)) self::$debug_level = $level;
	}
	
	public static function setOutputFunc(callable $callable){
		self::$echo = $callable;
	}
	
	// public static function setHandler_exit(callable $callable){
		// self::$handler_exit = $callable;
	// }
	
	public static function debug($str, $level = 3, $paint = false){
		if($level <= self::$debug_level){
			(self::$echo)('['. (new \DateTime())->format('Y-m-d/H:i:s.v') .'] '.
				Colors::{self::$debug_color[$level][1]}(
					Colors::{self::$debug_color[$level][0]}(self::$debug_color[$level][2], true)
				) .': '. ($paint ? self::str_colorer($str) : $str) . PHP_EOL);
				
				if(ob_get_level()) ob_flush();
		}
	}
	
	public static function var_dump($dump, $level = 3, $paint = false){
		if($level <= self::$debug_level){
			ob_start();
			var_dump($dump);
			$str = ob_get_clean();
			
			self::debug($str, $level, $paint);
		}
	}
	
	public static function print($str, $paint = false){ self::debug($str, 1, $paint); }
	public static function error($str, $paint = false){ self::debug($str, 2, $paint); }
	public static function notice($str, $paint = false){ self::debug($str, 3, $paint); }
	public static function debug_1($str, $paint = false){ self::debug($str, 4, $paint); }
	public static function debug_2($str, $paint = false){ self::debug($str, 5, $paint); }
	public static function debug_3($str, $paint = false){ self::debug($str, 6, $paint); }
}