<?php
namespace Arturka\CLI;

use Arturka\CLI\Debug;
use Arturka\CLI\EscapeColors as Colors;

require_once('Debug.php');
require_once('EscapeColors.php');

class Test{
	private static $exit_on_fail = true;
	private static $is_printed = false;
	private static $debug_lvl = null;
	private static $run_id = null;
	private static $cur_id = 0;
	private static $enable_profile = false;
	private static $test_complite = false;
	private static $attempts = 1;
	private const MARKER_LEN = 100;
	private const MARKER_OFFSET = 4;
	
	public static function run($name, $func){
		self::$cur_id++;
		
		if(self::$run_id && self::$run_id != self::$cur_id)
			return false;
		
		$msg = Colors::bold_cyan($name) .'"';
		$markOff = str_repeat('-', self::MARKER_OFFSET);
		$marker = $markOff.$markOff . Colors::red(Colors::magenta(str_repeat('-', self::MARKER_LEN - self::MARKER_OFFSET * 4), true)) . $markOff.$markOff . PHP_EOL;
		$marker2 = $markOff. Colors::red(Colors::blue(str_repeat('-', self::MARKER_LEN - self::MARKER_OFFSET * 2), true)) . $markOff . PHP_EOL;
		$XHRrofRuns = class_exists('XHProfRuns_Default', false) ? new XHProfRuns_Default() : null;
		
		self::$attempts = 1;
		
		for($attempt=1; self::$attempts >= $attempt; $attempt++){
			$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);
			if($attempt != 1) Debug::print('Retry test ('. $attempt .')', true);
			Debug::print('Start №'. (self::$cur_id) .' "'. $msg);
			Debug::print('CurrFile: ' . $stack[0]['file'] . ':' . $stack[0]['line']);
			
			self::$test_complite = false;
			
			ob_start(function($buffer) use($marker){ return Test::is_printed(true) ? false : PHP_EOL . ($marker . $buffer); });
				call_user_func($func, $name, $attempt);
				
				if(self::$enable_profile){
					$Profile = xhprof_disable();
					self::$enable_profile = false;
				}
			ob_end_flush();
			
			if(self::is_printed(false))
				echo $marker . PHP_EOL;
			
			if(!is_null(self::$debug_lvl))
				Debug::debug_level(self::$debug_lvl);
			
			self::$debug_lvl = null;
			
			if(self::$test_complite){
				if($Profile ?? false) Debug::notice('Profile id: '. $XHRrofRuns->save_run($Profile, 'Tests_'. $name));
				Debug::print('End "'. $msg .' '. Colors::black(Colors::green('[OK]', true)));
				echo PHP_EOL . $marker2 . PHP_EOL . PHP_EOL;
				return true;
			}else{
				Debug::print('End "'. $msg .' '. Colors::black(Colors::red('[FAIL]', true)));
				
				if(self::$attempts == $attempt){
					echo PHP_EOL . $marker2 . PHP_EOL . PHP_EOL;
					
					if(self::$exit_on_fail)
						self::shutdown();
				}
			}
		}
	}
	
	public static function attempts($num){
		if(is_numeric($num)) self::$attempts = $num;
	}
	
	public static function complite($ret){
		if(is_bool($ret)) self::$test_complite = $ret;
	}
	
	public static function debug_level($lvl){
		if(is_null(self::$debug_lvl))
			self::$debug_lvl = Debug::debug_level();
		
		Debug::debug_level($lvl);
	}
	
	public static function setXhprofRoot($path){
		$XHPROF_ROOT = 'D:/server/data/htdocs/xhprof';

		include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php";
		include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_runs.php";
	}
	
	public static function profiler(){
		self::$enable_profile = true;
		xhprof_enable();
	}
	
	public static function set_test($id){
		self::$run_id = $id;
	}
	
	public static function is_printed($stat = null){
		$tmp = self::$is_printed;
		self::$is_printed = $stat;
		return $tmp;
	}
	
	public static function exit_on_fail($stat){
		self::$exit_on_fail = (bool)$stat;
	}
	
	public static function shutdown(){
		exit(1);
	}
	
	private static $execPath;
	private static $force = false;
	public static function init($argv){
		self::$execPath = $argv[0] ?? '';
		$file = '';
		
		foreach($argv as $k => $val){
			if(!$k) continue;
			
			if($val == '--test')
				unset($argv[$k]);
			
			if($val == '-f'){
				self::$force = true;
			}elseif(substr($val, -4, 4) == '.php'){
				$file = $val;
			}elseif(is_numeric($val)){
				Test::set_test((int)$val);
			}
		}
		
		Test::exit_on_fail(!self::$force);
		
		if($file){
			require($file);
			exit(0);
		}
		
		if(count($argv) > 1 && !$file){
			Debug::error('Not passed php file, exit...');
			exit(1);
		}
	}
	
	public static function executeFile($path, &$tests){
		if(system("php ". self::$execPath .' --test '. (self::$force ? '-f ' : '') .'"'. $path. '"', $code) !== false){
			$msg = 'Test '. Colors::black(Colors::cyan("'". $path ."'", true)) .' ';
			
			if(!$code){
				Debug::print($tests[] = $msg. Colors::black(Colors::green('[SUCCESS]', true)));
			}else{
				Debug::print($tests[] = $msg. Colors::black(Colors::red('[FAILED]', true)));
			}
		}else{
			Debug::error('Fail run tests');
			
			exit(1);
		}
	}
	
	public static function executeFolder($dir, &$tests){
		foreach(scandir($dir) as $val){
			if($val != '.' && $val != '..' && substr($val, 0, 1) != '@'){
				$path = $dir .'/'. $val;
				
				if(is_dir($val)){
					self::executeFolder($path, $tests);
				}elseif(substr($val, -4, 4) == '.php'){
					self::executeFile($path, $tests);
				}
			}
		}
	}
	
	public static function execute($path){
		$tests = [];
		
		if(is_dir($path)){
			self::executeFolder($path, $tests);
		}else{
			self::executeFile($path, $tests);
		}
		
		echo PHP_EOL . PHP_EOL . PHP_EOL . Colors::red(Colors::yellow(str_repeat('-', self::MARKER_LEN), true)) . PHP_EOL;
		
		foreach($tests as $msg){
			Debug::print($msg);
		}
	}
}
