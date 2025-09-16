<?php

namespace BaseFrame\Router\Middleware;

use BaseFrame\Exception\Domain\ParseFatalException;
use BaseFrame\Exception\Domain\ReturnFatalException;
use BaseFrame\Exception\Request\InappropriateContentException;
use BaseFrame\Icap\Client\HttpRequestBuilder;
use BaseFrame\Icap\Client\IcapClient;
use BaseFrame\Icap\IcapProvider;
use BaseFrame\Path\PathProvider;
use BaseFrame\Router\Request;
use BaseFrame\System\File;
use BaseFrame\Url\UrlProvider;
use Random\RandomException;

/**
 * Мидлвар для фильтрации контента
 */
class FilterContent implements Main {

	/**
	 * Фильтруем контент через ICAP клиент
	 *
	 * @param Request $request
	 *
	 * @return Request
	 * @throws InappropriateContentException
	 * @throws ParseFatalException
	 * @throws RandomException
	 * @throws ReturnFatalException
	 */
	public static function handle(Request $request):Request {

		// проверяем, включен ли клиент
		if (!IcapProvider::isEnabled()) {
			return $request;
		}

		// если метод не подлежит фильтрации контента, пропускаем мидлвар
		if (!in_array($request->method_name, array_map("strtolower", $request->controller_class::NEED_FILTER_CONTENT_METHODS))) {
			return $request;
		}

		// формируем болванку http запроса для ICAP
		$http_request_builder = (new HttpRequestBuilder())
			->method("POST")
			->url("/" . str_replace(".", "/", $request->route))
			->addHeader("Host", UrlProvider::pivotDomain());

		$is_file = isset($request->post_data["token"], $request->post_data["file_path"]);

		// если передали файл - формируем multipart запрос
		if ($is_file) {

			// если файлы не контролируются - выходим
			if (!IcapProvider::isFileControlled()) {
				return $request;
			}

			$file_name = urlencode(urldecode($request->post_data["original_file_name"] ?? $request->post_data["file_name"]));
			$http_request_builder
				->bodyFromMultipartFile(
					"file",
					$request->post_data["file_path"],
					$file_name,
				);
		} else {

			// если сообщения не контролируются - выходим
			if (!IcapProvider::isTextControlled()) {
				return $request;
			}

			// иначе обычный запрос формируем, убираем все из данных, кроме текста
			$check_post_data = self::_processTextPostData($request->post_data);
			$http_request_builder
				->bodyFromForm($check_post_data);
		}

		// создаем ICAP клиент
		$icap_client = new IcapClient(
			sprintf("icap://%s:%d/%s",
				IcapProvider::host(),
				IcapProvider::port(),
				IcapProvider::reqmodService()));

		// если уже сформировали ответ, то пропускаем
		if (count($request->response) > 1) {
			return $request;
		}

		// отправляем REQMOD запрос
		try {
			$icap_response = $icap_client->reqmod($http_request_builder->build());
		} catch (\Throwable $t) {

			// любой экзепшен при включенном icap - автоматический блок контента
			throw new InappropriateContentException("icap request error: {$t->getMessage()}");
		}

		// если изменился запрос - значит отдаем ошибку
		if ($icap_response->isRequestModified($icap_client->getLastRequest())) {

			// файл нужно удалить, чтобы не захламлять место
			if ($is_file) {
				self::_removeRestrictedFile($request->post_data["file_path"]);
			}
			throw new InappropriateContentException("icap request changed");
		}

		return $request;
	}

	/**
	 * Подготовить текстовые post данные для icap
	 *
	 * @param array $post_data
	 *
	 * @return array
	 */
	protected static function _processTextPostData(array $post_data):array {

		unset($post_data["file_name"]);

		if (!isset($post_data["client_message_list"])) {
			return $post_data;
		}

		foreach ($post_data["client_message_list"] as &$message) {
			unset($message["file_name"]);
		}

		return $post_data;
	}

	/**
	 * Удалить непрошедший проверку файл
	 *
	 * @param string $file_path
	 *
	 * @return void
	 * @throws ReturnFatalException
	 */
	protected static function _removeRestrictedFile(string $file_path):void {

		$tmp_path = PathProvider::tmp();
		if (!str_starts_with($file_path, $tmp_path)) {
			throw new ReturnFatalException("file was not in tmp dir");
		}
		$file_subpath = substr($file_path, strlen($tmp_path));

		$file = File::init($tmp_path, $file_subpath);
		$file->delete();
	}
}