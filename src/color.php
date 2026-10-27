<?php
declare(strict_types=1);
namespace ff\cli;
/**
 * 颜色标记转 ANSI。
 * 标记格式：{red}text{/red}、{bold}text{/bold}
 * 非 TTY 环境自动剥离标记。
 */
function color(string $text): string{
	if(!stream_isatty(STDOUT)) return strip_colors($text);
	$map = [
		'black' => '0;30', 'red' => '0;31', 'green' => '0;32', 'yellow' => '0;33',
		'blue' => '0;34', 'magenta' => '0;35', 'cyan' => '0;36', 'white' => '0;37',
		'bold' => '1', 'dim' => '2', 'italic' => '3', 'underline' => '4',
	];
	return preg_replace_callback('/\{(\w+)\}(.*?)\{\/\1\}/', function($m) use ($map){
		$code = $map[$m[1]] ?? '';
		return $code !== '' ? "\e[{$code}m{$m[2]}\e[0m" : $m[2];
	}, $text);
}
function strip_colors(string $text): string{
	return preg_replace('/\{(\w+)\}(.*?)\{\/\1\}/s', '$2', $text);
}
