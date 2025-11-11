<?php
/**
 * 测试打印方法
 * @FileName: test_helper.php
 * @Author: TekinTian
 * @QQ: 3316872019
 * @Email: tekintian@gmail.com
 * @Supported: http://dev.yunnan.ws/
 * @Date:   2017-01-23 11:45:26
 * @Last Modified 2018-06-30
 */

if (!function_exists('dd')) {
	/**
	 * 测试打印函数
	 * @param  [type] $arr [description]
	 * @return [type]      [description]
	 */
	function dd($arr) {
		if (is_array($arr)) {
			echo "<pre>";
			print_r($arr);
			echo "</pre>";
		} else if (is_object($arr)) {
			echo "<pre>";
			print_r($arr);
			echo "</pre>";

		} else {
			echo $arr;
		}
		die;
	}
}

if (!function_exists('pp')) {
	/**
	 * 格式化输出
	 * @param  [type] $arr [description]
	 * @return [type]      [description]
	 */
	function pp($arr) {
		if (is_array($arr)) {
			echo "<pre>";
			print_r($arr);
			echo "</pre>";
		} else if (is_object($arr)) {
			echo "<pre>";
			print_r($arr);
			echo "</pre>";

		} else {
			echo $arr;
		}
		die;
	}
}

if (!function_exists('pr')) {
	/**
	 * 打印不中断
	 */
	function pr($arr) {
		if (is_array($arr)) {
			echo "<pre>";
			print_r($arr);
			echo "</pre>";
		} else if (is_object($arr)) {
			echo "<pre>";
			print_r($arr);
			echo "</pre>";

		} else {
			echo $arr;
		}
	}
}

if (!function_exists('console_log')) {
	/**
	 * console.log控制台调试函数
	 * @param  [type] $data [要在控制台输出的数据 支持数组、对象和字符串]
	 * @return [type]       [description]
	 */
	function console_log($data) {
		if (is_array($data) || is_object($data)) {
			echo ("<script>console.log('" . json_encode($data) . "');</script>");
		} else {
			echo ("<script>console.log('" . $data . "');</script>");
		}
	}
}

if (!function_exists('vd')) {
	/**
	 * 测试打印函数
	 * @param  [type] $arr [description]
	 * @return [type]      [description]
	 */
	function vd($arr) {
		echo "<pre>";
		var_dump($arr);
		echo "</pre>";
		die;
	}
}

if (!function_exists('vv')) {
	function vv($arr) {
		echo "<pre>";
		var_dump($arr);
		echo "</pre>";
	}
}

/**
 * 浏览器友好的变量输出,便于调试时候使用
 *
 * @param     mixed   $var       要输出查看的内容
 * @param     bool    $echo      是否直接输出
 * @param     string  $label     加上说明标签,如果有,这显示"标签名:"这种形式
 * @param     bool    $strict    是否严格过滤
 * @return    string
 */
if (!function_exists('dump')) {
	function dump($var, $echo = true, $label = null, $strict = true) {
		$label = ($label === null) ? '' : rtrim($label) . ' ';
		if (!$strict) {
			if (ini_get('html_errors')) {
				$output = print_r($var, true);
				$output = "<pre>" . $label . htmlspecialchars($output, ENT_QUOTES) . "</pre>";
			} else {
				$output = $label . " : " . print_r($var, true);
			}
		} else {
			ob_start();
			var_dump($var);
			$output = ob_get_clean();
			if (!extension_loaded('xdebug')) {
				$output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
				$output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
			}
		}
		if ($echo) {
			echo ($output);
			return null;
		} else {
			return $output;
		}

	}
}

if (!function_exists('get_content_from_url')) {
	/**
	 * 从指定URL获取内容，并可选择性地缓存到本地文件
	 * 
	 * @param string $url 要获取内容的URL地址
	 * @param string|null $saveToFile 可选，用于缓存内容的本地文件路径
	 * @return string|null 返回获取到的内容，失败时返回null
	 * @throws Exception 网络请求失败时抛出异常
	 */
	function get_content_from_url($url, $saveToFile = null, $timeout = 10)
	{
		try {
			if ($saveToFile && file_exists($saveToFile)) {
				$response = file_get_contents($saveToFile);
				if ($response !== false) {
					return $response;
				}
			}

			$context = stream_context_create([
				'http' => [
                    'timeout' => $timeout,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'header' => "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                               "Accept-Language: zh-CN,zh;q=0.8,en-US;q=0.5,en;q=0.3\r\n" .
                               "Connection: close\r\n"
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
			]);

			$response = @file_get_contents($url, false, $context);
			if ($response === false) {
				echo "❌ 请求失败\n";
                $error = error_get_last();
                if ($error) {
                    echo "错误信息: " . $error['message'] . "\n";
                }
				return null;
			}
			
			// 确保目录存在
			@mkdir(dirname($saveToFile), 0755, true);
			// 写入文件
			file_put_contents($saveToFile, $response);

			return $response;
		} catch (Exception $e) {
			// 忽略网络错误
		}
	}
}