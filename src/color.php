<?php
declare(strict_types=1);
namespace ff\cli;

/**
 * CLI 颜色标记转 ANSI。
 * 供 i18n 格式串和测试报告使用，支持 `[字母]` 标记：
 *  - 小写=标准色：k黑 r红 g绿 y黄 b蓝 m品 c青 w白 n灰(亮黑)
 *  - 大写=亮色：K亮黑 R亮红 G亮绿 Y亮黄 B亮蓝 M亮品 C亮青 W亮白
 *  - `[r:w]` 前景红底白；`[:g]` 仅背景绿；`[ :]` 重置前景，`[: ]` 重置背景，`[ : ]`/`[:]` 重置全部
 *  - 非 CLI SAPI（PHP_SAPI !== 'cli'）剥离标记
 *  - CLI SAPI 下不检测 TTY，重定向到文件也保留 ANSI；输出末尾统一追加重置
 */
function color(string $text): string{
	$match = '/\[([krgybmcwnKRGYBMCW ]?)(:?)([krgybmcwnKRGYBMCW ]?)]/';
	if(PHP_SAPI!=='cli') return preg_replace($match, '', $text);
	return preg_replace_callback($match, static function($m){
			$fg = ['k' => 30, 'r' => 31, 'g' => 32, 'y' => 33, 'b' => 34, 'm' => 35, 'c' => 36, 'w' => 37, 'n' => 90, 'K' => 90, 'R' => 91, 'G' => 92, 'Y' => 93, 'B' => 94, 'M' => 95, 'C' => 96, 'W' => 97, ' ' => 39];
			$bg = ['k' => 40, 'r' => 41, 'g' => 42, 'y' => 43, 'b' => 44, 'm' => 45, 'c' => 46, 'w' => 47, 'K' => 100, 'R' => 101, 'G' => 102, 'Y' => 103, 'B' => 104, 'M' => 105, 'C' => 106, 'W' => 107, ' ' => 49];
			if($m[1] === '' && $m[2] === ':' && $m[3] === '') return "\033[39;49m";
			$codes = array_filter([$fg[$m[1]] ?? null, $m[2] === ':' ? ($bg[$m[3]] ?? null) : null]);
			return $codes ? "\033[" . implode(';', $codes) . "m" : '';
		}, $text) . "\033[0m";
}