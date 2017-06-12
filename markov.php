<?php
/**
 * Генератор текста на цепях Маркова
 */
header('Content-Type: text/html; charset=utf-8'); 

require_once("class_markov.php");

$data = file_get_contents("tz.txt");

$mk = new \Generator\Markov($data,15);
$text = $mk->get_result();

echo "<p style='word-wrap: break-word;'>".$text."</p>";