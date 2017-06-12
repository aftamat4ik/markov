<?php
/**
 * Алгоритм Маркова для Кириллических текстов, учитывающий пунктуацию и имена собственные
 * минимально-допустимая версия php - 5.4, обязательно расширение mbstring
 * @author Александр Штокман
 * @year 2017
 */

namespace Generator;
 
class Markov{
	# Var
	private $table = array(); // массив лексем
	private $text = ""; // базовый текст
	private $pr_count = 15; // базовый текст
	
	public $result = ""; // результат
	
	
	/**
	 * Конструктор
	 * @var $text - исходный текст
	 * @var $pr_count - кол-во генерируемых предложений
	 */
    function __construct( $text, $pr_count = 15 ){
		mb_internal_encoding("UTF-8");
		
		$this->text = $text;
		$this->pr_count = intval($pr_count);
		$this->prepare();
		$this->generate();
    }
	
	# Public
	// получение результата
	public function get_result(){
		return $this->result;
	}
	
	# Private:
	// генерация
	private function generate(){
		if(empty($this->table)) throw new Exception("Вызовите метод ->prepare перед генерацией!");
		$word = "";
		for( $i=0; $i < $this->pr_count; $i++ ){
			$word = $this->get_random_word($word,array("!",".","?"));
			// массив слов будущего предложения
			$predl = array();
			$predl[] = $this->mb_ucfirst($word); // с заглавной буквы - первое слово
			
			$prlen = rand(5,15); // средняя длина предложения от 6 до 16 слов(+1 слово, заглавное)
			
			while(!$this->in_str($word,array("!",".","?"))){ // пока не выпадет точка
				
				$word = $this->get_random_word($word);
				
				// если слово содержит точку и при этом кол-во слов в результате меньше, чем надо
				if($this->in_str($word,array("!",".","?")) && count($predl) < $prlen){
					// убираем точку
					$word = str_replace(array("!",".","?"),"",$word);
				}
				
				$predl[] = $word;
			}
			
			if(mb_strlen(end($predl)) < 4){ // если кол-во букв в последнем слове предложения меньше 4
				array_pop($predl); // удаляем это слово
				$predl[] = "."; // и добавляем в конец точку
			}
			
			$this->result .= implode(" ",$predl)." ";
		}
		
		$this->result = preg_replace('~\s([!\?\.\,])\s~u','\1 ',$this->result); // убираем пробелы перед знаками препинания
	}
	
	// подготовка
	private function prepare(){
		
		if($this->text == "") throw new Exception("Ваш текст пуст!");
		$data = $this->text;
		
		//$data = preg_replace("~([,\:\-])~u"," \$1 ",$data); // знаки препинания воспринимаем как отдельные слова, то есть добавляем перед знаком пробел и после него тоже
		
		$data = preg_replace("~(\S+)\s*[\r\n]+-+\s*[\r\n]+(\S+)~u"," \$1\$2 ",$data); // переносы объединяем
		
		$data = preg_replace('~[^a-zёа-я0-9 -!\?\.\,]~ui',' ',$data); // убираем лишнее
		$data = preg_replace('~\.+~ui','.',$data); // дубли точек и многоточия объединяем

		$words = explode(" ",$data); // разбиваем полученные данные по пробелу
		$table = array(); // строим массив пар сочетаний
		foreach($words as $key=>$word){
			if( isset($words[$key+1]) ){
				$word = trim($word);
				$word = $this->trimUpper($word, $words[$key-1]);
				$sword = $words[$key+1];
				$sword = $this->trimUpper($sword, $word);
				
				$table[$word][] = trim($sword); // пара слово -> следующее слово
				$table[$word] = array_filter($table[$word],"strlen"); // убираем пустые
				$table[$word] = array_unique($table[$word]); // убираем дубли
				/**
				 * Если слово содержит за собой один из спецсимволов - убираем символ, после чего помещаем копию слова без символа в массив
				 */
				if($this->in_str($word,array("!",".","?"))){
					$word = str_replace(array("!",".","?"),"",$word);
					$table[$word][] = trim($sword);
				}
				
			} else { /* если пар не найдено - пропускаем */ }
		}
		
		$this->table = $table;
	}
	
	// проверяет есть ли символы из массива $items в строке $str
	private function in_str($str,$items = array(".")){
		foreach($items as $item){
			if(mb_strpos($str,$item) !== false) return true;
		}
		return false;
	}
	
	// мультибайтовый аналог ucfirst
    private function mb_ucfirst($value)
    {
        return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }
	
	// убирает заглавные только в том случае, если в $previous есть знаки препинания
	private function trimUpper($word, $previous = null){
		if(preg_match("~[A-ZА-Я]~",$word)){
			/**
			 * И если предыдущее слово отсутствует или содержит .!? знак, то мы опускаем его в нижний регистр т.к. это начало предложения.
			 * Во всех остальных случаях очевидно, что заглавные буквы являются именами собственными, то есть именами людей, стран и прочего.
			 */
			if(!isset($previous) || $this->in_str($previous,array("!",".","?"))){
				$word = mb_strtolower($word);
			}
		}
		return $word;
	}
	
	// генерирует уникальное случайное слово
	private function get_random_word($word = "", $ex = array()){ // получает случайное слово
		$nw = "";
		
		if($word == ""){
			$wkeys = array_keys($this->table); // ключи, то есть первые входные слова. Используется для генерации начал предложений.
			$nw = $wkeys[array_rand($wkeys)];
		}else {
			$subw = $this->table[$word];
			if(empty($subw)){
				return $this->get_random_word("", $ex);
			}
			$nw = $subw[array_rand($subw)];
		}
		
		/**
		 * Рекурсивно исключаем дубли, слова с запрещенными символами($ex), а так-же просто пустые строчки
		 */
		if(!$nw || !empty($ex) && $this->in_str($nw,$ex) || $nw == $word){
			return $this->get_random_word($nw, $ex);
		}
		return $nw;
	}
}