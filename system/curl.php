<?php

use BaseFrame\Exception\Domain\ReturnFatalException;
use BaseFrame\Exception\Domain\UnsupportedProxyProtocol;
use BaseFrame\Exception\Domain\InvalidUrl;

/**
 * новый класс curl
 * !! Тестим здесь: http://httpbin.org/
 */
class Curl
{
	protected ?CurlHandle $_curl = null;

	protected int $_response_code = 0;

	protected string $_redirect_url = "";

	protected string $_content_type = "";

	protected string $_effective_url = "";

	protected string $_user_agent = "Robot";

	protected string $_accept_language = "";

	protected array $_cookies = [];

	protected array $_extra_headers = [];

	protected array $_response_headers = [];

	protected int $_timeout = 30;

	protected mixed $_options = [
		"url" => null,
	];

	protected string $_ca_certificate = "";

	// поддерживаемые протоколы прокси
	public const PROXY_PROTOCOL_HTTP    = "http";
	public const PROXY_PROTOCOL_HTTPS   = "https";
	public const PROXY_PROTOCOL_SOCKS5  = "socks5";
	public const PROXY_PROTOCOL_SOCKS5H = "socks5h";

	// поддерживаемые протоколы прокси
	public const ALLOWED_PROXY_PROTOCOLS = [
		Curl::PROXY_PROTOCOL_HTTP,
		Curl::PROXY_PROTOCOL_HTTPS,
		Curl::PROXY_PROTOCOL_SOCKS5,
		Curl::PROXY_PROTOCOL_SOCKS5H
	];

	/**
	 * конструктор
	 * @throws returnException
	 */
	public function __construct()
	{

		if (!extension_loaded("curl")) {
			throw new ReturnFatalException("cURL library is not loaded");
		}

		if (is_null($this->_curl)) {
			$this->_curl = curl_init();
		}

		curl_setopt($this->_curl, CURLOPT_HEADER, true);
		curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->_curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($this->_curl, CURLOPT_SSL_VERIFYPEER, false);
	}

	/**
	 * destructor
	 */
	public function __destruct()
	{

		curl_close($this->_curl);
	}

	/**
	 * Устанавливаем CA сертификат
	 */
	public function setCaCertificate(string $ca_certificate): void
	{
		$this->_ca_certificate = $ca_certificate;
	}

	/**
	 * устанавливаем user_agent
	 *
	 * @return $this
	 */
	public function setUserAgent(string $user_agent): self
	{

		$this->_user_agent = $user_agent;
		return $this;
	}

	/**
	 * Установить хедер принимаемого языка
	 *
	 * @return $this
	 */
	public function setAcceptLanguage(string $lang): self
	{

		$this->_accept_language = $lang;
		return $this;
	}

	/**
	 * устанавливаем таймаут
	 *
	 * @return $this
	 */
	public function setTimeout(int $_timeout): self
	{

		$this->_timeout = $_timeout;
		return $this;
	}

	/**
	 * добавить куку
	 *
	 * @return $this
	 */
	public function addCookie(string $key, string $value): self
	{

		$this->_cookies[$key] = $value;
		return $this;
	}

	/**
	 * Установить прокси
	 */
	public function setProxy(string $protocol, string $host, int $port, ?string $username = null, ?string $password = null): self
	{

		// отсекаем неподдерживаемые протоколы
		$proxy_protocol_opt = match ($protocol) {
			self::PROXY_PROTOCOL_HTTP    => CURLPROXY_HTTP,
			self::PROXY_PROTOCOL_HTTPS   => CURLPROXY_HTTPS,
			self::PROXY_PROTOCOL_SOCKS5  => CURLPROXY_SOCKS5,
			self::PROXY_PROTOCOL_SOCKS5H => CURLPROXY_SOCKS5_HOSTNAME,
			default                      => throw new UnsupportedProxyProtocol("unknown protocol passed")
		};

		// устанавливаем тип прокси
		curl_setopt($this->_curl, CURLOPT_PROXYTYPE, $proxy_protocol_opt);

		// парсим хост прокси
		$full_host_address = "$host:$port";
		$parsed_host       = parse_url($full_host_address);

		if (!isset($parsed_host["host"], $parsed_host["port"])) {
			throw new InvalidUrl("cant find host or port");
		}

		curl_setopt($this->_curl, CURLOPT_PROXY, $full_host_address);

		// если передали username, добавляем
		if ($username) {
			curl_setopt($this->_curl, CURLOPT_PROXYUSERPWD, "$username:$password");
		}

		// если выбран http протокол, включаем прямой туннель до https узла
		if ($protocol == self::PROXY_PROTOCOL_HTTP) {
			curl_setopt($this->_curl, CURLOPT_HTTPPROXYTUNNEL, true);
		}

		return $this;
	}

	/**
	 * получить куки
	 */
	public function getCookies(): array
	{

		return $this->_cookies;
	}

	/**
	 * get запрос
	 *
	 * @throws cs_CurlError
	 */
	public function get(string $url, array $params = [], array $headers = []): string
	{

		if (count($params) > 0) {
			$url .= "?" . http_build_query($params);
		}

		curl_setopt($this->_curl, CURLOPT_POST, 0);
		return $this->_exec($url, $headers);
	}

