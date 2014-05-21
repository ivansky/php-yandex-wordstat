<?php

class YADWordstat {

	const JSON_URL = 'https://api.direct.yandex.ru/v4/json/';

	public $login;
	private static $redis = null;
	
	public $errors = array(
		30 => 'Массив слов пустой',
		31 => 'Попытка создать 6-ой отчет',
		56 => 'Превышен лимит запросов',
		71 => 'Параметры запроса указаны неверно',
		152 => 'Не достаточно баллов'
	);

	private $request = array('locale' => 'ru');

	private $wait = array();
	private $complete = array();

	public static function pdo($__pdo){
		self::$pdo = $__pdo;
	}

	public static function redis(Redis &$__redis){
		self::$redis = $__redis;
	}

	public function __construct($login, $certdir){
		$this->login = $login;
		$this->certdir = $certdir;
		$this->request['login'] = $this->login;
	}

	public function units(){
		$crc = crc32(sprintf('yad_units_%s', $this->login));
		if(self::$redis && $units = self::$redis->get($crc)){
			return $units;
		}
		$this->request['method'] = 'GetClientsUnits';
		$this->request['param'] = array($this->login);
		$this->request['login'] = $this->login;
		$response = $this->request();

		$units = (int)$response->data[0]->UnitsRest;

		if(self::$redis){
			self::$redis->set($crc, $units, 3600 * 2);
		}
		return $units;
	}

	public function getReportList(){
		$this->request['method'] = 'GetWordstatReportList';
		$this->request['param'] = array();
		$this->request['login'] = $this->login;
		$response = $this->request();
		//var_dump($response);
		if(isset($response->data)){
			return $response->data;
		}
		return false;
	}

	public function getReport($id){
		$this->request['method'] = 'GetWordstatReport';
		$this->request['param'] = (int)$id;
		$response = $this->request();
		if(isset($response->data)){
			return $response->data;
		}
		return false;
	}
	
	public function getReportInfo($id){
		$report = $this->getReport($id);
		$data = $report->data;
		
	}

	public function deleteReport($id){
  		$this->request['method'] = 'DeleteWordstatReport';
		$this->request['param'] = (int)$id;
		$this->request['login'] = $this->login;
		$response = $this->request();
		return $response->data;
	}
	
	/**
	 * Remove from string all invalid characters
	 * Leave only English, Russian, Turkish, Kazakh and Numbers
	 * @link http://www.unicode.org/charts/
	 * @param string $w
	 * @return string
	 */
	public static function prepareWord($w){
		// АаБбВвГгДдЕеЁёЖжЗзИиЙйКкЛлМм
		// НнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъ
		// ЫыЬьЭэЮюЯя
		$RUSSIAN = '\\x{0410}-\\x{045F}'; 
		
		// çğışöü ÇĞİŞÖÜ
		$TURKISH = '\\x{00E7}\\x{011F}\\x{0131}\\x{015F}'.
			'\\x{00F6}\\x{00FC}\\x{00C7}\\x{011E}'.
			'\\x{0130}\\x{015E}\\x{00D6}\\x{00DC}';
		
		//ӘҒҚҢӨҮҰҺІ әғқңөүұһі
		$KAZAKH = '\\x{04A2}\\x{04A3}\\x{0406}\\x{0456}'.
			'\\x{0492}\\x{0493}\\x{049A}\\x{049B}'.
			'\\x{04AE}\\x{04AF}\\x{04B0}\\x{04B1}'.
			'\\x{04BA}\\x{04BB}\\x{04D8}\\x{04D9}'.
			'\\x{04E8}\\x{04E9}'; 
		
		$w = preg_replace('/[^a-z0-9'.$RUSSIAN.$TURKISH.$KAZAKH.']+/ui', ' ', $w);
		$w = preg_replace('/[\s]+/', ' ', $w);
		return trim($w);
	}

	public function delReport($id){
		return $this->deleteReport($id);
	}

	public function removeReport($id){
		return $this->deleteReport($id);
	}

	public function createReport(array $keywords = array(), array $regions = array()){

		foreach($keywords as $k=>$v)
			$keywords[$k] = self::magicUTF8($v);

		if($keywords && count($keywords)){

			$this->request['method'] = 'CreateNewWordstatReport';
			$this->request['param'] = array(
			   'Phrases' => $keywords,
			   'GeoID' => $regions
			);

			$this->request['login'] = $this->login;
			$response = $this->request();
			
			if(isset($response->error_code)){
				$error_code = (int)$response->error_code;
				var_dump($response);
				return (0 - $error_code);
			}

			if(!isset($response->data)) return false;
			
			return $response->data;
		}

		return false;
	}

	private function request(){
		$request = json_encode($this->request);

		# параметры запроса
		$opts = array(
			'http'=>array(
				'method'	=> 'POST',
				'content'	=> $request,
				'header' 	=> 'Content-type: application/json; charset=utf-8' . "\r\n"
			)
		);

		# создание контекста потока
		$context = stream_context_create($opts);

		# подключаем объединенный с приватным ключом сертификат
		stream_context_set_option($context, 'ssl', 'local_cert', $this->certdir . '/solid-cert.crt');

		# отправляем запрос и получаем ответ от сервера
		$result = file_get_contents(self::JSON_URL, 0, $context);

		return json_decode($result);
	}

	public static function magicUTF8($s){
		$s = iconv('utf-8', 'windows-1251', $s);
		$s = iconv('windows-1251', 'utf-8', $s);
		$s = iconv('ISO-8859-1', 'utf-8', $s);
		return $s;
	}

	public static function crc($keyword, $region, $strict = 0){
		$strict = (int)$strict;
		return crc32('yafreq_'.str_replace(' ','-',mb_strtolower($keyword,'UTF-8')).'_'.$region.'_'.$strict);
	}

}
