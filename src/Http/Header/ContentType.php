<?php

namespace BaseFrame\Http\Header;

/**
 * Обработчик для заголовка типа контента
 */
class ContentType extends Header {

	protected const _HEADER_KEY = "CONTENT_TYPE"; // ключ хедера

	public function getContentType():string {

		return trim(explode(";", $this->getValue())[0]);
	}
}