<?php
declare(strict_types=1);
namespace ff\cli;
/**
 * 解析命令行参数
 * 该函数用于解析命令行传入的参数，支持多种格式的选项和参数。
 * 可以处理字符串格式或数组格式的输入，返回解析后的结构化数组。
 * # 支持的参数格式
 * ## 1. 短选项 (Single dash)
 * - 单个字符选项: `-a` → `['a' => true]`
 * - 组合选项: `-abc` → `['a' => true, 'b' => true, 'c' => true]`
 * - 多个独立选项: `-a -b -c` → `['a' => true, 'b' => true, 'c' => true]`
 * ## 2. 长选项 (Double dash)
 * - 布尔选项: `--verbose` → `['verbose' => true]`
 * - 带值选项: `--file=test.php` → `['file' => 'test.php']`
 * - 带引号的值: `--name=\"John Doe\"` → `['name' => 'John Doe']`
 * - 空值选项: `--empty=` → `['empty' => '']
 * ## 3. 无标记参数
 * - 普通参数: `file.txt` → `[0 => 'file.txt']`
 * - 多个参数: `file1.txt file2.txt` → `[0 => 'file1.txt', 1 => 'file2.txt']`
 * # 特性说明
 * - **自动去除引号**: 值如果被单引号或双引号包围，会自动去除
 * - **保持数据类型**: 所有值都以字符串形式返回，布尔选项返回 `true`
 * - **混合模式**: 可以同时使用短选项、长选项和无标记参数
 * - **数组输入**: 可以直接传入预分割的参数数组
 * # 使用示例
 * ```
 * // 字符串输入
 * $args = args('-v --file=test.php input.txt');
 * // 结果: ['v' => true, 'file' => 'test.php', 'input.txt']
 * // 数组输入
 * $args = args(['-abc', '--verbose', '--name=John', 'data.txt']);
 * // 结果: ['a' => true, 'b' => true, 'c' => true, 'verbose' => true, 'name' => 'John', 'data.txt']
 * // 带引号的值
 * $args = args('--message=\"Hello World\" --path=\'/usr/local\'');
 * // 结果: ['message' => 'Hello World', 'path' => '/usr/local']
 * ```
 * @param array|string $input 命令行输入，可以是：
 *                            - 字符串: 如 `'-v --file=test.php arg1'`
 *                            - 数组: 如 `['-v', '--file=test.php', 'arg1']`
 * @return array 解析后的参数数组，结构如下：
 *                            - 选项以键值对形式存储，布尔选项值为 `true`
 *                            - 无标记参数以数字索引存储
 *                            - 例如: `['v' => true, 'file' => 'test.php', 0 => 'arg1']`
 * @internal
 * @see   https://www.php.net/manual/en/features.commandline.php PHP命令行用法
 * @see   https://www.gnu.org/software/libc/manual/html_node/Argument-Syntax.html GNU参数语法标准
 */
function args(array|string $input): array{
	if(empty($input)) return [];
	// 解析字符串为数组
	if(is_string($input)){
		$args = [];
		$current = '';
		$inQuotes = false;
		$quoteChar = '';
		$escaped = false;
		$len = strlen($input);
		for($i = 0; $i < $len; $i++){
			$c = $input[$i];
			if($escaped){
				$current .= $c;
				$escaped = false;
				continue;
			}
			// 只有在需要转义引号或空格时才处理反斜杠
			if($c === '\\'){
				$next = $i + 1 < $len ? $input[$i + 1] : '';
				if($next === '"' || $next === "'" || $next === ' '){
					$escaped = true;
					continue;
				}
				// 否则保留反斜杠（Windows路径）
				$current .= $c;
				continue;
			}
			if(($c === '"' || $c === "'") && !$inQuotes){
				$inQuotes = true;
				$quoteChar = $c;
				continue;
			}
			if($c === $quoteChar && $inQuotes){
				$inQuotes = false;
				$quoteChar = '';
				continue;
			}
			if($c === ' ' && !$inQuotes){
				if($current !== ''){
					$args[] = $current;
					$current = '';
				}
				continue;
			}
			$current .= $c;
		}
		if($current !== '') $args[] = $current;
	}
	else{
		$args = $input;
		if(isset($args[0]) && str_contains($args[0], '.php')) array_shift($args);
	}
	$result = [];
	$i = 0;
	$stop = false;
	$n = count($args);
	$unquote = function($v){
		if(!is_string($v)) return $v;
		$len = strlen($v);
		if($len > 1 && (($v[0] === '"' && $v[$len - 1] === '"') || ($v[0] === "'" && $v[$len - 1] === "'"))) return substr($v, 1, -1);
		return $v;
	};
	while($i < $n){
		$arg = $args[$i];
		// 分隔符处理
		if(!$stop && $arg === '--'){
			$stop = true;
			$i++;
			continue;
		}
		if($stop || !str_starts_with($arg, '-') || $arg === '-' || preg_match('/^-{3,}$/', $arg)){
			$result[] = $arg;
			$i++;
			continue;
		}
		// 长选项处理
		if(str_starts_with($arg, '--')){
			$opt = substr($arg, 2);
			if($opt === '' || $opt[0] === '='){
				$result[] = $arg;
				$i++;
				continue;
			}
			if(str_contains($arg, '=')){
				[$key, $val] = explode('=', $opt, 2) + [1 => ''];
				$result[$key] = $unquote($val);
			}
			else $result[$opt] = true;
			$i++;
			continue;
		}
		// 短选项处理
		$opts = substr($arg, 1);
		if($opts === '' || $opts[0] === '=' || !preg_match('/^[a-zA-Z]+$/', str_replace(['=', '_'], '', $opts))){
			$result[] = $arg;
			$i++;
			continue;
		}
		if(strlen($opts) === 1){
			// 单个短选项
			$key = $opts;
			if($i + 1 < $n && (!str_starts_with($args[$i + 1], '-') || is_numeric($args[$i + 1]))) $result[$key] = $unquote($args[++$i]);
			else $result[$key] = true;
		}
		else{
			// 组合短选项
			for($j = 0; $j < strlen($opts) - 1; $j++) $result[$opts[$j]] = true;
			$last = $opts[-1];
			if($i + 1 < $n && (!str_starts_with($args[$i + 1], '-') || is_numeric($args[$i + 1]))) $result[$last] = $unquote($args[++$i]);
			else $result[$last] = true;
		}
		$i++;
	}
	return $result;
}
