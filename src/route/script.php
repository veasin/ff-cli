<?php
declare(strict_types=1);
namespace ff\cli\route;

use function ff\{container, i18n, output};
use function ff\cli\color;

/**
 * 脚本命令编排，类同 rest()：rest() 对 HTTP，script() 对命令行脚本。
 * 基于 route() 的 cli: 协议，将命令配置树展开为路由键数组。
 * 配置规则：
 * - 无 key 值六类：
 *   string            → 描述，多条换行合并
 *   array(list)       → callable 展平进本层栈（非 callable 忽略）
 *   array(string-key) → args 声明，多条合并（仅用于 help 显示，不参与解析/校验）
 *   callable          → 进本层栈
 *   false             → 本层 help 拦截：顶层无参数或 --help 时直接 help，不执行本层闭包；子层命中即 help
 *   true              → 在本层追加 unknown + help（未知子命令）
 * - args 声明的 `f|file` 只是 help 别名，实际参数键由 args() 决定（`-f` → `f`，`--file` → `file`），不会自动映射
 * - 特殊键 '' / '?'：
 *   ''  值可为 false/闭包/闭包数组；[] 等价 false；显式覆盖 false 简写
 *   '?' 值可为 true/闭包/闭包数组；[] 等价 true；显式覆盖 true 简写
 *   闭包数组最后一项为业务终点，前面的项作为前置中间件；业务终点会被 help 检查包裹
 *   '' => true、'?' => false 等类型错配会在展开时抛 InvalidArgumentException
 * - 每层展开（按此顺序）：
 *   ''  → cli:<path>          （有 '' 或 false）
 *   '?' → cli:<path> *        （有 '?' 或 true；顶层为 cli:*）
 *   固定子命令 → 递归（按配置顺序）
 *   叶子层（无 ''/?'/子命令，但栈非空）→ cli:<path>，handler = 父继承栈 + 本层栈
 * - 父层 callable 栈向下继承，作为子层所有路由的前置中间件
 * - 无优先级排序，靠 $next() 阻断；栈中最后一个闭包通常作为业务终点
 * - 所有 handler 签名 fn($next)，与 middleware() 约定一致；业务终点不调 $next，从 container('#in.params') 取输入
 * - help 拦截：
 *     叶子路由或显式 ''/'?' 业务闭包命中 --help 时，输出该层 help 并阻断业务（不支持 -h）
 *     false 在 --help 或顶层无参数时直接输出 help，不执行业务闭包；有参数时执行本层闭包，闭包缺参可调用 $next() 落到 help
 *     true 简写的未知命令 handler 总是先输出 unknown 再输出 help，--help 不改变该顺序
 * - help 输出当前层 + 所有下级，说明列全局对齐（颜色标记不计宽）；尾注仅顶层且未声明 help 选项时输出
 * - 固定文案走 i18n（#ff.cli.script.help / #ff.cli.script.unknown），未配回落默认中文；输出前统一 color()
 * 简单脚本（无子命令）：
 * ```
 * route(script([
 *     '文件处理工具',
 *     ['file' => '目标文件', 'force' => '强制'],
 *     false,                                      // 无参数 / --help → help
 *     function($next){
 *         $params = container('#in.params') ?? [];
 *         if(!isset($params['file'])) return $next(); // 缺参 → help
 *         output('处理：'.$params['file']);
 *     },
 * ]));
 * // php a.php --file=x.txt → 处理：x.txt
 * // php a.php              → 输出 help
 * // php a.php --help       → 输出 help
 * ```
 * 多子命令 + 嵌套 + 中间件 + 未知命令：
 * ```
 * route(script([
 *     '项目管理',
 *     ['v|verbose' => '详细日志'],
 *     fn($next) => $next(),                        // 中间件，作用于所有路由
 *     false,                                       // 无命令 → help
 *     true,                                        // 未知命令 → unknown + help
 *     'list' => [
 *         '列出文件',
 *         ['a|all' => '含隐藏'],
 *         fn($next) => output('列出文件'),
 *     ],
 *     'remote' => [
 *         '远程管理',
 *         false, true,
 *         'add' => fn($next) => output('添加远程'),
 *         'rm'  => fn($next) => output('删除远程'),
 *     ],
 * ]));
 * // php a.php list             → 列出文件
 * // php a.php list --help      → list 的 help
 * // php a.php remote           → remote 的 help
 * // php a.php remote add       → 添加远程
 * // php a.php foo              → 未知命令：foo + 顶层 help
 * ```
 * 自定义 '' / '?' 行为（闭包链）：
 * ```
 * script([
 *     ''  => fn($next) => output('无命令，自定义提示'),
 *     '?' => fn($next) => output('未知命令，自定义提示'),
 * ]);
 * ```
 * @param array $config 命令配置树
 * @return array route() 可接受的路由键数组
 */