	/**
	 * получаем картинку
	 *
	 * @throws cs_CurlError
	 */
	public function getImage(string $file_url): string
	{

		$host = $this->_getDomain($file_url);

		// картинка
		$this->setOpt(CURLOPT_HTTPHEADER, array_merge([
			"Accept: image/webp,*/*;q=0.8",
			"Accept-encoding: gzip, deflate, sdch",
			"Accept-language: ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4,zh-CN;q=0.2,zh;q=0.2",
			"Host: {$host}",
		], []));

		return $this->get($file_url);
	}

	/**
	 * post запрос
	 *
	 * @param array $params
	 *
	 * @throws cs_CurlError
	 * @mixed
	 */
	public function post(string $url, mixed $params = [], array $headers = []): string
	{

		$params = is_array($params) ? http_build_query($params) : $params;

		curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->_curl, CURLOPT_POST, 1);
		curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($this->_curl, CURLOPT_USERAGENT, $this->_user_agent);

		return $this->_exec($url, $headers);
	}

	/**
	 * put запрос
	 *
	 * @throws cs_CurlError
	 */
	public function put(string $url, mixed $params = [], array $headers = []): string
	{

		$params = is_array($params) ? http_build_query($params) : $params;

		curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($this->_curl, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($this->_curl, CURLOPT_USERAGENT, $this->_user_agent);

		return $this->_exec($url, $headers);
	}

	/**
	 * delete запрос
	 *
	 * @throws cs_CurlError
	 */
	public function delete(string $url, array $headers = []): string
	{

		curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->_curl, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($this->_curl, CURLOPT_USERAGENT, $this->_user_agent);

		return $this->_exec($url, $headers);
	}

	/**
	 * patch запрос
	 *
	 * @throws cs_CurlError
	 */
	public function patch(string $url, mixed $params = [], array $headers = []): string
	{

		$params = is_array($params) ? http_build_query($params) : $params;

		curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($this->_curl, CURLOPT_CUSTOMREQUEST, "PATCH");
		curl_setopt($this->_curl, CURLOPT_USERAGENT, $this->_user_agent);

		return $this->_exec($url, $headers);
	}

	/**
	 * получаем response code
	 */
	public function getResponseCode(): int
	{

		return $this->_response_code;
	}

	/**
	 * получаем effective url
	 */
	public function getEffectiveUrl(): string
	{

		return $this->_effective_url;
	}

	/**
	 * получаем redirect url
	 */
	public function getRedirectUrl(): string
	{

		return $this->_redirect_url;
	}

	/**
	 * получаем content type
	 */
	public function getContentType(): string
	{

		return $this->_content_type;
	}

	/**
	 * получаем headers
	 */
	public function getHeaders(): array
	{

		return $this->_response_headers;
	}

	/**
	 * @return $this
	 */
	public function addHeader(string $key, string $value): self
	{

		$this->_extra_headers[$key] = $value;
		return $this;
	}

	/**
	 * загрузить файл
	 *
	 * @mixed
	 * @throws cs_CurlError
	 */
	public function uploadFile($url, $ar_post, $file_path, $headers = [], $mime_type = "", $posted_filename = ""): bool | string
	{

		// докидываем файл в апи запрос
		$ar_post["file"] = new CURLFile($file_path, $mime_type, $posted_filename);

		curl_setopt($this->_curl, CURLOPT_POST, true);
		curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $ar_post);
		return $this->_exec($url, $headers);
	}

	/**
	 * загружаем файл с помощью его содержимого
	 *
	 * @throws cs_CurlError
	 */
	public function uploadFileBase64(string $url, array $ar_post, string $base64_encoded_file_content, string $mime_type, string $posted_filename, array $headers = []): bool | string
	{

		// создаем временный файл для хранения blob данных
		$tmp_file = tmpfile();
		fwrite($tmp_file, base64_decode($base64_encoded_file_content));

		// получаем метаданные временного файла
		$meta_data     = stream_get_meta_data($tmp_file);
		$tmp_file_path = $meta_data["uri"];

		// докидываем файл в апи запрос
		$ar_post["file"] = new CURLFile($tmp_file_path, $mime_type, $posted_filename);

		curl_setopt($this->_curl, CURLOPT_POST, true);
		curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $ar_post);
		$response = $this->_exec($url, $headers);

		// закрываем временный файл
		// здесь же он и удаляется
		fclose($tmp_file);

		return $response;
	}

	/**
	 * добавляем опции к запросу
	 *
	 * @mixed
	 */
	public function setOpt(int $option, mixed $value): void
	{

		$option = strtolower($option);
		curl_setopt($this->_curl, $option, $value);
	}

	/**
	 * Ограничить размер загружаемой страницы
	 */
	public function restrictContentLength(int $max_byte_length): self
	{

		// мониторим, сколько скачали, если больше переданного значения, то завершаем
		// нельзя верить только хедеру. Им могут манипулировать. Для этого и добавлена функция прогресса
		$this->setOpt(CURLOPT_MAXFILESIZE, $max_byte_length);
		$this->setOpt(CURLOPT_PROGRESSFUNCTION, function (int $download_size, int $downloaded, int $upload_size, int $uploaded) use ($max_byte_length) {

			return ($downloaded > $max_byte_length) ? 1 : 0;
		});

		return $this;
	}

	/**
	 * Требуется верификация сертификата вызываемого хоста
	 *
	 * @return $this
	 */
	public function needVerify(): self
	{

		curl_setopt($this->_curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($this->_curl, CURLOPT_SSL_VERIFYPEER, true);

		return $this;
	}

	// -------------------------------------------------------
	// PROTECTED
	// -------------------------------------------------------

	/**
	 * получаем домен
	 *
	 * @param null $url
	 *
	 * @mixed
	 */
	protected function _getDomain($url = null): string
	{

		if ($url === null) {
			$url = $this->_options["url"];
		}

		return ltrim(parse_url($url, PHP_URL_HOST), ".");
	}

	/**
	 * выполняем запрос
	 *
	 * @throws cs_CurlError
	 * @mixed
	 */
	protected function _exec(string $url, array $headers): string
	{

		if ($url === "") {
			throw new cs_CurlError("empty url");
		}

		// устанавливаем таймаут
		curl_setopt($this->_curl, CURLOPT_TIMEOUT, $this->_timeout);
		curl_setopt($this->_curl, CURLOPT_CONNECTTIMEOUT, $this->_timeout);
		curl_setopt($this->_curl, CURLOPT_USERAGENT, $this->_user_agent);
		curl_setopt($this->_curl, CURLOPT_URL, $url);

		// устанавливаем хедеры
		$headers["cookie"] = $this->_getCookieString();

		if ($this->_accept_language !== "") {
			$headers["Accept-Language"] = $this->_accept_language;
		}

		// если передали ca сертификат - устанавливаем
		if ($this->_ca_certificate !== "") {
			curl_setopt($this->_curl, CURLOPT_CAINFO_BLOB, $this->_ca_certificate);
		}

		if (count($headers) > 0) {
			curl_setopt($this->_curl, CURLOPT_HTTPHEADER, $this->_doFormatHeaders($headers));
		}
		$response = curl_exec($this->_curl);
		if (curl_errno($this->_curl) !== CURLE_OK) {

			throw new cs_CurlError(curl_error($this->_curl));
		}

		if ($response === false) {
			throw new cs_CurlError("empty response");
		}

		return $this->_getResponse($response);
	}

	// получаем строку кук
	protected function _getCookieString(): string
	{

		$cookies = "";
		foreach ($this->_cookies as $key => $value) {
			$cookies .= $key . "=" . $value . ";";
		}
		return $cookies;
	}

	// получаем форматированные заголовки
	protected function _doFormatHeaders(array $headers): array
	{

		$headers = array_merge($this->_extra_headers, $headers);

		$output = [];
		foreach ($headers as $key => $value) {
			$output[] = $key . ": " . $value;
		}

		$this->_extra_headers = [];
		return $output;
	}

	/**
	 * собираем ответ curl
	 */
	protected function _getResponse(mixed $response): bool | string
	{

		// достаем информацию
		$this->_response_code = formatInt(curl_getinfo($this->_curl, CURLINFO_HTTP_CODE));
		$this->_effective_url = curl_getinfo($this->_curl, CURLINFO_EFFECTIVE_URL);
		$this->_redirect_url  = curl_getinfo($this->_curl, CURLINFO_REDIRECT_URL);
		$this->_content_type  = curl_getinfo($this->_curl, CURLINFO_CONTENT_TYPE);
		$header_size          = curl_getinfo($this->_curl, CURLINFO_HEADER_SIZE);

		$this->_response_headers = $this->_parseHeaders(substr($response, 0, $header_size));

		return substr($response, $header_size);
	}

	/**
	 * парсим заголовки
	 *
	 * @mixed
	 */
	protected function _parseHeaders(string $headers): array
	{

		$header_list = explode("\n", trim($headers));

		$output = [];
		foreach ($header_list as $line) {

			$temp = explode(":", $line, 2);
			if (count($temp) != 2) {
				continue;
			}

			$key = trim($temp[0]);
			if (mb_strtolower($key) == "set-cookie") {

				$this->_parseCookie($temp[1]);
				continue;
			}
			$output[$key] = trim($temp[1]);
		}

		return $output;
	}

	/**
	 * парсим куки
	 */
	protected function _parseCookie(string $cookie_string): void
	{

		$cookie_path = explode(";", trim($cookie_string));
		foreach ($cookie_path as $v) {

			$cookie_param = explode("=", $v, 2);
			if (count($cookie_param) != 2 || in_array(mb_strtolower(trim($cookie_param[0])), ["domain", "expires", "path", "secure", "comment"])) {
				continue;
			}
			$this->addCookie(trim($cookie_param[0]), trim($cookie_param[1]));
		}
	}
}
