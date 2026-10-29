<?php
declare(strict_types=1);
namespace ff\cli;
/**
 * 解析命令行参数。
 * 输入可以是字符串（按空格分词）或已分割的数组，返回「选项键值 + 位置参数数字索引」的数组。
 * # 支持的参数格式
 * ## 1. 短选项 (Single dash)
 * - 单个字符选项: `-a` → `['a' => true]`
 * - 组合选项: `-abc` → `['a' => true, 'b' => true, 'c' => true]`
 * - 多个独立选项: `-a -b -c` → `['a' => true, 'b' => true, 'c' => true]`
 * - 带值选项: `-f file.txt` → `['f' => 'file.txt']`（下一项不以 `-` 开头，或为数字时取值）
 * - 组合选项末位带值: `-abc value` → `['a' => true, 'b' => true, 'c' => 'value']`
 * ## 2. 长选项 (Double dash)
 * - 布尔选项: `--verbose` → `['verbose' => true]`
 * - 带值选项: `--file=test.php` → `['file' => 'test.php']`
 * - 带引号的值: `--name="John Doe"` → `['name' => 'John Doe']`
 * - 空值选项: `--empty=` → `['empty' => '']`
 * - 值中的等号保留: `--data=a=b=c` → `['data' => 'a=b=c']`
 * ## 3. 无标记参数
 * - 普通参数: `file.txt` → `[0 => 'file.txt']`
 * - 多个参数: `file1.txt file2.txt` → `[0 => 'file1.txt', 1 => 'file2.txt']`
 * ## 4. 分隔符
 * - `--` 之后全部按无标记参数处理，不再解析为选项
 * - 单独的 `-`、`---` 及无效选项格式按无标记参数保留
 * # 特性说明
 * - **短选项可带值**: 短选项与值用空格分隔；不支持 `-f=value` / `-abc=value`（会按组合短选项解析）
 * - **长选项带值用等号**: `--file test.php` 中 `test.php` 是位置参数，不是 `--file` 的值
 * - **重复选项覆盖**: 同名选项后出现的值覆盖先出现的值
 * - **字符串模式处理引号**: 单/双引号包裹的值去引号；反斜杠仅在引号或空格前转义，Windows 路径原样保留
 * - **数组模式不做分词和转义**: 值按原样使用；仅去除整体包裹值的成对引号；首项含 `.php` 时视为脚本名丢弃
 * - **值类型**: 选项值和无标记参数均为字符串，布尔选项为 `true`
 * # 使用示例
 * ```
 * // 字符串输入
 * $args = args('-v --file=test.php input.txt');
 * // 结果: ['v' => true, 'file' => 'test.php', 0 => 'input.txt']
 * // 数组输入
 * $args = args(['-abc', '--verbose', '--name=John', 'data.txt']);
 * // 结果: ['a' => true, 'b' => true, 'c' => true, 'verbose' => true, 'name' => 'John', 0 => 'data.txt']
 * // 带引号的值
 * $args = args('--message="Hello World" --path="/usr/local"');
 * // 结果: ['message' => 'Hello World', 'path' => '/usr/local']
 * // 短选项取值
 * $args = args('-f file.txt -v');
 * // 结果: ['f' => 'file.txt', 'v' => true]
 * ```
 * @param array|string $input 命令行输入，可以是：
 *                            - 字符串: 按空格分词并处理引号/转义
 *                            - 数组: 已分割的参数；首项含 `.php` 时视为脚本名丢弃
 * @return array 选项为字符串键，无标记参数为递增数字键：
 *               - 布尔选项值为 `true`
 *               - 带值选项和位置参数值为 `string`
 *               - 例如: `['v' => true, 'file' => 'test.php', 0 => 'arg1']`
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