function script(array $config): array{
	// 可见长度：剥离 [fg:bg] 标记后计字符数
	static $visible = fn(string $s): int => strlen(preg_replace('/\[([krgybmcwnKRGYBMCW ]?)(:?)([krgybmcwnKRGYBMCW ]?)]/', '', $s));
	// 选项名格式化：f|file → -f, --file
	static $fmt = fn(string $k): string => explode('|', $k)
			|> (fn($x) => array_map(fn($n) => strlen($n) === 1 ? "-$n" : "--$n", $x))
			|> (fn($x) => implode(', ', $x));
	$help = function(array $meta) use ($visible, $fmt): callable{
		$script = basename($_SERVER['argv'][0] ?? 'script');
		// 收集所有名字行的可见宽度（缩进 + 格式化后的名字）
		$widths = [];
		$collect = function(array $meta, int $indent) use (&$collect, &$widths, $visible, $fmt): void{
			$pad = str_repeat('  ', $indent);
			foreach($meta['args'] ?? [] as $k => $_){
				$widths[] = strlen($pad) + 2 + $visible($fmt($k));   // ← 用 fmt 后的名字
			}
			foreach($meta['subs'] ?? [] as $name => $sub){
				$widths[] = strlen($pad) + 2 + $visible($name);
				$collect($sub, $indent + 1);
			}
		};
		$collect($meta, 0);
		$col = $widths ? max($widths) + 2 : 0;
		$render = function(array $meta, int $indent = 0, bool $top = false) use (&$render, $col, $visible, $fmt): array{
			$pad = str_repeat('  ', $indent);
			$lines = [];
			// 描述
			if(!empty($meta['desc'])){
				$lines[] = '';
				$lines[] = $pad . implode("\n$pad", $meta['desc']);
				$lines[] = '';
			}
			foreach($meta['args'] ?? [] as $k => $d){
				$plain = $fmt($k);
				$lines[] = $pad . '  [c]' . $plain . '[:]' . str_repeat(' ', max(0, $col - strlen($pad) - 2 - $visible($plain))) . $d;
			}
			foreach($meta['subs'] ?? [] as $name => $sub){
				$first = $sub['desc'][0] ?? '';
				$lines[] = $pad . '  [g]' . $name . '[:]' . str_repeat(' ', max(0, $col - strlen($pad) - 2 - $visible($name))) . $first;
				$inner = $render($sub, $indent + 1);
				if(($inner[0] ?? null) === '') array_shift($inner);   // 开头空行
				if(!empty($sub['desc'])) array_shift($inner);   // 描述（已作摘要）
				foreach($inner as $l) if($l !== '') $lines[] = $l;
			}
			if($top && !isset($meta['args']['help'])){
				$lines[] = '';
				$lines[] = i18n('#ff.cli.script.help');
				$lines[] = '';
			}
			return $lines;
		};
		$top = $meta['path'] === '';
		return fn($next) => $render($meta, 0, $top)
				|> (fn($x) => implode("\n", $x))
				|> color(...)
				|> output(...);
	};
	$unknown = fn(array $meta, callable $helpFn) => function($next) use ($meta, $helpFn){
		$offset = $meta['path'] === '' ? 0 : count(explode(' ', $meta['path']));
		$name = (container('#in.params') ?? [])[$offset] ?? '';
		i18n('#ff.cli.script.unknown', ['name' => $name])
			|> color(...)
			|> output(...);
		return $helpFn($next);
	};
	$withHelp = fn(callable $business, callable $helpFn) => function($next) use ($business, $helpFn){
		if(!empty((container('#in.params') ?? [])['help'])) return $helpFn($next);
		return $business($next);
	};
	$gate = fn(array $meta, callable $helpFn) => function($next) use ($meta, $helpFn){
		$params = container('#in.params') ?? [];
		if(!empty($params['help'])) return $helpFn($next);
		if($meta['path'] !== '' || !$params) return $helpFn($next);
		return $next();
	};
	$extract = function(array $list) use (&$extract): array{
		$desc = [];
		$args = [];
		$subs = [];
		foreach($list as $k => $v){
			if(is_int($k)){
				if(is_string($v)) $desc[] = $v;
				elseif(is_array($v) && !array_is_list($v)) $args = array_merge($args, $v);
				continue;
			}
			if($k === '' || $k === '?') continue;
			$subs[$k] = is_callable($v) ? ['desc' => [], 'args' => [], 'subs' => []] : $extract((array)$v);
		}
		return ['desc' => $desc, 'args' => $args, 'subs' => $subs];
	};
	$routes = [];
	$expand = function(array $list, string $path, array $inherited = []) use (&$expand, &$routes, $help, $unknown, $withHelp, $gate, $extract): void{
		$desc = [];
		$args = [];
		$stack = [];
		$subs = [];
		$empty = null;
		$unk = null;
		foreach($list as $k => $v){
			if(is_int($k)){
				match (true) {
					is_string($v) => $desc[] = $v,
					is_callable($v) => $stack[] = $v,
					$v === false => $empty ??= false,
					$v === true => $unk ??= true,
					is_array($v) && array_is_list($v) => $stack = [...$stack, ...array_filter($v, 'is_callable')],
					is_array($v) => $args = array_merge($args, $v),
					default => null,
				};
				continue;
			}
			if($k === ''){
				if($v !== false && !is_callable($v) && !is_array($v))
					throw new \InvalidArgumentException("script(): 键 '' 只接受 false/callable/array，收到 " . get_debug_type($v));
				$empty = $v === [] ? false : $v;
				continue;
			}
			if($k === '?'){
				if($v !== true && !is_callable($v) && !is_array($v))
					throw new \InvalidArgumentException("script(): 键 '?' 只接受 true/callable/array，收到 " . get_debug_type($v));
				$unk = $v === [] ? true : $v;
				continue;
			}
			$subs[$k] = $v;
		}
		$prefix = [...$inherited, ...$stack];
		$meta = ['path' => $path, 'desc' => $desc, 'args' => $args, 'subs' => []];
		foreach($subs as $word => $sub){
			$meta['subs'][$word] = is_callable($sub) ? ['desc' => [], 'args' => [], 'subs' => []] : $extract((array)$sub);
		}
		if($empty !== null){
			if($empty === false){
				$helpFn = $help($meta);
				$handler = [$gate($meta, $helpFn), ...$stack, $helpFn];
				$routes["cli:$path"] = [...$inherited, ...$handler];
			}
			else{
				$arr = (array)$empty;
				$handler = [...array_slice($arr, 0, -1), $withHelp(end($arr), $help($meta))];
				$routes["cli:$path"] = [...$prefix, ...$handler];
			}
		}
		if($unk !== null){
			if($unk === true) $handler = [$unknown($meta, $help($meta))];
			else{
				$arr = (array)$unk;
				$handler = [...array_slice($arr, 0, -1), $withHelp(end($arr), $help($meta))];
			}
			$routes[$path === '' ? 'cli:*' : "cli:$path *"] = [...$prefix, ...$handler];
		}
		foreach($subs as $word => $sub){
			$expand(is_callable($sub) ? [$sub] : (array)$sub, ltrim("$path $word", ' '), $prefix);
		}
		if($empty === null && $unk === null && !$subs && $stack){
			$business = array_pop($prefix);
			$routes["cli:$path"] = [...$prefix, $withHelp($business, $help($meta))];
		}
	};
	$expand($config, '');
	return $routes;
}