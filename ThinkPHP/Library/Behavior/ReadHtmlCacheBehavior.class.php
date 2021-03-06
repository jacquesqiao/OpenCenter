<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace Behavior;
use Think\Storage;

/**
 * 系统行为扩展：静态缓存读取
 */
class ReadHtmlCacheBehavior {
	static public function fileMTime($cacheFile) {
		static $_fmt = array();
		if (isset($_fmt[$cacheFile])) {
			return $_fmt[$cacheFile];
		}
		$_fmt[$cacheFile] = Storage::get($cacheFile, 'mtime', 'html');
		return $_fmt[$cacheFile];
	}

	/**
	 * http缓存(用于不支持etag的情况)
	 * @param  integer $mtime 修改时间戳
	 * @param  boolean $check 是否检测时间
	 * @return void
	 */
	static function httpCached($mtime, $check = true) {
		// Checking if the client is validating his cache and if it is current.
		if ($check && isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime)) {
			// Client's cache IS current, so we just respond '304 Not Modified'.
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT', true, 304);
			exit;
		} else {
			// Image not cached or cache outdated, we respond '200 OK' and output the image.
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT', true, 200);
		}
	}

	// 行为扩展的执行入口必须是run
	public function run(&$params) {
		// 开启静态缓存
		if (IS_GET && C('HTML_CACHE_ON')) {
			$cacheTime = $this->requireHtmlCache();
			if (false !== $cacheTime && $this->checkHTMLCache(HTML_FILE_NAME, $cacheTime)) {
				$mtime = self::fileMTime(HTML_FILE_NAME);
				if (!empty($_SERVER['HTTP_IF_NONE_MATCH'])) {
					if ($_SERVER['HTTP_IF_NONE_MATCH'] >= $mtime) {
						header('Etag:' . $_SERVER['HTTP_IF_NONE_MATCH'], true, 304);
						exit();
					}
					self::httpCached($mtime, false);
				} else {
					self::httpCached($mtime);
				}
				header('Etag:' . $mtime, true, 200);
				//静态页面有效
				// 读取静态页面输出
				echo Storage::read(HTML_FILE_NAME, 'html');
				exit();
			}
			$mtime = NOW_TIME + 5;
			header('Etag:' . $mtime, true, 200);
			self::httpCached($mtime, false);
		}
	}

	// 判断是否需要静态缓存
	static private function requireHtmlCache() {
		// 分析当前的静态规则
		$htmls = C('HTML_CACHE_RULES'); // 读取静态规则
		if (!empty($htmls)) {
			$htmls = array_change_key_case($htmls);
			// 静态规则文件定义格式 actionName=>array('静态规则','缓存时间','附加规则')
			// 'read'=>array('{id},{name}',60,'md5') 必须保证静态规则的唯一性 和 可判断性
			// 检测静态规则
			$controllerName = strtolower(CONTROLLER_NAME);
			$actionName = strtolower(ACTION_NAME);
			if (isset($htmls[$controllerName . ':' . $actionName])) {
				$html = $htmls[$controllerName . ':' . $actionName]; // 某个控制器的操作的静态规则
			} elseif (isset($htmls[$controllerName . ':'])) {
				// 某个控制器的静态规则
				$html = $htmls[$controllerName . ':'];
			} elseif (isset($htmls[$actionName])) {
				$html = $htmls[$actionName]; // 所有操作的静态规则
			} elseif (isset($htmls['*'])) {
				$html = $htmls['*']; // 全局静态规则
			}
			if (!empty($html)) {
				// 解读静态规则
				$rule = is_array($html) ? $html[0] : $html;
				// 以$_开头的系统变量
				$callback = function ($match) {
					switch ($match[1]) {
					case '_GET':$var = $_GET[$match[2]];
						break;
					case '_POST':$var = $_POST[$match[2]];
						break;
					case '_REQUEST':$var = $_REQUEST[$match[2]];
						break;
					case '_SERVER':$var = $_SERVER[$match[2]];
						break;
					case '_SESSION':$var = $_SESSION[$match[2]];
						break;
					case '_COOKIE':$var = $_COOKIE[$match[2]];
						break;
					}
					return (count($match) == 4) ? $match[3]($var) : $var;
				};
				$rule = preg_replace_callback('/{\$(_\w+)\.(\w+)(?:\|(\w+))?}/', $callback, $rule);
				// {ID|FUN} GET变量的简写
				$rule = preg_replace_callback('/{(\w+)\|(\w+)}/', function ($match) {return $match[2]($_GET[$match[1]]);}, $rule);
				$rule = preg_replace_callback('/{(\w+)}/', function ($match) {return $_GET[$match[1]];}, $rule);
				// 特殊系统变量
				$rule = str_ireplace(
					array('{:controller}', '{:action}', '{:module}'),
					array(CONTROLLER_NAME, ACTION_NAME, MODULE_NAME),
					$rule);
				// {|FUN} 单独使用函数
				$rule = preg_replace_callback('/{|(\w+)}/', function ($match) {return $match[1]();}, $rule);
				$cacheTime = C('HTML_CACHE_TIME', null, 60);
				if (is_array($html)) {
					if (!empty($html[2])) {
						$rule = $html[2]($rule);
					}
					// 应用附加函数
					$cacheTime = isset($html[1]) ? $html[1] : $cacheTime; // 缓存有效期
				} else {
					$cacheTime = $cacheTime;
				}

				// 当前缓存文件
				define('HTML_FILE_NAME', HTML_PATH . $rule . C('HTML_FILE_SUFFIX', null, '.html'));
				return $cacheTime;
			}
		}
		// 无需缓存
		return false;
	}

	/**
	 * 检查静态HTML文件是否有效
	 * 如果无效需要重新更新
	 * @access public
	 * @param string $cacheFile  静态文件名
	 * @param integer $cacheTime  缓存有效期
	 * @return boolean
	 */
	static public function checkHTMLCache($cacheFile = '', $cacheTime = '') {
		if (!is_file($cacheFile) && 'sae' != APP_MODE) {
			return false;
		} elseif (filemtime(\Think\Think::instance('Think\View')->parseTemplate()) > self::fileMTime($cacheFile)) {
			// 模板文件如果更新静态文件需要更新
			return false;
		} elseif (!is_numeric($cacheTime) && is_callable($cacheTime)) {
			return $cacheTime($cacheFile);
		} elseif ($cacheTime != 0 && NOW_TIME > self::fileMTime($cacheFile) + $cacheTime) {
			// 文件是否在有效期
			return false;
		}
		//静态文件有效
		return true;
	}

}